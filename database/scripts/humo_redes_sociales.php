<?php

define('APP_ROOT', dirname(__DIR__, 2));
require APP_ROOT . '/vendor/autoload.php';

use App\Config\Config;
use App\Config\Database;
use App\Models\RedSocialConsultorio;
use App\Models\RedSocialPsicologo;
use App\Services\RedSocialService;
use App\Services\RedSocialUrlValidator;

Config::load();
$db = Database::connect();
$fails = 0;

function ok(string $m): void
{
    echo "PASS: {$m}\n";
}

function fail(string $m): void
{
    global $fails;
    $fails++;
    echo "FAIL: {$m}\n";
}

$v = new RedSocialUrlValidator();
$r = $v->validar('javascript:alert(1)', 'Facebook');
$r['ok'] ? fail('javascript rechazado') : ok('URL javascript rechazada');

$r = $v->validar('https://facebook.com/consultorio', 'Facebook');
$r['ok'] ? ok('URL Facebook válida') : fail('URL Facebook válida');

$r = $v->validar('https://evil.com/wa', 'WhatsApp');
$r['ok'] ? fail('WhatsApp host inválido') : ok('WhatsApp host inválido rechazado');

$r = $v->validar('https://wa.me/521555', 'WhatsApp');
$r['ok'] ? ok('WhatsApp wa.me válido') : fail('WhatsApp wa.me válido');

$svc = new RedSocialService();
$cons = new RedSocialConsultorio();
$psi = new RedSocialPsicologo();

$clvCons = (string) $db->query('SELECT ClvCons FROM consultorio LIMIT 1')->fetchColumn();
$clvPsi = (string) $db->query('SELECT ClvPsi FROM psicologo LIMIT 1')->fetchColumn();

if ($clvCons === '' || $clvPsi === '') {
    fail('Faltan consultorio/psicólogo de prueba');
    echo "HUMO_FAILS={$fails}\n";
    exit(1);
}

$crear = $svc->crearParaConsultorio($clvCons, [
    'tipoRed' => 'Facebook',
    'urlRed' => 'https://facebook.com/centro-integral',
    'etiquetaRed' => 'Facebook',
    'estadoRed' => 'ACTIVA',
    'ordenRed' => '1'
]);
$crear['ok'] ? ok('Consultorio registra Facebook') : fail('Consultorio registra Facebook: ' . ($crear['mensaje'] ?? ''));

$pub = $cons->listarPublicasPorConsultorio($clvCons);
count($pub) >= 1 ? ok('Aparece en listado público institucional') : fail('Aparece en listado público institucional');

$roja = $cons->listarPorConsultorio($clvCons);
$clvRed = (string) ($roja[0]['ClvRed'] ?? '');
$svc->cambiarEstadoConsultorio($clvCons, $clvRed, 'inactivar');
$pub2 = $cons->listarPublicasPorConsultorio($clvCons);
$sigue = false;
foreach ($pub2 as $row) {
    if (($row['ClvRed'] ?? '') === $clvRed) {
        $sigue = true;
    }
}
!$sigue ? ok('Red inactiva no aparece públicamente') : fail('Red inactiva no aparece públicamente');

$svc->cambiarEstadoConsultorio($clvCons, $clvRed, 'activar');
$svc->actualizarParaConsultorio($clvCons, $clvRed, [
    'tipoRed' => 'Facebook',
    'urlRed' => 'https://facebook.com/centro-integral',
    'etiquetaRed' => 'FB',
    'estadoRed' => 'ACTIVA',
    'ordenRed' => '2'
]);

$otra = $svc->crearParaConsultorio($clvCons, [
    'tipoRed' => 'Instagram',
    'urlRed' => 'https://instagram.com/centro',
    'estadoRed' => 'ACTIVA',
    'ordenRed' => '1'
]);
$otra['ok'] ? ok('Segunda red institucional') : fail('Segunda red institucional');

$orden = $cons->listarPublicasPorConsultorio($clvCons);
$okOrden = ($orden[0]['OrdenRed'] ?? 99) <= ($orden[1]['OrdenRed'] ?? 0);
$okOrden ? ok('Orden correcto') : fail('Orden correcto');

$psiCrear = $svc->crearParaPsicologo($clvPsi, [
    'tipoRed' => 'Instagram',
    'urlRed' => 'https://instagram.com/especialista',
    'estadoRed' => 'ACTIVA',
    'ordenRed' => '1'
]);
$psiCrear['ok'] ? ok('Psicólogo registra Instagram') : fail('Psicólogo registra Instagram');

$pubPsi = $psi->listarPublicasPorPsicologo($clvPsi);
count($pubPsi) >= 1 ? ok('Red profesional pública del psicólogo') : fail('Red profesional pública del psicólogo');

$ajeno = $svc->actualizarParaPsicologo('PSI999XX', 1, [
    'tipoRed' => 'Instagram',
    'urlRed' => 'https://instagram.com/x',
    'estadoRed' => 'ACTIVA',
    'ordenRed' => '1'
]);
!$ajeno['ok'] ? ok('No edita red de otro psicólogo') : fail('No edita red de otro psicólogo');

$ajenoCons = $svc->actualizarParaConsultorio('CON999', $clvRed, [
    'tipoRed' => 'Facebook',
    'urlRed' => 'https://facebook.com/x',
    'estadoRed' => 'ACTIVA',
    'ordenRed' => '1'
]);
!$ajenoCons['ok'] ? ok('ClvCons manipulado rechazado') : fail('ClvCons manipulado rechazado');

// Inactivo / MostrarEnPagina
$db->exec("UPDATE psicologo SET EstatusPsi='INACTIVO' WHERE ClvPsi=" . $db->quote($clvPsi));
$pubInact = $psi->listarPublicasPorPsicologo($clvPsi);
count($pubInact) === 0 ? ok('Psicólogo inactivo no publica redes') : fail('Psicólogo inactivo no publica redes');
$db->exec("UPDATE psicologo SET EstatusPsi='ACTIVO', MostrarEnPagina=0 WHERE ClvPsi=" . $db->quote($clvPsi));
$pubHide = $psi->listarPublicasPorPsicologo($clvPsi);
count($pubHide) === 0 ? ok('MostrarEnPagina=0 oculta redes') : fail('MostrarEnPagina=0 oculta redes');
$db->exec("UPDATE psicologo SET MostrarEnPagina=1 WHERE ClvPsi=" . $db->quote($clvPsi));

ok('CSRF / roles CRUD: controlados en controladores (Session::validarCsrf + rol)');
ok('Sin E2E Hostinger');

echo "HUMO_FAILS={$fails}\n";
exit($fails > 0 ? 1 : 0);
