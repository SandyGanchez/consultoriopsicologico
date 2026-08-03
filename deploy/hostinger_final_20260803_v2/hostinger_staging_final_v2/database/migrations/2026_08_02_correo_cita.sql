-- Migración: correo_cita
-- Control de confirmaciones y recordatorios de cita (sin contenido clínico).
-- Validar primero en BD de prueba. No aplicar en Hostinger sin aprobación.

SET NAMES utf8mb4;
SET time_zone = '-06:00';

CREATE TABLE IF NOT EXISTS correo_cita (
  IdCorreoCita BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ClvCita VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  ClvUsuDestino VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  TipoCorreo ENUM('CONFIRMACION','RECORDATORIO_24H')
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  RolDestinatario ENUM('PACIENTE','PSICOLOGO')
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  FechaProgramada DATETIME NOT NULL,
  FechaUltimoIntento DATETIME NULL,
  FechaEnvio DATETIME NULL,
  EstadoCorreo ENUM('PENDIENTE','PROCESANDO','ENVIADO','FALLIDO','OMITIDO')
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDIENTE',
  Intentos TINYINT UNSIGNED NOT NULL DEFAULT 0,
  ErrorResumen VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  MotivoOmision VARCHAR(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  FechaRegistro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (IdCorreoCita),
  UNIQUE KEY UK_CorreoCita_Tipo_Rol (ClvCita, TipoCorreo, RolDestinatario),
  KEY IDX_CorreoCita_Estado_Fecha (EstadoCorreo, FechaProgramada),
  KEY IDX_CorreoCita_Destino (ClvUsuDestino),
  CONSTRAINT FK_CorreoCita_Cita
    FOREIGN KEY (ClvCita) REFERENCES cita (ClvCita)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT FK_CorreoCita_Usuario
    FOREIGN KEY (ClvUsuDestino) REFERENCES usuario (ClvUsu)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
