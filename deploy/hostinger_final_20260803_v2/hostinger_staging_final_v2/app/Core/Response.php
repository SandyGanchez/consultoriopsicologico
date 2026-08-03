<?php

namespace App\Core;

use App\Helpers\Helper;

class Response
{
    public static function redirect(
        string $ruta,
        int $codigo = 302
    ): void {
        $codigo = in_array($codigo, [301, 302, 303, 307, 308], true)
            ? $codigo
            : 302;

        http_response_code($codigo);
        header('Location: ' . Helper::baseUrl($ruta));
        exit;
    }
}