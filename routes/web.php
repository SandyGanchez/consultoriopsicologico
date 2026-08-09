<?php

use App\Controllers\HomeController;
use App\Controllers\AuthController;
use App\Controllers\PacienteController;
use App\Controllers\PsicologoController;
use App\Controllers\ConsultorioController;
use App\Controllers\AdministradorController;
use App\Controllers\NotificacionController;
use App\Controllers\ActivacionController;
use App\Controllers\RestablecerAccesoConsultorioController;
use App\Controllers\PrivacidadController;

return [

    /*
    =========================================
                  RUTAS GET
    =========================================
    */

    'GET' => [

        /*
        =====================================
                  RUTAS PÚBLICAS
        =====================================
        */

        '/' => [
            HomeController::class,
            'index'
        ],

        '/consultorios/{consultorio}' => [
            HomeController::class,
            'mostrarConsultorio'
        ],

        '/consultorios/{consultorio}/especialistas/{psicologo}' => [
            HomeController::class,
            'perfilEspecialista'
        ],

        '/login' => [
            AuthController::class,
            'login'
        ],

        '/registro' => [
            AuthController::class,
            'register'
        ],

        '/verificar-correo' => [
            AuthController::class,
            'mostrarVerificarCorreo'
        ],

        '/cambiar-contrasena' => [
            AuthController::class,
            'changeTemporaryPassword'
        ],

        '/forgot-password' => [
            AuthController::class,
            'forgotPassword'
        ],

        '/activar-cuenta' => [
            ActivacionController::class,
            'mostrar'
        ],

        '/restablecer-acceso-consultorio' => [
            RestablecerAccesoConsultorioController::class,
            'mostrar'
        ],

        '/verify-code' => [
            AuthController::class,
            'verifyCode'
        ],

        '/new-password' => [
            AuthController::class,
            'newPassword'
        ],

        '/especialistas/{psicologo}' => [
            HomeController::class,
            'perfilEspecialistaCorto'
        ],

        '/agendar-cita' => [
            HomeController::class,
            'agendarCita'
        ],

        '/aviso-de-privacidad' => [
            PrivacidadController::class,
            'avisoDePrivacidad'
        ],

        '/privacidad/consentimiento' => [
            PrivacidadController::class,
            'consentimiento'
        ],

        /*
        =====================================
                  ADMINISTRADOR
        =====================================
        */

   '/administrador' => [
    AdministradorController::class,
    'dashboard'
],

'/administrador/consultorio' => [
    AdministradorController::class,
    'verConsultorio'
],

'/administrador/consultorio/configurar' => [
    AdministradorController::class,
    'crearConsultorio'
],

'/administrador/consultorio/editar' => [
    AdministradorController::class,
    'editarConsultorio'
],

'/administrador/incidencias' => [
    AdministradorController::class,
    'listarIncidencias'
],

'/administrador/incidencias/{id}' => [
    AdministradorController::class,
    'verIncidencia'
],

/* Legacy multiconsultorio → redirección controlada (sin aceptar ClvCons). */
'/administrador/consultorios' => [
    AdministradorController::class,
    'listarConsultorios'
],

'/administrador/consultorios/crear' => [
    AdministradorController::class,
    'crearConsultorio'
],

'/administrador/consultorios/ver/{id}' => [
    AdministradorController::class,
    'redirigirCuentaUnica'
],

'/administrador/consultorios/vista-previa/{id}' => [
    AdministradorController::class,
    'redirigirCuentaUnica'
],

'/administrador/consultorios/editar/{id}' => [
    AdministradorController::class,
    'redirigirCuentaUnica'
],
        /*
        =====================================
                   CONSULTORIO
        =====================================
        */

        '/consultorio' => [
            ConsultorioController::class,
            'dashboard'
        ],

        '/consultorio/agenda' => [
            ConsultorioController::class,
            'agenda'
        ],

        '/consultorio/agenda/eventos' => [
            ConsultorioController::class,
            'eventosAgenda'
        ],

        '/consultorio/actividad-especialistas' => [
            ConsultorioController::class,
            'actividadEspecialistas'
        ],

        '/consultorio/psicologos' => [
            ConsultorioController::class,
            'psicologos'
        ],

        '/consultorio/pacientes' => [
            ConsultorioController::class,
            'pacientes'
        ],

        '/consultorio/pacientes/ver/{id}' => [
            ConsultorioController::class,
            'verPaciente'
        ],

        '/consultorio/pacientes/editar/{id}' => [
            ConsultorioController::class,
            'editarPaciente'
        ],

        '/consultorio/psicologos/nuevo' => [
            ConsultorioController::class,
            'nuevoPsicologo'
        ],

        '/consultorio/psicologos/editar' => [
            ConsultorioController::class,
            'editarPsicologo'
        ],

        /*
        Legacy GET de toggle de estatus: no modifica estado.
        Conservado solo para no romper bookmarks; redirige.
        */
        '/consultorio/psicologos/cambiar-estatus' => [
            ConsultorioController::class,
            'cambiarEstatusPsicologo'
        ],

        '/consultorio/horario' => [
            ConsultorioController::class,
            'horario'
        ],

        '/consultorio/configuracion' => [
            ConsultorioController::class,
            'configuracion'
        ],

        '/consultorio/privacidad/solicitudes' => [
            ConsultorioController::class,
            'solicitudesPrivacidad'
        ],

        '/consultorio/incidencias' => [
            ConsultorioController::class,
            'listarIncidenciasAcceso'
        ],

        '/consultorio/incidencias/{id}' => [
            ConsultorioController::class,
            'verIncidenciaAcceso'
        ],

        '/consultorio/servicios' => [
            ConsultorioController::class,
            'servicios'
        ],

        '/consultorio/servicios/nuevo' => [
            ConsultorioController::class,
            'nuevoServicio'
        ],

        '/consultorio/servicios/ver' => [
            ConsultorioController::class,
            'verServicio'
        ],

        '/consultorio/servicios/editar' => [
            ConsultorioController::class,
            'editarServicio'
        ],

        '/consultorio/servicios/sugerencias' => [
            ConsultorioController::class,
            'sugerenciasServicio'
        ],

        '/consultorio/servicios/sugerencias/ver' => [
            ConsultorioController::class,
            'verSugerenciaServicio'
        ],

        '/consultorio/vista-previa' => [
            ConsultorioController::class,
            'vistaPrevia'
        ],

        /*
        =====================================
                    PACIENTE
        =====================================
        */

        '/paciente' => [
            PacienteController::class,
            'dashboard'
        ],

        '/paciente/mis-citas' => [
            PacienteController::class,
            'misCitas'
        ],

        '/paciente/agendar' => [
            PacienteController::class,
            'agendar'
        ],


'/paciente/horarios-disponibles' => [
    PacienteController::class,
    'horariosDisponibles'
],

'/paciente/dias-disponibles' => [
    PacienteController::class,
    'diasDisponibles'
],

'/paciente/servicios-por-psicologo' => [
    PacienteController::class,
    'serviciosPorPsicologo'
],

'/paciente/historial' => [
    PacienteController::class,
    'historial'
],

'/paciente/cita-detalle' => [
    PacienteController::class,
    'detalleCita'
],

'/paciente/perfil' => [
    PacienteController::class,
    'perfil'
],
        '/paciente/historial' => [
            PacienteController::class,
            'historial'
        ],

        '/paciente/perfil' => [
            PacienteController::class,
            'perfil'
        ],

        '/paciente/perfil/editar' => [
            PacienteController::class,
            'editarPerfil'
        ],

        '/paciente/configuracion' => [
            PacienteController::class,
            'configuracion'
        ],

        '/paciente/notificaciones' => [
            PacienteController::class,
            'notificaciones'
        ],

        /*
        =====================================
                   PSICÓLOGO
        =====================================
        */

        '/psicologo' => [
            PsicologoController::class,
            'dashboard'
        ],

        '/psicologo/perfil' => [
            PsicologoController::class,
            'perfil'
        ],

        '/psicologo/configuracion' => [
            PsicologoController::class,
            'configuracion'
        ],

        '/psicologo/servicios' => [
            PsicologoController::class,
            'servicios'
        ],

        // Legacy retirada: selección manual de servicios.
        // '/psicologo/servicios/seleccionar' => [PsicologoController::class, 'seleccionarServicio'],

        '/psicologo/servicios/editar' => [
            PsicologoController::class,
            'editarServicio'
        ],

        '/psicologo/disponibilidad' => [
            PsicologoController::class,
            'disponibilidad'
        ],

        '/psicologo/agenda' => [
            PsicologoController::class,
            'agenda'
        ],

        '/psicologo/agenda/eventos' => [
            PsicologoController::class,
            'eventosAgenda'
        ],

        '/psicologo/agenda/pendientes-operativos' => [
            PsicologoController::class,
            'pendientesOperativosAgenda'
        ],

        '/psicologo/agenda/horarios-disponibles' => [
            PsicologoController::class,
            'horariosDisponiblesAgenda'
        ],

        '/psicologo/calendario' => [
            PsicologoController::class,
            'calendario'
        ],

        '/psicologo/pacientes' => [
            PsicologoController::class,
            'pacientes'
        ],

        '/psicologo/pacientes/registrar' => [
            PsicologoController::class,
            'registrarPacienteNuevo'
        ],

        '/psicologo/expediente' => [
            PsicologoController::class,
            'expediente'
        ],

        '/psicologo/expedientes' => [
            PsicologoController::class,
            'expedientes'
        ],

        '/psicologo/pacientes/ver/{id}' => [
            PsicologoController::class,
            'verPaciente'
        ],

        '/psicologo/pacientes/ver/{id}/expediente' => [
            PsicologoController::class,
            'expedientePaciente'
        ],

        '/psicologo/pacientes/ver/{id}/historia/nueva' => [
            PsicologoController::class,
            'crearHistoriaClinica'
        ],

        '/psicologo/pacientes/ver/{id}/completar-informacion' => [
            PsicologoController::class,
            'completarInformacionPaciente'
        ],

        '/psicologo/pacientes/historia/editar/{id}' => [
            PsicologoController::class,
            'editarHistoriaClinica'
        ],

        '/psicologo/expediente/{id}/seguimientos/nuevo' => [
            PsicologoController::class,
            'nuevoSeguimiento'
        ],

        '/psicologo/expediente/seguimientos/ver/{id}' => [
            PsicologoController::class,
            'verSeguimiento'
        ],

        '/psicologo/expediente/seguimientos/editar/{id}' => [
            PsicologoController::class,
            'editarSeguimiento'
        ],
        /*
        =====================================
                  NOTIFICACIONES
        =====================================
        */

        '/notificaciones' => [
            NotificacionController::class,
            'index'
        ],

        '/notificaciones/recientes' => [
            NotificacionController::class,
            'recientes'
        ],

        '/notificaciones/no-leidas' => [
            NotificacionController::class,
            'contarNoLeidas'
        ],

        '/notificaciones/abrir/{clave}' => [
            NotificacionController::class,
            'abrir'
        ],

        /*
        =====================================
                     SESIÓN
        =====================================
        */

        '/logout' => [
            AuthController::class,
            'logout'
        ]
    ],

    /*
    =========================================
                  RUTAS POST
    =========================================
    */

    'POST' => [

        /*
        =====================================
                  AUTENTICACIÓN
        =====================================
        */

        '/login' => [
            AuthController::class,
            'autenticar'
        ],

        '/logout' => [
            AuthController::class,
            'logout'
        ],

        '/registro' => [
            AuthController::class,
            'guardar'
        ],

        '/verificar-correo' => [
            AuthController::class,
            'verificarCorreo'
        ],

        '/verificar-correo/reenviar' => [
            AuthController::class,
            'reenviarCodigoVerificacion'
        ],

        '/cambiar-contrasena' => [
            AuthController::class,
            'saveTemporaryPassword'
        ],

        '/forgot-password' => [
            AuthController::class,
            'sendRecoveryCode'
        ],

        '/activar-cuenta' => [
            ActivacionController::class,
            'activar'
        ],

        '/restablecer-acceso-consultorio' => [
            RestablecerAccesoConsultorioController::class,
            'guardar'
        ],

        '/login/ayuda-cuenta' => [
            AuthController::class,
            'reportarAyudaCuenta'
        ],

        '/privacidad/consentimiento' => [
            PrivacidadController::class,
            'guardarConsentimiento'
        ],

        '/privacidad/solicitud' => [
            PrivacidadController::class,
            'solicitarDerechos'
        ],

        '/verify-code' => [
            AuthController::class,
            'validateRecoveryCode'
        ],

        '/new-password' => [
            AuthController::class,
            'updateRecoveredPassword'
        ],


            '/paciente/guardar-cita' => [
    PacienteController::class,
    'guardarCita'
 ],
        /*
        =====================================
                  ADMINISTRADOR
        =====================================
        */

        '/administrador/consultorio/guardar' => [
            AdministradorController::class,
            'guardarConsultorio'
        ],

        '/administrador/consultorio/reenviar-activacion' => [
            AdministradorController::class,
            'reenviarActivacionConsultorio'
        ],

        '/administrador/consultorio/restablecer-acceso' => [
            AdministradorController::class,
            'restablecerAcceso'
        ],

        '/administrador/consultorio/cambiar-estado-cuenta' => [
            AdministradorController::class,
            'cambiarEstadoCuenta'
        ],

        '/administrador/consultorio/actualizar' => [
            AdministradorController::class,
            'actualizarConsultorio'
        ],

        '/administrador/consultorio/cambiar-estatus-institucional' => [
            AdministradorController::class,
            'cambiarEstatusInstitucional'
        ],

        '/administrador/consultorio/eliminar-sin-actividad' => [
            AdministradorController::class,
            'eliminarConsultorioSinActividad'
        ],

        '/administrador/incidencias/{id}/actualizar' => [
            AdministradorController::class,
            'actualizarIncidencia'
        ],

'/paciente/cancelar-cita' => [
    PacienteController::class,
    'cancelarCita'
],

'/paciente/perfil/actualizar' => [
    PacienteController::class,
    'actualizarPerfil'
],

'/paciente/configuracion/cambiar-contrasena' => [
    PacienteController::class,
    'cambiarContrasenaConfiguracion'
],

'/paciente/configuracion/actualizar-telefono' => [
    PacienteController::class,
    'actualizarTelefonoConfiguracion'
],

'/paciente/configuracion/solicitar-cambio-correo' => [
    PacienteController::class,
    'solicitarCambioCorreo'
],

'/paciente/configuracion/verificar-cambio-correo' => [
    PacienteController::class,
    'verificarCambioCorreo'
],

'/paciente/configuracion/reenviar-codigo-correo' => [
    PacienteController::class,
    'reenviarCodigoCorreo'
],

'/paciente/configuracion/cancelar-cambio-correo' => [
    PacienteController::class,
    'cancelarCambioCorreo'
],

/* Legacy POST multiconsultorio: ignoran {id} y redirigen sin operar. */
'/administrador/consultorios/actualizar/{id}' => [
    AdministradorController::class,
    'redirigirCuentaUnicaPost'
],

'/administrador/consultorios/desactivar/{id}' => [
    AdministradorController::class,
    'redirigirCuentaUnicaPost'
],

'/administrador/consultorios/activar/{id}' => [
    AdministradorController::class,
    'redirigirCuentaUnicaPost'
],

'/administrador/consultorios/restablecer-acceso/{id}' => [
    AdministradorController::class,
    'redirigirCuentaUnicaPost'
],

'/administrador/consultorios/reenviar-activacion/{id}' => [
    AdministradorController::class,
    'redirigirCuentaUnicaPost'
],

'/administrador/consultorios/guardar' => [
    AdministradorController::class,
    'redirigirCuentaUnicaPost'
],

        /*
        =====================================
                   CONSULTORIO
        =====================================
        */

        '/consultorio/psicologos/guardar' => [
            ConsultorioController::class,
            'guardarPsicologo'
        ],

        '/consultorio/psicologos/reenviar-activacion' => [
            ConsultorioController::class,
            'reenviarActivacionPsicologo'
        ],

        '/consultorio/psicologos/actualizar' => [
            ConsultorioController::class,
            'actualizarPsicologo'
        ],

        '/consultorio/psicologos/cancelar-registro' => [
            ConsultorioController::class,
            'cancelarRegistroPsicologo'
        ],

        '/consultorio/psicologos/eliminar' => [
            ConsultorioController::class,
            'eliminarPsicologo'
        ],

        '/consultorio/psicologos/desactivar' => [
            ConsultorioController::class,
            'desactivarPsicologo'
        ],

        '/consultorio/psicologos/reactivar' => [
            ConsultorioController::class,
            'reactivarPsicologo'
        ],

        '/consultorio/pacientes/actualizar' => [
            ConsultorioController::class,
            'actualizarPaciente'
        ],

        '/consultorio/pacientes/eliminar' => [
            ConsultorioController::class,
            'eliminarPaciente'
        ],

        '/consultorio/pacientes/inactivar' => [
            ConsultorioController::class,
            'inactivarPaciente'
        ],

        '/consultorio/pacientes/reactivar' => [
            ConsultorioController::class,
            'reactivarPaciente'
        ],

        '/consultorio/horario/guardar' => [
            ConsultorioController::class,
            'guardarHorarioSemana'
        ],

        '/consultorio/horario/actualizar' => [
            ConsultorioController::class,
            'actualizarHorario'
        ],

        '/consultorio/horario/cambiar-estatus' => [
            ConsultorioController::class,
            'cambiarEstatusHorario'
        ],

        '/consultorio/configuracion/actualizar' => [
            ConsultorioController::class,
            'actualizarConfiguracion'
        ],

        '/consultorio/configuracion/redes/guardar' => [
            ConsultorioController::class,
            'guardarRedSocial'
        ],

        '/consultorio/configuracion/redes/actualizar' => [
            ConsultorioController::class,
            'actualizarRedSocial'
        ],

        '/consultorio/configuracion/redes/estado' => [
            ConsultorioController::class,
            'cambiarEstadoRedSocial'
        ],

        '/consultorio/privacidad/solicitudes/responder' => [
            ConsultorioController::class,
            'responderSolicitudPrivacidad'
        ],

        '/consultorio/incidencias/{id}/actualizar' => [
            ConsultorioController::class,
            'actualizarIncidenciaAcceso'
        ],

        '/consultorio/incidencias/{id}/escalar' => [
            ConsultorioController::class,
            'escalarIncidenciaAcceso'
        ],

        '/consultorio/configuracion/logo' => [
            ConsultorioController::class,
            'actualizarLogoConfiguracion'
        ],

        '/consultorio/configuracion/portada' => [
            ConsultorioController::class,
            'actualizarPortadaConfiguracion'
        ],

        '/consultorio/servicios/guardar' => [
            ConsultorioController::class,
            'guardarServicio'
        ],

        '/consultorio/servicios/actualizar' => [
            ConsultorioController::class,
            'actualizarServicio'
        ],

        '/consultorio/servicios/cambiar-estatus' => [
            ConsultorioController::class,
            'cambiarEstatusServicio'
        ],

        '/consultorio/servicios/sugerencias/rechazar' => [
            ConsultorioController::class,
            'rechazarSugerenciaServicio'
        ],

        '/consultorio/publicacion/publicar' => [
            ConsultorioController::class,
            'publicar'
        ],

        '/consultorio/publicacion/ocultar' => [
            ConsultorioController::class,
            'ocultar'
        ],

        /*
        =====================================
                    PACIENTE
        =====================================
        */

        '/paciente/agendar' => [
            PacienteController::class,
            'guardarCita'
        ],
/*
=====================================
            NOTIFICACIONES
=====================================
*/

        '/notificaciones/marcar-leida/{clave}' => [
            NotificacionController::class,
            'marcarLeida'
        ],

        '/notificaciones/marcar-todas-leidas' => [
            NotificacionController::class,
            'marcarTodasLeidas'
        ],

        '/notificaciones/eliminar/{clave}' => [
            NotificacionController::class,
            'eliminar'
        ],
        /*
        =====================================
                   PSICÓLOGO
        =====================================
        */

        '/psicologo/perfil/actualizar' => [
            PsicologoController::class,
            'actualizarPerfil'
        ],

        '/psicologo/perfil/redes/guardar' => [
            PsicologoController::class,
            'guardarRedProfesional'
        ],

        '/psicologo/perfil/redes/actualizar' => [
            PsicologoController::class,
            'actualizarRedProfesional'
        ],

        '/psicologo/perfil/redes/estado' => [
            PsicologoController::class,
            'cambiarEstadoRedProfesional'
        ],

        '/psicologo/configuracion/cambiar-contrasena' => [
            PsicologoController::class,
            'cambiarContrasenaConfiguracion'
        ],

        '/psicologo/configuracion/actualizar-telefono' => [
            PsicologoController::class,
            'actualizarTelefonoConfiguracion'
        ],

        '/psicologo/configuracion/solicitar-cambio-correo' => [
            PsicologoController::class,
            'solicitarCambioCorreo'
        ],

        '/psicologo/configuracion/verificar-cambio-correo' => [
            PsicologoController::class,
            'verificarCambioCorreo'
        ],

        '/psicologo/configuracion/reenviar-codigo-correo' => [
            PsicologoController::class,
            'reenviarCodigoCorreo'
        ],

        '/psicologo/configuracion/cancelar-cambio-correo' => [
            PsicologoController::class,
            'cancelarCambioCorreo'
        ],

        // Legacy retirada: POST de asignación manual.
        // '/psicologo/servicios/guardar' => [PsicologoController::class, 'guardarServicio'],

        '/psicologo/servicios/actualizar' => [
            PsicologoController::class,
            'actualizarServicio'
        ],

        '/psicologo/servicios/cambiar-estatus' => [
            PsicologoController::class,
            'cambiarEstatusServicio'
        ],

        '/psicologo/servicios/sugerir' => [
            PsicologoController::class,
            'sugerirServicio'
        ],

        '/psicologo/disponibilidad/guardar' => [
            PsicologoController::class,
            'guardarDisponibilidad'
        ],

        '/psicologo/disponibilidad/actualizar' => [
            PsicologoController::class,
            'actualizarDisponibilidad'
        ],

        '/psicologo/disponibilidad/cambiar-estatus' => [
            PsicologoController::class,
            'cambiarEstatusDisponibilidad'
        ],

        '/psicologo/agenda/guardar-cita' => [
            PsicologoController::class,
            'guardarCitaAgenda'
        ],

        '/psicologo/pacientes/guardar-nuevo' => [
            PsicologoController::class,
            'guardarPacienteNuevo'
        ],

        '/psicologo/pacientes/reenviar-activacion' => [
            PsicologoController::class,
            'reenviarActivacionPaciente'
        ],

        '/psicologo/pacientes/completar-informacion' => [
            PsicologoController::class,
            'guardarCompletarInformacionPaciente'
        ],

        '/psicologo/agenda/registrar-asistencia' => [
            PsicologoController::class,
            'registrarAsistenciaCita'
        ],

        '/psicologo/pacientes/historia/guardar' => [
            PsicologoController::class,
            'guardarHistoriaClinica'
        ],

        '/psicologo/pacientes/historia/actualizar' => [
            PsicologoController::class,
            'actualizarHistoriaClinica'
        ],

        '/psicologo/expediente/seguimientos/guardar' => [
            PsicologoController::class,
            'guardarSeguimiento'
        ],

        '/psicologo/expediente/seguimientos/actualizar' => [
            PsicologoController::class,
            'actualizarSeguimiento'
        ]
    ]
];