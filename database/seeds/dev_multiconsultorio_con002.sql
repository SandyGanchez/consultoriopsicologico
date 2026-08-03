-- RETIRADO DEL FLUJO DE DEMOSTRACIÓN Y DEL PAQUETE HOSTINGER.
-- No ejecutar en producción ni en instalaciones de consultorio único.
-- Conservado solo como referencia histórica de desarrollo.
--
-- Datos de desarrollo para prueba multiconsultorio (CON002).
-- Idempotente: no duplica si CON002 ya existe.
-- No forma parte del dump oficial.

SET NAMES utf8mb4;

UPDATE usuario
SET EstadoUsu = 1
WHERE ClvUsu = 'UC001'
  AND RolUsu = 'CONSULTORIO';

INSERT INTO direccion (
    ClvDir, PaisDir, EstadoDir, MunicipioDir, ColoniaDir,
    CalleDir, CodPostDir, NumExtDir, NumIntDir
)
SELECT
    'DIR003', 'México', 'Ciudad de México', 'Coyoacán', 'Del Carmen',
    'Av. Universidad', '04100', '300', NULL
WHERE NOT EXISTS (
    SELECT 1 FROM direccion WHERE ClvDir = 'DIR003'
);

INSERT INTO consultorio (
    ClvCons, NombreCons, Slogan, Descripcion, TelefonoCons,
    CorreoElectronico, LimiteCancHoras, ClvDir, EstatusCons,
    PublicadoCons, FechaPublicacionCons
)
SELECT
    'CON002',
    'Centro Bienestar Oriente',
    'Acompañamiento cercano',
    'Consultorio de desarrollo para prueba multiconsultorio.',
    '5551112233',
    'con002@psicomatch.test',
    24,
    'DIR003',
    'ACTIVO',
    1,
    NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM consultorio WHERE ClvCons = 'CON002'
);

INSERT INTO persona (
    ClvPer, NombrePer, ApPatPer, ApMatPer, FechaNacimiento, GeneroPer
)
SELECT
    'PER100', 'Laura', 'Mendoza', 'Ruiz', '1988-05-12', 'Femenino'
WHERE NOT EXISTS (
    SELECT 1 FROM persona WHERE ClvPer = 'PER100'
);

INSERT INTO usuario (
    ClvUsu, CorreoUsu, TelefonoUsu, ContrasenaUsu, EstadoUsu, RolUsu, ClvPer
)
SELECT
    'UC002',
    'responsable.con002@psicomatch.test',
    '5551112233',
    '$2y$10$Ax0kHKI.6O5uwVV/CdGQleBABA/.FoVcxCBuLmU39J/XwNY6vPyoa',
    1,
    'CONSULTORIO',
    'PER100'
WHERE NOT EXISTS (
    SELECT 1 FROM usuario WHERE ClvUsu = 'UC002'
);

INSERT INTO consultorio_usuario (
    ClvConsUsu, ClvCons, ClvUsu, EsResponsable, EstatusConsUsu
)
SELECT
    'CU002', 'CON002', 'UC002', 1, 'ACTIVO'
WHERE NOT EXISTS (
    SELECT 1 FROM consultorio_usuario WHERE ClvConsUsu = 'CU002'
);

INSERT INTO persona (
    ClvPer, NombrePer, ApPatPer, ApMatPer, FechaNacimiento, GeneroPer
)
SELECT
    'PER101', 'Diego', 'Salinas', 'Vega', '1990-09-03', 'Masculino'
WHERE NOT EXISTS (
    SELECT 1 FROM persona WHERE ClvPer = 'PER101'
);

INSERT INTO usuario (
    ClvUsu, CorreoUsu, TelefonoUsu, ContrasenaUsu, EstadoUsu, RolUsu, ClvPer
)
SELECT
    'US100',
    'psi.con002@psicomatch.test',
    '5552223344',
    '$2y$10$Ax0kHKI.6O5uwVV/CdGQleBABA/.FoVcxCBuLmU39J/XwNY6vPyoa',
    1,
    'PSICOLOGO',
    'PER101'
WHERE NOT EXISTS (
    SELECT 1 FROM usuario WHERE ClvUsu = 'US100'
);

INSERT INTO psicologo (
    ClvPsi, CedulaProfesional, EspecialidadPsi, DescripcionProfesional,
    EstatusPsi, MostrarEnPagina, ClvUsu, ClvCons
)
SELECT
    'PSI100',
    'CED-CON002-100',
    'Psicologia infantil',
    'Especialista del consultorio B para prueba multiconsultorio.',
    'ACTIVO',
    1,
    'US100',
    'CON002'
WHERE NOT EXISTS (
    SELECT 1 FROM psicologo WHERE ClvPsi = 'PSI100'
);

INSERT INTO servicios (
    ClvServ, NombreServicio, Descripcion, ClvCons,
    DuracionMinutos, CostoServicio, EstatusServicio
)
SELECT
    'SER100',
    'Terapia Infantil B',
    'Servicio exclusivo del consultorio B.',
    'CON002',
    60,
    650.00,
    'ACTIVO'
WHERE NOT EXISTS (
    SELECT 1 FROM servicios WHERE ClvServ = 'SER100'
);

INSERT INTO psicologo_servicio (
    ClvPsiServ, ClvPsi, ClvServ, PrecioServicio, DuracionMinutos,
    DescripcionServicio, EstatusAsignacion
)
SELECT
    'PS100',
    'PSI100',
    'SER100',
    650.00,
    60,
    'Sesión individual infantil en consultorio B.',
    'ACTIVA'
WHERE NOT EXISTS (
    SELECT 1 FROM psicologo_servicio WHERE ClvPsiServ = 'PS100'
);

INSERT INTO horario_consultorio (
    ClvHorarioCons, DiaSemana, HoraInicio, HoraFin, EstatusHorario, ClvCons
)
SELECT
    'HC100', 'LUNES', '09:00:00', '17:00:00', 'ACTIVO', 'CON002'
WHERE NOT EXISTS (
    SELECT 1 FROM horario_consultorio WHERE ClvHorarioCons = 'HC100'
);

-- Alinear especialidad con el encoding real de CON001 (prueba multiconsultorio).
UPDATE psicologo p
INNER JOIN psicologo ref
    ON ref.ClvPsi = 'PSI002'
SET p.EspecialidadPsi = ref.EspecialidadPsi
WHERE p.ClvPsi = 'PSI100';
