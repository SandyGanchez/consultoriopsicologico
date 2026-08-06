<?php

/**
 * Procesa confirmaciones y recordatorios de cita (CLI).
 * También genera la notificación de campana del recordatorio (Fase 3D)
 * una sola vez por fila de correo_cita (ledger UNIQUE).
 *
 * Uso:
 *   php database/scripts/procesar_correos_citas.php
 *
 * Cron sugerido (cada 15 min; no aplicar en Hostinger desde esta fase):
 *   minuto 0,15,30,45 * * * * php /ruta/private/database/scripts/procesar_correos_citas.php >> /ruta/logs/correos_citas.log 2>&1
 *
 * Variables útiles:
 *   MAIL_CITA_DRY_RUN=1       → no envía SMTP; marca ENVIADO en pruebas.
 *   CITA_RECORDATORIO_HORAS=24 → anticipación (1–168); no aceptar vía web.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Solo ejecución por CLI.\n");
    exit(1);
}

define('APP_ROOT', dirname(__DIR__, 2));
require APP_ROOT . '/vendor/autoload.php';

use App\Config\Config;
use App\Services\CorreoCitaService;

Config::load();

$lockPath = APP_ROOT . '/storage/locks/procesar_correos_citas.lock';
$lockDir = dirname($lockPath);
if (!is_dir($lockDir)) {
    mkdir($lockDir, 0775, true);
}

$lockFh = fopen($lockPath, 'c+');
if ($lockFh === false) {
    fwrite(STDERR, "No fue posible abrir el archivo de bloqueo.\n");
    exit(2);
}

if (!flock($lockFh, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "Ya hay otra ejecución en curso.\n");
    exit(3);
}

try {
    date_default_timezone_set('America/Mexico_City');

    $servicio = new CorreoCitaService();
    if (!$servicio->persistenciaDisponible()) {
        echo "correo_cita no disponible en esta base.\n";
        exit(0);
    }

    $resumen = $servicio->procesarLote(50);

    echo 'OK '
        . 'recuperados=' . $resumen['recuperados']
        . ' reactivados=' . ($resumen['reactivados'] ?? 0)
        . ' procesados=' . $resumen['procesados']
        . ' enviados=' . $resumen['enviados']
        . ' fallidos=' . $resumen['fallidos']
        . ' omitidos=' . $resumen['omitidos']
        . ' dry_run=' . (
            in_array(
                strtolower((string) Config::get('MAIL_CITA_DRY_RUN', '')),
                ['1', 'true', 'yes', 'on'],
                true
            ) ? '1' : '0'
        )
        . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR_CONTROLADO\n");
    exit(4);
} finally {
    flock($lockFh, LOCK_UN);
    fclose($lockFh);
}
