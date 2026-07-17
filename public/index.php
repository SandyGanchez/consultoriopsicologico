<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\Config;
use App\Core\App;
use App\Core\Session;
Config::load();
date_default_timezone_set('America/Mexico_City');
$isHttps =
    (!empty($_SERVER['HTTPS']) &&
     $_SERVER['HTTPS'] !== 'off')
    ||
    (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

session_name('CONSULTORIO_SESSION');

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax'
]);

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');

session_start();

$app = new App();
$app->run();