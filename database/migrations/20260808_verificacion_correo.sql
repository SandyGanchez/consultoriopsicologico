-- ============================================================
-- Migración: verificación de correo (registro público PACIENTE)
-- Archivo: database/migrations/20260808_verificacion_correo.sql
--
-- NO ejecutar automáticamente en producción.
-- Revisar y aplicar manualmente en cada entorno.
--
-- utf8mb4 / utf8mb4_unicode_ci alineado con usuario.ClvUsu.
-- ============================================================

-- 1) Columnas en usuario
ALTER TABLE `usuario`
  ADD COLUMN `CorreoVerificado` TINYINT(1) NOT NULL DEFAULT 0
    COMMENT '1 = correo confirmado por OTP o flujo confiable'
    AFTER `RequiereCambioContrasena`,
  ADD COLUMN `FechaVerificacionCorreo` DATETIME NULL
    COMMENT 'Momento en que se confirmó el correo'
    AFTER `CorreoVerificado`;

-- 2) Cuentas preexistentes: no bloquear usuarios legítimos
UPDATE `usuario`
SET
  `CorreoVerificado` = 1,
  `FechaVerificacionCorreo` = COALESCE(`FechaVerificacionCorreo`, NOW())
WHERE `CorreoVerificado` = 0;

-- 3) Ledger OTP (semántica distinta de activacion_cuenta / recuperacion_password)
CREATE TABLE IF NOT EXISTS `verificacion_correo` (
  `IdVerificacion` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ClvUsu` VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `CodigoHash` VARCHAR(255) NOT NULL,
  `FechaCreacion` DATETIME NOT NULL,
  `FechaExpiracion` DATETIME NOT NULL,
  `FechaUso` DATETIME NULL,
  `Intentos` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `NumReenvios` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `Estado` ENUM('PENDIENTE', 'USADA', 'EXPIRADA', 'REVOCADA')
    NOT NULL DEFAULT 'PENDIENTE',
  `FechaUltimoEnvio` DATETIME NULL,
  PRIMARY KEY (`IdVerificacion`),
  KEY `IDX_VerificacionCorreo_Usuario_Estado` (`ClvUsu`, `Estado`),
  KEY `IDX_VerificacionCorreo_Expiracion` (`FechaExpiracion`),
  CONSTRAINT `FK_VerificacionCorreo_Usuario`
    FOREIGN KEY (`ClvUsu`) REFERENCES `usuario` (`ClvUsu`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
