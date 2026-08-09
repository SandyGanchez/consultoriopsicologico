-- ============================================================
-- Migración: pacientes dependientes y responsables
-- Archivo: database/migrations/20260809_pacientes_dependientes.sql
--
-- NO ejecutar automáticamente en producción.
-- Revisar y aplicar manualmente en cada entorno.
--
-- Requisitos previos (ABORTAR si fallan — ver sección 0):
--   - Todo paciente con ClvUsu apunta a usuario existente
--   - Ese usuario apunta a persona existente
--   - No hay dos pacientes con el mismo ClvPer vía usuario
--   - No hay dos pacientes con el mismo ClvUsu (ambigüedad)
--
-- Charset: utf8mb4 / utf8mb4_unicode_ci (alineado a BD local real).
-- ============================================================

-- ------------------------------------------------------------
-- 0) Guardas de integridad (signal 45000 = abortar)
-- ------------------------------------------------------------

SET @pac_sin_usuario := (
  SELECT COUNT(*) FROM paciente p
  LEFT JOIN usuario u ON u.ClvUsu = p.ClvUsu
  WHERE u.ClvUsu IS NULL
);

SET @pac_sin_persona := (
  SELECT COUNT(*) FROM paciente p
  INNER JOIN usuario u ON u.ClvUsu = p.ClvUsu
  LEFT JOIN persona per ON per.ClvPer = u.ClvPer
  WHERE per.ClvPer IS NULL
);

SET @dup_clvper := (
  SELECT COUNT(*) FROM (
    SELECT u.ClvPer
    FROM paciente p
    INNER JOIN usuario u ON u.ClvUsu = p.ClvUsu
    GROUP BY u.ClvPer
    HAVING COUNT(*) > 1
  ) x
);

SET @dup_clvusu := (
  SELECT COUNT(*) FROM (
    SELECT ClvUsu
    FROM paciente
    WHERE ClvUsu IS NOT NULL
    GROUP BY ClvUsu
    HAVING COUNT(*) > 1
  ) x
);

SET @msg := NULL;
SET @msg := IF(@pac_sin_usuario > 0,
  'ABORT: pacientes sin usuario existente', @msg);
SET @msg := IF(@pac_sin_persona > 0,
  'ABORT: pacientes con usuario sin persona', @msg);
SET @msg := IF(@dup_clvper > 0,
  'ABORT: ClvPer duplicado entre pacientes (via usuario)', @msg);
SET @msg := IF(@dup_clvusu > 0,
  'ABORT: ClvUsu duplicado en paciente (ambigüedad backfill)', @msg);

SET @guard_sql := IF(
  @msg IS NULL,
  'SELECT ''OK integridad pacientes'' AS migracion_4c',
  CONCAT('SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT = ', QUOTE(@msg))
);
PREPARE stmt_guard FROM @guard_sql;
EXECUTE stmt_guard;
DEALLOCATE PREPARE stmt_guard;

-- ------------------------------------------------------------
-- 1) paciente.ClvPer (nullable → backfill → NOT NULL + UNIQUE + FK)
-- ------------------------------------------------------------

ALTER TABLE `paciente`
  ADD COLUMN `ClvPer` VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL
    COMMENT 'Identidad del paciente (persona); independiente de cuenta'
    AFTER `ClvUsu`;

UPDATE `paciente` p
INNER JOIN `usuario` u ON u.ClvUsu = p.ClvUsu
SET p.ClvPer = u.ClvPer
WHERE p.ClvPer IS NULL;

SET @sin_clvper := (
  SELECT COUNT(*) FROM paciente WHERE ClvPer IS NULL OR ClvPer = ''
);
SET @guard_sql := IF(
  @sin_clvper = 0,
  'SELECT ''OK backfill ClvPer'' AS migracion_4c',
  'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT = ''ABORT: quedan pacientes sin ClvPer tras backfill'''
);
PREPARE stmt_guard2 FROM @guard_sql;
EXECUTE stmt_guard2;
DEALLOCATE PREPARE stmt_guard2;

ALTER TABLE `paciente`
  MODIFY COLUMN `ClvPer` VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
    COMMENT 'Identidad del paciente (persona); independiente de cuenta';

ALTER TABLE `paciente`
  ADD UNIQUE KEY `UK_Paciente_Persona` (`ClvPer`);

ALTER TABLE `paciente`
  ADD CONSTRAINT `FK_Paciente_Persona`
    FOREIGN KEY (`ClvPer`) REFERENCES `persona` (`ClvPer`)
    ON UPDATE CASCADE
    ON DELETE RESTRICT;

-- ------------------------------------------------------------
-- 2) paciente.ClvUsu nullable (cuenta opcional)
-- ------------------------------------------------------------

ALTER TABLE `paciente`
  MODIFY COLUMN `ClvUsu` VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL
    COMMENT 'Cuenta de acceso; NULL = dependiente sin usuario';

-- ------------------------------------------------------------
-- 3) paciente_responsable
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `paciente_responsable` (
  `IdRelacion` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ClvPac` VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ClvUsuResponsable` VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Parentesco` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `EsTutorLegal` TINYINT(1) NOT NULL DEFAULT 0
    COMMENT 'Declaración del usuario; no verifica tutela jurídica',
  `PuedeAgendar` TINYINT(1) NOT NULL DEFAULT 1,
  `EstadoRelacion` ENUM('ACTIVA','INACTIVA')
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
    NOT NULL DEFAULT 'ACTIVA',
  `FechaRegistro` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `FechaActualizacion` DATETIME NULL,
  PRIMARY KEY (`IdRelacion`),
  UNIQUE KEY `UK_PacienteResponsable_Pac_Usu` (`ClvPac`, `ClvUsuResponsable`),
  KEY `IDX_PacienteResponsable_Usu_Estado` (`ClvUsuResponsable`, `EstadoRelacion`),
  CONSTRAINT `FK_PacienteResponsable_Paciente`
    FOREIGN KEY (`ClvPac`) REFERENCES `paciente` (`ClvPac`)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  CONSTRAINT `FK_PacienteResponsable_Usuario`
    FOREIGN KEY (`ClvUsuResponsable`) REFERENCES `usuario` (`ClvUsu`)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 4) consentimiento: sujeto vs quien acepta
-- ------------------------------------------------------------

ALTER TABLE `consentimiento_datos_personales`
  ADD COLUMN `ClvPacSujeto` VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL
    COMMENT 'Paciente cuyos datos se procesan'
    AFTER `ClvUsu`,
  ADD COLUMN `IdRelacionResponsable` BIGINT UNSIGNED NULL
    COMMENT 'Relación responsable-dependiente si aplica'
    AFTER `ClvPacSujeto`;

-- Backfill solo cuando hay exactamente un paciente con ese ClvUsu
UPDATE `consentimiento_datos_personales` c
INNER JOIN (
  SELECT ClvUsu, MIN(ClvPac) AS ClvPac
  FROM paciente
  WHERE ClvUsu IS NOT NULL
  GROUP BY ClvUsu
  HAVING COUNT(*) = 1
) p ON p.ClvUsu = c.ClvUsu
SET c.ClvPacSujeto = p.ClvPac
WHERE c.ClvPacSujeto IS NULL;

ALTER TABLE `consentimiento_datos_personales`
  ADD KEY `IDX_Consentimiento_PacienteSujeto` (`ClvPacSujeto`);

ALTER TABLE `consentimiento_datos_personales`
  ADD CONSTRAINT `FK_Consentimiento_PacienteSujeto`
    FOREIGN KEY (`ClvPacSujeto`) REFERENCES `paciente` (`ClvPac`)
    ON UPDATE CASCADE
    ON DELETE RESTRICT;

ALTER TABLE `consentimiento_datos_personales`
  ADD CONSTRAINT `FK_Consentimiento_RelacionResponsable`
    FOREIGN KEY (`IdRelacionResponsable`) REFERENCES `paciente_responsable` (`IdRelacion`)
    ON UPDATE CASCADE
    ON DELETE RESTRICT;
