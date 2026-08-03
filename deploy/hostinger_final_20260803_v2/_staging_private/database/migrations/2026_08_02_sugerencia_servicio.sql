-- Migración definitiva: sugerencia_servicio
-- Aplicada en consultorio_psicologico (local).
-- NO aplicar en Hostinger / producción sin aprobación.

SET NAMES utf8mb4;
SET time_zone = '-06:00';

CREATE TABLE IF NOT EXISTS sugerencia_servicio (
  IdSugerenciaServicio BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ClvPsi VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  ClvCons VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  NombreSugerido VARCHAR(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  DescripcionSugerida VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  Justificacion VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  EstadoSugerencia ENUM('PENDIENTE','APROBADA','RECHAZADA')
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDIENTE',
  ObservacionConsultorio VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  FechaSolicitud DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FechaRevision DATETIME NULL,
  ClvUsuRevision VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  ClvServCreado VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL
    COMMENT 'Referencia opcional al servicio institucional creado tras aprobar',
  PRIMARY KEY (IdSugerenciaServicio),
  KEY IDX_Sugerencia_Psi (ClvPsi),
  KEY IDX_Sugerencia_Cons_Estado (ClvCons, EstadoSugerencia),
  KEY IDX_Sugerencia_Revision (ClvUsuRevision),
  KEY IDX_Sugerencia_ServCreado (ClvServCreado),
  KEY IDX_Sugerencia_Psi_Estado_Nombre (ClvPsi, EstadoSugerencia, NombreSugerido),
  CONSTRAINT FK_Sugerencia_Psicologo
    FOREIGN KEY (ClvPsi) REFERENCES psicologo (ClvPsi)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT FK_Sugerencia_Consultorio
    FOREIGN KEY (ClvCons) REFERENCES consultorio (ClvCons)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT FK_Sugerencia_UsuarioRevision
    FOREIGN KEY (ClvUsuRevision) REFERENCES usuario (ClvUsu)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT FK_Sugerencia_ServicioCreado
    FOREIGN KEY (ClvServCreado) REFERENCES servicios (ClvServ)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
