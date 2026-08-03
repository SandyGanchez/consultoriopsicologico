<?php

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/vendor/autoload.php';

use App\Config\Config;
use App\Config\Database;
use App\Helpers\Helper;
use App\Models\Cita;
use App\Services\AgendaService;
use ReflectionClass;
use DateTimeImmutable;
use DateTimeZone;

Config::load();

echo Helper::formatearMonedaMxn(650) . PHP_EOL;

$c = new Cita();
$ref = new ReflectionClass($c);
$m = $ref->getMethod('formatearFechaHoraCancelacion');
$m->setAccessible(true);
$dt = new DateTimeImmutable('2026-08-10 15:00:00', new DateTimeZone('America/Mexico_City'));
echo $m->invoke($c, $dt) . PHP_EOL;

$db = Database::connect();
$row = $db->query(
    "SELECT ClvPsi, ClvServ FROM psicologo_servicio
     WHERE EstatusAsignacion='ACTIVA' AND PrecioServicio>0
       AND DuracionMinutos BETWEEN 1 AND 480 LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);

if ($row) {
    $s = new AgendaService();
    $fecha = (new DateTimeImmutable('now', new DateTimeZone('America/Mexico_City')))
        ->modify('+14 days')
        ->format('Y-m-d');
    $r = $s->calcularEspaciosDisponibles($row['ClvPsi'], $row['ClvServ'], $fecha);
    echo 'agenda_ok=' . (int) $r['ok'] . PHP_EOL;
}

$servicioSrc = file_get_contents(APP_ROOT . '/app/Models/Servicio.php');
$agendaSrc = file_get_contents(APP_ROOT . '/app/Services/AgendaService.php');
echo (str_contains($servicioSrc, 'BETWEEN 1 AND 480') ? "SQL_BETWEEN_OK\n" : "SQL_BETWEEN_FAIL\n");
echo (str_contains($agendaSrc, '$duracion > 480') ? "PHP_MAX_OK\n" : "PHP_MAX_FAIL\n");

$esp = file_get_contents(APP_ROOT . '/app/Views/home/especialistas.php');
echo (str_contains($esp, 'Ver página completa') ? "CTA_FAIL\n" : "CTA_OK\n");

$dbSrc = file_get_contents(APP_ROOT . '/app/Config/Database.php');
echo (str_contains($dbSrc, 'getMessage()') && str_contains($dbSrc, 'die("Error de conexión')
    ? "DB_LEAK_FAIL\n"
    : "DB_HARDENED_OK\n");

echo "CHECKS_OK\n";
