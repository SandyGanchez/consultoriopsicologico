-- Migración: redes institucionales (redsocial) + redes profesionales (red_social_psicologo)
-- Aplicar solo tras verificación de tipos y cero huérfanos.
-- NO aplicar en Hostinger sin aprobación.

SET NAMES utf8mb4;
SET time_zone = '-06:00';

START TRANSACTION;

-- ============================================================
-- 1) Ampliar redsocial (consultorio)
-- ============================================================

ALTER TABLE redsocial
  MODIFY COLUMN TipoRed ENUM(
    'Facebook',
    'Instagram',
    'WhatsApp',
    'TikTok',
    'YouTube',
    'LinkedIn',
    'Página Web'
  ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  ADD COLUMN EtiquetaRed VARCHAR(60)
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL
    AFTER URLRed,
  ADD COLUMN EstadoRed ENUM('ACTIVA','INACTIVA')
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVA'
    AFTER EtiquetaRed,
  ADD COLUMN OrdenRed SMALLINT UNSIGNED NOT NULL DEFAULT 1
    AFTER EstadoRed,
  ADD COLUMN FechaRegistro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    AFTER OrdenRed,
  ADD COLUMN FechaActualizacion DATETIME NULL
    AFTER FechaRegistro,
  ADD INDEX IDX_RedSocial_Cons_Estado_Orden (ClvCons, EstadoRed, OrdenRed),
  ADD CONSTRAINT FK_RedSocial_Consultorio
    FOREIGN KEY (ClvCons) REFERENCES consultorio (ClvCons)
    ON DELETE RESTRICT
    ON UPDATE CASCADE;

-- ============================================================
-- 2) Redes profesionales del psicólogo
-- ============================================================

CREATE TABLE IF NOT EXISTS red_social_psicologo (
  IdRedSocialPsi BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ClvPsi VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  TipoRed ENUM(
    'Facebook',
    'Instagram',
    'WhatsApp',
    'TikTok',
    'YouTube',
    'LinkedIn',
    'Página Web'
  ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  URLRed VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  EtiquetaRed VARCHAR(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  EstadoRed ENUM('ACTIVA','INACTIVA')
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVA',
  OrdenRed SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  FechaRegistro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FechaActualizacion DATETIME NULL,
  PRIMARY KEY (IdRedSocialPsi),
  KEY IDX_RedSocialPsi_Psi_Estado_Orden (ClvPsi, EstadoRed, OrdenRed),
  CONSTRAINT FK_RedSocialPsicologo_Psicologo
    FOREIGN KEY (ClvPsi) REFERENCES psicologo (ClvPsi)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
