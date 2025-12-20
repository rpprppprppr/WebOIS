<?php
namespace Legacy\API;

use Legacy\Iblock\CoursesTable;
use Legacy\Iblock\ModulesTable;
use Bitrix\Iblock\PropertyEnumerationTable;

use Legacy\General\Constants;

class Modules
{
    private static function resolveModuleType($type): int
    {
        if (is_numeric($type)) {
            return (int)$type;
        }

        if (!\CModule::IncludeModule('iblock')) {
            throw new \Exception('Модуль iblock не подключен');
        }

        $type = trim((string)$type);

        $res = PropertyEnumerationTable::getList([
            'filter' => [
                'PROPERTY_ID' => Constants::MODULE_TYPE,
                '=XML_ID' => $type,
            ],
            'select' => ['ID']
        ]);

        if ($row = $res->fetch()) {
            return (int)$row['ID'];
        }

        $res = PropertyEnumerationTable::getList([
            'filter' => [
                'PROPERTY_ID' => Constants::MODULE_TYPE,
            ],
            'select' => ['ID', 'VALUE']
        ]);

        while ($row = $res->fetch()) {
            if (mb_strtolower($row['VALUE']) === mb_strtolower($type)) {
                return (int)$row['ID'];
            }
        }

        throw new \Exception("Тип модуля '{$type}' не найден");
    }

    private static function mapDescription(?string $description): string
    {
        if (empty($description)) {
            return '';
        }

        $data = @unserialize($description);
        if ($data !== false && isset($data['TEXT'])) {
            return $data['TEXT'];
        }

        return $description;
    }

    private static function mapRow(array $row): array
    {
        return [
            'ID' => $row['ID'],
            'NAME' => $row['NAME'] ?? '',
            'DESCRIPTION' => self::mapDescription($row['DESCRIPTION'] ?? ''),
            'TYPE' => $row['TYPE'] ?? '',
            'MAX_SCORE' => (int)($row['MAX_SCORE'] ?? 0),
            'DEADLINE' => !empty($row['DEADLINE']) ? $row['DEADLINE'] : 'Бессрочно',
        ];
    }

    private static function getUserRole(): string
    {
        $groups = UserMapper::getCurrentUserGroups();
        if (in_array(Constants::GROUP_ADMINS, $groups)) return 'admin';
        if (in_array(Constants::GROUP_TEACHERS, $groups)) return 'teacher';
        if (in_array(Constants::GROUP_STUDENTS, $groups)) return 'student';
        return 'guest';
    }

    private static function getList(array $filter = [], int $limit = 50, int $page = 1): array
    {
        $query = ModulesTable::query();
        ModulesTable::withSelect($query);
        ModulesTable::withRuntimeProperties($query);
        ModulesTable::withFilter($query, $filter);
        ModulesTable::withOrder($query);
        ModulesTable::withPage($query, $limit, $page);

        $modules = [];
        $typeIds = [];

        $db = $query->exec();
        while ($row = $db->fetch()) {
            $id = (int)$row['ID'];

            if (!isset($modules[$id])) {
                $modules[$id] = $row;
                $modules[$id]['FILES'] = [];
            }

            if (!empty($row['FILE_ID'])) {
                $modules[$id]['FILES'][] = (int)$row['FILE_ID'];
            }

            if (!empty($row['TYPE'])) {
                $typeIds[$row['TYPE']] = $row['TYPE'];
            }
        }

        $fileIds = [];

        foreach ($modules as $module) {
            foreach ($module['FILES'] as $fid) {
                $fileIds[$fid] = $fid;
            }
        }

        $baseUrl = 'http://192.168.0.143';

        $fileMap = [];
        if ($fileIds) {
            $res = \CFile::GetList([], ['@ID' => implode(',', $fileIds)]);
            while ($file = $res->Fetch()) {
                $fileMap[$file['ID']] = [
                    'ID' => (int)$file['ID'],
                    'NAME' => $file['ORIGINAL_NAME'],
                    'URL' => $baseUrl . \CFile::GetPath($file['ID']),
                ];
            }
        }

        $typeMap = [];
        if ($typeIds) {
            $res = PropertyEnumerationTable::getList([
                'filter' => ['ID' => $typeIds],
                'select' => ['ID', 'VALUE']
            ]);
            while ($row = $res->fetch()) {
                $typeMap[$row['ID']] = $row['VALUE'];
            }
        }

        $modulesMapped = [];

        foreach ($modules as $row) {
            if (!empty($row['TYPE']) && isset($typeMap[$row['TYPE']])) {
                $row['TYPE'] = $typeMap[$row['TYPE']];
            }

            $mapped = self::mapRow($row);

            $mapped['FILES'] = [];
            foreach ($row['FILES'] as $fid) {
                if (isset($fileMap[$fid])) {
                    $mapped['FILES'][] = $fileMap[$fid];
                }
            }

            $modulesMapped[] = $mapped;
        }

        return [
            'count' => count($modulesMapped),
            'items' => $modulesMapped
        ];
    }

    // Получение всех модулей по курсу
    // /api/Modules/getByCourse/?id=
    public static function getByCourse(array $arRequest): void
    {
        $courseId = (int)($arRequest['course_id'] ?? $_GET['course_id'] ?? 0);
        if (!$courseId) throw new \Exception('Не указан ID курса');

        $userId = UserMapper::getCurrentUserId();
        if (!$userId) throw new \Exception('Неавторизованный пользователь');

        $role = self::getUserRole();

        $course = CoursesTable::getCourseById($courseId);
        if (!$course) throw new \Exception('Курс не найден');

        if ($role === 'teacher' && (int)$course['AUTHOR_ID'] !== $userId) {
            throw new \Exception('Доступ запрещен: это не ваш курс');
        }

        if ($role === 'student') {
            $students = is_array($course['STUDENT_ID']) ? $course['STUDENT_ID'] : [$course['STUDENT_ID']];
            if (!in_array($userId, $students)) {
                throw new \Exception('Доступ запрещен: вы не записаны на курс');
            }
        }

        $result = self::getList(['COURSE_PROP.VALUE' => $courseId]);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => 'ok',
            'result' => $result
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Добавление модуля
    // /api/Modules/add/
    public static function add(array $arData): array
    {
        $userId = UserMapper::getCurrentUserId();
        if (!$userId) {
            throw new \Exception('Неавторизованный пользователь');
        }

        $role = self::getUserRole();
        if (!in_array($role, ['admin', 'teacher'])) {
            throw new \Exception('Доступ запрещен');
        }

        $required = ['name', 'type', 'max_score', 'deadline', 'course_id'];
        foreach ($required as $f) {
            if (empty($arData[$f])) {
                throw new \Exception("Не заполнено обязательное поле: {$f}");
            }
        }

        $fields = [
            'NAME' => $arData['name'],
            'DESCRIPTION' => $arData['description'] ?? '',
            'TYPE' => self::resolveModuleType($arData['type']), // ✅
            'MAX_SCORE' => (int)$arData['max_score'],
            'DEADLINE' => $arData['deadline'],
            'COURSE_ID' => (int)$arData['course_id'],
            'FILES' => $arData['files'] ?? null,
        ];

        if ($role === 'teacher') {
            $course = CoursesTable::getCourseById($fields['COURSE_ID']);
            if ((int)$course['AUTHOR_ID'] !== $userId) {
                throw new \Exception('Нельзя добавить модуль в чужой курс');
            }
        }

        $id = ModulesTable::addModule($fields);

        return [
            'success' => true,
            'ID' => $id,
            'message' => 'Модуль успешно создан'
        ];
    }

    // Редактирование модуля
    // /api/Modules/update/
    public static function update(array $arData): array
    {
        $moduleId = (int)($arData['id'] ?? 0);
        if (!$moduleId) {
            throw new \Exception('Не указан ID модуля');
        }

        $userId = UserMapper::getCurrentUserId();
        if (!$userId) {
            throw new \Exception('Неавторизованный пользователь');
        }

        $role = self::getUserRole();
        if (!in_array($role, ['admin', 'teacher'])) {
            throw new \Exception('Доступ запрещен');
        }

        if ($role === 'teacher') {
            $module = ModulesTable::getModuleById($moduleId);
            $course = CoursesTable::getCourseById((int)$module['COURSE_ID']);
            if ((int)$course['AUTHOR_ID'] !== $userId) {
                throw new \Exception('Нельзя редактировать чужой модуль');
            }
        }

        $fields = [];

        if (isset($arData['name'])) {
            $fields['NAME'] = $arData['name'];
        }
        if (array_key_exists('description', $arData)) {
            $fields['DESCRIPTION'] = $arData['description'];
        }
        if (array_key_exists('type', $arData)) {
            $fields['TYPE'] = self::resolveModuleType($arData['type']); // ✅
        }
        if (array_key_exists('max_score', $arData)) {
            $fields['MAX_SCORE'] = (int)$arData['max_score'];
        }
        if (array_key_exists('deadline', $arData)) {
            $fields['DEADLINE'] = $arData['deadline'] ?: false;
        }

        ModulesTable::updateModule($moduleId, $fields);

        return [
            'success' => true,
            'message' => 'Модуль успешно обновлен'
        ];
    }

    // Удаление модуля
    // /api/Modules/delete/?id=
    public static function delete(array $arData): array
    {
        $moduleId = (int)($arData['id'] ?? $_GET['id'] ?? 0);
        if (!$moduleId) throw new \Exception('Не указан ID модуля');

        $userId = UserMapper::getCurrentUserId();
        $role = self::getUserRole();
        if (!in_array($role, ['admin', 'teacher'])) throw new \Exception('Доступ запрещен');

        if ($role === 'teacher') {
            $module = ModulesTable::getModuleById($moduleId);
            if (!$module) {
                throw new \Exception('Модуль не найден');
            }

            $course = CoursesTable::getCourseById((int)$module['COURSE_ID']);
            if ((int)$course['AUTHOR_ID'] !== $userId) throw new \Exception('Доступ запрещен: нельзя удалить чужой модуль');
        }

        if (!\CModule::IncludeModule('iblock')) {
            throw new \Exception('Не удалось подключить модуль iblock');
        }

        $el = new \CIBlockElement();
        if (!$el->Delete($moduleId)) {
            throw new \Exception('Не удалось удалить модуль: ' . $el->LAST_ERROR);
        }

        return ['success' => true, 'message' => 'Модуль успешно удален'];
    }
}