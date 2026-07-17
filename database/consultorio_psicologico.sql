-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 17-07-2026 a las 22:47:03
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `consultorio_psicologico`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `caracteristica`
--

CREATE TABLE `caracteristica` (
  `ClvCar` varchar(10) NOT NULL,
  `Titulo` varchar(60) NOT NULL,
  `Descripcion` varchar(150) NOT NULL,
  `Icono` varchar(50) NOT NULL,
  `OrdenCar` int(11) NOT NULL DEFAULT 1,
  `EstadoCar` tinyint(1) NOT NULL DEFAULT 1,
  `ClvCons` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `caracteristica`
--

INSERT INTO `caracteristica` (`ClvCar`, `Titulo`, `Descripcion`, `Icono`, `OrdenCar`, `EstadoCar`, `ClvCons`) VALUES
('CAR001', 'Atención Personalizada', 'Cada tratamiento se adapta a las necesidades del paciente.', 'bi-person-heart', 1, 1, 'CON001'),
('CAR002', 'Confidencialidad', 'Tu información siempre estará protegida.', 'bi-shield-lock', 2, 1, 'CON001'),
('CAR003', 'Citas Programadas', 'Agenda tus sesiones de forma sencilla y organizada.', 'bi-calendar-check', 3, 1, 'CON001');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cita`
--

CREATE TABLE `cita` (
  `ClvCita` varchar(10) NOT NULL,
  `FechaCita` date NOT NULL,
  `HraInicioCita` time NOT NULL,
  `HraFinCita` time DEFAULT NULL,
  `DuracionAplicadaMin` int(11) DEFAULT NULL,
  `CostoAplicado` decimal(10,2) DEFAULT NULL,
  `EstadoCita` enum('PROGRAMADA','ASISTIDA','CANCELADA','INASISTENCIA') NOT NULL DEFAULT 'PROGRAMADA',
  `NotasCita` varchar(255) DEFAULT NULL,
  `MotivoCancelacion` varchar(255) DEFAULT NULL,
  `FechaRegistroCita` datetime NOT NULL DEFAULT current_timestamp(),
  `FechaCancelacion` datetime DEFAULT NULL,
  `ClvPac` varchar(10) NOT NULL,
  `ClvPsi` varchar(10) NOT NULL,
  `ClvCons` varchar(10) NOT NULL,
  `ClvServ` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `cita`
--

INSERT INTO `cita` (`ClvCita`, `FechaCita`, `HraInicioCita`, `HraFinCita`, `DuracionAplicadaMin`, `CostoAplicado`, `EstadoCita`, `NotasCita`, `MotivoCancelacion`, `FechaRegistroCita`, `FechaCancelacion`, `ClvPac`, `ClvPsi`, `ClvCons`, `ClvServ`) VALUES
('CIT001', '2026-07-16', '10:00:00', '11:00:00', 60, 500.00, 'CANCELADA', 'Cita de prueba para la agenda', 'El paciente no podrá asistir', '2026-07-16 23:10:03', '2026-07-16 23:11:24', 'PAC001', 'PSI001', 'CON001', 'SER001');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `consecutivos`
--

CREATE TABLE `consecutivos` (
  `NombreTabla` varchar(30) NOT NULL,
  `UltimoNumero` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `consecutivos`
--

INSERT INTO `consecutivos` (`NombreTabla`, `UltimoNumero`) VALUES
('CONSULTORIO', 1),
('DIRECCION', 0),
('HORARIO', 5),
('PACIENTE', 0),
('PERSONA', 0),
('REDSOCIAL', 3),
('SERVICIO', 4),
('USUARIO', 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `consultorio`
--

CREATE TABLE `consultorio` (
  `ClvCons` varchar(10) NOT NULL,
  `NombreCons` varchar(100) NOT NULL,
  `LogotipoCons` varchar(150) DEFAULT NULL,
  `FaviconCons` varchar(150) DEFAULT NULL,
  `ImagenPortada` varchar(255) DEFAULT NULL,
  `Slogan` varchar(150) DEFAULT NULL,
  `Descripcion` text DEFAULT NULL,
  `TelefonoCons` varchar(20) NOT NULL,
  `CorreoElectronico` varchar(100) DEFAULT NULL,
  `LimiteCancHoras` int(11) NOT NULL DEFAULT 24,
  `ClvDir` varchar(10) NOT NULL,
  `ServidorSMTP` varchar(100) DEFAULT NULL,
  `PuertoSMTP` int(11) DEFAULT NULL,
  `SeguridadSMTP` enum('ssl','tls') DEFAULT NULL,
  `CorreoSMTP` varchar(120) DEFAULT NULL,
  `PasswordSMTP` varchar(255) DEFAULT NULL,
  `NombreRemitente` varchar(120) DEFAULT NULL,
  `EstatusCons` enum('ACTIVO','INACTIVO','BLOQUEADO') NOT NULL DEFAULT 'ACTIVO',
  `FechaRegistroCons` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `consultorio`
--

INSERT INTO `consultorio` (`ClvCons`, `NombreCons`, `LogotipoCons`, `FaviconCons`, `ImagenPortada`, `Slogan`, `Descripcion`, `TelefonoCons`, `CorreoElectronico`, `LimiteCancHoras`, `ClvDir`, `ServidorSMTP`, `PuertoSMTP`, `SeguridadSMTP`, `CorreoSMTP`, `PasswordSMTP`, `NombreRemitente`, `EstatusCons`, `FechaRegistroCons`) VALUES
('CON001', 'Consultorio Psicológico Integral', 'logo.png', NULL, NULL, 'Cuidando tu bienestar emocional', 'Brindamos atención psicológica profesional para niños, adolescentes y adultos.', '7221234567', 'consultorio@gmail.com', 24, 'DIR001', NULL, NULL, NULL, NULL, NULL, NULL, 'ACTIVO', '2026-07-16 21:27:29');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `consultorio_usuario`
--

CREATE TABLE `consultorio_usuario` (
  `ClvConsUsu` varchar(10) NOT NULL,
  `ClvCons` varchar(10) NOT NULL,
  `ClvUsu` varchar(10) NOT NULL,
  `EsResponsable` tinyint(1) NOT NULL DEFAULT 1,
  `EstatusConsUsu` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  `FechaAsignacion` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `consultorio_usuario`
--

INSERT INTO `consultorio_usuario` (`ClvConsUsu`, `ClvCons`, `ClvUsu`, `EsResponsable`, `EstatusConsUsu`, `FechaAsignacion`) VALUES
('CU001', 'CON001', 'UC001', 1, 'ACTIVO', '2026-07-16 21:41:49');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `direccion`
--

CREATE TABLE `direccion` (
  `ClvDir` varchar(10) NOT NULL,
  `PaisDir` varchar(50) NOT NULL,
  `EstadoDir` varchar(50) NOT NULL,
  `MunicipioDir` varchar(50) NOT NULL,
  `ColoniaDir` varchar(50) NOT NULL,
  `CalleDir` varchar(70) DEFAULT NULL,
  `CodPostDir` char(5) NOT NULL,
  `NumExtDir` varchar(10) DEFAULT NULL,
  `NumIntDir` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `direccion`
--

INSERT INTO `direccion` (`ClvDir`, `PaisDir`, `EstadoDir`, `MunicipioDir`, `ColoniaDir`, `CalleDir`, `CodPostDir`, `NumExtDir`, `NumIntDir`) VALUES
('DIR001', 'México', 'Estado de México', 'Tejupilco', 'Centro', 'Av. Benito Juárez', '51400', '120', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `disponibilidad_psicologo`
--

CREATE TABLE `disponibilidad_psicologo` (
  `ClvDisponibilidad` varchar(10) NOT NULL,
  `DiaSemana` enum('LUNES','MARTES','MIERCOLES','JUEVES','VIERNES','SABADO','DOMINGO') NOT NULL,
  `HoraInicio` time NOT NULL,
  `HoraFin` time NOT NULL,
  `EstatusDisponibilidad` enum('ACTIVA','INACTIVA') NOT NULL DEFAULT 'ACTIVA',
  `FechaRegistroDisponibilidad` datetime NOT NULL DEFAULT current_timestamp(),
  `ClvPsi` varchar(10) NOT NULL
) ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `horario`
--

CREATE TABLE `horario` (
  `ClvHorario` varchar(10) NOT NULL,
  `DiaSemana` enum('LUNES','MARTES','MIERCOLES','JUEVES','VIERNES','SABADO','DOMINGO') NOT NULL,
  `HoraInicio` time NOT NULL,
  `HoraFin` time NOT NULL,
  `ClvCons` varchar(10) NOT NULL,
  `EstatusHorario` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `horario`
--

INSERT INTO `horario` (`ClvHorario`, `DiaSemana`, `HoraInicio`, `HoraFin`, `ClvCons`, `EstatusHorario`) VALUES
('HOR001', 'LUNES', '09:00:00', '18:00:00', 'CON001', 'ACTIVO'),
('HOR002', 'MARTES', '09:00:00', '18:00:00', 'CON001', 'ACTIVO'),
('HOR003', 'MIERCOLES', '09:00:00', '18:00:00', 'CON001', 'ACTIVO'),
('HOR004', 'JUEVES', '09:00:00', '18:00:00', 'CON001', 'ACTIVO'),
('HOR005', 'VIERNES', '09:00:00', '18:00:00', 'CON001', 'ACTIVO');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `paciente`
--

CREATE TABLE `paciente` (
  `ClvPac` varchar(10) NOT NULL,
  `FotoPerfilPac` varchar(150) DEFAULT 'default.png',
  `EstadoActivoPac` tinyint(1) NOT NULL DEFAULT 1,
  `ClvUsu` varchar(10) NOT NULL,
  `ClvCons` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `paciente`
--

INSERT INTO `paciente` (`ClvPac`, `FotoPerfilPac`, `EstadoActivoPac`, `ClvUsu`, `ClvCons`) VALUES
('PAC001', 'perfil-default.png', 1, 'U001', NULL),
('PAC002', 'perfil-default.png', 1, 'U002', NULL),
('PAC003', 'perfil-default.png', 1, 'U003', NULL),
('PAC004', 'perfil-default.png', 1, 'U004', NULL),
('PAC005', 'perfil-default.png', 1, 'U005', NULL),
('PAC006', 'perfil-default.png', 1, 'U006', NULL),
('PAC007', 'perfil-default.png', 1, 'U007', NULL),
('PAC008', 'perfil-default.png', 1, 'U008', NULL),
('PAC009', 'perfil-default.png', 1, 'U009', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `persona`
--

CREATE TABLE `persona` (
  `ClvPer` varchar(10) NOT NULL,
  `NombrePer` varchar(50) NOT NULL,
  `ApPatPer` varchar(50) NOT NULL,
  `ApMatPer` varchar(50) NOT NULL,
  `FechaNacimiento` date NOT NULL,
  `GeneroPer` enum('Masculino','Femenino','Otro') NOT NULL,
  `FotoPerfilPer` varchar(255) DEFAULT NULL,
  `ClvDir` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `persona`
--

INSERT INTO `persona` (`ClvPer`, `NombrePer`, `ApPatPer`, `ApMatPer`, `FechaNacimiento`, `GeneroPer`, `FotoPerfilPer`, `ClvDir`) VALUES
('P001', 'Sandra', 'Sanchez', 'Garcia', '2005-06-06', 'Femenino', NULL, 'DIR001'),
('P002', 'Sandra', 'Sanchez', 'Garcia', '2005-06-06', 'Femenino', NULL, NULL),
('P003', 'diana elena', 'sanchez', 'garcia', '2010-03-17', 'Femenino', NULL, NULL),
('P004', 'Lola', 'Garcia', 'Alvarez', '1997-07-09', 'Femenino', NULL, NULL),
('P005', 'Joselyn', 'Salgado', 'Carbajal', '2005-12-03', 'Femenino', NULL, NULL),
('P006', 'diana elena', 'sanchez', 'garcia', '2005-12-20', 'Femenino', NULL, NULL),
('P007', 'diana elena', 'sanchez', 'garcia', '2005-12-20', 'Femenino', NULL, NULL),
('P008', 'Maira', 'Popoca', 'Eugenio', '2005-05-02', 'Femenino', NULL, NULL),
('P009', 'Sandra', 'Sanchez', 'Garcia', '2005-07-15', 'Femenino', NULL, NULL),
('P010', 'Sandra', 'Sanchez', 'Garcia', '2005-07-15', 'Femenino', NULL, NULL),
('P011', 'Responsable', 'PsicoMatch', 'Consultorio', '1990-01-01', 'Otro', NULL, NULL),
('PER010', 'Yazmin', 'Sanchez', 'Garcia', '1997-04-29', 'Femenino', NULL, NULL),
('PER011', 'Yazmin', 'Sanchez', 'Garcia', '1997-04-29', 'Femenino', NULL, NULL),
('PER012', 'Yazmin', 'Sanchez', 'Garcia', '1997-04-29', 'Femenino', NULL, NULL),
('PER013', 'Yazmin', 'Sanchez', 'Garcia', '1997-04-29', 'Femenino', 'perfil_09f2f4f136e69e8328e33aef.png', NULL),
('PER014', 'Maira', 'Popoca', 'Eugenio', '2005-05-02', 'Femenino', NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `psicologo`
--

CREATE TABLE `psicologo` (
  `ClvPsi` varchar(10) NOT NULL,
  `CedulaProfesional` varchar(20) NOT NULL,
  `EspecialidadPsi` varchar(100) NOT NULL,
  `DescripcionProfesional` text DEFAULT NULL,
  `EstatusPsi` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  `MostrarEnPagina` tinyint(1) NOT NULL DEFAULT 1,
  `FechaRegistroPsi` datetime NOT NULL DEFAULT current_timestamp(),
  `ClvUsu` varchar(10) NOT NULL,
  `ClvCons` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `psicologo`
--

INSERT INTO `psicologo` (`ClvPsi`, `CedulaProfesional`, `EspecialidadPsi`, `DescripcionProfesional`, `EstatusPsi`, `MostrarEnPagina`, `FechaRegistroPsi`, `ClvUsu`, `ClvCons`) VALUES
('PSI001', 'CEDULA001', 'Psicología clínica', 'Especialista en atención psicológica integral.', 'ACTIVO', 1, '2026-07-16 21:42:08', 'US001', 'CON001'),
('PSI002', '72458964', 'Psicología infantil', 'sd', 'ACTIVO', 1, '2026-07-17 02:30:50', 'USU010', 'CON001'),
('PSI003', '724589648', 'Conductivo-conductual', 'jhbdmjh', 'ACTIVO', 1, '2026-07-17 10:06:43', 'USU011', 'CON001');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `psicologo_servicio`
--

CREATE TABLE `psicologo_servicio` (
  `ClvPsiServ` varchar(10) NOT NULL,
  `ClvPsi` varchar(10) NOT NULL,
  `ClvServ` varchar(10) NOT NULL,
  `PrecioServicio` decimal(10,2) NOT NULL DEFAULT 0.00,
  `DuracionMinutos` int(11) NOT NULL DEFAULT 60,
  `DescripcionServicio` varchar(255) NOT NULL,
  `EstatusAsignacion` enum('ACTIVA','INACTIVA') NOT NULL DEFAULT 'ACTIVA',
  `FechaAsignacion` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `psicologo_servicio`
--

INSERT INTO `psicologo_servicio` (`ClvPsiServ`, `ClvPsi`, `ClvServ`, `PrecioServicio`, `DuracionMinutos`, `DescripcionServicio`, `EstatusAsignacion`, `FechaAsignacion`) VALUES
('PS001', 'PSI001', 'SER001', 0.00, 60, '', 'ACTIVA', '2026-07-16 21:42:18'),
('PS002', 'PSI001', 'SER002', 0.00, 60, '', 'ACTIVA', '2026-07-16 21:42:18');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `recuperacion_password`
--

CREATE TABLE `recuperacion_password` (
  `IdRec` int(11) NOT NULL,
  `ClvUsu` varchar(10) NOT NULL,
  `CodigoHash` varchar(255) NOT NULL,
  `FechaCreacion` datetime NOT NULL DEFAULT current_timestamp(),
  `FechaExpiracion` datetime NOT NULL,
  `Utilizado` tinyint(1) NOT NULL DEFAULT 0,
  `Intentos` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `FechaUltimoIntento` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `recuperacion_password`
--

INSERT INTO `recuperacion_password` (`IdRec`, `ClvUsu`, `CodigoHash`, `FechaCreacion`, `FechaExpiracion`, `Utilizado`, `Intentos`, `FechaUltimoIntento`) VALUES
(12, 'U008', '$2y$10$QgNMuGZib8dIWRAhtLGOceMlTrkiyNfUN0/VY41T6cIpqGEwLXF6G', '2026-07-16 02:01:31', '2026-07-16 02:11:31', 1, 0, NULL),
(13, 'U008', '$2y$10$NVjdqvlwzPZLY6Wrdw99h.Dd0hbcBfKSzEXVFcRMTSzPfXKjB1TMG', '2026-07-16 02:03:35', '2026-07-16 02:13:35', 1, 0, NULL),
(14, 'U008', '$2y$10$XHHQbK3kETJEXSrLjRqoeO8KhGeoscibqrzxW3SBDt4GPGfgalO9q', '2026-07-16 02:15:31', '2026-07-16 02:25:31', 1, 0, NULL),
(15, 'U008', '$2y$10$MnA44xOBMJELSJVytE8uzuXVnZcExOXKz/OAGejPVlw6HWVJ3FtQO', '2026-07-16 02:23:03', '2026-07-16 02:33:03', 1, 0, NULL),
(16, 'U008', '$2y$10$ZQHLBSiPPp5hcmSG5zIQYeGZ2uMAFdspILGGtZ6Awx6rFrzIxVo0K', '2026-07-16 02:24:11', '2026-07-16 02:34:11', 1, 0, NULL),
(17, 'U001', '$2y$10$TxZORS2GScTP1zLP0ARGv.VbMlJ3azJkXd1Ivjwpk0QyIfDhkWPOa', '2026-07-16 02:27:29', '2026-07-16 02:37:29', 1, 0, NULL),
(18, 'U008', '$2y$10$iGpl3yuVcl9iAUvKquoHVeVSNhmywkU0CVYy54wq5ipNeURTgZM1a', '2026-07-17 10:12:30', '2026-07-17 10:22:30', 0, 0, NULL),
(19, 'USU011', '$2y$10$GUEg3EL.PKoqVYb6HKXn..pMxUJDT3si9GTqH6XMvXLpIHyv/IBV2', '2026-07-17 10:12:56', '2026-07-17 10:22:56', 1, 0, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `redsocial`
--

CREATE TABLE `redsocial` (
  `ClvRed` varchar(10) NOT NULL,
  `TipoRed` enum('Facebook','Instagram','WhatsApp','TikTok','Página Web') NOT NULL,
  `URLRed` varchar(255) NOT NULL,
  `ClvCons` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `redsocial`
--

INSERT INTO `redsocial` (`ClvRed`, `TipoRed`, `URLRed`, `ClvCons`) VALUES
('RED001', 'Facebook', 'https://facebook.com/consultorio', 'CON001'),
('RED002', 'Instagram', 'https://instagram.com/consultorio', 'CON001'),
('RED003', 'WhatsApp', 'https://wa.me/527221234567', 'CON001');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `servicios`
--

CREATE TABLE `servicios` (
  `ClvServ` varchar(10) NOT NULL,
  `NombreServicio` varchar(60) NOT NULL,
  `Descripcion` varchar(255) NOT NULL,
  `ClvCons` varchar(10) NOT NULL,
  `DuracionMinutos` int(11) NOT NULL DEFAULT 60,
  `CostoServicio` decimal(10,2) NOT NULL DEFAULT 0.00,
  `EstatusServicio` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `servicios`
--

INSERT INTO `servicios` (`ClvServ`, `NombreServicio`, `Descripcion`, `ClvCons`, `DuracionMinutos`, `CostoServicio`, `EstatusServicio`) VALUES
('SER001', 'Terapia Individual', 'Atención psicológica personalizada.', 'CON001', 60, 0.00, 'ACTIVO'),
('SER002', 'Terapia Infantil', 'Atención psicológica para niños.', 'CON001', 60, 0.00, 'ACTIVO'),
('SER003', 'Terapia Familiar', 'Mejora la comunicación familiar.', 'CON001', 60, 0.00, 'ACTIVO'),
('SER004', 'Evaluación Psicológica', 'Aplicación de pruebas psicológicas.', 'CON001', 60, 0.00, 'ACTIVO');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario`
--

CREATE TABLE `usuario` (
  `ClvUsu` varchar(10) NOT NULL,
  `CorreoUsu` varchar(100) NOT NULL,
  `TelefonoUsu` varchar(10) NOT NULL,
  `ContrasenaUsu` varchar(255) NOT NULL,
  `EstadoUsu` tinyint(1) NOT NULL DEFAULT 1,
  `RequiereCambioContrasena` tinyint(1) NOT NULL DEFAULT 1,
  `RolUsu` enum('ADMINISTRADOR','CONSULTORIO','PSICOLOGO','PACIENTE') NOT NULL,
  `ClvPer` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuario`
--

INSERT INTO `usuario` (`ClvUsu`, `CorreoUsu`, `TelefonoUsu`, `ContrasenaUsu`, `EstadoUsu`, `RequiereCambioContrasena`, `RolUsu`, `ClvPer`) VALUES
('U001', 'sandisg321@gmail.com', '7298440866', '$2y$10$7LAqpZfukvAFEZx6BJ2XQOJJJ.L/hcLPMMc4ZCUUMXFtCHix1/MxC', 1, 1, 'PACIENTE', 'P002'),
('U002', 'diana23@gmail.com', '7245896452', '$2y$10$EUCOpPSLvJjEZYJtsyE4RurD.HhDuliY8Rt5NTXZdVPqm4RpVZHpq', 1, 1, 'PACIENTE', 'P003'),
('U003', 'lola@gmail.com', '7223487954', '$2y$10$DR2gDDKJte9yUZOVmhIfKOzHC067a9nU2qGvzOlYX910VRr1jxKqe', 1, 1, 'PACIENTE', 'P004'),
('U004', 'lynsc0613@gmail.com', '7223487954', '$2y$10$YcCZvWr9GwPxUp/3u6zov.ous6qG.K3kqODMnOj7Ko9ebRWmWiilO', 1, 1, 'PACIENTE', 'P005'),
('U005', 'mai@gmail.com', '7245896542', '$2y$10$1X6uivm45pKAX7hCevuOV.6433.TSAiU/gnGKUR00kK0ABQ7TkfGC', 1, 1, 'PACIENTE', 'P006'),
('U006', 'mai23@gmail.com', '7245896542', '$2y$10$wIEZ/GZwOBREFbilbHi6H.kClyazr9aZJwRhUVq7eIE.CSvgSZTn2', 1, 1, 'PACIENTE', 'P007'),
('U007', 'popocaenim@gmail.com', '7228944923', '$2y$10$39Ed1f6zKi0CFCJrHj5X7OwJNTKE0cgWyad.vTq53knUewrtuYahq', 1, 1, 'PACIENTE', 'P008'),
('U008', 'sanchezsandibell0@gmail.com', '7223984064', '$2y$10$3OAAgsliFW3nPPTpXCw1Qu2m/hNthxkmoM6BUfsynHW5vmNbKYTja', 1, 1, 'PACIENTE', 'P009'),
('U009', 'sandiilusg@gmail.com', '7246895232', '$2y$10$sXX8e8pTNTjtJEKmH4LFUuHcVwGaTc9GqmIvfBT6bxYyoIci.fYf.', 1, 1, 'PACIENTE', 'P010'),
('UC001', 'responsable@psicomatch.com', '7220000000', '$2y$10$vkddPAnJs4nBBTWw/EjkFe.u8boL82PPxwkfP8FvNEVFE.u9cc8Yq', 1, 1, 'CONSULTORIO', 'P011'),
('US001', 'sandi321@gmail.com', '7223984064', '$2y$10$abyHpv2CjAVIodi1J0X08uVf.7KrLjmf3f3mtWNOyPlgzVOgBRMmm', 1, 1, 'PSICOLOGO', 'P001'),
('USU010', 'sandysanchezgarcia444@gmail.com', '7246984565', '$2y$10$JwtVp1mSfw8iM6QDaCgg9OxKvTqmN4NpbY/pmTENkZVxvC4iGSdaW', 1, 1, 'PSICOLOGO', 'PER013'),
('USU011', 'mayblakpe15@gmail.com', '7246985213', '$2y$10$uS05Z6TX/WhtBfzwaN0tmuQfCIxI6RNeoSsOQnqpcau0iNXGxhzQa', 1, 1, 'PSICOLOGO', 'PER014');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `caracteristica`
--
ALTER TABLE `caracteristica`
  ADD PRIMARY KEY (`ClvCar`),
  ADD KEY `FK_Caracteristica_Consultorio` (`ClvCons`);

--
-- Indices de la tabla `cita`
--
ALTER TABLE `cita`
  ADD PRIMARY KEY (`ClvCita`),
  ADD KEY `FK_Cita_Paciente` (`ClvPac`),
  ADD KEY `FK_Cita_Consultorio` (`ClvCons`),
  ADD KEY `FK_Cita_Servicio` (`ClvServ`),
  ADD KEY `FK_Cita_Psicologo` (`ClvPsi`);

--
-- Indices de la tabla `consecutivos`
--
ALTER TABLE `consecutivos`
  ADD PRIMARY KEY (`NombreTabla`);

--
-- Indices de la tabla `consultorio`
--
ALTER TABLE `consultorio`
  ADD PRIMARY KEY (`ClvCons`),
  ADD KEY `FK_CONSULTORIO_DIRECCION` (`ClvDir`);

--
-- Indices de la tabla `consultorio_usuario`
--
ALTER TABLE `consultorio_usuario`
  ADD PRIMARY KEY (`ClvConsUsu`),
  ADD UNIQUE KEY `UK_ConsultorioUsuario` (`ClvCons`,`ClvUsu`),
  ADD KEY `FK_ConsultorioUsuario_Usuario` (`ClvUsu`);

--
-- Indices de la tabla `direccion`
--
ALTER TABLE `direccion`
  ADD PRIMARY KEY (`ClvDir`);

--
-- Indices de la tabla `disponibilidad_psicologo`
--
ALTER TABLE `disponibilidad_psicologo`
  ADD PRIMARY KEY (`ClvDisponibilidad`),
  ADD UNIQUE KEY `UK_Disponibilidad_Psicologo` (`ClvPsi`,`DiaSemana`,`HoraInicio`,`HoraFin`);

--
-- Indices de la tabla `horario`
--
ALTER TABLE `horario`
  ADD PRIMARY KEY (`ClvHorario`),
  ADD KEY `FK_HORARIO_CONSULTORIO` (`ClvCons`);

--
-- Indices de la tabla `paciente`
--
ALTER TABLE `paciente`
  ADD PRIMARY KEY (`ClvPac`),
  ADD KEY `FK_PACIENTE_USUARIO` (`ClvUsu`),
  ADD KEY `FK_Paciente_Consultorio` (`ClvCons`);

--
-- Indices de la tabla `persona`
--
ALTER TABLE `persona`
  ADD PRIMARY KEY (`ClvPer`),
  ADD KEY `FK_PERSONA_DIRECCION` (`ClvDir`);

--
-- Indices de la tabla `psicologo`
--
ALTER TABLE `psicologo`
  ADD PRIMARY KEY (`ClvPsi`),
  ADD UNIQUE KEY `UK_Psicologo_Cedula` (`CedulaProfesional`),
  ADD UNIQUE KEY `UK_Psicologo_Usuario` (`ClvUsu`),
  ADD KEY `FK_Psicologo_Consultorio` (`ClvCons`);

--
-- Indices de la tabla `psicologo_servicio`
--
ALTER TABLE `psicologo_servicio`
  ADD PRIMARY KEY (`ClvPsiServ`),
  ADD UNIQUE KEY `UK_PsicologoServicio` (`ClvPsi`,`ClvServ`),
  ADD KEY `FK_PsicologoServicio_Servicio` (`ClvServ`);

--
-- Indices de la tabla `recuperacion_password`
--
ALTER TABLE `recuperacion_password`
  ADD PRIMARY KEY (`IdRec`),
  ADD KEY `IDX_Recuperacion_Usuario` (`ClvUsu`,`Utilizado`,`FechaExpiracion`),
  ADD KEY `IDX_Recuperacion_Activo` (`ClvUsu`,`Utilizado`,`FechaExpiracion`);

--
-- Indices de la tabla `redsocial`
--
ALTER TABLE `redsocial`
  ADD PRIMARY KEY (`ClvRed`),
  ADD KEY `FK_REDSOCIAL_CONSULTORIO` (`ClvCons`);

--
-- Indices de la tabla `servicios`
--
ALTER TABLE `servicios`
  ADD PRIMARY KEY (`ClvServ`),
  ADD UNIQUE KEY `UK_Servicio_Consultorio` (`ClvCons`,`NombreServicio`);

--
-- Indices de la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD PRIMARY KEY (`ClvUsu`),
  ADD UNIQUE KEY `CorreoUsu` (`CorreoUsu`),
  ADD UNIQUE KEY `ClvPer` (`ClvPer`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `recuperacion_password`
--
ALTER TABLE `recuperacion_password`
  MODIFY `IdRec` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `caracteristica`
--
ALTER TABLE `caracteristica`
  ADD CONSTRAINT `FK_Caracteristica_Consultorio` FOREIGN KEY (`ClvCons`) REFERENCES `consultorio` (`ClvCons`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `cita`
--
ALTER TABLE `cita`
  ADD CONSTRAINT `FK_Cita_Consultorio` FOREIGN KEY (`ClvCons`) REFERENCES `consultorio` (`ClvCons`) ON UPDATE CASCADE,
  ADD CONSTRAINT `FK_Cita_Paciente` FOREIGN KEY (`ClvPac`) REFERENCES `paciente` (`ClvPac`) ON UPDATE CASCADE,
  ADD CONSTRAINT `FK_Cita_Psicologo` FOREIGN KEY (`ClvPsi`) REFERENCES `psicologo` (`ClvPsi`) ON UPDATE CASCADE,
  ADD CONSTRAINT `FK_Cita_Servicio` FOREIGN KEY (`ClvServ`) REFERENCES `servicios` (`ClvServ`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `consultorio`
--
ALTER TABLE `consultorio`
  ADD CONSTRAINT `FK_CONSULTORIO_DIRECCION` FOREIGN KEY (`ClvDir`) REFERENCES `direccion` (`ClvDir`);

--
-- Filtros para la tabla `consultorio_usuario`
--
ALTER TABLE `consultorio_usuario`
  ADD CONSTRAINT `FK_ConsultorioUsuario_Consultorio` FOREIGN KEY (`ClvCons`) REFERENCES `consultorio` (`ClvCons`) ON UPDATE CASCADE,
  ADD CONSTRAINT `FK_ConsultorioUsuario_Usuario` FOREIGN KEY (`ClvUsu`) REFERENCES `usuario` (`ClvUsu`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `disponibilidad_psicologo`
--
ALTER TABLE `disponibilidad_psicologo`
  ADD CONSTRAINT `FK_Disponibilidad_Psicologo` FOREIGN KEY (`ClvPsi`) REFERENCES `psicologo` (`ClvPsi`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `horario`
--
ALTER TABLE `horario`
  ADD CONSTRAINT `FK_HORARIO_CONSULTORIO` FOREIGN KEY (`ClvCons`) REFERENCES `consultorio` (`ClvCons`);

--
-- Filtros para la tabla `paciente`
--
ALTER TABLE `paciente`
  ADD CONSTRAINT `FK_PACIENTE_USUARIO` FOREIGN KEY (`ClvUsu`) REFERENCES `usuario` (`ClvUsu`) ON UPDATE CASCADE,
  ADD CONSTRAINT `FK_Paciente_Consultorio` FOREIGN KEY (`ClvCons`) REFERENCES `consultorio` (`ClvCons`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `persona`
--
ALTER TABLE `persona`
  ADD CONSTRAINT `FK_PERSONA_DIRECCION` FOREIGN KEY (`ClvDir`) REFERENCES `direccion` (`ClvDir`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `psicologo`
--
ALTER TABLE `psicologo`
  ADD CONSTRAINT `FK_Psicologo_Consultorio` FOREIGN KEY (`ClvCons`) REFERENCES `consultorio` (`ClvCons`) ON UPDATE CASCADE,
  ADD CONSTRAINT `FK_Psicologo_Usuario` FOREIGN KEY (`ClvUsu`) REFERENCES `usuario` (`ClvUsu`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `psicologo_servicio`
--
ALTER TABLE `psicologo_servicio`
  ADD CONSTRAINT `FK_PsicologoServicio_Psicologo` FOREIGN KEY (`ClvPsi`) REFERENCES `psicologo` (`ClvPsi`) ON UPDATE CASCADE,
  ADD CONSTRAINT `FK_PsicologoServicio_Servicio` FOREIGN KEY (`ClvServ`) REFERENCES `servicios` (`ClvServ`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `recuperacion_password`
--
ALTER TABLE `recuperacion_password`
  ADD CONSTRAINT `FK_Recuperacion_Usuario` FOREIGN KEY (`ClvUsu`) REFERENCES `usuario` (`ClvUsu`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `redsocial`
--
ALTER TABLE `redsocial`
  ADD CONSTRAINT `FK_REDSOCIAL_CONSULTORIO` FOREIGN KEY (`ClvCons`) REFERENCES `consultorio` (`ClvCons`);

--
-- Filtros para la tabla `servicios`
--
ALTER TABLE `servicios`
  ADD CONSTRAINT `FK_SERVICIO_CONSULTORIO` FOREIGN KEY (`ClvCons`) REFERENCES `consultorio` (`ClvCons`),
  ADD CONSTRAINT `FK_Servicios_Consultorio` FOREIGN KEY (`ClvCons`) REFERENCES `consultorio` (`ClvCons`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD CONSTRAINT `FK_USUARIO_PERSONA` FOREIGN KEY (`ClvPer`) REFERENCES `persona` (`ClvPer`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
