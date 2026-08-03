<?php

/**
 * Genera deploy/hostinger/sql/psicomatch_demo_hostinger.sql
 * Estructura actual + datos de demostración (sin pacientes/clínicos/tokens).
 * Ejecutar desde la raíz del proyecto.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 3);
$structureSrc = $root . '/deploy/hostinger/sql/estructura_tmp.sql';
$outFile = $root . '/deploy/hostinger/sql/psicomatch_demo_hostinger.sql';

if (!is_file($structureSrc)) {
    fwrite(STDERR, "Falta estructura_tmp.sql. Ejecuta mysqldump --no-data primero.\n");
    exit(1);
}

$structure = file_get_contents($structureSrc);
if ($structure === false) {
    fwrite(STDERR, "No se pudo leer la estructura.\n");
    exit(1);
}

// Quitar comentarios de host local del dump.
$structure = preg_replace(
    '/^-- Host:.*$/m',
    '-- Host: (omitido)',
    $structure
) ?? $structure;

$adminHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
$consHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
$psiHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);

if (!$adminHash || !$consHash || !$psiHash) {
    fwrite(STDERR, "No se pudieron generar hashes.\n");
    exit(1);
}

$demo = <<<SQL

-- =============================================================================
-- Datos de demostración PsicoMatch (Hostinger)
-- Sin pacientes, expedientes, tokens, recuperaciones ni SMTP en tablas.
-- Las contraseñas NO son operativas: usa scripts/set_admin_password.php
-- (y equivalentes) tras importar.
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM activacion_cuenta;
DELETE FROM recuperacion_password;
DELETE FROM incidencia_acceso;
DELETE FROM notificacion;
DELETE FROM cita;
DELETE FROM psicologo_servicio;
DELETE FROM disponibilidad_psicologo;
DELETE FROM horario;
DELETE FROM horario_consultorio;
DELETE FROM servicios;
DELETE FROM redsocial;
DELETE FROM consultorio_usuario;
DELETE FROM psicologo;
DELETE FROM paciente;
DELETE FROM consultorio;
DELETE FROM usuario;
DELETE FROM persona;
DELETE FROM direccion;
DELETE FROM caracteristica;
DELETE FROM consecutivos;

INSERT INTO direccion (
  ClvDir, PaisDir, EstadoDir, MunicipioDir, ColoniaDir, CalleDir,
  CodPostDir, NumExtDir, NumIntDir, LatitudDir, LongitudDir, ReferenciaDir
) VALUES (
  'DIR001', 'México', 'Estado de México', 'Toluca', 'Centro',
  'Av. Independencia', '50000', '100', NULL, NULL, NULL,
  'Consultorio de demostración'
);

INSERT INTO persona (
  ClvPer, NombrePer, ApPatPer, ApMatPer, FechaNacimiento, GeneroPer, FotoPerfilPer, ClvDir
) VALUES
('PER001', 'Administrador', 'PsicoMatch', 'Demo', '1990-01-01', 'Otro', NULL, NULL),
('PER002', 'Responsable', 'Demo', 'Consultorio', '1985-06-15', 'Femenino', NULL, NULL),
('PER003', 'Especialista', 'Demo', 'Clínico', '1988-03-20', 'Masculino', NULL, NULL);

INSERT INTO usuario (
  ClvUsu, CorreoUsu, TelefonoUsu, ContrasenaUsu, EstadoUsu,
  RequiereCambioContrasena, RolUsu, ClvPer
) VALUES
('U001', 'administrador@consultoriospsicologicospsicomatch.com', '7220000001',
  '{$adminHash}', 1, 1, 'ADMINISTRADOR', 'PER001'),
('UC001', 'responsable.demo@consultoriospsicologicospsicomatch.com', '7220000002',
  '{$consHash}', 1, 1, 'CONSULTORIO', 'PER002'),
('US001', 'especialista.demo@consultoriospsicologicospsicomatch.com', '7220000003',
  '{$psiHash}', 1, 1, 'PSICOLOGO', 'PER003');

INSERT INTO consultorio (
  ClvCons, NombreCons, LogotipoCons, FaviconCons, ImagenPortada, Slogan, Descripcion,
  TelefonoCons, CorreoElectronico, LimiteCancHoras, ClvDir,
  ServidorSMTP, PuertoSMTP, SeguridadSMTP, CorreoSMTP, PasswordSMTP, NombreRemitente,
  EstatusCons, PublicadoCons, FechaPublicacionCons, FechaRegistroCons
) VALUES (
  'CON001',
  'Consultorio Demo PsicoMatch',
  NULL, NULL, NULL,
  'Bienestar emocional con acompañamiento profesional',
  'Consultorio de demostración para validar la portada, la página pública y el flujo operativo de PsicoMatch.',
  '7220000010',
  'contacto.demo@consultoriospsicologicospsicomatch.com',
  24,
  'DIR001',
  NULL, NULL, NULL, NULL, NULL, NULL,
  'ACTIVO',
  1,
  NOW(),
  NOW()
);

INSERT INTO consultorio_usuario (
  ClvConsUsu, ClvCons, ClvUsu, EsResponsable, EstatusConsUsu, FechaAsignacion
) VALUES (
  'CU001', 'CON001', 'UC001', 1, 'ACTIVO', NOW()
);

INSERT INTO psicologo (
  ClvPsi, CedulaProfesional, EspecialidadPsi, DescripcionProfesional,
  EstatusPsi, MostrarEnPagina, FechaRegistroPsi, ClvUsu, ClvCons
) VALUES (
  'PSI001', 'DEMO-1001', 'Psicología clínica',
  'Especialista de demostración. Perfil visible en la página pública del consultorio demo.',
  'ACTIVO', 1, NOW(), 'US001', 'CON001'
);

INSERT INTO servicios (
  ClvServ, NombreServicio, Descripcion, ClvCons, DuracionMinutos, CostoServicio, EstatusServicio
) VALUES (
  'SER001', 'Terapia individual',
  'Sesión individual de acompañamiento psicológico (demostración).',
  'CON001', 50, 600.00, 'ACTIVO'
);

INSERT INTO psicologo_servicio (
  ClvPsiServ, ClvPsi, ClvServ, PrecioServicio, DuracionMinutos,
  DescripcionServicio, EstatusAsignacion, FechaAsignacion
) VALUES (
  'PS001', 'PSI001', 'SER001', 600.00, 50,
  'Sesión individual de demostración.', 'ACTIVA', NOW()
);

INSERT INTO horario_consultorio (
  ClvHorarioCons, DiaSemana, HoraInicio, HoraFin, EstatusHorario, ClvCons
) VALUES
('HCO001', 'LUNES', '09:00:00', '18:00:00', 'ACTIVO', 'CON001'),
('HCO002', 'MARTES', '09:00:00', '18:00:00', 'ACTIVO', 'CON001'),
('HCO003', 'MIERCOLES', '09:00:00', '18:00:00', 'ACTIVO', 'CON001'),
('HCO004', 'JUEVES', '09:00:00', '18:00:00', 'ACTIVO', 'CON001'),
('HCO005', 'VIERNES', '09:00:00', '18:00:00', 'ACTIVO', 'CON001'),
('HCO006', 'SABADO', '09:00:00', '14:00:00', 'INACTIVO', 'CON001'),
('HCO007', 'DOMINGO', '09:00:00', '14:00:00', 'INACTIVO', 'CON001');

INSERT INTO caracteristica (
  ClvCar, Titulo, Descripcion, Icono, OrdenCar, EstadoCar, ClvCons
) VALUES
('CAR001', 'Atención personalizada', 'Cada tratamiento se adapta a las necesidades del paciente.', 'bi-heart', 1, 1, 'CON001'),
('CAR002', 'Confidencialidad', 'Tu información siempre estará protegida.', 'bi-shield-lock', 2, 1, 'CON001'),
('CAR003', 'Citas programadas', 'Agenda tus sesiones de forma sencilla y organizada.', 'bi-calendar-check', 3, 1, 'CON001');

INSERT INTO consecutivos (NombreTabla, UltimoNumero) VALUES
('CONSULTORIO', 1),
('DIRECCION', 1),
('HORARIO', 0),
('HORARIO_CONSULTORIO', 7),
('PACIENTE', 0),
('PERSONA', 3),
('REDSOCIAL', 0),
('SERVICIO', 1),
('USUARIO', 1),
('CONSULTORIO_USUARIO', 1),
('PSICOLOGO', 1);

SET FOREIGN_KEY_CHECKS = 1;

SQL;

// Check caracteristica and psicologo_servicio schemas
$header = <<<HDR
-- =============================================================================
-- PsicoMatch — SQL de demostración para Hostinger
-- Dominio objetivo: https://consultoriospsicologicospsicomatch.com
-- Incluye: estructura actual + activacion_cuenta + PublicadoCons + demo mínima
-- Excluye: pacientes reales, expedientes, tokens, SMTP, backups
-- =============================================================================

HDR;

file_put_contents($outFile, $header . $structure . "\n" . $demo);
echo "OK: {$outFile}\n";
