<?php

declare(strict_types=1);

/**
 * Punto de entrada público de PsicoMatch.
 * Resuelve APP_ROOT sin rutas absolutas de Laragon/XAMPP/Hostinger.
 */

setlocale(LC_TIME, 'es_MX.UTF-8', 'es_MX', 'spanish');

$resolveAppRoot = static function (): string {
    $publicDir = __DIR__;
    $parentDir = dirname($publicDir);

    $candidates = [
        $parentDir . DIRECTORY_SEPARATOR . 'private',
        $parentDir,
        $publicDir,
    ];

    foreach ($candidates as $candidate) {
        $autoload = $candidate . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
        $appDir = $candidate . DIRECTORY_SEPARATOR . 'app';

        if (is_file($autoload) && is_dir($appDir)) {
            return $candidate;
        }
    }

    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'PsicoMatch: no se encontró APP_ROOT (vendor/autoload.php + app/).';
    exit(1);
};

define('APP_ROOT', $resolveAppRoot());
define('PUBLIC_PATH', __DIR__);

require APP_ROOT . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

use App\Config\Config;
use App\Config\Paths;
use App\Core\App;

Config::load();

$timezone = (string) Config::get('APP_TIMEZONE', 'America/Mexico_City');
if ($timezone !== '') {
    date_default_timezone_set($timezone);
} else {
    date_default_timezone_set('America/Mexico_City');
}

if (Paths::isProduction()) {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
} elseif (filter_var(Config::get('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN)) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

$isHttps = Paths::requestIsHttps();

$secureCookie = Config::get('SESSION_SECURE_COOKIE');
if ($secureCookie === null || $secureCookie === '') {
    $secure = Paths::isProduction() ? true : $isHttps;
} else {
    $secure = filter_var($secureCookie, FILTER_VALIDATE_BOOLEAN);
}

$httpOnly = Config::get('SESSION_HTTP_ONLY');
$httpOnly = ($httpOnly === null || $httpOnly === '')
    ? true
    : filter_var($httpOnly, FILTER_VALIDATE_BOOLEAN);

$sameSite = trim((string) Config::get('SESSION_SAME_SITE', 'Lax'));
if (!in_array($sameSite, ['Lax', 'Strict', 'None'], true)) {
    $sameSite = 'Lax';
}

session_name('CONSULTORIO_SESSION');

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $secure,
    'httponly' => $httpOnly,
    'samesite' => $sameSite,
]);

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', $httpOnly ? '1' : '0');
ini_set('session.cookie_secure', $secure ? '1' : '0');

session_start();

// Compatibilidad con vistas legacy que usan BASE_URL.
if (!defined('BASE_URL')) {
    define(
        'BASE_URL',
        rtrim((string) Config::get('APP_URL', ''), '/') . '/'
    );
}

$app = new App();
$app->run();
