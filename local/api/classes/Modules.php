<?php
namespace Legacy\API;

use Legacy\API\Access\UserAccess;
use Legacy\API\Access\CourseAccess;
use Legacy\API\Access\ModuleAccess;

use Legacy\Iblock\CoursesTable;
use Legacy\Iblock\ModulesTable;
use Bitrix\Iblock\PropertyEnumerationTable;

class Modules
{
    private static function getList(array $filter = [], int $limit = 50, int $page = 1): array
    {
        $query = ModulesTable::query();
        ModulesTable::withSelect($query);
        ModulesTable::withRuntimeProperties($query);
        ModulesTable::withFilter($query, $filter);
        ModulesTable::withOrder($query);
        ModulesTable::withPage($query, $limit, $page);

        $modules = [];
        $fileIds = [];

        $db = $query->exec();
        while ($row = $db->fetch()) {
            $id = (int)$row['ID'];
            if (!isset($modules[$id])) {
                $modules[$id] = $row;
                $modules[$id]['FILES'] = [];
            }

            if (!empty($row['FILE_ID'])) {
                $modules[$id]['FILES'][] = (int)$row['FILE_ID'];
                $fileIds[$row['FILE_ID']] = (int)$row['FILE_ID'];
            }
        }

        $fileMap = $fileIds ? array_combine(
            array_column($mapFiles = Mappers::mapFiles(array_values($fileIds)), 'ID'),
            $mapFiles
        ) : [];

        $modulesMapped = [];
        foreach ($modules as $row) {
            $mapped = Mappers::mapModule($row, $row['FILES']);
            $mapped['FILES'] = array_map(fn($fid) => $fileMap[$fid] ?? null, $row['FILES']);
            $modulesMapped[] = $mapped;
        }

        return ['count' => count($modulesMapped), 'items' => $modulesMapped];
    }

    // Получение всех модулей по курсу
    // /api/Modules/getByCourse/?id=
    public static function getByCourse(array $arRequest): array
    {
        $courseId = (int)($arRequest['course_id'] ?? 0);
        if (!$courseId) throw new \Exception('Не указан ID курса');

        CourseAccess::getCourseForView($courseId);

        return self::getList(['COURSE_PROP.VALUE' => $courseId]);
    }

    // Добавление модуля
    // /api/Modules/add/
    public static function add(array $arData): array
    {
        ModuleAccess::assertTeacherOrAdmin();

        $required = ['name', 'type', 'max_score', 'deadline', 'course_id'];
        foreach ($required as $f) {
            if (empty($arData[$f])) throw new \Exception("Не заполнено обязательное поле: {$f}");
        }

        if (UserAccess::getUserRole() === 'teacher') {
            $course = CoursesTable::getCourseById((int)$arData['course_id']);
            if ((int)$course['AUTHOR_ID'] !== UserAccess::getCurrentUserId()) {
                throw new \Exception('Нельзя добавить модуль в чужой курс');
            }
        }

        $fields = [
            'NAME' => $arData['name'],
            'DESCRIPTION' => $arData['description'] ?? '',
            'TYPE' => Mappers::resolveModuleType($arData['type']),
            'MAX_SCORE' => (int)$arData['max_score'],
            'DEADLINE' => $arData['deadline'],
            'COURSE_ID' => (int)$arData['course_id'],
            'FILES' => $arData['files'] ?? [],
        ];

        $id = ModulesTable::addModule($fields);

        return ['success' => true, 'ID' => $id, 'message' => 'Модуль успешно создан'];
    }

    // Редактирование модуля
    // /api/Modules/update/
    public static function update(array $arData): array
    {
        $moduleId = (int)($arData['id'] ?? 0);
        if (!$moduleId) throw new \Exception('Не указан ID модуля');

        ModuleAccess::getModuleForManage($moduleId);

        $fields = [];
        if (isset($arData['name'])) $fields['NAME'] = $arData['name'];
        if (array_key_exists('description', $arData)) $fields['DESCRIPTION'] = $arData['description'];
        if (array_key_exists('type', $arData)) $fields['TYPE'] = Mappers::resolveModuleType($arData['type']);
        if (array_key_exists('max_score', $arData)) $fields['MAX_SCORE'] = (int)$arData['max_score'];
        if (array_key_exists('deadline', $arData)) $fields['DEADLINE'] = $arData['deadline'] ?: false;

        ModulesTable::updateModule($moduleId, $fields);

        return ['success' => true, 'message' => 'Модуль успешно обновлен'];
    }

    // Удаление модуля
    // /api/Modules/delete/?id=
    public static function delete(array $arData): array
    {
        $moduleId = (int)($arData['id'] ?? 0);
        if (!$moduleId) throw new \Exception('Не указан ID модуля');

        ModuleAccess::getModuleForManage($moduleId);

        if (!\CModule::IncludeModule('iblock')) {
            throw new \Exception('Не удалось подключить модуль iblock');
        }

        $el = new \CIBlockElement();
        if (!$el->Delete($moduleId)) throw new \Exception('Не удалось удалить модуль: ' . $el->LAST_ERROR);

        return ['success' => true, 'message' => 'Модуль успешно удален'];
    }
}