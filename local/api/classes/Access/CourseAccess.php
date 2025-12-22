<?php
namespace Legacy\API\Access;

use Legacy\API\Courses;

class CourseAccess
{
    public static function getCourseForView(int $courseId): array
    {
        $userId = UserAccess::checkAuth();
        $role   = UserAccess::getUserRole();

        $result = Courses::getRawById($courseId);
        $course = $result['course'];

        $studentIds = [];
        if (!empty($course['STUDENT_ID'])) {
            if (isset($course['STUDENT_ID']['VALUE']) && is_array($course['STUDENT_ID']['VALUE'])) {
                $studentIds = $course['STUDENT_ID']['VALUE'];
            } elseif (is_array($course['STUDENT_ID'])) {
                $studentIds = $course['STUDENT_ID'];
            } elseif (is_string($course['STUDENT_ID'])) {
                $studentIds = array_filter(explode(',', $course['STUDENT_ID']));
            }
            $studentIds = array_map('intval', $studentIds);
        }

        $fullInfo = match ($role) {
            'admin'   => true,
            'teacher' => ((int)$course['AUTHOR_ID'] === $userId)
                ? true
                : throw new \Exception('Доступ запрещен: это не ваш курс'),
            'student' => in_array($userId, $studentIds, true)
                ? true
                : throw new \Exception('Доступ запрещен: вы не записаны на этот курс'),
            default   => throw new \Exception('Доступ запрещен'),
        };

        return [
            'course'   => $course,
            'fullInfo' => (bool)$fullInfo,
        ];
    }

    public static function getCourseForManage(int $courseId): array
    {
        $userId = UserAccess::checkAuth();
        $role   = UserAccess::getUserRole();

        if ($role === 'student') {
            throw new \Exception('Доступ запрещен: необходима роль админа или преподавателя');
        }

        $course = Courses::getRawById($courseId)['course'];

        if ($role === 'teacher' && (int)$course['AUTHOR_ID'] !== $userId) {
            throw new \Exception('Доступ запрещен: это не ваш курс');
        }

        return $course;
    }

    public static function assertTeacherOrAdmin(): void
    {
        UserAccess::checkAuth();

        if (UserAccess::getUserRole() === 'student') {
            throw new \Exception('Доступ запрещен: необходима роль админа или преподавателя');
        }
    }
}