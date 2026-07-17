<?php

use App\Controllers\HomeController;
use App\Controllers\AuthController;
use App\Controllers\PacienteController;
use App\Controllers\PsicologoController;
use App\Controllers\ConsultorioController;

return [

    'GET' => [

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
       
'/psicologo/perfil' => [
    PsicologoController::class,
    'perfil'
],
        '/consultorio/psicologos' => [
            ConsultorioController::class,
            'psicologos'
        ],

        '/consultorio/psicologos/nuevo' => [
            ConsultorioController::class,
            'nuevoPsicologo'
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

        '/paciente' => [
            PacienteController::class,
            'dashboard'
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

        '/psicologo' => [
            PsicologoController::class,
            'dashboard'
        ],
'/consultorio/psicologos/editar' => [
    ConsultorioController::class,
    'editarPsicologo'
],
'/especialista/perfil' => [
    HomeController::class,
    'perfilEspecialista'
],

'/agendar-cita' => [
    HomeController::class,
    'agendarCita'
],
'/consultorio/psicologos/cambiar-estatus' => [
    ConsultorioController::class,
    'cambiarEstatusPsicologo'
],
        '/logout' => [
            AuthController::class,
            'logout'
        ]

    ],

    'POST' => [

        '/login' => [
            AuthController::class,
            'autenticar'
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
],
'/psicologo/perfil/actualizar' => [
    PsicologoController::class,
    'actualizarPerfil'
],
        '/registro' => [
            AuthController::class,
            'guardar'
        ],
        '/cambiar-contrasena' => [
    AuthController::class,
    'saveTemporaryPassword'
],
'/consultorio/psicologos/actualizar' => [
    ConsultorioController::class,
    'actualizarPsicologo'
],
        '/consultorio/psicologos/guardar' => [
            ConsultorioController::class,
            'guardarPsicologo'
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
        ]

    ]

];