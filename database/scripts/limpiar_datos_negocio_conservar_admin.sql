-- Limpia datos de negocio/prueba. Conserva ADMINISTRADOR U008 / persona P009.
-- NO elimina catálogo caracteristica ni estructura.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Expediente / sesión clínica
DELETE FROM diagnostico_seguimiento;
DELETE FROM evolucion_sesion;
DELETE FROM recomendacion_sesion;
DELETE FROM reactivo_psicologico;
DELETE FROM seguimiento_sesion;
DELETE FROM actitud_conducta_inicial;
DELETE FROM adiccion;
DELETE FROM antecedente_familiar;
DELETE FROM antecedente_patologico;
DELETE FROM apreciacion_diagnostica;
DELETE FROM estado_psicologico_inicial;
DELETE FROM examen_mental_inicial;
DELETE FROM psicoanamnesis_familiar;
DELETE FROM vida_social_laboral;
DELETE FROM historial_clinico;

-- Operación
DELETE FROM cita;
DELETE FROM notificacion;
DELETE FROM incidencia_acceso;
DELETE FROM recuperacion_password;
DELETE FROM activacion_cuenta;

-- Agenda / servicios
DELETE FROM disponibilidad_psicologo;
DELETE FROM horario;
DELETE FROM horario_consultorio;
DELETE FROM psicologo_servicio;
DELETE FROM servicios;
DELETE FROM redsocial;

-- Roles de negocio
DELETE FROM consultorio_usuario;
DELETE FROM psicologo;
DELETE FROM paciente;
DELETE FROM consultorio;

-- Usuarios y personas (excepto administrador)
DELETE FROM usuario WHERE ClvUsu <> 'U008';
DELETE FROM persona WHERE ClvPer <> 'P009';
DELETE FROM direccion;

-- Consecutivos: reinicio limpio (claves se generan por MAX del campo)
REPLACE INTO consecutivos (NombreTabla, UltimoNumero) VALUES
  ('CONSULTORIO', 0),
  ('DIRECCION', 0),
  ('HORARIO', 0),
  ('HORARIO_CONSULTORIO', 0),
  ('PACIENTE', 0),
  ('PERSONA', 0),
  ('REDSOCIAL', 0),
  ('SERVICIO', 0),
  ('USUARIO', 0),
  ('CONSULTORIO_USUARIO', 0),
  ('PSICOLOGO', 0);

-- Admin operativo: sin forzar cambio de contraseña
UPDATE usuario
SET RequiereCambioContrasena = 0,
    EstadoUsu = 1
WHERE ClvUsu = 'U008';

SET FOREIGN_KEY_CHECKS = 1;

-- Verificación
SELECT ClvUsu, CorreoUsu, RolUsu, EstadoUsu, RequiereCambioContrasena
FROM usuario;
SELECT COUNT(*) AS personas FROM persona;
SELECT COUNT(*) AS consultorios FROM consultorio;
SELECT COUNT(*) AS caracteristicas FROM caracteristica;
