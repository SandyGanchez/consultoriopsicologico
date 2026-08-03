-- MySQL dump 10.13  Distrib 8.0.44, for Win64 (x86_64)
--
-- Host: localhost    Database: consultorio_psicologico
-- ------------------------------------------------------
-- Server version	8.4.8

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
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
  `ClvActitud` varchar(10) COLLATE utf8mb4_general_ci NOT NULL,
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
  `Otros` text COLLATE utf8mb4_general_ci,
  `ClvHist` varchar(10) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`ClvActitud`),
  UNIQUE KEY `UK_Actitud_Historial` (`ClvHist`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `actitud_conducta_inicial`
--

LOCK TABLES `actitud_conducta_inicial` WRITE;
/*!40000 ALTER TABLE `actitud_conducta_inicial` DISABLE KEYS */;
/*!40000 ALTER TABLE `actitud_conducta_inicial` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `adiccion`
--

DROP TABLE IF EXISTS `adiccion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `adiccion` (
  `ClvAdiccion` varchar(10) COLLATE utf8mb4_general_ci NOT NULL,
  `TipoAdiccion` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `EdadInicio` int DEFAULT NULL,
  `Frecuencia` enum('FRECUENTE','POCO_FRECUENTE','OCASIONAL','NO_ESPECIFICADA') COLLATE utf8mb4_general_ci DEFAULT NULL,
  `EstadoConsumo` enum('CONTROLADO','DESCONTROLADO','EN_ABSTINENCIA','EN_TRATAMIENTO','NO_ESPECIFICADO') COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ConflictosAsociados` text COLLATE utf8mb4_general_ci,
  `TratamientoRecibido` tinyint(1) NOT NULL DEFAULT '0',
  `DescripcionTratamiento` text COLLATE utf8mb4_general_ci,
  `ObservacionesAdiccion` text COLLATE utf8mb4_general_ci,
  `ClvHist` varchar(10) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`ClvAdiccion`),
  KEY `IDX_Adiccion_Historial` (`ClvHist`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `adiccion`
--

LOCK TABLES `adiccion` WRITE;
/*!40000 ALTER TABLE `adiccion` DISABLE KEYS */;
/*!40000 ALTER TABLE `adiccion` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `antecedente_familiar`
--

DROP TABLE IF EXISTS `antecedente_familiar`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `antecedente_familiar` (
  `ClvAntFam` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `TipoAntecedenteFam` enum('ALTERACION_PERSONALIDAD','DROGADICCION','ALCOHOLISMO','PSICOSIS','NEUROSIS','TRASTORNO_CONVULSIVO','PSICOPATIA','OTRO') COLLATE utf8mb4_unicode_ci NOT NULL,
  `PresentaAntecedenteFam` tinyint(1) NOT NULL DEFAULT '0',
  `FamiliarRelacionado` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `DescripcionAntFam` text COLLATE utf8mb4_unicode_ci,
  `ClvHist` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`ClvAntFam`),
  UNIQUE KEY `UK_AntFam_Historial_Tipo` (`ClvHist`,`TipoAntecedenteFam`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `antecedente_familiar`
--

LOCK TABLES `antecedente_familiar` WRITE;
/*!40000 ALTER TABLE `antecedente_familiar` DISABLE KEYS */;
/*!40000 ALTER TABLE `antecedente_familiar` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `antecedente_patologico`
--

DROP TABLE IF EXISTS `antecedente_patologico`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `antecedente_patologico` (
  `ClvAntPat` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `TipoAntecedente` enum('CARDIOVASCULAR','PULMONAR','RENAL','GASTROINTESTINAL','HEMATOLOGICO','ENDOCRINO','MENTAL','DERMATOLOGICO','NEUROLOGICO','METABOLICO','MARCAPASOS','CARDIOPATIA','NEUROPATIA','IMPLANTE_DENTAL','CANCER','CONVULSIONES','ENFERMEDAD_INFANCIA','OTRO') COLLATE utf8mb4_unicode_ci NOT NULL,
  `PresentaAntecedente` tinyint(1) NOT NULL DEFAULT '0',
  `DescripcionAntecedente` text COLLATE utf8mb4_unicode_ci,
  `TratamientoActual` text COLLATE utf8mb4_unicode_ci,
  `ClvHist` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`ClvAntPat`),
  UNIQUE KEY `UK_AntPat_Historial_Tipo` (`ClvHist`,`TipoAntecedente`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `antecedente_patologico`
--

LOCK TABLES `antecedente_patologico` WRITE;
/*!40000 ALTER TABLE `antecedente_patologico` DISABLE KEYS */;
/*!40000 ALTER TABLE `antecedente_patologico` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `apreciacion_diagnostica`
--

DROP TABLE IF EXISTS `apreciacion_diagnostica`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `apreciacion_diagnostica` (
  `ClvDiag` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `FechaDiagnostico` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ApreciacionPersonalidad` text COLLATE utf8mb4_unicode_ci,
  `DiagnosticoInicial` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `CodigoDiagnostico` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `SistemaClasificacion` enum('DSM5','CIE10','CIE11','OTRO') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `PlanTratamiento` text COLLATE utf8mb4_unicode_ci,
  `RecomendacionesIniciales` text COLLATE utf8mb4_unicode_ci,
  `PronosticoInicial` text COLLATE utf8mb4_unicode_ci,
  `ObservacionesDiagnosticas` text COLLATE utf8mb4_unicode_ci,
  `EstatusDiagnostico` enum('VIGENTE','MODIFICADO','DESCARTADO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'VIGENTE',
  `ClvHist` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ClvPsi` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`ClvDiag`),
  KEY `IDX_Apreciacion_Historial` (`ClvHist`),
  KEY `IDX_Apreciacion_Psicologo` (`ClvPsi`),
  KEY `IDX_Apreciacion_Fecha` (`FechaDiagnostico`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `apreciacion_diagnostica`
--

LOCK TABLES `apreciacion_diagnostica` WRITE;
/*!40000 ALTER TABLE `apreciacion_diagnostica` DISABLE KEYS */;
/*!40000 ALTER TABLE `apreciacion_diagnostica` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `caracteristica`
--

DROP TABLE IF EXISTS `caracteristica`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `caracteristica` (
  `ClvCar` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Titulo` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Descripcion` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Icono` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `OrdenCar` int NOT NULL DEFAULT '1',
  `EstadoCar` tinyint(1) NOT NULL DEFAULT '1',
  `ClvCons` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`ClvCar`),
  KEY `FK_Caracteristica_Consultorio` (`ClvCons`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `caracteristica`
--

LOCK TABLES `caracteristica` WRITE;
/*!40000 ALTER TABLE `caracteristica` DISABLE KEYS */;
INSERT INTO `caracteristica` VALUES ('CAR001','Atención Personalizada','Cada tratamiento se adapta a las necesidades del paciente.','bi-person-heart',1,1,'CON001'),('CAR002','Confidencialidad','Tu información siempre estará protegida.','bi-shield-lock',2,1,'CON001'),('CAR003','Citas Programadas','Agenda tus sesiones de forma sencilla y organizada.','bi-calendar-check',3,1,'CON001');
/*!40000 ALTER TABLE `caracteristica` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cita`
--

DROP TABLE IF EXISTS `cita`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cita` (
  `ClvCita` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `FechaCita` date NOT NULL,
  `HraInicioCita` time NOT NULL,
  `HraFinCita` time DEFAULT NULL,
  `DuracionAplicadaMin` int DEFAULT NULL,
  `CostoAplicado` decimal(10,2) DEFAULT NULL,
  `EstadoCita` enum('PROGRAMADA','ASISTIDA','CANCELADA','INASISTENCIA') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PROGRAMADA',
  `NotasCita` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `MotivoCancelacion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `FechaRegistroCita` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `FechaCancelacion` datetime DEFAULT NULL,
  `ClvPac` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ClvPsi` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ClvCons` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ClvServ` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`ClvCita`),
  KEY `FK_Cita_Paciente` (`ClvPac`),
  KEY `FK_Cita_Consultorio` (`ClvCons`),
  KEY `FK_Cita_Servicio` (`ClvServ`),
  KEY `FK_Cita_Psicologo` (`ClvPsi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cita`
--

LOCK TABLES `cita` WRITE;
/*!40000 ALTER TABLE `cita` DISABLE KEYS */;
INSERT INTO `cita` VALUES ('CIT001','2026-07-16','10:00:00','11:00:00',60,500.00,'CANCELADA','Cita de prueba para la agenda','El paciente no podrá asistir','2026-07-16 23:10:03','2026-07-16 23:11:24','PAC001','PSI001','CON001','SER001'),('CIT002','2026-07-30','11:00:00','12:00:00',60,0.00,'PROGRAMADA',NULL,NULL,'2026-07-27 23:22:35',NULL,'PAC004','PSI001','CON001','SER003');
/*!40000 ALTER TABLE `cita` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `consecutivos`
--

DROP TABLE IF EXISTS `consecutivos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `consecutivos` (
  `NombreTabla` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `UltimoNumero` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`NombreTabla`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `consecutivos`
--

LOCK TABLES `consecutivos` WRITE;
/*!40000 ALTER TABLE `consecutivos` DISABLE KEYS */;
INSERT INTO `consecutivos` VALUES ('CONSULTORIO',1),('DIRECCION',0),('HORARIO',5),('HORARIO_CONSULTORIO',0),('PACIENTE',0),('PERSONA',0),('REDSOCIAL',3),('SERVICIO',4),('USUARIO',0);
/*!40000 ALTER TABLE `consecutivos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `consultorio`
--

DROP TABLE IF EXISTS `consultorio`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `consultorio` (
  `ClvCons` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `NombreCons` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `LogotipoCons` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `FaviconCons` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ImagenPortada` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Slogan` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Descripcion` text COLLATE utf8mb4_unicode_ci,
  `TelefonoCons` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `CorreoElectronico` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `LimiteCancHoras` int NOT NULL DEFAULT '24',
  `ClvDir` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ServidorSMTP` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `PuertoSMTP` int DEFAULT NULL,
  `SeguridadSMTP` enum('ssl','tls') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `CorreoSMTP` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `PasswordSMTP` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `NombreRemitente` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `EstatusCons` enum('ACTIVO','INACTIVO','BLOQUEADO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVO',
  `FechaRegistroCons` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ClvCons`),
  KEY `FK_CONSULTORIO_DIRECCION` (`ClvDir`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `consultorio`
--

LOCK TABLES `consultorio` WRITE;
/*!40000 ALTER TABLE `consultorio` DISABLE KEYS */;
INSERT INTO `consultorio` VALUES ('CON001','Consultorio Psicológico Integral','logo.png',NULL,NULL,'Cuidando tu bienestar emocional','Brindamos atención psicológica profesional para niños, adolescentes y adultos.','7221234567','consultorio@gmail.com',24,'DIR001',NULL,NULL,NULL,NULL,NULL,NULL,'ACTIVO','2026-07-16 21:27:29');
/*!40000 ALTER TABLE `consultorio` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `consultorio_usuario`
--

DROP TABLE IF EXISTS `consultorio_usuario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `consultorio_usuario` (
  `ClvConsUsu` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ClvCons` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ClvUsu` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `EsResponsable` tinyint(1) NOT NULL DEFAULT '1',
  `EstatusConsUsu` enum('ACTIVO','INACTIVO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVO',
  `FechaAsignacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ClvConsUsu`),
  UNIQUE KEY `UK_ConsultorioUsuario` (`ClvCons`,`ClvUsu`),
  KEY `FK_ConsultorioUsuario_Usuario` (`ClvUsu`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `consultorio_usuario`
--

LOCK TABLES `consultorio_usuario` WRITE;
/*!40000 ALTER TABLE `consultorio_usuario` DISABLE KEYS */;
INSERT INTO `consultorio_usuario` VALUES ('CU001','CON001','UC001',1,'ACTIVO','2026-07-16 21:41:49');
/*!40000 ALTER TABLE `consultorio_usuario` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `diagnostico_seguimiento`
--

DROP TABLE IF EXISTS `diagnostico_seguimiento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `diagnostico_seguimiento` (
  `ClvDiagSeg` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `FechaDiagSeg` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `TipoCambioDiag` enum('SE_MANTIENE','MODIFICADO','DESCARTADO','NUEVO') COLLATE utf8mb4_unicode_ci NOT NULL,
  `DiagnosticoAnterior` text COLLATE utf8mb4_unicode_ci,
  `DiagnosticoActual` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `CodigoDiagnostico` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `SistemaClasificacion` enum('DSM5','CIE10','CIE11','OTRO') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `JustificacionCambio` text COLLATE utf8mb4_unicode_ci,
  `ClvSeg` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ClvPsi` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`ClvDiagSeg`),
  KEY `IDX_DiagSeg_Seguimiento` (`ClvSeg`),
  KEY `IDX_DiagSeg_Psicologo` (`ClvPsi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `diagnostico_seguimiento`
--

LOCK TABLES `diagnostico_seguimiento` WRITE;
/*!40000 ALTER TABLE `diagnostico_seguimiento` DISABLE KEYS */;
/*!40000 ALTER TABLE `diagnostico_seguimiento` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `direccion`
--

DROP TABLE IF EXISTS `direccion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `direccion` (
  `ClvDir` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `PaisDir` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `EstadoDir` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `MunicipioDir` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ColoniaDir` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `CalleDir` varchar(70) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `CodPostDir` char(5) COLLATE utf8mb4_unicode_ci NOT NULL,
  `NumExtDir` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `NumIntDir` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `LatitudDir` decimal(10,8) DEFAULT NULL,
  `LongitudDir` decimal(11,8) DEFAULT NULL,
  `ReferenciaDir` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`ClvDir`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `direccion`
--

LOCK TABLES `direccion` WRITE;
/*!40000 ALTER TABLE `direccion` DISABLE KEYS */;
INSERT INTO `direccion` VALUES ('DIR001','México','Estado de México','Tejupilco','Centro','Av. Benito Juárez','51400','120',NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `direccion` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `disponibilidad_psicologo`
--

DROP TABLE IF EXISTS `disponibilidad_psicologo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `disponibilidad_psicologo` (
  `ClvDisponibilidad` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `DiaSemana` enum('LUNES','MARTES','MIERCOLES','JUEVES','VIERNES','SABADO','DOMINGO') COLLATE utf8mb4_unicode_ci NOT NULL,
  `HoraInicio` time NOT NULL,
  `HoraFin` time NOT NULL,
  `EstatusDisponibilidad` enum('ACTIVA','INACTIVA') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVA',
  `FechaRegistroDisponibilidad` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ClvPsi` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`ClvDisponibilidad`),
  UNIQUE KEY `UK_Disponibilidad_Psicologo` (`ClvPsi`,`DiaSemana`,`HoraInicio`,`HoraFin`),
  CONSTRAINT `FK_Disponibilidad_Psicologo` FOREIGN KEY (`ClvPsi`) REFERENCES `psicologo` (`ClvPsi`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `disponibilidad_psicologo`
--

LOCK TABLES `disponibilidad_psicologo` WRITE;
/*!40000 ALTER TABLE `disponibilidad_psicologo` DISABLE KEYS */;
/*!40000 ALTER TABLE `disponibilidad_psicologo` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `estado_psicologico_inicial`
--

DROP TABLE IF EXISTS `estado_psicologico_inicial`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `estado_psicologico_inicial` (
  `ClvEstadoInicial` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `MotivoConsulta` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `SintomasReferidos` text COLLATE utf8mb4_unicode_ci,
  `Ansiedad` tinyint(1) NOT NULL DEFAULT '0',
  `Angustia` tinyint(1) NOT NULL DEFAULT '0',
  `AutoestimaBaja` tinyint(1) NOT NULL DEFAULT '0',
  `Indiferencia` tinyint(1) NOT NULL DEFAULT '0',
  `Confusion` tinyint(1) NOT NULL DEFAULT '0',
  `Descontrol` tinyint(1) NOT NULL DEFAULT '0',
  `Desorientacion` tinyint(1) NOT NULL DEFAULT '0',
  `Incoherencia` tinyint(1) NOT NULL DEFAULT '0',
  `Sobrevaloracion` tinyint(1) NOT NULL DEFAULT '0',
  `OtrosEstados` text COLLATE utf8mb4_unicode_ci,
  `ObservacionesIniciales` text COLLATE utf8mb4_unicode_ci,
  `ClvHist` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`ClvEstadoInicial`),
  UNIQUE KEY `UK_EstadoInicial_Historial` (`ClvHist`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `estado_psicologico_inicial`
--

LOCK TABLES `estado_psicologico_inicial` WRITE;
/*!40000 ALTER TABLE `estado_psicologico_inicial` DISABLE KEYS */;
/*!40000 ALTER TABLE `estado_psicologico_inicial` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `evolucion_sesion`
--

DROP TABLE IF EXISTS `evolucion_sesion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `evolucion_sesion` (
  `ClvEvolucion` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `AvancesSeg` text COLLATE utf8mb4_unicode_ci,
  `DificultadesSeg` text COLLATE utf8mb4_unicode_ci,
  `RetrocesosSeg` text COLLATE utf8mb4_unicode_ci,
  `CumplimientoTareas` enum('COMPLETO','PARCIAL','NO_REALIZADO','NO_APLICA') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `CambiosConductuales` text COLLATE utf8mb4_unicode_ci,
  `CambiosEmocionales` text COLLATE utf8mb4_unicode_ci,
  `FactoresRiesgo` text COLLATE utf8mb4_unicode_ci,
  `FactoresProtectores` text COLLATE utf8mb4_unicode_ci,
  `PronosticoActual` text COLLATE utf8mb4_unicode_ci,
  `ClvSeg` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`ClvEvolucion`),
  UNIQUE KEY `UK_Evolucion_Seguimiento` (`ClvSeg`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `evolucion_sesion`
--

LOCK TABLES `evolucion_sesion` WRITE;
/*!40000 ALTER TABLE `evolucion_sesion` DISABLE KEYS */;
/*!40000 ALTER TABLE `evolucion_sesion` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `examen_mental_inicial`
--

DROP TABLE IF EXISTS `examen_mental_inicial`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `examen_mental_inicial` (
  `ClvExamenMental` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Conciencia` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Orientacion` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Inteligencia` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Pensamiento` text COLLATE utf8mb4_unicode_ci,
  `Afectividad` text COLLATE utf8mb4_unicode_ci,
  `Atencion` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Memoria` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Sensopercepcion` text COLLATE utf8mb4_unicode_ci,
  `Psicomotricidad` text COLLATE utf8mb4_unicode_ci,
  `Habitos` text COLLATE utf8mb4_unicode_ci,
  `InstintosConservados` tinyint(1) DEFAULT NULL,
  `Lenguaje` text COLLATE utf8mb4_unicode_ci,
  `ObservacionesExamen` text COLLATE utf8mb4_unicode_ci,
  `ClvHist` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`ClvExamenMental`),
  UNIQUE KEY `UK_ExamenMental_Historial` (`ClvHist`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `examen_mental_inicial`
--

LOCK TABLES `examen_mental_inicial` WRITE;
/*!40000 ALTER TABLE `examen_mental_inicial` DISABLE KEYS */;
/*!40000 ALTER TABLE `examen_mental_inicial` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `historial_clinico`
--

DROP TABLE IF EXISTS `historial_clinico`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `historial_clinico` (
  `ClvHist` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `NumeroExpediente` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `FechaAperturaHist` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `FechaEntrevistaInicial` datetime DEFAULT NULL,
  `FechaActualizacionHist` datetime DEFAULT NULL,
  `EstatusHist` enum('ACTIVO','INACTIVO','CERRADO','ARCHIVADO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVO',
  `ClvPac` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ClvPsi` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ClvCons` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`ClvHist`),
  UNIQUE KEY `UK_Historial_NumeroExpediente` (`NumeroExpediente`),
  UNIQUE KEY `UK_Historial_Paciente_Consultorio` (`ClvPac`,`ClvCons`),
  KEY `IDX_Historial_Psicologo` (`ClvPsi`),
  KEY `IDX_Historial_Consultorio` (`ClvCons`),
  KEY `IDX_Historial_Estatus` (`EstatusHist`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `historial_clinico`
--

LOCK TABLES `historial_clinico` WRITE;
/*!40000 ALTER TABLE `historial_clinico` DISABLE KEYS */;
/*!40000 ALTER TABLE `historial_clinico` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `horario`
--

DROP TABLE IF EXISTS `horario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `horario` (
  `ClvHorario` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ClvPsi` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `DiaSemana` enum('LUNES','MARTES','MIERCOLES','JUEVES','VIERNES','SABADO','DOMINGO') COLLATE utf8mb4_unicode_ci NOT NULL,
  `HoraInicio` time NOT NULL,
  `HoraFin` time NOT NULL,
  `EstatusHorario` enum('ACTIVO','INACTIVO') COLLATE utf8mb4_unicode_ci DEFAULT 'ACTIVO',
  PRIMARY KEY (`ClvHorario`),
  KEY `fk_horario_psicologo` (`ClvPsi`),
  CONSTRAINT `FK_Horario_Psicologo` FOREIGN KEY (`ClvPsi`) REFERENCES `psicologo` (`ClvPsi`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `horario`
--

LOCK TABLES `horario` WRITE;
/*!40000 ALTER TABLE `horario` DISABLE KEYS */;
INSERT INTO `horario` VALUES ('HOR001','PSI001','LUNES','09:00:00','18:00:00','ACTIVO'),('HOR002','PSI001','MARTES','09:00:00','18:00:00','ACTIVO'),('HOR003','PSI001','MIERCOLES','09:00:00','18:00:00','ACTIVO'),('HOR004','PSI001','JUEVES','09:00:00','18:00:00','ACTIVO'),('HOR005','PSI001','VIERNES','09:00:00','18:00:00','ACTIVO'),('HOR006','PSI002','LUNES','10:00:00','17:00:00','ACTIVO'),('HOR007','PSI002','MARTES','10:00:00','17:00:00','ACTIVO'),('HOR008','PSI002','MIERCOLES','10:00:00','17:00:00','ACTIVO'),('HOR009','PSI002','JUEVES','10:00:00','17:00:00','ACTIVO'),('HOR010','PSI002','VIERNES','10:00:00','17:00:00','ACTIVO'),('HOR011','PSI003','LUNES','08:00:00','15:00:00','ACTIVO'),('HOR012','PSI003','MARTES','08:00:00','15:00:00','ACTIVO'),('HOR013','PSI003','MIERCOLES','08:00:00','15:00:00','ACTIVO'),('HOR014','PSI003','JUEVES','08:00:00','15:00:00','ACTIVO'),('HOR015','PSI003','VIERNES','08:00:00','15:00:00','ACTIVO');
/*!40000 ALTER TABLE `horario` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `horario_consultorio`
--

DROP TABLE IF EXISTS `horario_consultorio`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `horario_consultorio` (
  `ClvHorarioCons` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `DiaSemana` enum('LUNES','MARTES','MIERCOLES','JUEVES','VIERNES','SABADO','DOMINGO') COLLATE utf8mb4_unicode_ci NOT NULL,
  `HoraInicio` time NOT NULL,
  `HoraFin` time NOT NULL,
  `EstatusHorario` enum('ACTIVO','INACTIVO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVO',
  `ClvCons` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`ClvHorarioCons`),
  UNIQUE KEY `UK_HorarioConsultorio_Dia` (`ClvCons`,`DiaSemana`),
  CONSTRAINT `FK_HorarioConsultorio_Consultorio` FOREIGN KEY (`ClvCons`) REFERENCES `consultorio` (`ClvCons`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `horario_consultorio`
--

LOCK TABLES `horario_consultorio` WRITE;
/*!40000 ALTER TABLE `horario_consultorio` DISABLE KEYS */;
INSERT INTO `horario_consultorio` VALUES ('HCO001','LUNES','08:00:00','20:00:00','ACTIVO','CON001'),('HCO002','MARTES','08:00:00','20:00:00','ACTIVO','CON001'),('HCO003','MIERCOLES','08:00:00','20:00:00','ACTIVO','CON001'),('HCO004','JUEVES','08:00:00','20:00:00','ACTIVO','CON001'),('HCO005','VIERNES','08:00:00','20:00:00','ACTIVO','CON001'),('HCO006','SABADO','09:00:00','14:00:00','INACTIVO','CON001'),('HCO007','DOMINGO','09:00:00','14:00:00','INACTIVO','CON001');
/*!40000 ALTER TABLE `horario_consultorio` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `incidencia_acceso`
--

DROP TABLE IF EXISTS `incidencia_acceso`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `incidencia_acceso` (
  `ClvIncidencia` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `TipoIncidencia` enum('INTENTO_FALLIDO','CUENTA_BLOQUEADA','ACCESO_NO_AUTORIZADO','RECUPERACION_FALLIDA','SESION_EXPIRADA','OTRA') COLLATE utf8mb4_unicode_ci NOT NULL,
  `DescripcionIncidencia` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `FechaIncidencia` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `EstatusIncidencia` enum('PENDIENTE','EN_REVISION','RESUELTA','DESCARTADA') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDIENTE',
  `DireccionIP` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `AgenteUsuario` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ObservacionesAdmin` text COLLATE utf8mb4_unicode_ci,
  `FechaResolucion` datetime DEFAULT NULL,
  `ClvCons` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ClvUsu` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`ClvIncidencia`),
  KEY `IDX_Incidencia_Consultorio` (`ClvCons`),
  KEY `IDX_Incidencia_Usuario` (`ClvUsu`),
  KEY `IDX_Incidencia_Estatus` (`EstatusIncidencia`,`FechaIncidencia`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `incidencia_acceso`
--

LOCK TABLES `incidencia_acceso` WRITE;
/*!40000 ALTER TABLE `incidencia_acceso` DISABLE KEYS */;
/*!40000 ALTER TABLE `incidencia_acceso` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notificacion`
--

DROP TABLE IF EXISTS `notificacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notificacion` (
  `ClvNotif` varchar(10) COLLATE utf8mb4_general_ci NOT NULL,
  `TituloNotif` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `MensajeNotif` text COLLATE utf8mb4_general_ci NOT NULL,
  `TipoNotif` enum('CITA','CANCELACION','RECORDATORIO','CUENTA','PSICOLOGO','SISTEMA','OTRA') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'SISTEMA',
  `FechaNotif` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `LeidaNotif` tinyint(1) NOT NULL DEFAULT '0',
  `FechaLecturaNotif` datetime DEFAULT NULL,
  `ClvUsu` varchar(10) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`ClvNotif`),
  KEY `IDX_Notificacion_Usuario` (`ClvUsu`,`LeidaNotif`,`FechaNotif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notificacion`
--

LOCK TABLES `notificacion` WRITE;
/*!40000 ALTER TABLE `notificacion` DISABLE KEYS */;
/*!40000 ALTER TABLE `notificacion` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `paciente`
--

DROP TABLE IF EXISTS `paciente`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `paciente` (
  `ClvPac` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `FotoPerfilPac` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT 'default.png',
  `FechaRegistroPac` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `EstadoActivoPac` tinyint(1) NOT NULL DEFAULT '1',
  `ClvUsu` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ClvCons` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`ClvPac`),
  KEY `FK_PACIENTE_USUARIO` (`ClvUsu`),
  KEY `FK_Paciente_Consultorio` (`ClvCons`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `paciente`
--

LOCK TABLES `paciente` WRITE;
/*!40000 ALTER TABLE `paciente` DISABLE KEYS */;
INSERT INTO `paciente` VALUES ('PAC001','perfil-default.png','2026-07-30 11:52:12',1,'U001',NULL),('PAC002','perfil-default.png','2026-07-30 11:52:12',1,'U002',NULL),('PAC003','perfil-default.png','2026-07-30 11:52:12',1,'U003',NULL),('PAC004','perfil-default.png','2026-07-30 11:52:12',1,'U004',NULL),('PAC005','perfil-default.png','2026-07-30 11:52:12',1,'U005',NULL),('PAC006','perfil-default.png','2026-07-30 11:52:12',1,'U006',NULL),('PAC007','perfil-default.png','2026-07-30 11:52:12',1,'U007',NULL),('PAC008','perfil-default.png','2026-07-30 11:52:12',1,'U008',NULL),('PAC009','perfil-default.png','2026-07-30 11:52:12',1,'U009',NULL);
/*!40000 ALTER TABLE `paciente` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `persona`
--

DROP TABLE IF EXISTS `persona`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `persona` (
  `ClvPer` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `NombrePer` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ApPatPer` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ApMatPer` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `FechaNacimiento` date NOT NULL,
  `GeneroPer` enum('Masculino','Femenino','Otro') COLLATE utf8mb4_unicode_ci NOT NULL,
  `FotoPerfilPer` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ClvDir` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`ClvPer`),
  KEY `FK_PERSONA_DIRECCION` (`ClvDir`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `persona`
--

LOCK TABLES `persona` WRITE;
/*!40000 ALTER TABLE `persona` DISABLE KEYS */;
INSERT INTO `persona` VALUES ('P001','Sandra','Sanchez','Garcia','2005-06-06','Femenino',NULL,'DIR001'),('P002','Sandra','Sanchez','Garcia','2005-06-06','Femenino',NULL,NULL),('P003','diana elena','sanchez','garcia','2010-03-17','Femenino',NULL,NULL),('P004','Lola','Garcia','Alvarez','1997-07-09','Femenino',NULL,NULL),('P005','Joselyn','Salgado','Carbajal','2005-12-03','Femenino',NULL,NULL),('P006','diana elena','sanchez','garcia','2005-12-20','Femenino',NULL,NULL),('P007','diana elena','sanchez','garcia','2005-12-20','Femenino',NULL,NULL),('P008','Maira','Popoca','Eugenio','2005-05-02','Femenino',NULL,NULL),('P009','Sandra','Sanchez','Garcia','2005-07-15','Femenino',NULL,NULL),('P010','Sandra','Sanchez','Garcia','2005-07-15','Femenino',NULL,NULL),('P011','Responsable','PsicoMatch','Consultorio','1990-01-01','Otro',NULL,NULL),('PER010','Yazmin','Sanchez','Garcia','1997-04-29','Femenino',NULL,NULL),('PER011','Yazmin','Sanchez','Garcia','1997-04-29','Femenino',NULL,NULL),('PER012','Yazmin','Sanchez','Garcia','1997-04-29','Femenino',NULL,NULL),('PER013','Yazmin','Sanchez','Garcia','1997-04-29','Femenino','perfil_09f2f4f136e69e8328e33aef.png',NULL),('PER014','Maira','Popoca','Eugenio','2005-05-02','Femenino',NULL,NULL);
/*!40000 ALTER TABLE `persona` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `psicoanamnesis_familiar`
--

DROP TABLE IF EXISTS `psicoanamnesis_familiar`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `psicoanamnesis_familiar` (
  `ClvPsicoFam` varchar(10) COLLATE utf8mb4_general_ci NOT NULL,
  `PadresJuntos` tinyint(1) DEFAULT NULL,
  `PadreFallecido` tinyint(1) NOT NULL DEFAULT '0',
  `MadreFallecida` tinyint(1) NOT NULL DEFAULT '0',
  `ConflictoPadre` tinyint(1) NOT NULL DEFAULT '0',
  `ConflictoMadre` tinyint(1) NOT NULL DEFAULT '0',
  `ConflictoOtrosFamiliares` text COLLATE utf8mb4_general_ci,
  `ActitudPadres` enum('AFECTUOSA','SOBREPROTECTORA','INDIFERENTE','HOSTIL','INEXISTENTE','OTRA') COLLATE utf8mb4_general_ci DEFAULT NULL,
  `NumeroHermanos` int DEFAULT NULL,
  `NumeroHermanosVarones` int DEFAULT NULL,
  `NumeroHermanasMujeres` int DEFAULT NULL,
  `RelacionHermanos` enum('AFECTUOSA','SOBREPROTECTORA','APATICA','AGRESIVA','INEXISTENTE','OTRA') COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ObservacionesFamiliares` text COLLATE utf8mb4_general_ci,
  `ClvHist` varchar(10) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`ClvPsicoFam`),
  UNIQUE KEY `UK_PsicoFam_Historial` (`ClvHist`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `psicoanamnesis_familiar`
--

LOCK TABLES `psicoanamnesis_familiar` WRITE;
/*!40000 ALTER TABLE `psicoanamnesis_familiar` DISABLE KEYS */;
/*!40000 ALTER TABLE `psicoanamnesis_familiar` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `psicologo`
--

DROP TABLE IF EXISTS `psicologo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `psicologo` (
  `ClvPsi` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `CedulaProfesional` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `EspecialidadPsi` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `DescripcionProfesional` text COLLATE utf8mb4_unicode_ci,
  `EstatusPsi` enum('ACTIVO','INACTIVO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVO',
  `MostrarEnPagina` tinyint(1) NOT NULL DEFAULT '1',
  `FechaRegistroPsi` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ClvUsu` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ClvCons` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`ClvPsi`),
  UNIQUE KEY `UK_Psicologo_Cedula` (`CedulaProfesional`),
  UNIQUE KEY `UK_Psicologo_Usuario` (`ClvUsu`),
  KEY `FK_Psicologo_Consultorio` (`ClvCons`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `psicologo`
--

LOCK TABLES `psicologo` WRITE;
/*!40000 ALTER TABLE `psicologo` DISABLE KEYS */;
INSERT INTO `psicologo` VALUES ('PSI001','CEDULA001','Psicología clínica','Especialista en atención psicológica integral.','ACTIVO',1,'2026-07-16 21:42:08','US001','CON001'),('PSI002','72458964','Psicología infantil','sd','ACTIVO',1,'2026-07-17 02:30:50','USU010','CON001'),('PSI003','724589648','Conductivo-conductual','jhbdmjh','ACTIVO',1,'2026-07-17 10:06:43','USU011','CON001');
/*!40000 ALTER TABLE `psicologo` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `psicologo_servicio`
--

DROP TABLE IF EXISTS `psicologo_servicio`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `psicologo_servicio` (
  `ClvPsiServ` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ClvPsi` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ClvServ` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `PrecioServicio` decimal(10,2) NOT NULL DEFAULT '0.00',
  `DuracionMinutos` int NOT NULL DEFAULT '60',
  `DescripcionServicio` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `EstatusAsignacion` enum('ACTIVA','INACTIVA') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVA',
  `FechaAsignacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ClvPsiServ`),
  UNIQUE KEY `UK_PsicologoServicio` (`ClvPsi`,`ClvServ`),
  KEY `FK_PsicologoServicio_Servicio` (`ClvServ`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `psicologo_servicio`
--

LOCK TABLES `psicologo_servicio` WRITE;
/*!40000 ALTER TABLE `psicologo_servicio` DISABLE KEYS */;
INSERT INTO `psicologo_servicio` VALUES ('PS001','PSI001','SER001',0.00,60,'','ACTIVA','2026-07-16 21:42:18'),('PS002','PSI001','SER002',0.00,60,'','ACTIVA','2026-07-16 21:42:18');
/*!40000 ALTER TABLE `psicologo_servicio` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reactivo_psicologico`
--

DROP TABLE IF EXISTS `reactivo_psicologico`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reactivo_psicologico` (
  `ClvReact` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `NombreReactivo` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `FechaAplicacion` date NOT NULL,
  `ResultadoReactivo` text COLLATE utf8mb4_unicode_ci,
  `InterpretacionReactivo` text COLLATE utf8mb4_unicode_ci,
  `ArchivoResultado` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ClvHist` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ClvPsi` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`ClvReact`),
  KEY `IDX_Reactivo_Historial` (`ClvHist`),
  KEY `IDX_Reactivo_Psicologo` (`ClvPsi`),
  KEY `IDX_Reactivo_Fecha` (`FechaAplicacion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reactivo_psicologico`
--

LOCK TABLES `reactivo_psicologico` WRITE;
/*!40000 ALTER TABLE `reactivo_psicologico` DISABLE KEYS */;
/*!40000 ALTER TABLE `reactivo_psicologico` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `recomendacion_sesion`
--

DROP TABLE IF EXISTS `recomendacion_sesion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `recomendacion_sesion` (
  `ClvRecSeg` varchar(10) COLLATE utf8mb4_general_ci NOT NULL,
  `TipoRecomendacion` enum('TAREA','EJERCICIO','LECTURA','HABITO','CANALIZACION','ESTUDIO_COMPLEMENTARIO','OTRA') COLLATE utf8mb4_general_ci NOT NULL,
  `DescripcionRec` text COLLATE utf8mb4_general_ci NOT NULL,
  `FechaLimite` date DEFAULT NULL,
  `Cumplida` tinyint(1) NOT NULL DEFAULT '0',
  `FechaCumplimiento` date DEFAULT NULL,
  `ClvSeg` varchar(10) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`ClvRecSeg`),
  KEY `IDX_Recomendacion_Seguimiento` (`ClvSeg`),
  KEY `IDX_Recomendacion_Cumplida` (`Cumplida`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `recomendacion_sesion`
--

LOCK TABLES `recomendacion_sesion` WRITE;
/*!40000 ALTER TABLE `recomendacion_sesion` DISABLE KEYS */;
/*!40000 ALTER TABLE `recomendacion_sesion` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `recuperacion_password`
--

DROP TABLE IF EXISTS `recuperacion_password`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `recuperacion_password` (
  `IdRec` int NOT NULL AUTO_INCREMENT,
  `ClvUsu` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `CodigoHash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `FechaCreacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `FechaExpiracion` datetime NOT NULL,
  `Utilizado` tinyint(1) NOT NULL DEFAULT '0',
  `Intentos` tinyint unsigned NOT NULL DEFAULT '0',
  `FechaUltimoIntento` datetime DEFAULT NULL,
  PRIMARY KEY (`IdRec`),
  KEY `IDX_Recuperacion_Usuario` (`ClvUsu`,`Utilizado`,`FechaExpiracion`),
  KEY `IDX_Recuperacion_Activo` (`ClvUsu`,`Utilizado`,`FechaExpiracion`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `recuperacion_password`
--

LOCK TABLES `recuperacion_password` WRITE;
/*!40000 ALTER TABLE `recuperacion_password` DISABLE KEYS */;
INSERT INTO `recuperacion_password` VALUES (12,'U008','$2y$10$QgNMuGZib8dIWRAhtLGOceMlTrkiyNfUN0/VY41T6cIpqGEwLXF6G','2026-07-16 02:01:31','2026-07-16 02:11:31',1,0,NULL),(13,'U008','$2y$10$NVjdqvlwzPZLY6Wrdw99h.Dd0hbcBfKSzEXVFcRMTSzPfXKjB1TMG','2026-07-16 02:03:35','2026-07-16 02:13:35',1,0,NULL),(14,'U008','$2y$10$XHHQbK3kETJEXSrLjRqoeO8KhGeoscibqrzxW3SBDt4GPGfgalO9q','2026-07-16 02:15:31','2026-07-16 02:25:31',1,0,NULL),(15,'U008','$2y$10$MnA44xOBMJELSJVytE8uzuXVnZcExOXKz/OAGejPVlw6HWVJ3FtQO','2026-07-16 02:23:03','2026-07-16 02:33:03',1,0,NULL),(16,'U008','$2y$10$ZQHLBSiPPp5hcmSG5zIQYeGZ2uMAFdspILGGtZ6Awx6rFrzIxVo0K','2026-07-16 02:24:11','2026-07-16 02:34:11',1,0,NULL),(17,'U001','$2y$10$TxZORS2GScTP1zLP0ARGv.VbMlJ3azJkXd1Ivjwpk0QyIfDhkWPOa','2026-07-16 02:27:29','2026-07-16 02:37:29',1,0,NULL),(18,'U008','$2y$10$iGpl3yuVcl9iAUvKquoHVeVSNhmywkU0CVYy54wq5ipNeURTgZM1a','2026-07-17 10:12:30','2026-07-17 10:22:30',1,0,NULL),(19,'USU011','$2y$10$GUEg3EL.PKoqVYb6HKXn..pMxUJDT3si9GTqH6XMvXLpIHyv/IBV2','2026-07-17 10:12:56','2026-07-17 10:22:56',1,0,NULL),(20,'U004','$2y$10$.kSqRWzKmICRynyyRzXSSeThSoA.Rwb51eKm5eQqIgm69o56EVZtO','2026-07-23 21:17:22','2026-07-23 21:27:22',1,0,NULL),(21,'U001','$2y$10$PnCryHpA9o6lUEDH7BTxUeGTR6oSgAHg1RRW9HW94NoPh/StKOTQe','2026-07-28 11:15:24','2026-07-28 11:25:24',0,0,NULL),(22,'U008','$2y$10$LHDkjCcUiNHkUGmkud.Je.H3InjeEVQexkFgfVcmR52L0aEHd5Dle','2026-07-28 11:18:47','2026-07-28 11:28:47',1,0,NULL);
/*!40000 ALTER TABLE `recuperacion_password` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `redsocial`
--

DROP TABLE IF EXISTS `redsocial`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `redsocial` (
  `ClvRed` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `TipoRed` enum('Facebook','Instagram','WhatsApp','TikTok','Página Web') COLLATE utf8mb4_unicode_ci NOT NULL,
  `URLRed` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ClvCons` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`ClvRed`),
  KEY `FK_REDSOCIAL_CONSULTORIO` (`ClvCons`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `redsocial`
--

LOCK TABLES `redsocial` WRITE;
/*!40000 ALTER TABLE `redsocial` DISABLE KEYS */;
INSERT INTO `redsocial` VALUES ('RED001','Facebook','https://facebook.com/consultorio','CON001'),('RED002','Instagram','https://instagram.com/consultorio','CON001'),('RED003','WhatsApp','https://wa.me/527221234567','CON001');
/*!40000 ALTER TABLE `redsocial` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `seguimiento_sesion`
--

DROP TABLE IF EXISTS `seguimiento_sesion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `seguimiento_sesion` (
  `ClvSeg` varchar(10) COLLATE utf8mb4_general_ci NOT NULL,
  `NumeroSesion` int NOT NULL,
  `FechaRegistroSeg` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `HoraInicioReal` time DEFAULT NULL,
  `HoraFinReal` time DEFAULT NULL,
  `ObjetivoSesion` text COLLATE utf8mb4_general_ci,
  `TemaAbordado` text COLLATE utf8mb4_general_ci,
  `DesarrolloSesion` text COLLATE utf8mb4_general_ci,
  `TecnicasAplicadas` text COLLATE utf8mb4_general_ci,
  `RespuestaPaciente` text COLLATE utf8mb4_general_ci,
  `EstadoEmocional` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ObservacionesSeg` text COLLATE utf8mb4_general_ci,
  `AcuerdosSeg` text COLLATE utf8mb4_general_ci,
  `TareasAsignadas` text COLLATE utf8mb4_general_ci,
  `RecomendacionesSeg` text COLLATE utf8mb4_general_ci,
  `ProximaAccion` text COLLATE utf8mb4_general_ci,
  `EstatusSeg` enum('BORRADOR','FINALIZADO','CORREGIDO','ANULADO') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'BORRADOR',
  `ClvHist` varchar(10) COLLATE utf8mb4_general_ci NOT NULL,
  `ClvCita` varchar(10) COLLATE utf8mb4_general_ci NOT NULL,
  `ClvPsi` varchar(10) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`ClvSeg`),
  UNIQUE KEY `UK_Seguimiento_Cita` (`ClvCita`),
  UNIQUE KEY `UK_Seguimiento_NumeroSesion` (`ClvHist`,`NumeroSesion`),
  KEY `IDX_Seguimiento_Psicologo` (`ClvPsi`),
  KEY `IDX_Seguimiento_Fecha` (`FechaRegistroSeg`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `seguimiento_sesion`
--

LOCK TABLES `seguimiento_sesion` WRITE;
/*!40000 ALTER TABLE `seguimiento_sesion` DISABLE KEYS */;
/*!40000 ALTER TABLE `seguimiento_sesion` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `servicios`
--

DROP TABLE IF EXISTS `servicios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `servicios` (
  `ClvServ` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `NombreServicio` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Descripcion` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ClvCons` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `DuracionMinutos` int NOT NULL DEFAULT '60',
  `CostoServicio` decimal(10,2) NOT NULL DEFAULT '0.00',
  `EstatusServicio` enum('ACTIVO','INACTIVO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVO',
  PRIMARY KEY (`ClvServ`),
  UNIQUE KEY `UK_Servicio_Consultorio` (`ClvCons`,`NombreServicio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `servicios`
--

LOCK TABLES `servicios` WRITE;
/*!40000 ALTER TABLE `servicios` DISABLE KEYS */;
INSERT INTO `servicios` VALUES ('SER001','Terapia Individual','Atención psicológica personalizada.','CON001',60,0.00,'ACTIVO'),('SER002','Terapia Infantil','Atención psicológica para niños.','CON001',60,0.00,'ACTIVO'),('SER003','Terapia Familiar','Mejora la comunicación familiar.','CON001',60,0.00,'ACTIVO'),('SER004','Evaluación Psicológica','Aplicación de pruebas psicológicas.','CON001',60,0.00,'ACTIVO');
/*!40000 ALTER TABLE `servicios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuario`
--

DROP TABLE IF EXISTS `usuario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuario` (
  `ClvUsu` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `CorreoUsu` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `TelefonoUsu` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ContrasenaUsu` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `EstadoUsu` tinyint(1) NOT NULL DEFAULT '1',
  `RequiereCambioContrasena` tinyint(1) NOT NULL DEFAULT '1',
  `RolUsu` enum('ADMINISTRADOR','CONSULTORIO','PSICOLOGO','PACIENTE') COLLATE utf8mb4_unicode_ci NOT NULL,
  `ClvPer` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`ClvUsu`),
  UNIQUE KEY `CorreoUsu` (`CorreoUsu`),
  UNIQUE KEY `ClvPer` (`ClvPer`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuario`
--

LOCK TABLES `usuario` WRITE;
/*!40000 ALTER TABLE `usuario` DISABLE KEYS */;
INSERT INTO `usuario` VALUES ('U001','sandisg321@gmail.com','7298440866','$2y$10$7LAqpZfukvAFEZx6BJ2XQOJJJ.L/hcLPMMc4ZCUUMXFtCHix1/MxC',1,1,'PACIENTE','P002'),('U002','diana23@gmail.com','7245896452','$2y$10$EUCOpPSLvJjEZYJtsyE4RurD.HhDuliY8Rt5NTXZdVPqm4RpVZHpq',1,1,'PACIENTE','P003'),('U003','lola@gmail.com','7223487954','$2y$10$DR2gDDKJte9yUZOVmhIfKOzHC067a9nU2qGvzOlYX910VRr1jxKqe',1,1,'PACIENTE','P004'),('U004','lynsc0613@gmail.com','7223487954','$2y$10$YcCZvWr9GwPxUp/3u6zov.ous6qG.K3kqODMnOj7Ko9ebRWmWiilO',1,1,'PACIENTE','P005'),('U005','mai@gmail.com','7245896542','$2y$10$1X6uivm45pKAX7hCevuOV.6433.TSAiU/gnGKUR00kK0ABQ7TkfGC',1,1,'PACIENTE','P006'),('U006','mai23@gmail.com','7245896542','$2y$10$wIEZ/GZwOBREFbilbHi6H.kClyazr9aZJwRhUVq7eIE.CSvgSZTn2',1,1,'PACIENTE','P007'),('U007','popocaenim@gmail.com','7228944923','$2y$10$39Ed1f6zKi0CFCJrHj5X7OwJNTKE0cgWyad.vTq53knUewrtuYahq',1,1,'PACIENTE','P008'),('U008','sanchezsandibell0@gmail.com','7223984064','$2y$10$EblWsLIMNzl0R4U.UPbXTu0DdKTYLOIgXtVn1h6sX846TgyBQzSJm',1,1,'ADMINISTRADOR','P009'),('U009','sandiilusg@gmail.com','7246895232','$2y$10$sXX8e8pTNTjtJEKmH4LFUuHcVwGaTc9GqmIvfBT6bxYyoIci.fYf.',1,1,'PACIENTE','P010'),('UC001','responsable@psicomatch.com','7220000000','$2y$10$4umXQt7OQCS4oQsBdP4uEuKzbU5FmYRTMb0cOrGOQrt7NyLAQLvzi',1,1,'CONSULTORIO','P011'),('US001','sandi321@gmail.com','7223984064','$2y$10$abyHpv2CjAVIodi1J0X08uVf.7KrLjmf3f3mtWNOyPlgzVOgBRMmm',1,1,'PSICOLOGO','P001'),('USU010','sandysanchezgarcia444@gmail.com','7246984565','$2y$10$JwtVp1mSfw8iM6QDaCgg9OxKvTqmN4NpbY/pmTENkZVxvC4iGSdaW',1,1,'PSICOLOGO','PER013'),('USU011','mayblakpe15@gmail.com','7246985213','$2y$10$uS05Z6TX/WhtBfzwaN0tmuQfCIxI6RNeoSsOQnqpcau0iNXGxhzQa',1,1,'PSICOLOGO','PER014');
/*!40000 ALTER TABLE `usuario` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vida_social_laboral`
--

DROP TABLE IF EXISTS `vida_social_laboral`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vida_social_laboral` (
  `ClvVidaSocial` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `CantidadAmigos` enum('MUCHOS','POCOS','NINGUNO') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `TipoGrupoSocial` enum('DISOCIAL','MIXTO','SANO','SIN_GRUPO') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `EstabilidadLaboral` enum('ESTABLE','INESTABLE','NO_APLICA') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `SatisfaccionLaboral` enum('SATISFECHO','PARCIALMENTE_SATISFECHO','INSATISFECHO','NO_APLICA') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `AdaptacionLaboral` enum('ADECUADA','REGULAR','INADECUADA','NO_APLICA') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `SituacionLaboral` enum('REALIZADO','FRUSTRADO','DESEMPLEADO','DESPEDIDO','SANCIONADO','REUBICADO','REINGRESADO','NO_APLICA','OTRO') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ManejoDineroAdecuado` tinyint(1) DEFAULT NULL,
  `ActividadesTiempoLibre` text COLLATE utf8mb4_unicode_ci,
  `ObservacionesVidaSocial` text COLLATE utf8mb4_unicode_ci,
  `ClvHist` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`ClvVidaSocial`),
  UNIQUE KEY `UK_VidaSocial_Historial` (`ClvHist`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vida_social_laboral`
--

LOCK TABLES `vida_social_laboral` WRITE;
/*!40000 ALTER TABLE `vida_social_laboral` DISABLE KEYS */;
/*!40000 ALTER TABLE `vida_social_laboral` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-30 12:12:22
