<?php
namespace Legacy\API;

use CUser;
use Legacy\API\Access\UserAccess;
use Legacy\General\Constants;

class User
{
    // Создание пользователя
    // /api/User/add/?login=test&password=123123&email=test@mail.ru&role=student
    public static function add(array $arRequest): array
    {
        UserAccess::checkAdmin();

        $login    = trim($arRequest['login'] ?? '');
        $password = trim($arRequest['password'] ?? '');
        $email    = trim($arRequest['email'] ?? '');
        $role     = strtolower(trim($arRequest['role'] ?? ''));

        if (CUser::GetByLogin($login)->Fetch()) {
            throw new \Exception('Пользователь с таким логином уже существует');
        }

        if (!$login || !$password || !$email || !$role) {
            throw new \Exception('Не переданы обязательные параметры: login, password, email, role');
        }

        switch ($role) {
            case 'student':
                $groupId = Constants::GROUP_STUDENTS;
                break;
            case 'teacher':
                $groupId = Constants::GROUP_TEACHERS;
                break;
            default:
                throw new \Exception('Некорректная роль. Допустимые значения: student, teacher');
        }

        $user = new CUser();
        $userId = $user->Add([
            'LOGIN'            => $login,
            'PASSWORD'         => $password,
            'CONFIRM_PASSWORD' => $password,
            'EMAIL'            => $email,
            'GROUP_ID'         => [$groupId],
            'ACTIVE'           => 'Y',
        ]);

        if (!$userId) {
            throw new \Exception('Не удалось создать пользователя: ' . $user->LAST_ERROR);
        }

        return [
            'status' => 'success',
            'ID' => $userId,
            'message' => 'Пользователь успешно создан'];
    }

    // Обновление пользователя
    // /api/User/update/?id=5&login=newlogin&email=test@mail.ru&role=teacher
    public static function update(array $arRequest): array
    {
        $currentUserId = UserAccess::checkAuth();
        $isAdmin = UserAccess::getUserRole() === 'admin';

        $userId = (int)($arRequest['id'] ?? 0);
        if (!$userId) {
            throw new \Exception('Не передан ID пользователя');
        }

        if (!$isAdmin && $userId !== $currentUserId) {
            throw new \Exception('Недостаточно прав для редактирования пользователя');
        }

        $arUser = CUser::GetByID($userId)->Fetch();
        if (!$arUser) {
            throw new \Exception('Пользователь не найден');
        }

        $fields = [];

        $map = [
            'login'       => 'LOGIN',
            'email'       => 'EMAIL',
            'first_name'  => 'NAME',
            'last_name'   => 'LAST_NAME',
            'second_name' => 'SECOND_NAME',
        ];

        foreach ($map as $reqKey => $bitrixKey) {
            if (isset($arRequest[$reqKey])) {
                $fields[$bitrixKey] = trim($arRequest[$reqKey]);
            }
        }

        if (!empty($arRequest['login'])) {
            $login = trim($arRequest['login']);
            if ($u = CUser::GetByLogin($login)->Fetch()) {
                if ((int)$u['ID'] !== $userId) {
                    throw new \Exception('Пользователь с таким логином уже существует');
                }
            }

            $fields['LOGIN'] = $login;
        }

        if (!empty($arRequest['role'])) {
            if (!$isAdmin) {
                throw new \Exception('Изменение роли доступно только администратору');
            }

            $groupMap = [
                'student' => Constants::GROUP_STUDENTS,
                'teacher' => Constants::GROUP_TEACHERS,
            ];

            $role = strtolower(trim($arRequest['role']));
            if (!isset($groupMap[$role])) {
                throw new \Exception('Некорректная роль');
            }

            CUser::SetUserGroup($userId, [$groupMap[$role]]);
        }

        if (!$fields) {
            throw new \Exception('Нет данных для обновления');
        }

        $user = new CUser();
        if (!$user->Update($userId, $fields)) {
            throw new \Exception('Ошибка обновления пользователя: ' . $user->LAST_ERROR);
        }

        return [
            'status' => 'success',
            'message' => 'Пользователь успешно обновлен',
        ];
    }


    // Удаление пользователя
    // /api/User/delete/?id=5
    public static function delete(array $arRequest): array
    {
        UserAccess::checkAdmin();

        $userId = (int)($arRequest['id'] ?? 0);
        if (!$userId) throw new \Exception('Не передан ID пользователя');

        if ($userId === UserAccess::getCurrentUserId()) {
            throw new \Exception('Нельзя удалить самого себя');
        }

        $user = new CUser();
        if (!$user->Delete($userId)) {
            throw new \Exception('Не удалось удалить пользователя');
        }

        return ['status' => 'success', 'message' => 'Пользователь успешно удален'];
    }

    private static function getList(array $arRequest = []): array
    {
        $filter = $arRequest['filter'] ?? [];
        $limit  = (int)($arRequest['limit'] ?? 20);
        $page   = (int)($arRequest['page'] ?? 1);

        $navParams = ['nPageSize' => $limit, 'iNumPage' => $page];
        $rsUsers = CUser::GetList('ID', 'ASC', $filter, $navParams);

        $users = [];
        while ($arUser = $rsUsers->Fetch()) {
            $users[] = Mappers::mapUser($arUser, false);
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
        UserAccess::checkAdmin();

        return self::getList($arRequest);
    }

    // Получение пользователя по ID
    // /api/User/getById/?id=
    public static function getById(array $arRequest): ?array
    {
        UserAccess::checkAdmin();

        $userId = (int)($arRequest['id'] ?? 0);
        if (!$userId) throw new \Exception('Не передан ID пользователя');

        $rsUser = CUser::GetByID($userId);
        $arUser = $rsUser->Fetch();

        return $arUser ? Mappers::mapUser($arUser, true) : null;
    }

    // Получение преподавателей
    // /api/User/getTeachers/
    public static function getTeachers(array $arRequest = []): array
    {
        UserAccess::checkAdmin();

        return self::getList(array_merge($arRequest, ['filter' => ['GROUPS_ID' => Constants::GROUP_TEACHERS]]));
    }

    // Получение студентов
    // /api/User/getStudents/
    public static function getStudents(array $arRequest = []): array
    {
        UserAccess::checkAdmin();

        return self::getList(array_merge($arRequest, ['filter' => ['GROUPS_ID' => Constants::GROUP_STUDENTS]]));
    }
}