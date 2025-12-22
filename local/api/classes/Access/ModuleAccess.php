<?php
namespace Legacy\API\Access;

use Legacy\Iblock\ModulesTable;
use Legacy\Iblock\CoursesTable;

class ModuleAccess
{
    public static function getModuleForView(int $moduleId): array
    {
        $userId = UserAccess::checkAuth();
        $role = UserAccess::getUserRole();

        $module = ModulesTable::getModuleById($moduleId);
        if (!$module) throw new \Exception('Модуль не найден');

        $course = CoursesTable::getCourseById((int)$module['COURSE_ID']);
        if (!$course) throw new \Exception('Курс модуля не найден');

        if ($role === 'teacher' && (int)$course['AUTHOR_ID'] !== $userId) {
            throw new \Exception('Доступ запрещен: это не ваш курс');
        }

        if ($role === 'student') {
            $students = is_array($course['STUDENT_ID']) ? $course['STUDENT_ID'] : [$course['STUDENT_ID']];
            if (!in_array($userId, $students)) {
                throw new \Exception('Доступ запрещен: вы не записаны на курс');
            }
        }

        return $module;
    }

    public static function getModuleForManage(int $moduleId): array
    {
        $userId = UserAccess::checkAuth();
        $role = UserAccess::getUserRole();

        if (!in_array($role, ['admin', 'teacher'])) {
            throw new \Exception('Доступ запрещен: необходима роль админа или преподавателя');
        }

        $module = ModulesTable::getModuleById($moduleId);
        if (!$module) throw new \Exception('Модуль не найден');

        if ($role === 'teacher') {
            $course = CoursesTable::getCourseById((int)$module['COURSE_ID']);
            if ((int)$course['AUTHOR_ID'] !== $userId) {
                throw new \Exception('Доступ запрещен: нельзя управлять чужим модулем');
            }
        }

        return $module;
    }

    public static function assertTeacherOrAdmin(): void
    {
        UserAccess::checkAuth();
        if (UserAccess::getUserRole() === 'student') {
            throw new \Exception('Доступ запрещен: необходима роль админа или преподавателя');
        }
    }
}