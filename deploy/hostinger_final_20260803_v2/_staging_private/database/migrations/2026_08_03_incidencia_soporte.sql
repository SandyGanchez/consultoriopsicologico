-- Migración aplicada localmente: incidencia_soporte
-- Tickets de soporte de acceso para el administrador local.
-- No reutiliza solicitud_privacidad ni incidencia_acceso (legacy).
--
-- Collations alineadas con SHOW CREATE TABLE consultorio / usuario:
-- utf8mb4_unicode_ci, ClvCons/ClvUsu VARCHAR(10).

SET NAMES utf8mb4;
SET time_zone = '-06:00';

CREATE TABLE IF NOT EXISTS `incidencia_soporte` (
  `IdIncidencia` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ClvCons` VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ClvUsuSolicitante` VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `CorreoReportado` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `TipoIncidencia` ENUM(
    'AUTENTICACION',
    'CUENTA_BLOQUEADA',
    'ACTIVACION',
    'RECUPERACION',
    'CAMBIO_CORREO',
    'OTRO_ACCESO'
  ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Descripcion` VARCHAR(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `EstadoIncidencia` ENUM('PENDIENTE','EN_PROCESO','RESUELTA')
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDIENTE',
  `FechaSolicitud` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `FechaActualizacion` DATETIME NULL,
  `ObservacionAdministrador` VARCHAR(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `ClvUsuAtencion` VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `FechaResolucion` DATETIME NULL,
  PRIMARY KEY (`IdIncidencia`),
  KEY `IDX_IncidenciaSoporte_Estado_Fecha` (`EstadoIncidencia`, `FechaSolicitud`),
  KEY `IDX_IncidenciaSoporte_Fecha` (`FechaSolicitud`),
  KEY `IDX_IncidenciaSoporte_Consultorio` (`ClvCons`),
  KEY `IDX_IncidenciaSoporte_Correo` (`CorreoReportado`),
  KEY `IDX_IncidenciaSoporte_Solicitante` (`ClvUsuSolicitante`),
  KEY `IDX_IncidenciaSoporte_Atencion` (`ClvUsuAtencion`),
  CONSTRAINT `FK_IncidenciaSoporte_Consultorio`
    FOREIGN KEY (`ClvCons`) REFERENCES `consultorio` (`ClvCons`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `FK_IncidenciaSoporte_Solicitante`
    FOREIGN KEY (`ClvUsuSolicitante`) REFERENCES `usuario` (`ClvUsu`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `FK_IncidenciaSoporte_Atencion`
    FOREIGN KEY (`ClvUsuAtencion`) REFERENCES `usuario` (`ClvUsu`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
