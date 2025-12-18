<?php
namespace Legacy\API;

use CUser;
use Legacy\General\Constants;

class User
{
    private static function mapRow(array $arUser): array
    {
        $userGroups = CUser::GetUserGroup($arUser['ID']);

        return [
            'id'            => $arUser['ID'],
            'login'         => $arUser['LOGIN'],
            'email'         => $arUser['EMAIL'],
            'firstName'     => $arUser['NAME'] ?? '',
            'lastName'      => $arUser['LAST_NAME'] ?? '',
            'secondName'    => $arUser['SECOND_NAME'] ?? '',
            'dateRegister'  => $arUser['DATE_REGISTER'] ?? '',
            'lastLogin'     => $arUser['LAST_LOGIN'] ?? '',
            'active'        => $arUser['ACTIVE'] ?? 'N',
            'blocked'       => $arUser['UF_BLOCKED'] ?? 'N',
            'isTeacher'     => in_array(Constants::GROUP_TEACHERS, $userGroups),
            'isStudent'     => in_array(Constants::GROUP_STUDENTS, $userGroups),
            'isAdmin'       => in_array(Constants::GROUP_ADMINS, $userGroups)
        ];
    }

    private static function getList(array $arRequest = []): array
    {
        $filter = $arRequest['filter'] ?? [];
        $limit  = (int)($arRequest['limit'] ?? 50);
        $page   = (int)($arRequest['page'] ?? 1);

        $navParams = ['nPageSize' => $limit, 'iNumPage' => $page];
        $rsUsers = CUser::GetList('ID', 'ASC', $filter, $navParams);

        $users = [];
        while ($arUser = $rsUsers->Fetch()) {
            $users[] = self::mapRow($arUser);
        }

        // Получаем общее количество подходящих пользователей
        $rsTotal = CUser::GetList('ID', 'ASC', $filter);
        $total = $rsTotal->SelectedRowsCount();

        return [
            'count' => count($users),
            'total' => $total,
            'items' => $users
        ];
    }

    // Получение текущего пользователя
    // /api/User/getCurrent/
    public static function getCurrent(): array
    {
        global $USER;

        if (!$USER->IsAuthorized()) {
            return ['message' => 'User not authenticated'];
        }

        $userId = $USER->GetID();
        $rsUser = CUser::GetByID($userId);
        $arUser = $rsUser->Fetch();

        return self::mapRow($arUser);
    }

    // Получение пользователя по ID
    // /api/User/getById/?id=
    public static function getById(array $arRequest): ?array
    {
        $userId = (int)($arRequest['id'] ?? 0);
        if (!$userId) {
            throw new \Exception('Не передан ID пользователя');
        }

        $rsUser = CUser::GetByID($userId);
        $arUser = $rsUser->Fetch();

        return $arUser ? self::mapRow($arUser) : null;
    }

    // Получение преподавателей
    // /api/User/getTeachers/
    public static function getTeachers(array $arRequest = []): array
    {
        $filter = ['GROUPS_ID' => Constants::GROUP_TEACHERS];
        return self::getList(array_merge($arRequest, ['filter' => $filter]));
    }

    // Получение студентов
    // /api/User/getStudents/
    public static function getStudents(array $arRequest = []): array
    {
        $filter = ['GROUPS_ID' => Constants::GROUP_STUDENTS];
        return self::getList(array_merge($arRequest, ['filter' => $filter]));
    }
}
