<?php
namespace Legacy\API;

use CUser;

class Auth
{
    // Вход
    // /api/Auth/login/?login=123&password=123
    public static function login(array $arRequest): array
    {
        global $USER;

        $login = trim($arRequest['login'] ?? '');
        $password = (string)($arRequest['password'] ?? '');

        if ($login === '' || $password === '') {
            throw new \Exception('Требуется ввести логин и пароль');
        }

        if ($USER->Login($login, $password, 'Y') !== true) {
            throw new \Exception('Неверный логин или пароль');
        }

        $arUser = CUser::GetByID($USER->GetID())->Fetch();
        return ['user' => UserMapper::map($arUser, true)];
    }

    // Выход
    // /api/Auth/logout/
    public static function logout(): array
    {
        global $USER;
        $USER->Logout();
        return ['success' => true];
    }

    // Получение профиля текущего пользователя
    // /api/Auth/profile/
    public static function profile(): array
    {
        global $USER;
        if (!$USER->IsAuthorized()) throw new \Exception('Пользователь не авторизован');

        $arUser = CUser::GetByID($USER->GetID())->Fetch();
        return UserMapper::map($arUser, true);
    }
}