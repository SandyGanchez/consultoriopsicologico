-- ============================================================
-- Migración: cita responsable / creador + RolDestinatario RESPONSABLE
-- Archivo: database/migrations/20260809_cita_responsable.sql
--
-- NO ejecutar automáticamente en producción ni en BD real
-- consultorio_psicologico hasta revisión manual.
-- Aplicar SOLO en BD temporal de pruebas (p. ej. scripts *_prueba).
--
-- Charset: utf8mb4 / utf8mb4_unicode_ci
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '-06:00';

-- ------------------------------------------------------------
-- 1) Columnas de reserva en cita
-- ------------------------------------------------------------

ALTER TABLE `cita`
  ADD COLUMN `ClvUsuCreador` VARCHAR(10)
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL
    COMMENT 'Usuario que reservó la cita (responsable o paciente)'
    AFTER `ClvServ`,
  ADD COLUMN `OrigenCita` ENUM(
      'PACIENTE',
      'RESPONSABLE',
      'PSICOLOGO',
      'CONSULTORIO'
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
    NOT NULL DEFAULT 'PACIENTE'
    COMMENT 'Canal/origen de la reserva'
    AFTER `ClvUsuCreador`,
  ADD COLUMN `IdRelacionResponsable` BIGINT UNSIGNED NULL
    COMMENT 'Relación paciente_responsable cuando OrigenCita=RESPONSABLE'
    AFTER `OrigenCita`;

ALTER TABLE `cita`
  ADD KEY `IDX_Cita_ClvUsuCreador` (`ClvUsuCreador`),
  ADD KEY `IDX_Cita_OrigenCita` (`OrigenCita`),
  ADD KEY `IDX_Cita_IdRelacionResponsable` (`IdRelacionResponsable`);

ALTER TABLE `cita`
  ADD CONSTRAINT `FK_Cita_UsuCreador`
    FOREIGN KEY (`ClvUsuCreador`) REFERENCES `usuario` (`ClvUsu`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `FK_Cita_RelacionResponsable`
    FOREIGN KEY (`IdRelacionResponsable`) REFERENCES `paciente_responsable` (`IdRelacion`)
    ON DELETE RESTRICT ON UPDATE CASCADE;

-- ------------------------------------------------------------
-- 2) correo_cita.RolDestinatario: agregar RESPONSABLE
-- ------------------------------------------------------------

ALTER TABLE `correo_cita`
  MODIFY COLUMN `RolDestinatario` ENUM(
      'PACIENTE',
      'PSICOLOGO',
      'RESPONSABLE'
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;
