*************************** 1. row ***************************
       Table: consentimiento_datos_personales
Create Table: CREATE TABLE `consentimiento_datos_personales` (
  `IdConsentimiento` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ClvUsu` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `IdAvisoPrivacidad` bigint unsigned NOT NULL,
  `VersionAviso` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `HashContenidoAviso` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `AvisoLeido` tinyint(1) NOT NULL DEFAULT '0',
  `ConsentimientoDatosSensibles` tinyint(1) NOT NULL DEFAULT '0',
  `FechaAceptacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `MedioAceptacion` enum('REGISTRO','ACTIVACION','REACEPTACION','PANEL') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `EstadoConsentimiento` enum('VIGENTE','REVOCADO','SUPERSEDIDO') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'VIGENTE',
  `FechaRevocacion` datetime DEFAULT NULL COMMENT 'Solo cuando EstadoConsentimiento = REVOCADO',
  `FechaCambioEstado` datetime DEFAULT NULL COMMENT 'Cuando pasa a REVOCADO o SUPERSEDIDO',
  `FechaRegistro` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`IdConsentimiento`),
  KEY `IDX_Consentimiento_Usuario` (`ClvUsu`),
  KEY `IDX_Consentimiento_Aviso` (`IdAvisoPrivacidad`),
  KEY `IDX_Consentimiento_Usuario_Aviso_Estado` (`ClvUsu`,`IdAvisoPrivacidad`,`EstadoConsentimiento`),
  KEY `IDX_Consentimiento_Usuario_Version_Estado` (`ClvUsu`,`VersionAviso`,`EstadoConsentimiento`),
  CONSTRAINT `FK_Consentimiento_AvisoPrivacidad` FOREIGN KEY (`IdAvisoPrivacidad`) REFERENCES `aviso_privacidad_version` (`IdAvisoPrivacidad`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `FK_Consentimiento_Usuario` FOREIGN KEY (`ClvUsu`) REFERENCES `usuario` (`ClvUsu`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
