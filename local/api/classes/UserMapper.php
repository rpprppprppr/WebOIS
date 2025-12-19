<?php
namespace Legacy\API;

use CUser;
use Legacy\General\Constants;

class UserMapper
{
    public static function map(array $arUser, bool $full = false): array
    {
        $groups = CUser::GetUserGroup($arUser['ID']);
        $result = [
            'ID' => $arUser['ID'],
            'LOGIN' => $arUser['LOGIN'] ?? '',
            'EMAIL' => $arUser['EMAIL'] ?? '',
            'FIRST_NAME' => $arUser['NAME'] ?? '',
            'LAST_NAME' => $arUser['LAST_NAME'] ?? '',
            'SECOND_NAME' => $arUser['SECOND_NAME'] ?? '',
        ];

        if ($full) {
            $result['isTeacher'] = in_array(Constants::GROUP_TEACHERS, $groups);
            $result['isStudent'] = in_array(Constants::GROUP_STUDENTS, $groups);
            $result['isAdmin']   = in_array(Constants::GROUP_ADMINS, $groups);
        }

        return $result;
    }

    public static function hasGroup(int $userId, int $groupId): bool
    {
        $groups = CUser::GetUserGroup($userId);
        return in_array($groupId, $groups);
    }

    public static function getCurrentUserId(): int
    {
        global $USER;
        return (int)($USER->GetID() ?? 0);
    }

    public static function getCurrentUserGroups(): array
    {
        global $USER;
        return $USER->GetUserGroupArray();
    }
}