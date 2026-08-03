<?php

namespace App\Config;

/**
 * Resolución de rutas de aplicación y document root público.
 * Compatible con:
 * - Laragon local (proyecto/public + proyecto/)
 * - Hostinger separado (public_html + private/)
 * - Hostinger todo-en-public_html (bloqueando internos vía .htaccess)
 */
final class Paths
{
    public static function appRoot(): string
    {
        if (defined('APP_ROOT') && is_string(APP_ROOT) && APP_ROOT !== '') {
            return rtrim(str_replace('\\', '/', APP_ROOT), '/');
        }

        return rtrim(str_replace('\\', '/', dirname(__DIR__, 2)), '/');
    }

    public static function publicPath(): string
    {
        if (defined('PUBLIC_PATH') && is_string(PUBLIC_PATH) && PUBLIC_PATH !== '') {
            return rtrim(str_replace('\\', '/', PUBLIC_PATH), '/');
        }

        $candidate = self::appRoot() . '/public';

        if (is_dir($candidate)) {
            return $candidate;
        }

        return self::appRoot();
    }

    /**
     * Prefijo de URI derivado de APP_URL (vacío en dominio raíz).
     * Ej. local: /consultorio_psicologico/public
     * Ej. producción: ""
     */
    public static function urlBasePath(): string
    {
        $appUrl = (string) Config::get('APP_URL', '');
        $path = parse_url($appUrl, PHP_URL_PATH);

        if (!is_string($path) || $path === '' || $path === '/') {
            return '';
        }

        return rtrim($path, '/');
    }

    public static function isProduction(): bool
    {
        return strtolower((string) Config::get('APP_ENV', '')) === 'production';
    }

    public static function requestIsHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }

        if ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443') {
            return true;
        }

        // En producción Hostinger el TLS suele terminar en el proxy.
        // Solo se consultan encabezados reenviados con APP_ENV=production.
        if (!self::isProduction()) {
            return false;
        }

        $proto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));

        if ($proto === 'https') {
            return true;
        }

        if (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')) === 'on') {
            return true;
        }

        return false;
    }
}
