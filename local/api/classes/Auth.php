<?php
namespace Legacy\API;

use CUser;

class Auth
{
    // Вход
    // /api/Auth/login/
    public static function login(array $arRequest = []): array
    {
        global $USER;

        if ($USER->IsAuthorized()) {
            throw new \Exception('Вы уже авторизованы');
        }

        $login = trim($arRequest['login'] ?? '');
        $password = (string)($arRequest['password'] ?? '');

        if ($login === '' || $password === '') {
            throw new \Exception('Требуется ввести логин и пароль');
        }

        if ($USER->Login($login, $password, 'Y') !== true) {
            throw new \Exception('Неверный логин или пароль');
        }

        return [
            'status' => 'success',
            'message' => 'Вы успешно авторизованы'
        ];
    }

    // Выход
    // /api/Auth/logout/
    public static function logout(): array
    {
        global $USER;

        if (!$USER->IsAuthorized()) {
            throw new \Exception('Пользователь не авторизован');
        }

        $USER->Logout();

        return [
            'status' => 'success',
            'message' => 'Вы успешно вышли из системы'
        ];
    }

    // Получение профиля текущего пользователя
    // /api/Auth/profile/
    public static function profile(): array
    {
        global $USER;
        if (!$USER->IsAuthorized()) throw new \Exception('Пользователь не авторизован');

        $arUser = CUser::GetByID($USER->GetID())->Fetch();
        return Mappers::mapUser($arUser, true);
    }
}