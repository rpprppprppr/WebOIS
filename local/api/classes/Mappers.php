<?php
namespace Legacy\API;

use CUser;
use CFile;

use Legacy\General\Constants;

class Mappers
{
    private static string $baseUrl = 'http://192.168.0.143';

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

    public static function mapModule(array $row, array $files = []): array
    {
        $mapped = [
            'ID' => $row['ID'],
            'NAME' => $row['NAME'] ?? '',
            'DESCRIPTION' => self::mapDescription($row['DESCRIPTION'] ?? ''),
            'TYPE' => $row['TYPE'] ?? '',
            'MAX_SCORE' => (int)($row['MAX_SCORE'] ?? 0),
            'DEADLINE' => !empty($row['DEADLINE']) ? $row['DEADLINE'] : 'Бессрочно',
            'FILES' => $files
        ];

        return $mapped;
    }

    public static function mapSubmission(array $row): array
    {
        $student = $row['STUDENT'] ?? null;
        $module = $row['MODULE'] ?? null;
        $files = [];
        if (!empty($row['FILES'])) {
            foreach ((array)$row['FILES'] as $fid) {
                $file = CFile::GetFileArray($fid);
                if ($file) {
                    $files[] = [
                        'ID' => (int)$file['ID'],
                        'NAME' => $file['ORIGINAL_NAME'],
                        'URL' => self::$baseUrl . CFile::GetPath($file['ID']),
                    ];
                }
            }
        }

        return [
            'ID' => (int)$row['ID'],
            'STUDENT' => $student,
            'MODULE' => $module,
            'SCORE' => isset($row['UF_SCORE']) ? (int)$row['UF_SCORE'] : null,
            'STATUS' => $row['UF_STATUS'] ?? null,
            'ANSWER' => $row['UF_ANSWER'] ?? null,
            'REVIEW_COMMENT' => $row['UF_REVIEW_COMMENT'] ?? null,
            'DATE_SUBMITTED' => $row['UF_DATE_SUBMITTED'] ?? null,
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
        $files = [];
        foreach ($fileIds as $fid) {
            $file = CFile::GetFileArray($fid);
            if ($file) {
                $files[] = [
                    'ID' => (int)$file['ID'],
                    'NAME' => $file['ORIGINAL_NAME'],
                    'URL' => Mappers::$baseUrl . CFile::GetPath($file['ID']),
                ];
            }
        }
        return $files;
    }
}