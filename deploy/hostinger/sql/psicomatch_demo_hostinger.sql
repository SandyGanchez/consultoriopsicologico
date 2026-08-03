-- =============================================================================
-- PsicoMatch — SQL de demostración para Hostinger
-- Dominio objetivo: https://consultoriospsicologicospsicomatch.com
-- Instalación de consultorio único: un administrador + un consultorio (CON001)
-- Incluye: estructura actual + activacion_cuenta + PublicadoCons + demo mínima
-- Excluye: segundo consultorio, pacientes reales, expedientes, tokens, SMTP
-- No ejecutar database/seeds/dev_multiconsultorio_con002.sql
-- =============================================================================
-- MySQL dump 10.13  Distrib 8.4.3, for Win64 (x86_64)
--
-- Host: (omitido)
-- ------------------------------------------------------
-- Server version	8.4.3

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `actitud_conducta_inicial`
--

DROP TABLE IF EXISTS `actitud_conducta_inicial`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `actitud_conducta_inicial` (
  `ClvActitud` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `Independiente` tinyint(1) NOT NULL DEFAULT '0',
  `Dependiente` tinyint(1) NOT NULL DEFAULT '0',
  `Timida` tinyint(1) NOT NULL DEFAULT '0',
  `Expansiva` tinyint(1) NOT NULL DEFAULT '0',
  `Agresiva` tinyint(1) NOT NULL DEFAULT '0',
  `Controlada` tinyint(1) NOT NULL DEFAULT '0',
  `Frustrada` tinyint(1) NOT NULL DEFAULT '0',
  `Deprimida` tinyint(1) NOT NULL DEFAULT '0',
  `Alegre` tinyint(1) NOT NULL DEFAULT '0',
  `ConductaPsicopatica` tinyint(1) NOT NULL DEFAULT '0',
  `ProblemasConductuales` tinyint(1) NOT NULL DEFAULT '0',
  `TrabajoPrecoz` tinyint(1) NOT NULL DEFAULT '0',
  `FugaHogar` tinyint(1) NOT NULL DEFAULT '0',
  `EdadFugaHogar` int DEFAULT NULL,
  `SintomasNeuroticos` tinyint(1) NOT NULL DEFAULT '0',
  `ProblemasEscolares` tinyint(1) NOT NULL DEFAULT '0',
  `Otros` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `ClvHist` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`ClvActitud`),
  UNIQUE KEY `UK_Actitud_Historial` (`ClvHist`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `activacion_cuenta`
--

DROP TABLE IF EXISTS `activacion_cuenta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `activacion_cuenta` (
  `IdActivacion` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ClvUsu` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `TokenHash` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `TipoActivacion` enum('ALTA_PSICOLOGO','ALTA_PACIENTE','ALTA_CONSULTORIO') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ClvUsuInvitador` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `FechaCreacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `FechaExpiracion` datetime NOT NULL,
  `FechaUso` datetime DEFAULT NULL,
  `Estado` enum('PENDIENTE','USADA','EXPIRADA','REVOCADA') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDIENTE',
  `FechaUltimoEnvio` datetime DEFAULT NULL,
  `NumReenvios` tinyint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`IdActivacion`),
  UNIQUE KEY `UK_Activacion_TokenHash` (`TokenHash`),
  KEY `IDX_Activacion_Usuario` (`ClvUsu`),
  KEY `IDX_Activacion_Estado` (`Estado`),
  KEY `IDX_Activacion_Expiracion` (`FechaExpiracion`),
  KEY `IDX_Activacion_Usuario_Estado` (`ClvUsu`,`Estado`),
  KEY `FK_Activacion_Invitador` (`ClvUsuInvitador`),
  CONSTRAINT `FK_Activacion_Invitador` FOREIGN KEY (`ClvUsuInvitador`) REFERENCES `usuario` (`ClvUsu`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `FK_Activacion_Usuario` FOREIGN KEY (`ClvUsu`) REFERENCES `usuario` (`ClvUsu`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `adiccion`
--

DROP TABLE IF EXISTS `adiccion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `adiccion` (
  `ClvAdiccion` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `TipoAdiccion` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `EdadInicio` int DEFAULT NULL,
  `Frecuencia` enum('FRECUENTE','POCO_FRECUENTE','OCASIONAL','NO_ESPECIFICADA') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `EstadoConsumo` enum('CONTROLADO','DESCONTROLADO','EN_ABSTINENCIA','EN_TRATAMIENTO','NO_ESPECIFICADO') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ConflictosAsociados` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `TratamientoRecibido` tinyint(1) NOT NULL DEFAULT '0',
  `DescripcionTratamiento` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `ObservacionesAdiccion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `ClvHist` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`ClvAdiccion`),
  KEY `IDX_Adiccion_Historial` (`ClvHist`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `antecedente_familiar`
--

DROP TABLE IF EXISTS `antecedente_familiar`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `antecedente_familiar` (
  `ClvAntFam` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `TipoAntecedenteFam` enum('ALTERACION_PERSONALIDAD','DROGADICCION','ALCOHOLISMO','PSICOSIS','NEUROSIS','TRASTORNO_CONVULSIVO','PSICOPATIA','OTRO') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `PresentaAntecedenteFam` tinyint(1) NOT NULL DEFAULT '0',
  `FamiliarRelacionado` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `DescripcionAntFam` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ClvHist` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`ClvAntFam`),
  UNIQUE KEY `UK_AntFam_Historial_Tipo` (`ClvHist`,`TipoAntecedenteFam`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `antecedente_patologico`
--

DROP TABLE IF EXISTS `antecedente_patologico`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `antecedente_patologico` (
  `ClvAntPat` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `TipoAntecedente` enum('CARDIOVASCULAR','PULMONAR','RENAL','GASTROINTESTINAL','HEMATOLOGICO','ENDOCRINO','MENTAL','DERMATOLOGICO','NEUROLOGICO','METABOLICO','MARCAPASOS','CARDIOPATIA','NEUROPATIA','IMPLANTE_DENTAL','CANCER','CONVULSIONES','ENFERMEDAD_INFANCIA','OTRO') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `PresentaAntecedente` tinyint(1) NOT NULL DEFAULT '0',
  `DescripcionAntecedente` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `TratamientoActual` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ClvHist` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`ClvAntPat`),
  UNIQUE KEY `UK_AntPat_Historial_Tipo` (`ClvHist`,`TipoAntecedente`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `apreciacion_diagnostica`
--

DROP TABLE IF EXISTS `apreciacion_diagnostica`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `apreciacion_diagnostica` (
  `ClvDiag` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `FechaDiagnostico` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ApreciacionPersonalidad` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `DiagnosticoInicial` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `CodigoDiagnostico` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `SistemaClasificacion` enum('DSM5','CIE10','CIE11','OTRO') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `PlanTratamiento` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `RecomendacionesIniciales` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `PronosticoInicial` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ObservacionesDiagnosticas` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `EstatusDiagnostico` enum('VIGENTE','MODIFICADO','DESCARTADO') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'VIGENTE',
  `ClvHist` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ClvPsi` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`ClvDiag`),
  KEY `IDX_Apreciacion_Historial` (`ClvHist`),
  KEY `IDX_Apreciacion_Psicologo` (`ClvPsi`),
  KEY `IDX_Apreciacion_Fecha` (`FechaDiagnostico`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `caracteristica`
--

DROP TABLE IF EXISTS `caracteristica`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `caracteristica` (
  `ClvCar` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Titulo` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Descripcion` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Icono` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `OrdenCar` int NOT NULL DEFAULT '1',
  `EstadoCar` tinyint(1) NOT NULL DEFAULT '1',
  `ClvCons` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`ClvCar`),
  KEY `FK_Caracteristica_Consultorio` (`ClvCons`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cita`
--

DROP TABLE IF EXISTS `cita`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cita` (
  `ClvCita` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `FechaCita` date NOT NULL,
  `HraInicioCita` time NOT NULL,
  `HraFinCita` time DEFAULT NULL,
  `DuracionAplicadaMin` int DEFAULT NULL,
  `CostoAplicado` decimal(10,2) DEFAULT NULL,
  `EstadoCita` enum('PROGRAMADA','ASISTIDA','CANCELADA','INASISTENCIA') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PROGRAMADA',
  `NotasCita` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `MotivoCancelacion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `FechaRegistroCita` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `FechaCancelacion` datetime DEFAULT NULL,
  `ClvPac` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ClvPsi` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ClvCons` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ClvServ` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`ClvCita`),
  KEY `FK_Cita_Paciente` (`ClvPac`),
  KEY `FK_Cita_Consultorio` (`ClvCons`),
  KEY `FK_Cita_Servicio` (`ClvServ`),
  KEY `FK_Cita_Psicologo` (`ClvPsi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `consecutivos`
--

DROP TABLE IF EXISTS `consecutivos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `consecutivos` (
  `NombreTabla` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `UltimoNumero` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`NombreTabla`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `consultorio`
--

DROP TABLE IF EXISTS `consultorio`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `consultorio` (
  `ClvCons` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `NombreCons` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `LogotipoCons` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `FaviconCons` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ImagenPortada` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Slogan` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `TelefonoCons` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `CorreoElectronico` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `LimiteCancHoras` int NOT NULL DEFAULT '24',
  `ClvDir` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ServidorSMTP` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `PuertoSMTP` int DEFAULT NULL,
  `SeguridadSMTP` enum('ssl','tls') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `CorreoSMTP` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `PasswordSMTP` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `NombreRemitente` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `EstatusCons` enum('ACTIVO','INACTIVO','BLOQUEADO') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVO',
  `PublicadoCons` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = p??gina p??blica visible; 0 = borrador u oculto',
  `FechaPublicacionCons` datetime DEFAULT NULL COMMENT 'Fecha de la publicaci??n m??s reciente',
  `FechaRegistroCons` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ClvCons`),
  KEY `FK_CONSULTORIO_DIRECCION` (`ClvDir`),
  KEY `IDX_Consultorio_Publicado` (`PublicadoCons`,`EstatusCons`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `consultorio_usuario`
--

DROP TABLE IF EXISTS `consultorio_usuario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `consultorio_usuario` (
  `ClvConsUsu` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ClvCons` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ClvUsu` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `EsResponsable` tinyint(1) NOT NULL DEFAULT '1',
  `EstatusConsUsu` enum('ACTIVO','INACTIVO') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVO',
  `FechaAsignacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ClvConsUsu`),
  UNIQUE KEY `UK_ConsultorioUsuario` (`ClvCons`,`ClvUsu`),
  KEY `FK_ConsultorioUsuario_Usuario` (`ClvUsu`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `diagnostico_seguimiento`
--

DROP TABLE IF EXISTS `diagnostico_seguimiento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `diagnostico_seguimiento` (
  `ClvDiagSeg` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `FechaDiagSeg` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `TipoCambioDiag` enum('SE_MANTIENE','MODIFICADO','DESCARTADO','NUEVO') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `DiagnosticoAnterior` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `DiagnosticoActual` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `CodigoDiagnostico` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `SistemaClasificacion` enum('DSM5','CIE10','CIE11','OTRO') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `JustificacionCambio` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ClvSeg` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ClvPsi` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`ClvDiagSeg`),
  KEY `IDX_DiagSeg_Seguimiento` (`ClvSeg`),
  KEY `IDX_DiagSeg_Psicologo` (`ClvPsi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `direccion`
--

DROP TABLE IF EXISTS `direccion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `direccion` (
  `ClvDir` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `PaisDir` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `EstadoDir` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `MunicipioDir` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ColoniaDir` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `CalleDir` varchar(70) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `CodPostDir` char(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `NumExtDir` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `NumIntDir` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `LatitudDir` decimal(10,8) DEFAULT NULL,
  `LongitudDir` decimal(11,8) DEFAULT NULL,
  `ReferenciaDir` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`ClvDir`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `disponibilidad_psicologo`
--

DROP TABLE IF EXISTS `disponibilidad_psicologo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `disponibilidad_psicologo` (
  `ClvDisponibilidad` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `DiaSemana` enum('LUNES','MARTES','MIERCOLES','JUEVES','VIERNES','SABADO','DOMINGO') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `HoraInicio` time NOT NULL,
  `HoraFin` time NOT NULL,
  `EstatusDisponibilidad` enum('ACTIVA','INACTIVA') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVA',
  `FechaRegistroDisponibilidad` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ClvPsi` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`ClvDisponibilidad`),
  UNIQUE KEY `UK_Disponibilidad_Psicologo` (`ClvPsi`,`DiaSemana`,`HoraInicio`,`HoraFin`),
  CONSTRAINT `FK_Disponibilidad_Psicologo` FOREIGN KEY (`ClvPsi`) REFERENCES `psicologo` (`ClvPsi`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `estado_psicologico_inicial`
--

DROP TABLE IF EXISTS `estado_psicologico_inicial`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `estado_psicologico_inicial` (
  `ClvEstadoInicial` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `MotivoConsulta` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `SintomasReferidos` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `Ansiedad` tinyint(1) NOT NULL DEFAULT '0',
  `Angustia` tinyint(1) NOT NULL DEFAULT '0',
  `AutoestimaBaja` tinyint(1) NOT NULL DEFAULT '0',
  `Indiferencia` tinyint(1) NOT NULL DEFAULT '0',
  `Confusion` tinyint(1) NOT NULL DEFAULT '0',
  `Descontrol` tinyint(1) NOT NULL DEFAULT '0',
  `Desorientacion` tinyint(1) NOT NULL DEFAULT '0',
  `Incoherencia` tinyint(1) NOT NULL DEFAULT '0',
  `Sobrevaloracion` tinyint(1) NOT NULL DEFAULT '0',
  `OtrosEstados` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ObservacionesIniciales` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ClvHist` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`ClvEstadoInicial`),
  UNIQUE KEY `UK_EstadoInicial_Historial` (`ClvHist`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `evolucion_sesion`
--

DROP TABLE IF EXISTS `evolucion_sesion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `evolucion_sesion` (
  `ClvEvolucion` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `AvancesSeg` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `DificultadesSeg` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `RetrocesosSeg` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `CumplimientoTareas` enum('COMPLETO','PARCIAL','NO_REALIZADO','NO_APLICA') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `CambiosConductuales` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `CambiosEmocionales` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `FactoresRiesgo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `FactoresProtectores` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `PronosticoActual` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ClvSeg` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`ClvEvolucion`),
  UNIQUE KEY `UK_Evolucion_Seguimiento` (`ClvSeg`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `examen_mental_inicial`
--

DROP TABLE IF EXISTS `examen_mental_inicial`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `examen_mental_inicial` (
  `ClvExamenMental` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Conciencia` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Orientacion` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Inteligencia` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Pensamiento` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `Afectividad` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `Atencion` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Memoria` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Sensopercepcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `Psicomotricidad` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `Habitos` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `InstintosConservados` tinyint(1) DEFAULT NULL,
  `Lenguaje` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ObservacionesExamen` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ClvHist` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`ClvExamenMental`),
  UNIQUE KEY `UK_ExamenMental_Historial` (`ClvHist`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `historial_clinico`
--

DROP TABLE IF EXISTS `historial_clinico`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `historial_clinico` (
  `ClvHist` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `NumeroExpediente` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `FechaAperturaHist` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `FechaEntrevistaInicial` datetime DEFAULT NULL,
  `FechaActualizacionHist` datetime DEFAULT NULL,
  `EstatusHist` enum('ACTIVO','INACTIVO','CERRADO','ARCHIVADO') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVO',
  `ClvPac` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ClvPsi` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ClvCons` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`ClvHist`),
  UNIQUE KEY `UK_Historial_NumeroExpediente` (`NumeroExpediente`),
  UNIQUE KEY `UK_Historial_Paciente_Consultorio` (`ClvPac`,`ClvCons`),
  KEY `IDX_Historial_Psicologo` (`ClvPsi`),
  KEY `IDX_Historial_Consultorio` (`ClvCons`),
  KEY `IDX_Historial_Estatus` (`EstatusHist`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `horario`
--

DROP TABLE IF EXISTS `horario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `horario` (
  `ClvHorario` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ClvPsi` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `DiaSemana` enum('LUNES','MARTES','MIERCOLES','JUEVES','VIERNES','SABADO','DOMINGO') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `HoraInicio` time NOT NULL,
  `HoraFin` time NOT NULL,
  `EstatusHorario` enum('ACTIVO','INACTIVO') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'ACTIVO',
  PRIMARY KEY (`ClvHorario`),
  KEY `fk_horario_psicologo` (`ClvPsi`),
  CONSTRAINT `FK_Horario_Psicologo` FOREIGN KEY (`ClvPsi`) REFERENCES `psicologo` (`ClvPsi`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `horario_consultorio`
--

DROP TABLE IF EXISTS `horario_consultorio`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `horario_consultorio` (
  `ClvHorarioCons` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `DiaSemana` enum('LUNES','MARTES','MIERCOLES','JUEVES','VIERNES','SABADO','DOMINGO') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `HoraInicio` time NOT NULL,
  `HoraFin` time NOT NULL,
  `EstatusHorario` enum('ACTIVO','INACTIVO') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVO',
  `ClvCons` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`ClvHorarioCons`),
  UNIQUE KEY `UK_HorarioConsultorio_Dia` (`ClvCons`,`DiaSemana`),
  CONSTRAINT `FK_HorarioConsultorio_Consultorio` FOREIGN KEY (`ClvCons`) REFERENCES `consultorio` (`ClvCons`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `incidencia_acceso`
--

DROP TABLE IF EXISTS `incidencia_acceso`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `incidencia_acceso` (
  `ClvIncidencia` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `TipoIncidencia` enum('INTENTO_FALLIDO','CUENTA_BLOQUEADA','ACCESO_NO_AUTORIZADO','RECUPERACION_FALLIDA','SESION_EXPIRADA','OTRA') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `DescripcionIncidencia` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `FechaIncidencia` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `EstatusIncidencia` enum('PENDIENTE','EN_REVISION','RESUELTA','DESCARTADA') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDIENTE',
  `DireccionIP` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `AgenteUsuario` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ObservacionesAdmin` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `FechaResolucion` datetime DEFAULT NULL,
  `ClvCons` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ClvUsu` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`ClvIncidencia`),
  KEY `IDX_Incidencia_Consultorio` (`ClvCons`),
  KEY `IDX_Incidencia_Usuario` (`ClvUsu`),
  KEY `IDX_Incidencia_Estatus` (`EstatusIncidencia`,`FechaIncidencia`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `notificacion`
--

DROP TABLE IF EXISTS `notificacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notificacion` (
  `ClvNotif` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `TituloNotif` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `MensajeNotif` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `TipoNotif` enum('CITA','CANCELACION','RECORDATORIO','CUENTA','PSICOLOGO','SISTEMA','OTRA') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'SISTEMA',
  `FechaNotif` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `LeidaNotif` tinyint(1) NOT NULL DEFAULT '0',
  `FechaLecturaNotif` datetime DEFAULT NULL,
  `ClvUsu` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`ClvNotif`),
  KEY `IDX_Notificacion_Usuario` (`ClvUsu`,`LeidaNotif`,`FechaNotif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `paciente`
--

DROP TABLE IF EXISTS `paciente`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `paciente` (
  `ClvPac` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `FotoPerfilPac` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'default.png',
  `FechaRegistroPac` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `EstadoActivoPac` tinyint(1) NOT NULL DEFAULT '1',
  `ClvUsu` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ClvCons` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`ClvPac`),
  KEY `FK_PACIENTE_USUARIO` (`ClvUsu`),
  KEY `FK_Paciente_Consultorio` (`ClvCons`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `persona`
--

DROP TABLE IF EXISTS `persona`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `persona` (
  `ClvPer` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `NombrePer` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ApPatPer` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ApMatPer` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `FechaNacimiento` date NOT NULL,
  `GeneroPer` enum('Masculino','Femenino','Otro') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `FotoPerfilPer` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ClvDir` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`ClvPer`),
  KEY `FK_PERSONA_DIRECCION` (`ClvDir`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `psicoanamnesis_familiar`
--

DROP TABLE IF EXISTS `psicoanamnesis_familiar`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `psicoanamnesis_familiar` (
  `ClvPsicoFam` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `PadresJuntos` tinyint(1) DEFAULT NULL,
  `PadreFallecido` tinyint(1) NOT NULL DEFAULT '0',
  `MadreFallecida` tinyint(1) NOT NULL DEFAULT '0',
  `ConflictoPadre` tinyint(1) NOT NULL DEFAULT '0',
  `ConflictoMadre` tinyint(1) NOT NULL DEFAULT '0',
  `ConflictoOtrosFamiliares` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `ActitudPadres` enum('AFECTUOSA','SOBREPROTECTORA','INDIFERENTE','HOSTIL','INEXISTENTE','OTRA') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `NumeroHermanos` int DEFAULT NULL,
  `NumeroHermanosVarones` int DEFAULT NULL,
  `NumeroHermanasMujeres` int DEFAULT NULL,
  `RelacionHermanos` enum('AFECTUOSA','SOBREPROTECTORA','APATICA','AGRESIVA','INEXISTENTE','OTRA') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ObservacionesFamiliares` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `ClvHist` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`ClvPsicoFam`),
  UNIQUE KEY `UK_PsicoFam_Historial` (`ClvHist`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `psicologo`
--

DROP TABLE IF EXISTS `psicologo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `psicologo` (
  `ClvPsi` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `CedulaProfesional` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `EspecialidadPsi` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `DescripcionProfesional` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `EstatusPsi` enum('ACTIVO','INACTIVO') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVO',
  `MostrarEnPagina` tinyint(1) NOT NULL DEFAULT '1',
  `FechaRegistroPsi` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ClvUsu` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ClvCons` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`ClvPsi`),
  UNIQUE KEY `UK_Psicologo_Cedula` (`CedulaProfesional`),
  UNIQUE KEY `UK_Psicologo_Usuario` (`ClvUsu`),
  KEY `FK_Psicologo_Consultorio` (`ClvCons`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `psicologo_servicio`
--

DROP TABLE IF EXISTS `psicologo_servicio`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `psicologo_servicio` (
  `ClvPsiServ` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ClvPsi` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ClvServ` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `PrecioServicio` decimal(10,2) NOT NULL DEFAULT '0.00',
  `DuracionMinutos` int NOT NULL DEFAULT '60',
  `DescripcionServicio` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `EstatusAsignacion` enum('ACTIVA','INACTIVA') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVA',
  `FechaAsignacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ClvPsiServ`),
  UNIQUE KEY `UK_PsicologoServicio` (`ClvPsi`,`ClvServ`),
  KEY `FK_PsicologoServicio_Servicio` (`ClvServ`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `reactivo_psicologico`
--

DROP TABLE IF EXISTS `reactivo_psicologico`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reactivo_psicologico` (
  `ClvReact` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `NombreReactivo` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `FechaAplicacion` date NOT NULL,
  `ResultadoReactivo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `InterpretacionReactivo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ArchivoResultado` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ClvHist` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ClvPsi` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`ClvReact`),
  KEY `IDX_Reactivo_Historial` (`ClvHist`),
  KEY `IDX_Reactivo_Psicologo` (`ClvPsi`),
  KEY `IDX_Reactivo_Fecha` (`FechaAplicacion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `recomendacion_sesion`
--

DROP TABLE IF EXISTS `recomendacion_sesion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `recomendacion_sesion` (
  `ClvRecSeg` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `TipoRecomendacion` enum('TAREA','EJERCICIO','LECTURA','HABITO','CANALIZACION','ESTUDIO_COMPLEMENTARIO','OTRA') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `DescripcionRec` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `FechaLimite` date DEFAULT NULL,
  `Cumplida` tinyint(1) NOT NULL DEFAULT '0',
  `FechaCumplimiento` date DEFAULT NULL,
  `ClvSeg` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`ClvRecSeg`),
  KEY `IDX_Recomendacion_Seguimiento` (`ClvSeg`),
  KEY `IDX_Recomendacion_Cumplida` (`Cumplida`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `recuperacion_password`
--

DROP TABLE IF EXISTS `recuperacion_password`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `recuperacion_password` (
  `IdRec` int NOT NULL AUTO_INCREMENT,
  `ClvUsu` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `CodigoHash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `FechaCreacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `FechaExpiracion` datetime NOT NULL,
  `Utilizado` tinyint(1) NOT NULL DEFAULT '0',
  `Intentos` tinyint unsigned NOT NULL DEFAULT '0',
  `FechaUltimoIntento` datetime DEFAULT NULL,
  PRIMARY KEY (`IdRec`),
  KEY `IDX_Recuperacion_Usuario` (`ClvUsu`,`Utilizado`,`FechaExpiracion`),
  KEY `IDX_Recuperacion_Activo` (`ClvUsu`,`Utilizado`,`FechaExpiracion`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `redsocial`
--

DROP TABLE IF EXISTS `redsocial`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `redsocial` (
  `ClvRed` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `TipoRed` enum('Facebook','Instagram','WhatsApp','TikTok','Página Web') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `URLRed` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ClvCons` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`ClvRed`),
  KEY `FK_REDSOCIAL_CONSULTORIO` (`ClvCons`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `seguimiento_sesion`
--

DROP TABLE IF EXISTS `seguimiento_sesion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `seguimiento_sesion` (
  `ClvSeg` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `NumeroSesion` int NOT NULL,
  `FechaRegistroSeg` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `HoraInicioReal` time DEFAULT NULL,
  `HoraFinReal` time DEFAULT NULL,
  `ObjetivoSesion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `TemaAbordado` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `DesarrolloSesion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `TecnicasAplicadas` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `RespuestaPaciente` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `EstadoEmocional` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ObservacionesSeg` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `AcuerdosSeg` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `TareasAsignadas` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `RecomendacionesSeg` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `ProximaAccion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `EstatusSeg` enum('BORRADOR','FINALIZADO','CORREGIDO','ANULADO') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'BORRADOR',
  `ClvHist` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `ClvCita` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `ClvPsi` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`ClvSeg`),
  UNIQUE KEY `UK_Seguimiento_Cita` (`ClvCita`),
  UNIQUE KEY `UK_Seguimiento_NumeroSesion` (`ClvHist`,`NumeroSesion`),
  KEY `IDX_Seguimiento_Psicologo` (`ClvPsi`),
  KEY `IDX_Seguimiento_Fecha` (`FechaRegistroSeg`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `servicios`
--

DROP TABLE IF EXISTS `servicios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `servicios` (
  `ClvServ` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `NombreServicio` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Descripcion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ClvCons` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `DuracionMinutos` int NOT NULL DEFAULT '60',
  `CostoServicio` decimal(10,2) NOT NULL DEFAULT '0.00',
  `EstatusServicio` enum('ACTIVO','INACTIVO') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVO',
  PRIMARY KEY (`ClvServ`),
  UNIQUE KEY `UK_Servicio_Consultorio` (`ClvCons`,`NombreServicio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `usuario`
--

DROP TABLE IF EXISTS `usuario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuario` (
  `ClvUsu` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `CorreoUsu` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `TelefonoUsu` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ContrasenaUsu` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `EstadoUsu` tinyint(1) NOT NULL DEFAULT '1',
  `RequiereCambioContrasena` tinyint(1) NOT NULL DEFAULT '1',
  `RolUsu` enum('ADMINISTRADOR','CONSULTORIO','PSICOLOGO','PACIENTE') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ClvPer` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`ClvUsu`),
  UNIQUE KEY `CorreoUsu` (`CorreoUsu`),
  UNIQUE KEY `ClvPer` (`ClvPer`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vida_social_laboral`
--

DROP TABLE IF EXISTS `vida_social_laboral`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vida_social_laboral` (
  `ClvVidaSocial` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `CantidadAmigos` enum('MUCHOS','POCOS','NINGUNO') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `TipoGrupoSocial` enum('DISOCIAL','MIXTO','SANO','SIN_GRUPO') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `EstabilidadLaboral` enum('ESTABLE','INESTABLE','NO_APLICA') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `SatisfaccionLaboral` enum('SATISFECHO','PARCIALMENTE_SATISFECHO','INSATISFECHO','NO_APLICA') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `AdaptacionLaboral` enum('ADECUADA','REGULAR','INADECUADA','NO_APLICA') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `SituacionLaboral` enum('REALIZADO','FRUSTRADO','DESEMPLEADO','DESPEDIDO','SANCIONADO','REUBICADO','REINGRESADO','NO_APLICA','OTRO') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ManejoDineroAdecuado` tinyint(1) DEFAULT NULL,
  `ActividadesTiempoLibre` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ObservacionesVidaSocial` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ClvHist` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`ClvVidaSocial`),
  UNIQUE KEY `UK_VidaSocial_Historial` (`ClvHist`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping routines for database 'consultorio_psicologico'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-02 11:53:33


-- =============================================================================
-- Datos de demostración PsicoMatch (Hostinger)
-- Sin pacientes, expedientes, tokens, recuperaciones ni SMTP en tablas.
-- Las contraseñas NO son operativas: usa scripts/set_admin_password.php
-- (y equivalentes) tras importar.
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM activacion_cuenta;
DELETE FROM recuperacion_password;
DELETE FROM incidencia_acceso;
DELETE FROM notificacion;
DELETE FROM cita;
DELETE FROM psicologo_servicio;
DELETE FROM disponibilidad_psicologo;
DELETE FROM horario;
DELETE FROM horario_consultorio;
DELETE FROM servicios;
DELETE FROM redsocial;
DELETE FROM consultorio_usuario;
DELETE FROM psicologo;
DELETE FROM paciente;
DELETE FROM consultorio;
DELETE FROM usuario;
DELETE FROM persona;
DELETE FROM direccion;
DELETE FROM caracteristica;
DELETE FROM consecutivos;

INSERT INTO direccion (
  ClvDir, PaisDir, EstadoDir, MunicipioDir, ColoniaDir, CalleDir,
  CodPostDir, NumExtDir, NumIntDir, LatitudDir, LongitudDir, ReferenciaDir
) VALUES (
  'DIR001', 'México', 'Estado de México', 'Toluca', 'Centro',
  'Av. Independencia', '50000', '100', NULL, NULL, NULL,
  'Consultorio de demostración'
);

INSERT INTO persona (
  ClvPer, NombrePer, ApPatPer, ApMatPer, FechaNacimiento, GeneroPer, FotoPerfilPer, ClvDir
) VALUES
('PER001', 'Administrador', 'PsicoMatch', 'Demo', '1990-01-01', 'Otro', NULL, NULL),
('PER002', 'Responsable', 'Demo', 'Consultorio', '1985-06-15', 'Femenino', NULL, NULL),
('PER003', 'Especialista', 'Demo', 'Clínico', '1988-03-20', 'Masculino', NULL, NULL);

INSERT INTO usuario (
  ClvUsu, CorreoUsu, TelefonoUsu, ContrasenaUsu, EstadoUsu,
  RequiereCambioContrasena, RolUsu, ClvPer
) VALUES
('U001', 'administrador@consultoriospsicologicospsicomatch.com', '7220000001',
  '$2y$10$ne5ENBQHX5Qkzg24nDf6BeZ05FMZhdgXxxZ5mbzZYSLVB2jSOKOLq', 1, 1, 'ADMINISTRADOR', 'PER001'),
('UC001', 'responsable.demo@consultoriospsicologicospsicomatch.com', '7220000002',
  '$2y$10$8QUHlmyaigq91Sdif7EqUe0V/G7aurX.3N.uGM8FMnWBHfzDm3WRC', 1, 1, 'CONSULTORIO', 'PER002'),
('US001', 'especialista.demo@consultoriospsicologicospsicomatch.com', '7220000003',
  '$2y$10$c.FW1VzkfoS3I8NCEXsHx.5ccbGV.NesBlc51E/Myx0fmPv4vGkyW', 1, 1, 'PSICOLOGO', 'PER003');

INSERT INTO consultorio (
  ClvCons, NombreCons, LogotipoCons, FaviconCons, ImagenPortada, Slogan, Descripcion,
  TelefonoCons, CorreoElectronico, LimiteCancHoras, ClvDir,
  ServidorSMTP, PuertoSMTP, SeguridadSMTP, CorreoSMTP, PasswordSMTP, NombreRemitente,
  EstatusCons, PublicadoCons, FechaPublicacionCons, FechaRegistroCons
) VALUES (
  'CON001',
  'Consultorio Demo PsicoMatch',
  NULL, NULL, NULL,
  'Bienestar emocional con acompañamiento profesional',
  'Consultorio de demostración para validar la portada, la página pública y el flujo operativo de PsicoMatch.',
  '7220000010',
  'contacto.demo@consultoriospsicologicospsicomatch.com',
  24,
  'DIR001',
  NULL, NULL, NULL, NULL, NULL, NULL,
  'ACTIVO',
  1,
  NOW(),
  NOW()
);

INSERT INTO consultorio_usuario (
  ClvConsUsu, ClvCons, ClvUsu, EsResponsable, EstatusConsUsu, FechaAsignacion
) VALUES (
  'CU001', 'CON001', 'UC001', 1, 'ACTIVO', NOW()
);

INSERT INTO psicologo (
  ClvPsi, CedulaProfesional, EspecialidadPsi, DescripcionProfesional,
  EstatusPsi, MostrarEnPagina, FechaRegistroPsi, ClvUsu, ClvCons
) VALUES (
  'PSI001', 'DEMO-1001', 'Psicología clínica',
  'Especialista de demostración. Perfil visible en la página pública del consultorio demo.',
  'ACTIVO', 1, NOW(), 'US001', 'CON001'
);

INSERT INTO servicios (
  ClvServ, NombreServicio, Descripcion, ClvCons, DuracionMinutos, CostoServicio, EstatusServicio
) VALUES (
  'SER001', 'Terapia individual',
  'Sesión individual de acompañamiento psicológico (demostración).',
  'CON001', 50, 600.00, 'ACTIVO'
);

INSERT INTO psicologo_servicio (
  ClvPsiServ, ClvPsi, ClvServ, PrecioServicio, DuracionMinutos,
  DescripcionServicio, EstatusAsignacion, FechaAsignacion
) VALUES (
  'PS001', 'PSI001', 'SER001', 600.00, 50,
  'Sesión individual de demostración.', 'ACTIVA', NOW()
);

INSERT INTO horario_consultorio (
  ClvHorarioCons, DiaSemana, HoraInicio, HoraFin, EstatusHorario, ClvCons
) VALUES
('HCO001', 'LUNES', '09:00:00', '18:00:00', 'ACTIVO', 'CON001'),
('HCO002', 'MARTES', '09:00:00', '18:00:00', 'ACTIVO', 'CON001'),
('HCO003', 'MIERCOLES', '09:00:00', '18:00:00', 'ACTIVO', 'CON001'),
('HCO004', 'JUEVES', '09:00:00', '18:00:00', 'ACTIVO', 'CON001'),
('HCO005', 'VIERNES', '09:00:00', '18:00:00', 'ACTIVO', 'CON001'),
('HCO006', 'SABADO', '09:00:00', '14:00:00', 'INACTIVO', 'CON001'),
('HCO007', 'DOMINGO', '09:00:00', '14:00:00', 'INACTIVO', 'CON001');

INSERT INTO caracteristica (
  ClvCar, Titulo, Descripcion, Icono, OrdenCar, EstadoCar, ClvCons
) VALUES
('CAR001', 'Atención personalizada', 'Cada tratamiento se adapta a las necesidades del paciente.', 'bi-heart', 1, 1, 'CON001'),
('CAR002', 'Confidencialidad', 'Tu información siempre estará protegida.', 'bi-shield-lock', 2, 1, 'CON001'),
('CAR003', 'Citas programadas', 'Agenda tus sesiones de forma sencilla y organizada.', 'bi-calendar-check', 3, 1, 'CON001');

INSERT INTO consecutivos (NombreTabla, UltimoNumero) VALUES
('CONSULTORIO', 1),
('DIRECCION', 1),
('HORARIO', 0),
('HORARIO_CONSULTORIO', 7),
('PACIENTE', 0),
('PERSONA', 3),
('REDSOCIAL', 0),
('SERVICIO', 1),
('USUARIO', 1),
('CONSULTORIO_USUARIO', 1),
('PSICOLOGO', 1);

SET FOREIGN_KEY_CHECKS = 1;
