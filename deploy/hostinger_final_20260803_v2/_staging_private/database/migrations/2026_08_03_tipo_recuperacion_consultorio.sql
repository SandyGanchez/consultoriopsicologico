-- Migración aplicada localmente: RECUPERACION_CONSULTORIO
-- Base: consultorio_psicologico
-- No modificar Hostinger / ZIP / producción desde este archivo.

SET NAMES utf8mb4;

ALTER TABLE `activacion_cuenta`
  MODIFY COLUMN `TipoActivacion`
    enum(
      'ALTA_PSICOLOGO',
      'ALTA_PACIENTE',
      'ALTA_CONSULTORIO',
      'RECUPERACION_CONSULTORIO'
    ) COLLATE utf8mb4_unicode_ci NOT NULL;