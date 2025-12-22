<?php
namespace Legacy\API\Access;

use Legacy\API\Courses;

use Legacy\Iblock\CoursesTable;
use Legacy\Iblock\ModulesTable;

class SubmissionsAccess
{
    public static function getAllowedModuleIds(): array
    {
        $userId = UserAccess::checkAuth();
        $role   = UserAccess::getUserRole();

        if (in_array($role, ['admin', 'student'], true)) {
            return [];
        }

        $query = ModulesTable::query();

        ModulesTable::withSelect($query);
        ModulesTable::withRuntimeProperties($query);
        ModulesTable::withFilter($query);

        $result = $query->exec();

        $allowed = [];

        while ($module = $result->fetch()) {
            if (empty($module['COURSE_ID'])) {
                continue;
            }

            $course = CoursesTable::getCourseById((int)$module['COURSE_ID']);
            if (!$course) continue;

            if ((int)$course['AUTHOR_ID'] === $userId) {
                $allowed[] = (int)$module['ID'];
            }
        }

        return $allowed;
    }

    public static function assertStudent(): int
    {
        $userId = UserAccess::checkAuth();
        $role = UserAccess::getUserRole();
        if ($role !== 'student') {
            throw new \Exception('Доступ запрещен: только студенты могут сдавать работу');
        }
        return $userId;
    }

    public static function assertTeacherOrAdmin(): int
    {
        $userId = UserAccess::checkAuth();
        $role = UserAccess::getUserRole();
        if (!in_array($role, ['teacher', 'admin'])) {
            throw new \Exception('Доступ запрещен: необходимо иметь роль преподаватель или админ');
        }
        return $userId;
    }

    public static function checkSubmissionOwnership(array $row): void
    {
        $role = UserAccess::getUserRole();
        $userId = UserAccess::getCurrentUserId();

        if ($role === 'student' && (int)$row['UF_STUDENT_ID'] !== $userId) {
            throw new \Exception('Доступ запрещен: это не ваша работа');
        }

        if ($role === 'teacher') {
            $module = ModulesTable::getModuleById((int)$row['UF_MODULE']);
            if (!$module) throw new \Exception('Модуль не найден');

            $course = CoursesTable::getCourseById((int)$module['COURSE_ID']);
            if ((int)$course['AUTHOR_ID'] !== $userId) {
                throw new \Exception('Доступ запрещен: это не ваш модуль');
            }
        }
    }

    public static function assertModuleStudentAccess(int $moduleId, int $userId): void
    {
        $module = ModulesTable::getModuleById($moduleId);
        if (!$module) throw new \Exception('Модуль не найден');

        $course = CoursesTable::getCourseById((int)$module['COURSE_ID']);
        if (!$course) throw new \Exception('Курс не найден');

        $students = Courses::parseStudentIds($course['STUDENT_ID'] ?? '');

        if (!in_array($userId, $students, true)) {
            throw new \Exception('Доступ запрещен: вы не записаны на этот курс');
        }
    }

    public static function requireSubmissionId(array $arData): int
    {
        $id = (int)($arData['submission_id'] ?? 0);
        if (!$id) throw new \Exception('Не передан ID submission');
        return $id;
    }
}