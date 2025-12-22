<?php
namespace Legacy\API;

use CUser;

use Legacy\API\Access\UserAccess;
use Legacy\API\Access\CourseAccess;

use Legacy\General\Constants;

use Legacy\Iblock\CoursesTable;

class Courses
{
    public static function getRawById(int $courseId): array
    {
        $result = self::getList([], ['ID' => $courseId]);
        if (empty($result['items'])) {
            throw new \Exception('Курс не найден');
        }
        return ['course' => $result['items'][0]];
    }

    private static function mapCourseRow(array $row, bool $fullInfo = false, bool $withModules = true): array
    {
        $author = !empty($row['AUTHOR_ID'])
            ? UserAccess::getUserById((int)$row['AUTHOR_ID'], $fullInfo)
            : null;

        $students = [];
        if (!empty($row['STUDENT_ID'])) {
            foreach ((array)$row['STUDENT_ID'] as $id) {
                if ($user = UserAccess::getUserById((int)$id)) {
                    $students[] = $user;
                }
            }
        }

        $modules = [];
        if ($withModules) {
            $modulesData = Modules::getByCourse(['course_id' => $row['ID']]);
            $modules = $modulesData['items'] ?? [];
        }

        return Mappers::mapCourse([
            'ID' => $row['ID'],
            'NAME' => $row['NAME'] ?? '',
            'DESCRIPTION' => $row['DESCRIPTION'] ?? '',
            'AUTHOR' => $author,
        ], $fullInfo, $modules, $students);
    }

    private static function getList(array $arRequest = [], array $filter = []): array
    {
        $limit = (int)($arRequest['limit'] ?? 20);
        $page  = (int)($arRequest['page'] ?? 1);

        $query = CoursesTable::query();
        CoursesTable::withSelect($query);
        CoursesTable::withRuntimeProperties($query);
        CoursesTable::withFilter($query, $filter);
        CoursesTable::withOrder($query);
        CoursesTable::withPage($query, $limit, $page);

        $items = [];
        $db = $query->exec();
        while ($row = $db->fetch()) {
            $items[] = $row;
        }

        return [
            'count' => count($items),
            'items' => $items,
        ];
    }

    // Получение всех доступных курсов
    // /api/Courses/get/
    public static function get(array $arRequest = []): array
    {
        $userId = UserAccess::checkAuth();
        $role = UserAccess::getUserRole();

        $filter = match ($role) {
            'admin'   => [],
            'teacher' => ['AUTHOR_PROP.VALUE' => $userId],
            'student' => ['STUDENTS_PROP.VALUE' => $userId],
            default   => throw new \Exception('Доступ запрещен'),
        };

        $result = self::getList($arRequest, $filter);
        $result['items'] = array_map(fn($row) => self::mapCourseRow($row), $result['items']);

        return $result;
    }

    // Получение курсов по ID
    // /api/Courses/getById/?id=
    public static function getById(array $arRequest): array
    {
        $courseId = (int)($arRequest['id'] ?? 0);
        if (!$courseId) throw new \Exception('Не передан ID курса');

        $access = CourseAccess::getCourseForView($courseId);

        return [self::mapCourseRow($access['course'], $access['fullInfo'])];
    }

    // Получение курсов по преподавателю (ADMIN)
    // /api/Courses/getByTeacher/?id=
    public static function getByTeacher(array $arRequest): array
    {
        return self::getByRole($arRequest, 'AUTHOR_PROP.VALUE', false);
    }

    // Получение курсов по студенту (ADMIN)
    // /api/Courses/getByStudent/?id=
    public static function getByStudent(array $arRequest): array
    {
        return self::getByRole($arRequest, 'STUDENTS_PROP.VALUE', false);
    }

    private static function getByRole(array $arRequest, string $prop, bool $fullInfo): array
    {
        UserAccess::checkAdmin();

        $id = (int)($arRequest['id'] ?? 0);
        if (!$id) throw new \Exception('Не передан ID');

        $withModules = $prop !== 'STUDENTS_PROP.VALUE';
        $result = self::getList($arRequest, [$prop => $id]);
        $result['items'] = array_map(
            fn($row) => self::mapCourseRow($row, $fullInfo, $withModules),
            $result['items']
        );

        return $result;
    }

    // Добавление курса
    // /api/Courses/add/
    public static function add(array $arData): array
    {
        CourseAccess::assertTeacherOrAdmin();

        $role = UserAccess::getUserRole();
        $name = trim($arData['name'] ?? '');
        $description = trim($arData['description'] ?? '');
        if ($name === '' || $description === '') {
            throw new \Exception('name и description обязательны');
        }

        $authorId = $role === 'teacher'
            ? UserAccess::getCurrentUserId()
            : (int)($arData['author_id'] ?? 0);

        $author = UserAccess::getUserById($authorId);
        if (!$author || UserAccess::getUserRole($authorId) !== 'teacher') {
            throw new \Exception('Автор должен быть преподавателем');
        }

        $id = CoursesTable::addCourse([
            'NAME'        => $name,
            'DESCRIPTION' => Mappers::mapDescription($description),
            'AUTHOR_PROP' => $author,
        ]);

        if (!$id) throw new \Exception('Ошибка создания курса');

        return [
            'success' => true, 'ID' => $id, 'message' => 'Курс успешно создан'
        ];
    }

    // Добавление студента в курс
    // /api/Courses/addStudent/?course_id=1&student_id=2
    public static function addStudent(array $arData): array
    {
        $courseId = (int)($arData['course_id'] ?? 0);
        $studentId = (int)($arData['student_id'] ?? 0);

        return self::updateCourseStudents($courseId, $studentId, 'add');
    }

    // Удаление студента из курса
    // /api/Courses/removeStudent/?course_id=1&student_id=2
    public static function removeStudent(array $arData): array
    {
        $courseId = (int)($arData['course_id'] ?? 0);
        $studentId = (int)($arData['student_id'] ?? 0);

        return self::updateCourseStudents($courseId, $studentId, 'remove');
    }

    private static function updateCourseStudents(int $courseId, int $studentId, string $action): array
    {
        if (!$courseId || !$studentId) {
            throw new \Exception('Не передан course_id или student_id');
        }

        $course = CourseAccess::getCourseForManage($courseId);

        $students = [];
        if (!empty($course['STUDENT_ID'])) {
            if (isset($course['STUDENT_ID']['VALUE']) && is_array($course['STUDENT_ID']['VALUE'])) {
                $students = $course['STUDENT_ID']['VALUE'];
            } elseif (is_array($course['STUDENT_ID'])) {
                $students = $course['STUDENT_ID'];
            } elseif (is_string($course['STUDENT_ID'])) {
                $students = array_filter(explode(',', $course['STUDENT_ID']));
            }
            $students = array_map('intval', $students);
        }

        if ($action === 'add') {
            if (in_array($studentId, $students, true)) {
                throw new \Exception('Студент уже добавлен в курс');
            }
            $students[] = $studentId;
            $message = 'Студент успешно добавлен в курс';
        } elseif ($action === 'remove') {
            if (!in_array($studentId, $students, true)) {
                throw new \Exception('Студента нет в курсе');
            }
            $students = array_values(array_filter($students, fn($id) => $id != $studentId));
            $message = 'Студент успешно удален из курса';
        } else {
            throw new \Exception('Неверное действие');
        }

        \CIBlockElement::SetPropertyValuesEx(
            $courseId,
            Constants::IB_COURSES,
            [Constants::COURSE_STUDENTS => $students ?: false]
        );

        return ['success' => true, 'message' => $message];
    }

    // Удаление курса
    // /api/Courses/delete/?id=1
    public static function delete(array $arData): array
    {
        $courseId = (int)($arData['id'] ?? 0);
        if (!$courseId) throw new \Exception('Не передан ID курса');

        CourseAccess::getCourseForManage($courseId);

        if (!\CModule::IncludeModule('iblock')) {
            throw new \Exception('Не удалось подключить модуль iblock');
        }

        $el = new \CIBlockElement();
        if (!$el->Delete($courseId)) {
            throw new \Exception('Не удалось удалить курс');
        }

        return ['success' => true, 'message' => 'Курс успешно удален'];
    }
}