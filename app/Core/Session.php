<?php

namespace App\Core;

class Session
{
    public static function start()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function set($key, $value)
    {
        self::start();

        $_SESSION[$key] = $value;
    }

    public static function get($key)
    {
        self::start();

        return $_SESSION[$key] ?? null;
    }

    public static function has($key)
    {
        self::start();

        return isset($_SESSION[$key]);
    }
    public static function remove(string $key): void
{
    self::start();

    unset($_SESSION[$key]);
}

    public static function destroy()
    {
        self::start();

        session_destroy();
    }
    public static function csrfToken(): string
{
    $token = self::get('csrf_token');

    if (!is_string($token) || $token === '') {
        $token = bin2hex(random_bytes(32));
        self::set('csrf_token', $token);
    }

    return $token;
}

public static function validarCsrf(
    ?string $token
): bool {
    $tokenSesion = self::get('csrf_token');

    return
        is_string($token) &&
        is_string($tokenSesion) &&
        $token !== '' &&
        hash_equals($tokenSesion, $token);
}

public static function regenerar(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}
}