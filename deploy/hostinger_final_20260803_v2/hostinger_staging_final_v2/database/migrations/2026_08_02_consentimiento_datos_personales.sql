-- =============================================================================
-- Migración FINAL: aviso_privacidad_version + consentimiento_datos_personales
--                 + solicitud_privacidad
-- Fecha: 2026-08-02
--
-- NO EJECUTAR en consultorio_psicologico hasta aprobación explícita.
--
-- Inmutabilidad:
--   Una fila publicada en aviso_privacidad_version NUNCA modifica
--   VersionAviso, FechaPublicacion, ContenidoAviso ni HashContenidoAviso.
--   Solo EstadoAviso puede pasar de VIGENTE a SUSTITUIDO al publicar otra.
--
-- Versión 1.0:
--   NO se inserta aquí con marcadores. Usar seeder controlado
--   database/seeders/publicar_aviso_privacidad.php que solo publica si
--   existen datos legales reales del consultorio único.
--
-- Compatibilidad: MySQL 8.x / MariaDB 10.3+ (InnoDB, utf8mb4_unicode_ci).
-- =============================================================================

SET NAMES utf8mb4;
-- Política B: coincidir con APP_TIMEZONE=America/Mexico_City.
-- En instalaciones sin tablas de zona horaria de MySQL se usa el offset fijo
-- UTC-06:00 (Ciudad de México sin DST federal vigente).
SET time_zone = '-06:00';

-- -----------------------------------------------------------------------------
-- 1) aviso_privacidad_version (inmutable una vez publicada)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `aviso_privacidad_version` (
  `IdAvisoPrivacidad` bigint unsigned NOT NULL AUTO_INCREMENT,
  `VersionAviso` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `FechaPublicacion` datetime NOT NULL,
  `ContenidoAviso` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `HashContenidoAviso` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `EstadoAviso` enum('VIGENTE','SUSTITUIDO')
    COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'VIGENTE',
  `FechaRegistro` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`IdAvisoPrivacidad`),
  UNIQUE KEY `UK_AvisoPrivacidad_Version` (`VersionAviso`),
  UNIQUE KEY `UK_AvisoPrivacidad_Hash` (`HashContenidoAviso`),
  KEY `IDX_AvisoPrivacidad_Estado` (`EstadoAviso`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2) consentimiento_datos_personales
-- PK: IdConsentimiento AUTO_INCREMENT (sin ClaveService / MAX+1)
-- ClvUsu idéntico a usuario.ClvUsu: varchar(10) utf8mb4_unicode_ci
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `consentimiento_datos_personales` (
  `IdConsentimiento` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ClvUsu` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `IdAvisoPrivacidad` bigint unsigned NOT NULL,
  `VersionAviso` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `HashContenidoAviso` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `AvisoLeido` tinyint(1) NOT NULL DEFAULT 0,
  `ConsentimientoDatosSensibles` tinyint(1) NOT NULL DEFAULT 0,
  `FechaAceptacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `MedioAceptacion` enum(
    'REGISTRO',
    'ACTIVACION',
    'REACEPTACION',
    'PANEL'
  ) COLLATE utf8mb4_unicode_ci NOT NULL,
  `EstadoConsentimiento` enum(
    'VIGENTE',
    'REVOCADO',
    'SUPERSEDIDO'
  ) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'VIGENTE',
  `FechaRevocacion` datetime DEFAULT NULL
    COMMENT 'Solo cuando EstadoConsentimiento = REVOCADO',
  `FechaCambioEstado` datetime DEFAULT NULL
    COMMENT 'Cuando pasa a REVOCADO o SUPERSEDIDO',
  `FechaRegistro` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`IdConsentimiento`),
  KEY `IDX_Consentimiento_Usuario` (`ClvUsu`),
  KEY `IDX_Consentimiento_Aviso` (`IdAvisoPrivacidad`),
  KEY `IDX_Consentimiento_Usuario_Aviso_Estado`
    (`ClvUsu`, `IdAvisoPrivacidad`, `EstadoConsentimiento`),
  KEY `IDX_Consentimiento_Usuario_Version_Estado`
    (`ClvUsu`, `VersionAviso`, `EstadoConsentimiento`),
  CONSTRAINT `FK_Consentimiento_Usuario`
    FOREIGN KEY (`ClvUsu`) REFERENCES `usuario` (`ClvUsu`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT `FK_Consentimiento_AvisoPrivacidad`
    FOREIGN KEY (`IdAvisoPrivacidad`)
      REFERENCES `aviso_privacidad_version` (`IdAvisoPrivacidad`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3) solicitud_privacidad
-- Autorización (aplicación):
--   CONSULTORIO: consultar, revisar, responder, cambiar estado.
--   PACIENTE: crear propias, consultar estado y RespuestaTitular.
--   ADMINISTRADOR: sin detalle (no DetalleSolicitud, datos personales,
--                  NotasInternas); limitado a cuentas/accesos.
--   PSICOLOGO: sin acceso ARCO; no acepta consentimiento por el paciente.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `solicitud_privacidad` (
  `IdSolicitudPrivacidad` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ClvUsu` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ClvPac` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `TipoSolicitud` enum(
    'ARCO_ACCESO',
    'ARCO_RECTIFICACION',
    'ARCO_CANCELACION',
    'ARCO_OPOSICION',
    'REVOCACION_CONSENTIMIENTO',
    'OTRO'
  ) COLLATE utf8mb4_unicode_ci NOT NULL,
  `DetalleSolicitud` text COLLATE utf8mb4_unicode_ci,
  `NombreSolicitante` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `CorreoSolicitante` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `TelefonoSolicitante` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `IdAvisoPrivacidad` bigint unsigned DEFAULT NULL,
  `EstadoSolicitud` enum(
    'RECIBIDA',
    'EN_REVISION',
    'ATENDIDA',
    'RECHAZADA'
  ) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'RECIBIDA',
  `FechaSolicitud` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `FechaAtencion` datetime DEFAULT NULL,
  `ClvUsuAtencion` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `RespuestaTitular` text COLLATE utf8mb4_unicode_ci,
  `FechaRespuesta` datetime DEFAULT NULL,
  `NotasInternas` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`IdSolicitudPrivacidad`),
  KEY `IDX_SolicitudPrivacidad_Usuario` (`ClvUsu`),
  KEY `IDX_SolicitudPrivacidad_Paciente` (`ClvPac`),
  KEY `IDX_SolicitudPrivacidad_Aviso` (`IdAvisoPrivacidad`),
  KEY `IDX_SolicitudPrivacidad_Estado_Fecha`
    (`EstadoSolicitud`, `FechaSolicitud`),
  KEY `IDX_SolicitudPrivacidad_Tipo` (`TipoSolicitud`),
  KEY `IDX_SolicitudPrivacidad_Atencion` (`ClvUsuAtencion`),
  CONSTRAINT `FK_SolicitudPrivacidad_Usuario`
    FOREIGN KEY (`ClvUsu`) REFERENCES `usuario` (`ClvUsu`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT `FK_SolicitudPrivacidad_Paciente`
    FOREIGN KEY (`ClvPac`) REFERENCES `paciente` (`ClvPac`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT `FK_SolicitudPrivacidad_Aviso`
    FOREIGN KEY (`IdAvisoPrivacidad`)
      REFERENCES `aviso_privacidad_version` (`IdAvisoPrivacidad`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT `FK_SolicitudPrivacidad_UsuarioAtencion`
    FOREIGN KEY (`ClvUsuAtencion`) REFERENCES `usuario` (`ClvUsu`)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
