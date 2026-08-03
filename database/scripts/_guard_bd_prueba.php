<?php

/**
 * Guardrail para scripts de prueba/validación.
 * Rechaza BD real, producción o nombres sin sufijo de prueba.
 *
 * @return never|void
 */
function pm_rechazar_bd_no_prueba(string $dbName, string $appEnv = ''): void
{
    $db = strtolower(trim($dbName));
    $env = strtolower(trim($appEnv));

    if ($env === 'production' || $env === 'prod') {
        fwrite(STDERR, "BLOQUEADO: APP_ENV=production. Este script no puede ejecutarse.\n");
        exit(99);
    }

    if ($db === 'consultorio_psicologico') {
        fwrite(STDERR, "BLOQUEADO: DB_NAME=consultorio_psicologico (base real). Este script no puede ejecutarse.\n");
        exit(99);
    }

    $esPrueba = str_contains($db, '_prueba')
        || str_contains($db, '_tmp')
        || str_contains($db, '_test')
        || str_starts_with($db, 'pm_validacion_');

    if (!$esPrueba) {
        fwrite(
            STDERR,
            "BLOQUEADO: el nombre de BD '{$dbName}' debe incluir _prueba, _tmp o _test.\n"
        );
        exit(99);
    }
}
