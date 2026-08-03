<?php

declare(strict_types=1);

/**
 * Humo local: enrutamiento de incidencias. No envía correos reales.
 * Limpia los registros creados al final.
 */

define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/vendor/autoload.php';

use App\Config\Database;
use App\Services\IncidenciaSoporteService;

$svc = new IncidenciaSoporteService();
if (!$svc->moduloDisponible()) {
    fwrite(STDERR, "Modulo no disponible\n");
    exit(1);
}

$casos = [
    ['correo' => 'sandisg321@gmail.com', 'esperado' => 'CONSULTORIO', 'etiqueta' => 'PACIENTE'],
    ['correo' => 'sandysanchezgarcia444@gmail.com', 'esperado' => 'CONSULTORIO', 'etiqueta' => 'PSICOLOGO'],
    ['correo' => 'rosasg321@gmail.com', 'esperado' => 'ADMINISTRADOR', 'etiqueta' => 'CONSULTORIO'],
    ['correo' => 'sanchezsandibell0@gmail.com', 'esperado' => null, 'etiqueta' => 'ADMINISTRADOR'],
    ['correo' => 'noexiste_humo_' . time() . '@example.test', 'esperado' => 'CONSULTORIO', 'etiqueta' => 'INEXISTENTE'],
];

$ids = [];
$db = Database::connect();

foreach ($casos as $i => $caso) {
    $post = [
        'correo' => $caso['correo'],
        'tipo' => 'AUTENTICACION',
        'descripcion' => 'Prueba humo auditoria enrutamiento ' . ($i + 1) . ' validacion automatica.',
        'rol' => 'HACKER', // debe ignorarse
    ];

    $res = $svc->registrarDesdeLogin($post, 'humo_test_' . $i);
    echo $caso['etiqueta'] . ' ok=' . (int) $res['ok'] . ' id=' . ($res['id'] ?? 'n/a') . PHP_EOL;

    if ($caso['esperado'] === null) {
        if (!empty($res['id'])) {
            echo "FAIL: ADMIN no debe crear ticket\n";
            exit(1);
        }
        continue;
    }

    if (empty($res['id'])) {
        // posible rate limit / duplicado; consultar última por correo
        $stmt = $db->prepare(
            'SELECT IdIncidencia, RolDestino, NivelAtencion
             FROM incidencia_soporte
             WHERE CorreoReportado = :c
             ORDER BY IdIncidencia DESC LIMIT 1'
        );
        $stmt->execute(['c' => strtolower($caso['correo'])]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            echo "FAIL: no se creó incidencia para {$caso['etiqueta']}\n";
            exit(1);
        }
        $id = (int) $row['IdIncidencia'];
    } else {
        $id = (int) $res['id'];
        $stmt = $db->prepare(
            'SELECT RolDestino, NivelAtencion FROM incidencia_soporte WHERE IdIncidencia = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    $ids[] = $id;
    $destino = (string) ($row['RolDestino'] ?? '');
    $nivel = (string) ($row['NivelAtencion'] ?? '');

    if ($destino !== $caso['esperado'] || $nivel !== 'PRIMER_NIVEL') {
        echo "FAIL {$caso['etiqueta']}: destino={$destino} nivel={$nivel}\n";
        exit(1);
    }

    echo "OK {$caso['etiqueta']} → {$destino}/{$nivel}\n";
}

// Limpiar humo
if ($ids !== []) {
    $in = implode(',', array_map('intval', $ids));
    $db->exec("DELETE FROM incidencia_soporte WHERE IdIncidencia IN ({$in})");
    echo "Humo eliminado: {$in}\n";
}

$count = (int) $db->query('SELECT COUNT(*) FROM incidencia_soporte')->fetchColumn();
echo "Total incidencias finales: {$count}\n";
echo "SMOKE_INCIDENCIAS_OK\n";
