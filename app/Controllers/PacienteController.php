<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Core\Response;

class PacienteController extends Controller
{
    public function dashboard()
    {
        if (!Session::has('usuario')) {
            Response::redirect('login');
        }

        $usuario = Session::get('usuario');

        if ($usuario['RolUsu'] !== 'PACIENTE') {
            Response::redirect('login');
        }

        echo "<h1>Bienvenido Paciente</h1>";
        echo "<pre>";
        print_r($usuario);
        echo "</pre>";
    }
}