<?php
namespace Legacy\API;

use CUser;
use CFile;

use Legacy\General\Constants;
use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Iblock\PropertyEnumerationTable;

class Mappers
{
    public static string $baseUrl = 'http://192.168.0.143';

    private static array $typeCache = [];
    private static array $statusCache = [];

    public static function formatDateRus(?string $dateTime): ?string
    {
        if (empty($dateTime)) return null;
        $timestamp = strtotime($dateTime);
        if (!$timestamp) return $dateTime;
        return date('d.m.Y H:i:s', $timestamp);
    }


    public static function mapUser(array $arUser, bool $full = false): array
    {
        $groups = CUser::GetUserGroup($arUser['ID']);
        $role = null;
        if (in_array(Constants::GROUP_ADMINS, $groups)) $role = 'admin';
        elseif (in_array(Constants::GROUP_TEACHERS, $groups)) $role = 'teacher';
        elseif (in_array(Constants::GROUP_STUDENTS, $groups)) $role = 'student';

        $result = [
            'ID' => $arUser['ID'],
            'FIRST_NAME' => $arUser['NAME'] ?? '',
            'LAST_NAME' => $arUser['LAST_NAME'] ?? '',
            'SECOND_NAME' => $arUser['SECOND_NAME'] ?? '',
        ];

        if ($full) {
            $result['LOGIN'] = $arUser['LOGIN'] ?? '';
            $result['EMAIL'] = $arUser['EMAIL'] ?? '';
            $result['ROLE'] = $role;
        }

        return $result;
    }

    public static function mapCourse(array $row, bool $fullInfo = false, array $modules = [], array $students = []): array
    {
        $author = null;
        if (!empty($row['AUTHOR'])) {
            $author = $fullInfo ? $row['AUTHOR'] : [
                'ID' => $row['AUTHOR']['ID'] ?? 0,
                'FIRST_NAME' => $row['AUTHOR']['FIRST_NAME'] ?? '',
                'LAST_NAME' => $row['AUTHOR']['LAST_NAME'] ?? '',
                'SECOND_NAME' => $row['AUTHOR']['SECOND_NAME'] ?? '',
            ];
        }

        $result = [
            'ID' => $row['ID'],
            'NAME' => $row['NAME'] ?? '',
            'DESCRIPTION' => self::mapDescription($row['DESCRIPTION'] ?? ''),
            'AUTHOR' => $author,
            'MODULES_COUNT' => count($modules),
            'STUDENTS_COUNT' => count($students),
        ];

        if ($fullInfo) {
            $result['MODULES'] = $modules;
            $result['STUDENTS'] = $students;
        }

        return $result;
    }

    public static function mapModule(array $row, array $fileIds = []): array
    {
        $files = self::mapFiles($fileIds);

        // Преобразуем TYPE из ID enum в текст
        if (!empty($row['TYPE']) && is_numeric($row['TYPE'])) {
            $typeId = (int)$row['TYPE'];
            if (!isset(self::$typeCache[$typeId])) {
                $res = PropertyEnumerationTable::getList([
                    'filter' => ['ID' => $typeId],
                    'select' => ['VALUE']
                ]);
                self::$typeCache[$typeId] = ($enum = $res->fetch()) ? $enum['VALUE'] : 'Неизвестный тип';
            }
            $row['TYPE'] = self::$typeCache[$typeId];
        }

        $deadline = $row['DEADLINE'] ?? null;
        if (!empty($deadline) && $deadline !== 'Бессрочно') {
            $deadline = self::formatDateRus($deadline);
        } else {
            $deadline = 'Бессрочно';
        }

        return [
            'ID' => $row['ID'],
            'NAME' => $row['NAME'] ?? '',
            'DESCRIPTION' => self::mapDescription($row['DESCRIPTION'] ?? ''),
            'TYPE' => $row['TYPE'] ?? '',
            'MAX_SCORE' => (int)($row['MAX_SCORE'] ?? 0),
            'DEADLINE' => $deadline,
            'FILES' => $files
        ];
    }

    public static function mapSubmission(array $row): array
    {
        $student = $row['STUDENT'] ?? null;
        $module = $row['MODULE'] ?? null;

        if ($module) {
            $module = self::mapModule($module, $module['FILES'] ?? []);
        }

        $files = self::mapFiles($row['FILES'] ?? []);

        $score = $row['UF_SCORE'] !== null ? (int)$row['UF_SCORE'] : "Нет оценки";

        // Преобразуем статус в текст
        $status = $row['UF_STATUS'] ?? null;
        if ($status !== null) {
            $status = self::resolveSubmissionStatus($status);
        }

        return [
            'ID' => (int)$row['ID'],
            'STUDENT' => $student,
            'MODULE' => $module,
            'SCORE' => $score,
            'STATUS' => $status,
            'ANSWER' => $row['UF_ANSWER'] ?? null,
            'LINK' => $row['UF_LINK'] ?? null,
            'REVIEW_COMMENT' => $row['UF_REVIEW_COMMENT'] ?? null,
            'DATE_SUBMITTED' => isset($row['UF_DATE_SUBMITTED']) ? self::formatDateRus($row['UF_DATE_SUBMITTED']) : null,
            'FILES' => $files,
        ];
    }

    public static function mapDescription(?string $description): string
    {
        if (empty($description)) return '';
        $data = @unserialize($description);
        if ($data !== false && isset($data['TEXT'])) return $data['TEXT'];
        return $description;
    }

    public static function mapFiles(array $fileIds): array
    {
        return array_values(array_filter(array_map(function($fid) {
            $file = \CFile::GetFileArray($fid);
            return $file ? [
                'ID' => (int)$file['ID'],
                'NAME' => $file['ORIGINAL_NAME'],
                'URL' => self::$baseUrl . \CFile::GetPath($file['ID']),
            ] : null;
        }, $fileIds)));
    }

    public static function resolveModuleType($type): int
    {
        if (is_numeric($type)) return (int)$type;

        $type = trim((string)$type);

        if (!isset(self::$typeCache[$type])) {
            $res = PropertyEnumerationTable::getList([
                'filter' => ['PROPERTY_ID' => Constants::MODULE_TYPE, '=XML_ID' => $type],
                'select' => ['ID']
            ]);
            if ($row = $res->fetch()) {
                self::$typeCache[$type] = (int)$row['ID'];
            } else {
                $res = PropertyEnumerationTable::getList([
                    'filter' => ['PROPERTY_ID' => Constants::MODULE_TYPE],
                    'select' => ['ID', 'VALUE']
                ]);
                while ($row = $res->fetch()) {
                    if (mb_strtolower($row['VALUE']) === mb_strtolower($type)) {
                        self::$typeCache[$type] = (int)$row['ID'];
                        break;
                    }
                }
            }
        }

        return self::$typeCache[$type] ?? 0;
    }

    public static function resolveSubmissionStatus($status): string {
        if (empty($status)) return '';
        $id = (int)$status;
        $map = [
            1 => 'Ожидает проверки',
            2 => 'Оценен'
        ];

        return $map[$id] ?? 'Неизвестный статус'; }
}