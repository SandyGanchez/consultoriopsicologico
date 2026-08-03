<?php

/**
 * Uso (en el servidor, fuera de la web, con .env cargado):
 *   php set_admin_password.php "TuClaveSegura123"
 *
 * Actualiza solo el usuario ADMINISTRADOR. No imprime el hash.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 3);
if (!is_file($root . '/vendor/autoload.php')) {
    // Estructura Hostinger: script dentro de private/deploy/... o private/scripts
    $alt = dirname(__DIR__, 2);
    if (is_file($alt . '/vendor/autoload.php')) {
        $root = $alt;
    }
}

require $root . '/vendor/autoload.php';

use App\Config\Config;
use App\Config\Database;

if ($argc < 2) {
    fwrite(STDERR, "Uso: php set_admin_password.php \"ClaveSegura\"\n");
    exit(1);
}

$password = (string) $argv[1];

if (
    strlen($password) < 8
    || !preg_match('/[A-Za-z]/', $password)
    || !preg_match('/[0-9]/', $password)
) {
    fwrite(STDERR, "La clave debe tener mínimo 8 caracteres, con letras y números.\n");
    exit(1);
}

if (!defined('APP_ROOT')) {
    define('APP_ROOT', $root);
}

Config::load();
$db = Database::connect();

$hash = password_hash($password, PASSWORD_DEFAULT);
if (!$hash) {
    fwrite(STDERR, "No se pudo generar el hash.\n");
    exit(1);
}

$stmt = $db->prepare(
    "UPDATE usuario
     SET ContrasenaUsu = :hash,
         RequiereCambioContrasena = 0,
         EstadoUsu = 1
     WHERE RolUsu = 'ADMINISTRADOR'
     LIMIT 1"
);

$stmt->execute(['hash' => $hash]);

if ($stmt->rowCount() < 1) {
    fwrite(STDERR, "No se actualizó ningún administrador.\n");
    exit(1);
}

echo "Administrador actualizado correctamente.\n";
