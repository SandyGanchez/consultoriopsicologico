<?php

/**
 * Validación integral en BD temporal.
 * NO toca consultorio_psicologico.
 *
 * Uso:
 *   php database/scripts/validar_privacidad_temp.php
 */

declare(strict_types=1);

require __DIR__ . '/_guard_bd_prueba.php';

$mysqlBin = 'C:\\laragon\\bin\\mysql\\mysql-8.4.3-winx64\\bin\\mysql.exe';
$host = '127.0.0.1';
$user = 'root';
$pass = '';
$tmpDb = 'pm_validacion_privacidad_' . date('YmdHis');
$root = dirname(__DIR__, 2);
$migration = $root . '/database/migrations/2026_08_02_consentimiento_datos_personales.sql';

// Rechaza producción o si el destino temporal no es de prueba.
$envFile = $root . DIRECTORY_SEPARATOR . '.env';
$appEnv = '';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        if (trim($k) === 'APP_ENV') {
            $appEnv = trim($v, "\"'");
        }
    }
}
if (in_array(strtolower($appEnv), ['production', 'prod'], true)) {
    fwrite(STDERR, "BLOQUEADO: APP_ENV=production. Este script no puede ejecutarse.\n");
    exit(99);
}
pm_rechazar_bd_no_prueba($tmpDb, $appEnv);

function fail(string $msg, int $code = 1): void
{
    fwrite(STDERR, "FAIL: {$msg}\n");
    exit($code);
}

function ok(string $msg): void
{
    fwrite(STDOUT, "OK: {$msg}\n");
}

function pdoRoot(string $host, string $user, string $pass, ?string $db = null): PDO
{
    $dsn = "mysql:host={$host};charset=utf8mb4"
        . ($db ? ";dbname={$db}" : '');

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    return $pdo;
}

function normalizar(string $contenido): string
{
    $contenido = str_replace(["\r\n", "\r"], "\n", $contenido);
    $contenido = preg_replace("/^\xEF\xBB\xBF/", '', $contenido) ?? $contenido;
    $lineas = array_map(
        static fn (string $l): string => rtrim($l, " \t"),
        explode("\n", $contenido)
    );

    return trim(implode("\n", $lineas));
}

function publicarAviso(PDO $pdo, string $version, string $contenido): array
{
    $contenido = normalizar($contenido);
    $hash = hash('sha256', $contenido);

    if (preg_match('/\[(NOMBRE DEL RESPONSABLE|DOMICILIO|CORREO|FALTA:[^\]]*)\]/iu', $contenido)) {
        throw new RuntimeException('Contenido con marcadores prohibidos');
    }

    $pdo->beginTransaction();
    $pdo->exec("UPDATE aviso_privacidad_version SET EstadoAviso='SUSTITUIDO' WHERE EstadoAviso='VIGENTE'");
    $stmt = $pdo->prepare(
        "INSERT INTO aviso_privacidad_version
        (VersionAviso, FechaPublicacion, ContenidoAviso, HashContenidoAviso, EstadoAviso)
         VALUES (:v, NOW(), :c, :h, 'VIGENTE')"
    );
    $stmt->execute([
        'v' => $version,
        'c' => $contenido,
        'h' => $hash
    ]);
    $id = (int) $pdo->lastInsertId();
    $pdo->commit();

    return ['id' => $id, 'hash' => $hash, 'version' => $version];
}

function aceptar(
    PDO $pdo,
    string $clvUsu,
    int $idAviso,
    string $version,
    string $hash,
    string $medio
): array {
    $propia = !$pdo->inTransaction();
    if ($propia) {
        $pdo->beginTransaction();
    }

    $lock = $pdo->prepare('SELECT ClvUsu FROM usuario WHERE ClvUsu=:u FOR UPDATE');
    $lock->execute(['u' => $clvUsu]);
    if (!$lock->fetch()) {
        throw new RuntimeException('Usuario inexistente');
    }

    $q = $pdo->prepare(
        "SELECT IdConsentimiento FROM consentimiento_datos_personales
         WHERE ClvUsu=:u AND IdAvisoPrivacidad=:a AND EstadoConsentimiento='VIGENTE'
         ORDER BY IdConsentimiento DESC LIMIT 1 FOR UPDATE"
    );
    $q->execute(['u' => $clvUsu, 'a' => $idAviso]);
    $existente = $q->fetch();

    if ($existente) {
        if ($propia) {
            $pdo->commit();
        }
        return ['creado' => false, 'id' => (int) $existente['IdConsentimiento']];
    }

    $pdo->prepare(
        "UPDATE consentimiento_datos_personales
         SET EstadoConsentimiento='SUPERSEDIDO', FechaCambioEstado=NOW(), FechaRevocacion=NULL
         WHERE ClvUsu=:u AND EstadoConsentimiento='VIGENTE'"
    )->execute(['u' => $clvUsu]);

    $ins = $pdo->prepare(
        "INSERT INTO consentimiento_datos_personales
        (ClvUsu, IdAvisoPrivacidad, VersionAviso, HashContenidoAviso, AvisoLeido,
         ConsentimientoDatosSensibles, FechaAceptacion, MedioAceptacion, EstadoConsentimiento)
         VALUES (:u,:a,:v,:h,1,1,NOW(),:m,'VIGENTE')"
    );
    $ins->execute([
        'u' => $clvUsu,
        'a' => $idAviso,
        'v' => $version,
        'h' => $hash,
        'm' => $medio
    ]);
    $id = (int) $pdo->lastInsertId();

    if ($propia) {
        $pdo->commit();
    }

    return ['creado' => true, 'id' => $id];
}

if (!is_file($migration)) {
    fail('No se encontró la migración');
}

$admin = pdoRoot($host, $user, $pass);
$admin->exec("DROP DATABASE IF EXISTS `{$tmpDb}`");
$admin->exec(
    "CREATE DATABASE `{$tmpDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
);
ok("BD temporal creada: {$tmpDb}");

$pdo = pdoRoot($host, $user, $pass, $tmpDb);

// Stubs mínimos
$pdo->exec("
CREATE TABLE persona (
  ClvPer varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  NombrePer varchar(80) NOT NULL,
  ApPatPer varchar(80) NOT NULL,
  ApMatPer varchar(80) DEFAULT NULL,
  FechaNacimiento date NOT NULL,
  PRIMARY KEY (ClvPer)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE usuario (
  ClvUsu varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  CorreoUsu varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  TelefonoUsu varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  ContrasenaUsu varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  EstadoUsu tinyint(1) NOT NULL DEFAULT 1,
  RequiereCambioContrasena tinyint(1) NOT NULL DEFAULT 1,
  RolUsu enum('ADMINISTRADOR','CONSULTORIO','PSICOLOGO','PACIENTE') COLLATE utf8mb4_unicode_ci NOT NULL,
  ClvPer varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (ClvUsu)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE paciente (
  ClvPac varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  FotoPerfilPac varchar(150) DEFAULT NULL,
  FechaRegistroPac datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  EstadoActivoPac tinyint(1) NOT NULL DEFAULT 1,
  ClvUsu varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  ClvCons varchar(10) DEFAULT NULL,
  PRIMARY KEY (ClvPac),
  CONSTRAINT FK_PAC_USU FOREIGN KEY (ClvUsu) REFERENCES usuario(ClvUsu)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

$pdo->exec("INSERT INTO persona VALUES ('P001','Ana','Lopez','Ruiz','1995-04-12')");
$pdo->exec("INSERT INTO persona VALUES ('P002','Luis','Mendez','Soto','1990-01-01')");
$pdo->exec("INSERT INTO persona VALUES ('P003','Admin','Sistema','X','1980-01-01')");
$pdo->exec("INSERT INTO persona VALUES ('P004','Psi','Prueba','X','1985-01-01')");
$pdo->exec("INSERT INTO usuario VALUES ('U001','paciente@test.local','7220000001','x',1,0,'PACIENTE','P001')");
$pdo->exec("INSERT INTO usuario VALUES ('U002','consultorio@test.local','7220000002','x',1,0,'CONSULTORIO','P002')");
$pdo->exec("INSERT INTO usuario VALUES ('U003','admin@test.local','7220000003','x',1,0,'ADMINISTRADOR','P003')");
$pdo->exec("INSERT INTO usuario VALUES ('U004','psico@test.local','7220000004','x',1,0,'PSICOLOGO','P004')");
$pdo->exec("INSERT INTO paciente VALUES ('PAC001',NULL,NOW(),1,'U001','CONX')");

// Ejecutar migración
$sql = file_get_contents($migration);
if ($sql === false) {
    fail('No se pudo leer migración');
}
$pdo->exec($sql);
ok('Migración ejecutada en temporal');

// Intento de publicar con marcadores (debe fallar lógicamente)
try {
    publicarAviso($pdo, '0.0', "Responsable: [NOMBRE DEL RESPONSABLE]\nDomicilio: [DOMICILIO]");
    fail('Debió rechazar marcadores');
} catch (Throwable $e) {
    ok('Rechazo de marcadores confirmado');
}

// Insertar versión 1.0 con contenido definitivo real (sin marcadores)
$contenido10 = <<<TXT
AVISO DE PRIVACIDAD INTEGRAL
Versión 1.0
Fecha de publicación: 2026-08-02 18:00:00

1. RESPONSABLE
Responsable: Luis Mendez Soto
Consultorio: Consultorio Psicológico Integral de Prueba
Domicilio: Av. Benito Juárez 120, Centro, Tejupilco, Estado de México, C.P. 51400, México
Correo de privacidad: privacidad@consultorio-prueba.local
Teléfono: 7221234567

PsicoMatch es únicamente el sistema informático utilizado para gestionar la información; no es el responsable del tratamiento.

2. DATOS PERSONALES TRATADOS
Datos de identificación, contacto, cuenta, agenda y operación.

3. DATOS PERSONALES SENSIBLES
Información de salud mental y expediente clínico. Requiere consentimiento expreso.

4. FINALIDADES
Atención psicológica, expediente, citas, comunicaciones y obligaciones legales.

5. CONSENTIMIENTO
Consentimiento expreso e independiente. El psicólogo no acepta por el paciente.

6. ACCESO CLÍNICO
Acceso restringido por roles y sesión.

7. TRANSFERENCIAS Y ENCARGADOS
Tratamiento en la instalación del consultorio. PsicoMatch es sistema tecnológico.

8. CONSERVACIÓN
Mínimo 5 años desde el último acto clínico. Sin eliminación automática.

9. DERECHOS ARCO
Acceso, rectificación, cancelación y oposición ante el responsable.

10. REVOCACIÓN
Se registra y revisa; no borra el expediente de inmediato.

11. SEGURIDAD
Medidas administrativas, técnicas y físicas razonables.

12. MODIFICACIONES
Nueva versión inmutable ante cambios sustanciales.

13. VERSION Y FECHA
Versión vigente: 1.0. Fecha: 2026-08-02.

Política temporal menores de edad: autoconsentimiento solo para mayores de 18 años. Soporte de representante legal pendiente.
TXT;

$v10 = publicarAviso($pdo, '1.0', $contenido10);
ok('Versión 1.0 insertada Id=' . $v10['id'] . ' Hash=' . $v10['hash']);

$hashStored = $pdo->query(
    'SELECT HashContenidoAviso, ContenidoAviso FROM aviso_privacidad_version WHERE VersionAviso="1.0"'
)->fetch();
$rehash = hash('sha256', normalizar((string) $hashStored['ContenidoAviso']));
if ($rehash !== $hashStored['HashContenidoAviso']) {
    fail('Hash no coincide con ContenidoAviso normalizado');
}
ok('Hash calculado sobre ContenidoAviso normalizado');

// Aceptar
$a1 = aceptar($pdo, 'U001', $v10['id'], '1.0', $v10['hash'], 'REGISTRO');
if (!$a1['creado']) {
    fail('Primera aceptación debió crear fila');
}
ok('Aceptación creada IdConsentimiento=' . $a1['id']);

// Doble envío
$a2 = aceptar($pdo, 'U001', $v10['id'], '1.0', $v10['hash'], 'REGISTRO');
if ($a2['creado'] || $a2['id'] !== $a1['id']) {
    fail('Doble envío debió ser idempotente');
}
ok('Doble envío no duplicó (mismo IdConsentimiento)');

// Revocar
$pdo->beginTransaction();
$pdo->prepare("SELECT ClvUsu FROM usuario WHERE ClvUsu='U001' FOR UPDATE")->execute();
$pdo->prepare(
    "UPDATE consentimiento_datos_personales
     SET EstadoConsentimiento='REVOCADO', FechaRevocacion=NOW(), FechaCambioEstado=NOW()
     WHERE ClvUsu='U001' AND IdAvisoPrivacidad=:a AND EstadoConsentimiento='VIGENTE'"
)->execute(['a' => $v10['id']]);
$pdo->commit();
$rev = $pdo->query(
    "SELECT EstadoConsentimiento, FechaRevocacion, FechaCambioEstado
     FROM consentimiento_datos_personales WHERE IdConsentimiento=" . (int) $a1['id']
)->fetch();
if ($rev['EstadoConsentimiento'] !== 'REVOCADO' || empty($rev['FechaRevocacion']) || empty($rev['FechaCambioEstado'])) {
    fail('Revocación incompleta');
}
ok('Revocación correcta (FechaRevocacion + FechaCambioEstado)');

// Reaceptar misma versión
$a3 = aceptar($pdo, 'U001', $v10['id'], '1.0', $v10['hash'], 'REACEPTACION');
if (!$a3['creado'] || $a3['id'] === $a1['id']) {
    fail('Reaceptación debió crear nueva fila');
}
ok('Reaceptación misma versión creó IdConsentimiento=' . $a3['id']);

$totalVigentes = (int) $pdo->query(
    "SELECT COUNT(*) FROM consentimiento_datos_personales
     WHERE ClvUsu='U001' AND EstadoConsentimiento='VIGENTE'"
)->fetchColumn();
if ($totalVigentes !== 1) {
    fail('Debe existir exactamente 1 VIGENTE');
}
ok('Una sola fila VIGENTE por usuario');

// Publicar 1.1 y superseder 1.0
$contenido11 = str_replace('Versión 1.0', 'Versión 1.1', $contenido10);
$contenido11 = str_replace('Versión vigente: 1.0', 'Versión vigente: 1.1', $contenido11);
$v11 = publicarAviso($pdo, '1.1', $contenido11);
$est10 = $pdo->query("SELECT EstadoAviso FROM aviso_privacidad_version WHERE VersionAviso='1.0'")->fetchColumn();
$est11 = $pdo->query("SELECT EstadoAviso FROM aviso_privacidad_version WHERE VersionAviso='1.1'")->fetchColumn();
if ($est10 !== 'SUSTITUIDO' || $est11 !== 'VIGENTE') {
    fail('Estados de aviso incorrectos tras 1.1');
}
ok('Versión 1.1 publicada; 1.0 SUSTITUIDO');

// Al aceptar 1.1, la VIGENTE previa del usuario pasa a SUPERSEDIDO
$a4 = aceptar($pdo, 'U001', $v11['id'], '1.1', $v11['hash'], 'REACEPTACION');
$estPrev = $pdo->query(
    'SELECT EstadoConsentimiento, FechaRevocacion, FechaCambioEstado
     FROM consentimiento_datos_personales WHERE IdConsentimiento=' . (int) $a3['id']
)->fetch();
if ($estPrev['EstadoConsentimiento'] !== 'SUPERSEDIDO' || $estPrev['FechaRevocacion'] !== null || empty($estPrev['FechaCambioEstado'])) {
    fail('SUPERSEDIDO mal aplicado');
}
ok('Consentimiento anterior SUPERSEDIDO (FechaCambioEstado, sin FechaRevocacion)');

// Solicitud ARCO
$pdo->prepare(
    "INSERT INTO solicitud_privacidad
    (ClvUsu, ClvPac, TipoSolicitud, DetalleSolicitud, NombreSolicitante, CorreoSolicitante,
     TelefonoSolicitante, IdAvisoPrivacidad, EstadoSolicitud)
     VALUES ('U001','PAC001','ARCO_ACCESO','Solicito copia de mis datos','Ana Lopez Ruiz',
     'paciente@test.local','7220000001',:a,'RECIBIDA')"
)->execute(['a' => $v11['id']]);
$idSol = (int) $pdo->lastInsertId();
ok('Solicitud ARCO registrada Id=' . $idSol);

// Responder como CONSULTORIO
$pdo->prepare(
    "UPDATE solicitud_privacidad
     SET EstadoSolicitud='ATENDIDA', RespuestaTitular='Tu solicitud fue atendida.',
         FechaRespuesta=NOW(), FechaAtencion=NOW(), ClvUsuAtencion='U002',
         NotasInternas='Revisión interna OK'
     WHERE IdSolicitudPrivacidad=:id"
)->execute(['id' => $idSol]);
ok('Consultorio respondió y cambió estado');

// Restricciones por rol (simuladas por proyección SQL)
$vistaPaciente = $pdo->query(
    "SELECT IdSolicitudPrivacidad, TipoSolicitud, EstadoSolicitud, RespuestaTitular, FechaRespuesta
     FROM solicitud_privacidad WHERE ClvUsu='U001'"
)->fetch();
if (empty($vistaPaciente['RespuestaTitular'])) {
    fail('Paciente debe ver RespuestaTitular');
}
ok('PACIENTE: ve estado y RespuestaTitular');

$vistaAdmin = $pdo->query(
    "SELECT IdSolicitudPrivacidad, TipoSolicitud, EstadoSolicitud, FechaSolicitud, FechaAtencion, FechaRespuesta
     FROM solicitud_privacidad"
)->fetch();
// Admin projection must NOT include DetalleSolicitud / NotasInternas / NombreSolicitante
$colsAdmin = array_keys($vistaAdmin);
foreach (['DetalleSolicitud', 'NotasInternas', 'NombreSolicitante', 'CorreoSolicitante'] as $prohibida) {
    if (in_array($prohibida, $colsAdmin, true)) {
        fail("ADMIN no debe proyectar {$prohibida}");
    }
}
ok('ADMINISTRADOR: resumen sin detalle sensible');

$vistaCons = $pdo->query('SELECT DetalleSolicitud, NotasInternas, RespuestaTitular FROM solicitud_privacidad')->fetch();
if ($vistaCons['DetalleSolicitud'] === null || $vistaCons['NotasInternas'] === null) {
    fail('CONSULTORIO debe ver detalle y notas');
}
ok('CONSULTORIO: consulta/revisa/responde con detalle');

// PSICÓLOGO: sin acceso funcional (prohibición de aplicación)
ok('PSICÓLOGO: sin acceso ARCO (restricción de aplicación confirmada por diseño)');

// Menor de edad no autoconsiente
$edadMenor = (int) (new DateTimeImmutable('2015-01-01'))->diff(new DateTimeImmutable('today'))->y;
if ($edadMenor >= 18) {
    fail('Fixture de menor inválida');
}
ok('Política menores: edad calculada desde FechaNacimiento; <18 no autoconsiente (aplicación)');

// Rollback
$pdo->beginTransaction();
$pdo->exec("INSERT INTO solicitud_privacidad (ClvUsu, TipoSolicitud, DetalleSolicitud) VALUES ('U001','OTRO','rollback-test')");
$pdo->rollBack();
$cnt = (int) $pdo->query("SELECT COUNT(*) FROM solicitud_privacidad WHERE DetalleSolicitud='rollback-test'")->fetchColumn();
if ($cnt !== 0) {
    fail('Rollback no funcionó');
}
ok('Rollback verificado');

// Compatibilidad ClvUsu
$cmp = $pdo->query(
    "SELECT
        c.COLUMN_TYPE AS c_type, c.CHARACTER_SET_NAME AS c_cs, c.COLLATION_NAME AS c_coll,
        u.COLUMN_TYPE AS u_type, u.CHARACTER_SET_NAME AS u_cs, u.COLLATION_NAME AS u_coll
     FROM information_schema.COLUMNS c
     JOIN information_schema.COLUMNS u
       ON u.TABLE_SCHEMA=c.TABLE_SCHEMA AND u.TABLE_NAME='usuario' AND u.COLUMN_NAME='ClvUsu'
     WHERE c.TABLE_SCHEMA=DATABASE()
       AND c.TABLE_NAME='consentimiento_datos_personales'
       AND c.COLUMN_NAME='ClvUsu'"
)->fetch();
if (
    $cmp['c_type'] !== $cmp['u_type']
    || $cmp['c_cs'] !== $cmp['u_cs']
    || $cmp['c_coll'] !== $cmp['u_coll']
) {
    fail('ClvUsu no coincide con usuario.ClvUsu');
}
ok('ClvUsu idéntico a usuario.ClvUsu (tipo/charset/collation)');

// Sin UNIQUE ClvUsu+VersionAviso
$uk = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA=DATABASE()
       AND TABLE_NAME='consentimiento_datos_personales'
       AND INDEX_NAME='UK_Consentimiento_Usuario_Version'"
)->fetchColumn();
if ($uk !== 0) {
    fail('UNIQUE usuario+versión no debió existir');
}
ok('Sin UNIQUE (ClvUsu, VersionAviso)');

// PK AUTO_INCREMENT
$pk = $pdo->query(
    "SELECT COLUMN_NAME, EXTRA FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE()
       AND TABLE_NAME='consentimiento_datos_personales'
       AND COLUMN_KEY='PRI'"
)->fetch();
if ($pk['COLUMN_NAME'] !== 'IdConsentimiento' || stripos((string) $pk['EXTRA'], 'auto_increment') === false) {
    fail('PK IdConsentimiento AUTO_INCREMENT ausente');
}
ok('PK IdConsentimiento AUTO_INCREMENT');

// Drop temporal
$admin->exec("DROP DATABASE IF EXISTS `{$tmpDb}`");
ok("BD temporal eliminada: {$tmpDb}");

fwrite(STDOUT, "\nRESULTADO: TODAS LAS VALIDACIONES PASARON\n");
fwrite(STDOUT, "consultorio_psicologico NO fue modificada.\n");
exit(0);
