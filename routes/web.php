<?php

use App\Controllers\HomeController;
use App\Controllers\AuthController;
use App\Controllers\PacienteController;
use App\Controllers\PsicologoController;
use App\Controllers\ConsultorioController;
use App\Controllers\AdministradorController;
use App\Controllers\NotificacionController;

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

        '/verify-code' => [
            AuthController::class,
            'verifyCode'
        ],

        '/new-password' => [
            AuthController::class,
            'newPassword'
        ],

        '/especialista/perfil' => [
            HomeController::class,
            'perfilEspecialista'
        ],

        '/agendar-cita' => [
            HomeController::class,
            'agendarCita'
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

'/notificaciones/abrir/{clave}' => [
    NotificacionController::class,
    'abrir'
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

        '/psicologo/servicios' => [
            PsicologoController::class,
            'servicios'
        ],

        '/psicologo/servicios/nuevo' => [
            PsicologoController::class,
            'nuevoServicio'
        ],

        '/psicologo/servicios/editar/{id}' => [
            PsicologoController::class,
            'editarServicio'
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

        /*
        =====================================
                   CONSULTORIO
        =====================================
        */

        '/consultorio/psicologos/guardar' => [
            ConsultorioController::class,
            'guardarPsicologo'
        ],

        '/consultorio/psicologos/actualizar' => [
            ConsultorioController::class,
            'actualizarPsicologo'
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

        '/psicologo/servicios/asignar' => [
            PsicologoController::class,
            'asignarServicio'
        ],

        '/psicologo/servicios/guardar' => [
            PsicologoController::class,
            'guardarServicio'
        ],

        '/psicologo/servicios/actualizar' => [
            PsicologoController::class,
            'actualizarServicio'
        ],

        '/psicologo/servicios/cambiarEstado' => [
            PsicologoController::class,
            'cambiarEstadoServicio'
        ],

        '/psicologo/servicios/cambiar-estatus' => [
            PsicologoController::class,
            'cambiarEstatusServicio'
        ]
    ]
];