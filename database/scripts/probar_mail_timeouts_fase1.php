<?php

/**
 * Pruebas controladas de timeouts SMTP (sin envío real).
 * Ejecutar: php database/scripts/probar_mail_timeouts_fase1.php
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Config\Config;
use App\Services\MailService;

$fallos = 0;
$casos = [];

$assert = static function (
    bool $cond,
    string $nombre,
    string $detalle = ''
) use (&$casos, &$fallos): void {
    $casos[] = ['ok' => $cond, 'nombre' => $nombre, 'detalle' => $detalle];
    if (!$cond) {
        $fallos++;
    }
};

$mail = new MailService();
$ref = new ReflectionClass($mail);

$resolverTimeout = $ref->getMethod('resolverTimeoutSmtp');
$resolverTimeout->setAccessible(true);
$resolverTimelimit = $ref->getMethod('resolverTimelimitSmtp');
$resolverTimelimit->setAccessible(true);
$aplicar = $ref->getMethod('aplicarLimitesSmtp');
$aplicar->setAccessible(true);

$prevTimeout = Config::get('MAIL_TIMEOUT', '');
$prevTimelimit = Config::get('MAIL_TIMELIMIT', '');

// 1-2. Predeterminados (clave ausente / vacía inválida → default)
Config::override([
    'MAIL_TIMEOUT' => '',
    'MAIL_TIMELIMIT' => ''
]);
$assert(
    (int) $resolverTimeout->invoke($mail) === 15,
    '1. MAIL_TIMEOUT predeterminado 15'
);
$assert(
    (int) $resolverTimelimit->invoke($mail) === 20,
    '2. MAIL_TIMELIMIT predeterminado 20'
);

// Valores válidos
Config::override([
    'MAIL_TIMEOUT' => '25',
    'MAIL_TIMELIMIT' => '40'
]);
$assert((int) $resolverTimeout->invoke($mail) === 25, '3. Timeout válido 25');
$assert((int) $resolverTimelimit->invoke($mail) === 40, '4. Timelimit válido 40');

// Fuera de rango → predeterminado
Config::override(['MAIL_TIMEOUT' => '3']);
$assert((int) $resolverTimeout->invoke($mail) === 15, '5. Timeout <5 → 15');
Config::override(['MAIL_TIMEOUT' => '120']);
$assert((int) $resolverTimeout->invoke($mail) === 15, '6. Timeout >60 → 15');
Config::override(['MAIL_TIMELIMIT' => '2']);
$assert((int) $resolverTimelimit->invoke($mail) === 20, '7. Timelimit <5 → 20');
Config::override(['MAIL_TIMELIMIT' => '999']);
$assert((int) $resolverTimelimit->invoke($mail) === 20, '8. Timelimit >120 → 20');

// Aplicación sobre PHPMailer (sin conectar)
Config::override([
    'MAIL_TIMEOUT' => '15',
    'MAIL_TIMELIMIT' => '20'
]);
$phpMailer = new PHPMailer\PHPMailer\PHPMailer(true);
$phpMailer->isSMTP();
$aplicar->invoke($mail, $phpMailer);
$assert(
    (int) $phpMailer->Timeout === 15,
    '9. Timeout aplicado a PHPMailer'
);
$smtp = $phpMailer->getSMTPInstance();
$assert(
    (int) $smtp->Timelimit === 20 && (int) $smtp->Timeout === 15,
    '10. Timelimit/Timeout aplicados a SMTP'
);

// Restaurar
Config::override([
    'MAIL_TIMEOUT' => (string) $prevTimeout,
    'MAIL_TIMELIMIT' => (string) $prevTimelimit
]);

echo "Pruebas Mail timeouts Fase 1\n\n";
foreach ($casos as $caso) {
    echo ($caso['ok'] ? '[OK] ' : '[FAIL] ')
        . $caso['nombre']
        . ($caso['detalle'] !== '' ? ' — ' . $caso['detalle'] : '')
        . "\n";
}
echo "\nTotal: " . count($casos) . " | Fallos: {$fallos}\n";
exit($fallos > 0 ? 1 : 0);
