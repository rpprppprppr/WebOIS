<?php
namespace Legacy\API;

use CUser;
use Legacy\General\Constants;

class User
{
    private static function getList(array $arRequest = []): array
    {
        $filter = $arRequest['filter'] ?? [];
        $limit  = (int)($arRequest['limit'] ?? 20);
        $page   = (int)($arRequest['page'] ?? 1);

        $navParams = ['nPageSize' => $limit, 'iNumPage' => $page];
        $rsUsers = CUser::GetList('ID', 'ASC', $filter, $navParams);

        $users = [];
        while ($arUser = $rsUsers->Fetch()) {
            $users[] = UserMapper::map($arUser);
        }

        $total = CUser::GetList('ID', 'ASC', $filter)->SelectedRowsCount();

        return [
            'count' => count($users),
            'total' => $total,
            'items' => $users
        ];
    }

    // Получение всех пользователей
    // /api/User/get/
    public static function get(array $arRequest = []): array
    {
        return self::getList($arRequest);
    }

    // Получение пользователя по ID
    // /api/User/getById/?id=
    public static function getById(array $arRequest): ?array
    {
        $userId = (int)($arRequest['id'] ?? 0);
        if (!$userId) throw new \Exception('Не передан ID пользователя');

        $rsUser = CUser::GetByID($userId);
        $arUser = $rsUser->Fetch();

        return $arUser ? UserMapper::map($arUser) : null;
    }

    // Получение преподавателей
    // /api/User/getTeachers/
    public static function getTeachers(array $arRequest = []): array
    {
        return self::getList(array_merge($arRequest, ['filter' => ['GROUPS_ID' => Constants::GROUP_TEACHERS]]));
    }

    // Получение студентов
    // /api/User/getStudents/
    public static function getStudents(array $arRequest = []): array
    {
        return self::getList(array_merge($arRequest, ['filter' => ['GROUPS_ID' => Constants::GROUP_STUDENTS]]));
    }
}