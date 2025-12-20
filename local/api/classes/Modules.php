<?php
namespace Legacy\API;

use Legacy\Iblock\CoursesTable;
use Legacy\Iblock\ModulesTable;
use Bitrix\Iblock\PropertyEnumerationTable;

use Legacy\General\Constants;

class Modules
{
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
        $db = $query->exec();
        $typeIds = []; // Собираем ID типов
        while ($row = $db->fetch()) {
            $modules[] = $row;
            if (!empty($row['TYPE'])) {
                $typeIds[] = $row['TYPE'];
            }
        }

        // Получаем соответствие ID -> VALUE
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

        // Преобразуем ID в VALUE и маппируем строки через mapRow
        $modulesMapped = [];
        foreach ($modules as $row) {
            if (!empty($row['TYPE']) && isset($typeMap[$row['TYPE']])) {
                $row['TYPE'] = $typeMap[$row['TYPE']];
            }
            $modulesMapped[] = self::mapRow($row);
        }

        return [
            'count' => count($modulesMapped),
            'items' => $modulesMapped
        ];
    }

    // Получение всех модулей по курсу
    // /api/Modules/getByCourse/?id=
    public static function getByCourse(array $arRequest): array
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
        return $result;
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
            throw new \Exception('Доступ запрещен: только админ или преподаватель');
        }

        // Проверяем обязательные поля
        $required = ['name', 'type', 'max_score', 'deadline', 'course_id'];
        foreach ($required as $f) {
            if (empty($arData[$f])) {
                throw new \Exception("Не заполнено обязательное поле: {$f}");
            }
        }

        // Приводим к внутреннему формату
        $fields = [
            'NAME' => $arData['name'],
            'DESCRIPTION' => $arData['description'] ?? '',
            'TYPE' => $arData['type'],
            'MAX_SCORE' => (int)$arData['max_score'],
            'DEADLINE' => $arData['deadline'],
            'COURSE_ID' => (int)$arData['course_id'],
            'FILES' => $arData['files'] ?? null,
        ];

        // Проверка прав преподавателя на курс
        if ($role === 'teacher') {
            $course = CoursesTable::getCourseById($fields['COURSE_ID']);
            if (!$course || (int)$course['AUTHOR_ID'] !== $userId) {
                throw new \Exception('Доступ запрещен: нельзя добавить модуль в чужой курс');
            }
        }

        // TYPE: поддержка строкового значения
        $typeValue = $fields['TYPE'];
        $typeId = 0;
        if (is_numeric($typeValue)) {
            $typeId = (int)$typeValue;
        } else {
            $res = \Bitrix\Iblock\PropertyEnumerationTable::getList([
                'filter' => ['VALUE' => $typeValue],
                'select' => ['ID']
            ]);
            if ($row = $res->fetch()) {
                $typeId = (int)$row['ID'];
            }
        }
        if (!$typeId) {
            throw new \Exception("Тип модуля '{$typeValue}' не найден");
        }
        $fields['TYPE'] = $typeId;

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
            throw new \Exception('Доступ запрещен: только админ или преподаватель');
        }

        // Проверка прав преподавателя на курс модуля
        if ($role === 'teacher') {
            $module = ModulesTable::getModuleById($moduleId);
            if (!$module) {
                throw new \Exception('Модуль не найден');
            }

            $course = CoursesTable::getCourseById((int)$module['COURSE_ID']);
            if ((int)$course['AUTHOR_ID'] !== $userId) {
                throw new \Exception('Доступ запрещен: нельзя редактировать чужой модуль');
            }
        }

        // Приводим к внутреннему формату
        $fields = [
            'NAME' => $arData['name'] ?? null,
            'DESCRIPTION' => $arData['description'] ?? null,
            'TYPE' => $arData['type'] ?? null,
            'MAX_SCORE' => isset($arData['max_score']) ? (int)$arData['max_score'] : null,
            'DEADLINE' => $arData['deadline'] ?? null,
            'COURSE_ID' => isset($arData['course_id']) ? (int)$arData['course_id'] : null,
            'FILES' => $arData['files'] ?? null,
        ];

        // TYPE: поддержка строки или ID
        if ($fields['TYPE'] !== null) {
            $typeValue = $fields['TYPE'];
            $typeId = 0;
            if (is_numeric($typeValue)) {
                $typeId = (int)$typeValue;
            } else {
                $res = \Bitrix\Iblock\PropertyEnumerationTable::getList([
                    'filter' => ['VALUE' => $typeValue],
                    'select' => ['ID']
                ]);
                if ($row = $res->fetch()) {
                    $typeId = (int)$row['ID'];
                }
            }
            if (!$typeId) {
                throw new \Exception("Тип модуля '{$typeValue}' не найден");
            }
            $fields['TYPE'] = $typeId;
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