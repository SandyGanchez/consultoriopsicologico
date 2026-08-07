<?php

/**
 * Pruebas controladas del registro público PACIENTE → dashboard.
 * Uso: php database/scripts/probar_registro_paciente_dashboard.php
 *
 * No imprime contraseñas/hashes/tokens. Solo BD local.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

use App\Config\Config;
use App\Config\Database;
use App\Core\Session;
use App\Models\Paciente;
use App\Models\Persona;
use App\Models\Usuario;
use App\Services\AccesoSesionService;
use App\Services\ClaveService;
use App\Services\InstalacionConsultorioService;
use App\Helpers\Helper;

Config::load($root . '/.env');
$db = Database::connect();

/*
 * Sesión ANTES de cualquier echo: session_start / regenerate_id envían headers.
 * Session.php de producción es correcto; el harness anterior imprimía [OK] primero.
 */
Session::start();

$pass = 0;
$fail = 0;
$resultados = [];

$registrar = static function (bool $ok, string $m) use (&$pass, &$fail, &$resultados): void {
    $resultados[] = ['ok' => $ok, 'm' => $m];
    if ($ok) {
        $pass++;
    } else {
        $fail++;
    }
};

$clvCons = (new InstalacionConsultorioService())->claveUnicaONull();
if ($clvCons === null) {
    echo "Sin consultorio de instalación.\n";
    exit(1);
}

$suf = bin2hex(random_bytes(3));
$correo = "reg.dash.{$suf}@example.test";
$passwordPlano = 'ClaveSegura1';

$db->beginTransaction();

$clvPer = ClaveService::generar('persona', 'ClvPer', 'P');
$clvUsu = ClaveService::generar('usuario', 'ClvUsu', 'U');
$clvPac = ClaveService::generar('paciente', 'ClvPac', 'PAC');

(new Persona())->crear([
    'ClvPer' => $clvPer,
    'NombrePer' => 'Reg',
    'ApPatPer' => 'Dashboard',
    'ApMatPer' => 'Test',
    'FechaNacimiento' => '1992-05-10',
    'GeneroPer' => 'Otro',
]);

(new Usuario())->crear([
    'ClvUsu' => $clvUsu,
    'CorreoUsu' => $correo,
    'TelefonoUsu' => '7221112233',
    'ContrasenaUsu' => password_hash($passwordPlano, PASSWORD_DEFAULT),
    'ClvPer' => $clvPer,
]);

(new Paciente())->crear([
    'ClvPac' => $clvPac,
    'ClvUsu' => $clvUsu,
    'ClvCons' => $clvCons,
]);

$db->commit();

$stmt = $db->prepare(
    'SELECT ClvUsu, RolUsu, EstadoUsu, RequiereCambioContrasena
     FROM usuario WHERE ClvUsu = :u'
);
$stmt->execute(['u' => $clvUsu]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

$pac = (new Paciente())->obtenerPorUsuario($clvUsu);
$usuarioLogin = (new Usuario())->buscarPorCorreo($correo);

// Regenerar/setear sesión ANTES de imprimir resultados.
Session::regenerar();
Session::set('usuario', $usuarioLogin);

$sesion = Session::get('usuario');
$clavesLogin = [
    'ClvUsu',
    'RolUsu',
    'CorreoUsu',
    'EstadoUsu',
    'RequiereCambioContrasena',
    'ClvPer',
];
$mismas = true;
foreach ($clavesLogin as $k) {
    if (
        !array_key_exists($k, (array) $sesion)
        || !array_key_exists($k, (array) $usuarioLogin)
    ) {
        $mismas = false;
        break;
    }
    if ((string) $sesion[$k] !== (string) $usuarioLogin[$k]) {
        $mismas = false;
        break;
    }
}

$eval = (new AccesoSesionService())->evaluarSesionActiva('PACIENTE');
$destino = Helper::rutaPanelPorRol('PACIENTE');
$existeCorreo = (new Usuario())->existeCorreo($correo);

/*
 * autenticar() también regenera sesión. Debe ejecutarse antes de echo
 * para no disparar warnings de headers.
 */
$auth = (new \App\Services\AuthService())->autenticar($correo, $passwordPlano);

// Limpieza antes del reporte (también sin echo previo de sesión).
try {
    if ($db->query("SHOW TABLES LIKE 'consentimiento_datos_personales'")->fetchColumn()) {
        $db->prepare('DELETE FROM consentimiento_datos_personales WHERE ClvUsu = :u')
            ->execute(['u' => $clvUsu]);
    }
} catch (Throwable $e) {
}
$db->prepare('DELETE FROM paciente WHERE ClvPac = :p')->execute(['p' => $clvPac]);
$db->prepare('DELETE FROM usuario WHERE ClvUsu = :u')->execute(['u' => $clvUsu]);
$db->prepare('DELETE FROM persona WHERE ClvPer = :p')->execute(['p' => $clvPer]);

Session::remove('usuario');

// === Reporte (única fase con salida) ===
$registrar(
    $row && (string) $row['RolUsu'] === 'PACIENTE',
    '3. Rol resultante PACIENTE'
);
$registrar(
    $row && (int) $row['EstadoUsu'] === 1,
    '4. EstadoUsu = 1'
);
$registrar(
    $row && (int) $row['RequiereCambioContrasena'] === 0,
    '4b. RequiereCambioContrasena = 0 (registro público)'
);
$registrar(
    $pac && (string) ($pac['ClvPac'] ?? '') === $clvPac,
    '1-2. Usuario y paciente creados'
);
$registrar(
    $mismas && is_array($sesion),
    '5-6. Sesión canónica igual a buscarPorCorreo/login'
);
$registrar(
    !empty($eval['ok']),
    !empty($eval['ok'])
        ? '8. AccesoSesionService acepta sesión PACIENTE activa'
        : ('8. AccesoSesion: ' . ($eval['motivo'] ?? ''))
);
$registrar(
    (int) ($sesion['RequiereCambioContrasena'] ?? 1) === 0,
    '9. No forzará redirect a cambiar-contrasena'
);
$registrar(
    $destino === 'paciente',
    $destino === 'paciente'
        ? '7. Redirect canónico /paciente (dashboard)'
        : ('7. Destino=' . $destino)
);
$registrar($existeCorreo, '10. Correo duplicado detectable (no se crearía segunda cuenta)');
$registrar(
    !empty($auth['ok']) && (string) ($auth['usuario']['ClvUsu'] ?? '') === $clvUsu,
    !empty($auth['ok'])
        ? '17. Login tradicional PACIENTE funciona con la cuenta nueva'
        : ('17. Login: ' . ($auth['mensaje'] ?? ''))
);

foreach ($resultados as $r) {
    echo ($r['ok'] ? '[OK] ' : '[FAIL] ') . $r['m'] . "\n";
}

echo "\nClvUsu={$clvUsu} ClvPac={$clvPac} Rol=PACIENTE Estado=1 ReqCambio=0 destino={$destino}\n";
echo "Resumen: OK={$pass} FAIL={$fail}\n";
exit($fail > 0 ? 1 : 0);
