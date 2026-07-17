<?php

namespace App\Config;

class Config
{
    private static array $config = [];

    public static function load(): void
    {
        if (!empty(self::$config)) {
            return;
        }

        $env = dirname(__DIR__, 2) . '/.env';

        if (!file_exists($env)) {
            die('No se encontró el archivo .env');
        }

        $lines = file($env, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {

            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);

            self::$config[trim($key)] = trim($value, "\"'");
        }
    }

    public static function get(string $key, $default = null)
    {
        self::load();

        return self::$config[$key] ?? $default;
    }
}