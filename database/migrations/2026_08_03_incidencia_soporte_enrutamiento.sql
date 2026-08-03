-- Migración: enrutamiento de incidencias CONSULTORIO / ADMINISTRADOR
-- Alineada a SHOW CREATE TABLE incidencia_soporte (utf8mb4_unicode_ci).
-- No incluye backfill masivo a ADMINISTRADOR.
-- Validar primero en BD de prueba.

SET NAMES utf8mb4;
SET time_zone = '-06:00';

ALTER TABLE `incidencia_soporte`
  ADD COLUMN `RolDestino` ENUM('CONSULTORIO','ADMINISTRADOR')
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
    NOT NULL DEFAULT 'CONSULTORIO'
    AFTER `TipoIncidencia`,
  ADD COLUMN `NivelAtencion` ENUM('PRIMER_NIVEL','ESCALADA')
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
    NOT NULL DEFAULT 'PRIMER_NIVEL'
    AFTER `RolDestino`,
  ADD COLUMN `IdIncidenciaOrigen` BIGINT UNSIGNED NULL
    AFTER `NivelAtencion`,
  ADD COLUMN `ObservacionConsultorio` VARCHAR(1000)
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL
    AFTER `ObservacionAdministrador`,
  ADD KEY `IDX_IncidenciaSoporte_Destino_Estado_Fecha`
    (`RolDestino`, `EstadoIncidencia`, `FechaSolicitud`),
  ADD KEY `IDX_IncidenciaSoporte_Origen` (`IdIncidenciaOrigen`),
  ADD CONSTRAINT `FK_IncidenciaSoporte_Origen`
    FOREIGN KEY (`IdIncidenciaOrigen`)
    REFERENCES `incidencia_soporte` (`IdIncidencia`)
    ON DELETE SET NULL
    ON UPDATE CASCADE;
