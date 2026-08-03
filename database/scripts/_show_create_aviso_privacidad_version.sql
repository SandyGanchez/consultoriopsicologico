*************************** 1. row ***************************
       Table: aviso_privacidad_version
Create Table: CREATE TABLE `aviso_privacidad_version` (
  `IdAvisoPrivacidad` bigint unsigned NOT NULL AUTO_INCREMENT,
  `VersionAviso` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `FechaPublicacion` datetime NOT NULL,
  `ContenidoAviso` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `HashContenidoAviso` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `EstadoAviso` enum('VIGENTE','SUSTITUIDO') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'VIGENTE',
  `FechaRegistro` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`IdAvisoPrivacidad`),
  UNIQUE KEY `UK_AvisoPrivacidad_Version` (`VersionAviso`),
  UNIQUE KEY `UK_AvisoPrivacidad_Hash` (`HashContenidoAviso`),
  KEY `IDX_AvisoPrivacidad_Estado` (`EstadoAviso`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
