-- =============================================================================
-- Migración: publicación de página pública del consultorio
-- Fecha: 2026-08-02
-- Entorno: ejecutar SOLO tras respaldo y autorización.
--
-- Agrega:
--   PublicadoCons TINYINT(1) NOT NULL DEFAULT 0
--   FechaPublicacionCons DATETIME NULL
--   KEY IDX_Consultorio_Publicado (PublicadoCons, EstatusCons)
--
-- No modifica EstatusCons.
-- No modifica tablas clínicas.
-- No inserta datos. No publica consultorios existentes (DEFAULT 0).
--
-- Idempotencia (MySQL 8+):
--   Si las columnas ya existen, el ADD COLUMN fallará.
--   Verificar antes:
--     SHOW COLUMNS FROM consultorio LIKE 'PublicadoCons';
--   Solo ejecutar si no existe.
-- =============================================================================

SET NAMES utf8mb4;

ALTER TABLE `consultorio`
  ADD COLUMN `PublicadoCons` tinyint(1) NOT NULL DEFAULT 0
    COMMENT '1 = página pública visible; 0 = borrador u oculto'
    AFTER `EstatusCons`,
  ADD COLUMN `FechaPublicacionCons` datetime DEFAULT NULL
    COMMENT 'Fecha de la publicación más reciente'
    AFTER `PublicadoCons`,
  ADD KEY `IDX_Consultorio_Publicado`
    (`PublicadoCons`, `EstatusCons`);
