<?php

namespace App\Core;

use App\Helpers\Helper;

class Response
{
    public static function redirect(string $ruta)
    {
        header("Location: " . Helper::baseUrl($ruta));
        exit;
    }
}