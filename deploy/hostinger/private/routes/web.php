<?php

use App\Controllers\HomeController;
use App\Controllers\AuthController;
use App\Controllers\PacienteController;
use App\Controllers\PsicologoController;
use App\Controllers\ConsultorioController;
use App\Controllers\AdministradorController;
use App\Controllers\NotificacionController;
use App\Controllers\ActivacionController;

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

        /*
        =====================================
                  ADMINISTRADOR
        =====================================
        */

   '/administrador' => [
    AdministradorController::class,
    'dashboard'
],

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
    'verConsultorio'
],

'/administrador/consultorios/vista-previa/{id}' => [
    AdministradorController::class,
    'vistaPreviaConsultorio'
],

'/administrador/consultorios/editar/{id}' => [
    AdministradorController::class,
    'editarConsultorio'
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

        '/consultorio/psicologos' => [
            ConsultorioController::class,
            'psicologos'
        ],

        '/consultorio/psicologos/nuevo' => [
            ConsultorioController::class,
            'nuevoPsicologo'
        ],

        '/consultorio/psicologos/editar' => [
            ConsultorioController::class,
            'editarPsicologo'
        ],

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

        '/psicologo/servicios/seleccionar' => [
            PsicologoController::class,
            'seleccionarServicio'
        ],

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

        '/administrador/consultorios/guardar' => [
    AdministradorController::class,
    'guardarConsultorio'

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

'/administrador/consultorios/actualizar/{id}' => [
    AdministradorController::class,
    'actualizarConsultorio'
],


'/administrador/consultorios/desactivar/{id}' => [
    AdministradorController::class,
    'desactivarConsultorio'
],

'/administrador/consultorios/activar/{id}' => [
    AdministradorController::class,
    'activarConsultorio'
],

'/administrador/consultorios/restablecer-acceso/{id}' => [
    AdministradorController::class,
    'restablecerAcceso'
],

'/administrador/consultorios/reenviar-activacion/{id}' => [
    AdministradorController::class,
    'reenviarActivacionConsultorio'
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

        '/psicologo/servicios/guardar' => [
            PsicologoController::class,
            'guardarServicio'
        ],

        '/psicologo/servicios/actualizar' => [
            PsicologoController::class,
            'actualizarServicio'
        ],

        '/psicologo/servicios/cambiar-estatus' => [
            PsicologoController::class,
            'cambiarEstatusServicio'
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