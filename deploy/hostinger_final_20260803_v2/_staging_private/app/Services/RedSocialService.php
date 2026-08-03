<?php

namespace App\Services;

use App\Models\RedSocialConsultorio;
use App\Models\RedSocialPsicologo;

class RedSocialService
{
    private RedSocialUrlValidator $validator;
    private RedSocialConsultorio $consultorioModel;
    private RedSocialPsicologo $psicologoModel;

    public function __construct(
        ?RedSocialUrlValidator $validator = null,
        ?RedSocialConsultorio $consultorioModel = null,
        ?RedSocialPsicologo $psicologoModel = null
    ) {
        $this->validator = $validator ?? new RedSocialUrlValidator();
        $this->consultorioModel = $consultorioModel ?? new RedSocialConsultorio();
        $this->psicologoModel = $psicologoModel ?? new RedSocialPsicologo();
    }

    /**
     * @return array{ok: bool, mensaje?: string, datos?: array<string, mixed>}
     */
    public function normalizarEntrada(array $post): array
    {
        $tipo = trim((string) ($post['tipoRed'] ?? ''));
        $url = trim((string) ($post['urlRed'] ?? ''));
        $etiqueta = trim((string) ($post['etiquetaRed'] ?? ''));
        $estado = strtoupper(trim((string) ($post['estadoRed'] ?? 'ACTIVA')));
        $orden = trim((string) ($post['ordenRed'] ?? '1'));

        if (!$this->validator->plataformaValida($tipo)) {
            return ['ok' => false, 'mensaje' => 'La plataforma seleccionada no es válida.'];
        }

        $urlCheck = $this->validator->validar($url, $tipo);
        if (!$urlCheck['ok']) {
            return ['ok' => false, 'mensaje' => (string) ($urlCheck['mensaje'] ?? 'URL inválida.')];
        }

        if ($etiqueta !== '' && mb_strlen($etiqueta) > 60) {
            return ['ok' => false, 'mensaje' => 'La etiqueta no puede superar 60 caracteres.'];
        }

        if ($etiqueta !== '' && $etiqueta !== strip_tags($etiqueta)) {
            return ['ok' => false, 'mensaje' => 'La etiqueta no puede contener HTML.'];
        }

        if (!in_array($estado, ['ACTIVA', 'INACTIVA'], true)) {
            return ['ok' => false, 'mensaje' => 'El estado de la red no es válido.'];
        }

        if ($orden === '' || !ctype_digit($orden) || (int) $orden < 1 || (int) $orden > 9999) {
            return ['ok' => false, 'mensaje' => 'El orden debe ser un entero entre 1 y 9999.'];
        }

        return [
            'ok' => true,
            'datos' => [
                'TipoRed' => $tipo,
                'URLRed' => (string) $urlCheck['url'],
                'EtiquetaRed' => $etiqueta !== '' ? $etiqueta : null,
                'EstadoRed' => $estado,
                'OrdenRed' => (int) $orden
            ]
        ];
    }

    /**
     * @return array{ok: bool, mensaje: string}
     */
    public function crearParaConsultorio(string $clvCons, array $post): array
    {
        $norm = $this->normalizarEntrada($post);
        if (!$norm['ok']) {
            return ['ok' => false, 'mensaje' => (string) $norm['mensaje']];
        }

        $datos = $norm['datos'];
        $datos['ClvCons'] = trim($clvCons);
        $datos['ClvRed'] = ClaveService::generar('redsocial', 'ClvRed', 'RED');

        $ok = $this->consultorioModel->crear($datos);

        return [
            'ok' => $ok,
            'mensaje' => $ok
                ? 'La red se registró correctamente.'
                : 'No fue posible registrar la red.'
        ];
    }

    /**
     * @return array{ok: bool, mensaje: string}
     */
    public function actualizarParaConsultorio(
        string $clvCons,
        string $clvRed,
        array $post
    ): array {
        if ($this->consultorioModel->obtenerPropia($clvRed, $clvCons) === null) {
            return ['ok' => false, 'mensaje' => 'La red no existe o no pertenece a tu consultorio.'];
        }

        $norm = $this->normalizarEntrada($post);
        if (!$norm['ok']) {
            return ['ok' => false, 'mensaje' => (string) $norm['mensaje']];
        }

        $ok = $this->consultorioModel->actualizar(
            $clvRed,
            $clvCons,
            $norm['datos']
        );

        return [
            'ok' => $ok,
            'mensaje' => $ok
                ? 'La red se actualizó correctamente.'
                : 'No fue posible actualizar la red.'
        ];
    }

    /**
     * @return array{ok: bool, mensaje: string}
     */
    public function cambiarEstadoConsultorio(
        string $clvCons,
        string $clvRed,
        string $accion
    ): array {
        if ($this->consultorioModel->obtenerPropia($clvRed, $clvCons) === null) {
            return ['ok' => false, 'mensaje' => 'La red no existe o no pertenece a tu consultorio.'];
        }

        $estado = strtolower($accion) === 'activar' ? 'ACTIVA' : 'INACTIVA';
        if (!in_array(strtolower($accion), ['activar', 'inactivar'], true)) {
            return ['ok' => false, 'mensaje' => 'Acción no válida.'];
        }

        $ok = $this->consultorioModel->cambiarEstado($clvRed, $clvCons, $estado);

        return [
            'ok' => $ok,
            'mensaje' => $ok
                ? ($estado === 'ACTIVA' ? 'La red fue activada.' : 'La red fue inactivada.')
                : 'No fue posible cambiar el estado.'
        ];
    }

    /**
     * @return array{ok: bool, mensaje: string}
     */
    public function crearParaPsicologo(string $clvPsi, array $post): array
    {
        $norm = $this->normalizarEntrada($post);
        if (!$norm['ok']) {
            return ['ok' => false, 'mensaje' => (string) $norm['mensaje']];
        }

        $datos = $norm['datos'];
        $datos['ClvPsi'] = trim($clvPsi);
        $id = $this->psicologoModel->crear($datos);

        return [
            'ok' => $id > 0,
            'mensaje' => $id > 0
                ? 'La red profesional se registró correctamente.'
                : 'No fue posible registrar la red profesional.'
        ];
    }

    /**
     * @return array{ok: bool, mensaje: string}
     */
    public function actualizarParaPsicologo(
        string $clvPsi,
        int $id,
        array $post
    ): array {
        if ($this->psicologoModel->obtenerPropia($id, $clvPsi) === null) {
            return ['ok' => false, 'mensaje' => 'La red no existe o no te pertenece.'];
        }

        $norm = $this->normalizarEntrada($post);
        if (!$norm['ok']) {
            return ['ok' => false, 'mensaje' => (string) $norm['mensaje']];
        }

        $ok = $this->psicologoModel->actualizar($id, $clvPsi, $norm['datos']);

        return [
            'ok' => $ok,
            'mensaje' => $ok
                ? 'La red profesional se actualizó correctamente.'
                : 'No fue posible actualizar la red profesional.'
        ];
    }

    /**
     * @return array{ok: bool, mensaje: string}
     */
    public function cambiarEstadoPsicologo(
        string $clvPsi,
        int $id,
        string $accion
    ): array {
        if ($this->psicologoModel->obtenerPropia($id, $clvPsi) === null) {
            return ['ok' => false, 'mensaje' => 'La red no existe o no te pertenece.'];
        }

        if (!in_array(strtolower($accion), ['activar', 'inactivar'], true)) {
            return ['ok' => false, 'mensaje' => 'Acción no válida.'];
        }

        $estado = strtolower($accion) === 'activar' ? 'ACTIVA' : 'INACTIVA';
        $ok = $this->psicologoModel->cambiarEstado($id, $clvPsi, $estado);

        return [
            'ok' => $ok,
            'mensaje' => $ok
                ? ($estado === 'ACTIVA' ? 'La red fue activada.' : 'La red fue inactivada.')
                : 'No fue posible cambiar el estado.'
        ];
    }
}
