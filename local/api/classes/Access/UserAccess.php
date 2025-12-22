<?php
namespace Legacy\API\Access;

use CUser;

use Legacy\API\Mappers;
use Legacy\General\Constants;

class UserAccess
{
    public static function checkAuth(): int
    {
        $userId = self::getCurrentUserId();
        if ($userId <= 0) throw new \Exception('Пользователь не авторизован');
        return $userId;
    }

    public static function checkAdmin(): void
    {
        self::checkAuth();

        $role = self::getUserRole();
        if ($role !== 'admin') {
            throw new \Exception('Доступ запрещен: требуется роль администратора');
        }
    }

    public static function getUserById(int $userId, bool $full = false): ?array
    {
        if ($userId <= 0) return null;
        $rsUser = CUser::GetByID($userId);
        $arUser = $rsUser->Fetch();
        return $arUser ? Mappers::mapUser($arUser, $full) : null;
    }

    public static function getUserRole(?int $userId = null): string
    {
        global $USER;
        $groups = [];

        if ($userId === null) {
            $groups = $USER->GetUserGroupArray() ?? [];
        } else {
            $userData = CUser::GetByID($userId)->Fetch();

            if ($userData) {
                $groups = CUser::GetUserGroup($userId);
            } else {
                return 'student';
            }
        }

        if (in_array(Constants::GROUP_ADMINS, $groups)) return 'admin';
        if (in_array(Constants::GROUP_TEACHERS, $groups)) return 'teacher';
        else return 'student';
    }

    public static function getCurrentUserId(): int
    {
        global $USER;
        return (int)($USER->GetID() ?? 0);
    }
}
