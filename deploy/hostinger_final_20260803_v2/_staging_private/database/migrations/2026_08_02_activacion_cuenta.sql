-- =============================================================================
-- Migración: activacion_cuenta
-- Fecha: 2026-08-02
-- Entorno: ejecutar SOLO en el servidor tras respaldo (nunca desde una ruta web).
--
-- Propósito:
--   Enlaces de activación de un solo uso
--   (alta psicólogo / alta paciente / alta consultorio).
--   El token original NUNCA se almacena; solo TokenHash (SHA-256 hex).
--
-- Idempotencia:
--   CREATE TABLE IF NOT EXISTS + ALTER de enum compatible.
--   No inserta datos. No borra información. No usa CON001/PSI001/PAC001.
--
-- No modifica tablas clínicas.
-- =============================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS `activacion_cuenta` (
  `IdActivacion` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ClvUsu` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `TokenHash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `TipoActivacion` enum('ALTA_PSICOLOGO','ALTA_PACIENTE','ALTA_CONSULTORIO')
    COLLATE utf8mb4_unicode_ci NOT NULL,
  `ClvUsuInvitador` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `FechaCreacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `FechaExpiracion` datetime NOT NULL,
  `FechaUso` datetime DEFAULT NULL,
  `Estado` enum('PENDIENTE','USADA','EXPIRADA','REVOCADA')
    COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDIENTE',
  `FechaUltimoEnvio` datetime DEFAULT NULL,
  `NumReenvios` tinyint unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`IdActivacion`),
  UNIQUE KEY `UK_Activacion_TokenHash` (`TokenHash`),
  KEY `IDX_Activacion_Usuario` (`ClvUsu`),
  KEY `IDX_Activacion_Estado` (`Estado`),
  KEY `IDX_Activacion_Expiracion` (`FechaExpiracion`),
  KEY `IDX_Activacion_Usuario_Estado` (`ClvUsu`, `Estado`),
  CONSTRAINT `FK_Activacion_Usuario`
    FOREIGN KEY (`ClvUsu`) REFERENCES `usuario` (`ClvUsu`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `FK_Activacion_Invitador`
    FOREIGN KEY (`ClvUsuInvitador`) REFERENCES `usuario` (`ClvUsu`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Compatibilidad si la tabla ya existía sin ALTA_CONSULTORIO.
ALTER TABLE `activacion_cuenta`
  MODIFY COLUMN `TipoActivacion`
    enum('ALTA_PSICOLOGO','ALTA_PACIENTE','ALTA_CONSULTORIO')
    COLLATE utf8mb4_unicode_ci NOT NULL;
