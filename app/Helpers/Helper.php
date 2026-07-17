<?php

namespace App\Helpers;

use App\Config\Config;

class Helper
{
    public static function baseUrl(string $path = ''): string
    {
        $base = rtrim(Config::get('APP_URL'), '/');

        return $base . '/' . ltrim($path, '/');
    }
}