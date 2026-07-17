<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Consultorio;

class HomeController extends Controller
{
   public function index(): void
{
    $consultorioModel = new Consultorio();

    $consultorio = $consultorioModel->obtenerInformacion();

    $servicios = $consultorioModel->obtenerServicios();

    $horarios = $consultorioModel->obtenerHorarios();

    $redes = $consultorioModel->obtenerRedes();

    $caracteristicas =
        $consultorioModel->obtenerCaracteristicas();

    $especialistas = [];

    if (!empty($consultorio['ClvCons'])) {

        $especialistas =
            $consultorioModel->obtenerEspecialistasPublicos(
                $consultorio['ClvCons']
            );

        foreach ($especialistas as &$especialista) {

            $especialista['servicios'] =
                $consultorioModel
                    ->obtenerServiciosPublicosPsicologo(
                        $especialista['ClvPsi']
                    );
        }

        unset($especialista);
    }

    $this->view(
        'home/index',
        [
            'titulo' => 'Inicio',
            'consultorio' => $consultorio,
            'servicios' => $servicios,
            'horarios' => $horarios,
            'redes' => $redes,
            'caracteristicas' => $caracteristicas,
            'especialistas' => $especialistas
        ]
    );
}
}