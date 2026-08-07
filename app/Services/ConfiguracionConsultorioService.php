<?php

namespace App\Services;

use App\Config\Database;
use App\Config\Paths;
use App\Models\Caracteristica;
use App\Models\Consultorio;
use App\Models\Direccion;
use RuntimeException;

class ConfiguracionConsultorioService
{
    private Consultorio $consultorioModel;

    private Direccion $direccionModel;

    private Caracteristica $caracteristicaModel;

    public function __construct()
    {
        $this->consultorioModel = new Consultorio();
        $this->direccionModel = new Direccion();
        $this->caracteristicaModel = new Caracteristica();
    }

    public function guardarConfiguracion(
        string $clvCons,
        array $datosConsultorio,
        array $datosDireccion,
        array $caracteristicas
    ): void {
        if (
            !$this->direccionModel->perteneceAlConsultorio(
                $datosDireccion['ClvDir'] ?? '',
                $clvCons
            )
        ) {
            throw new RuntimeException(
                'No tienes permiso para modificar esta dirección.'
            );
        }

        $db = Database::connect();

        $db->beginTransaction();

        try {
            $this->consultorioModel->actualizarConfiguracionGeneral(
                $clvCons,
                $datosConsultorio
            );

            $this->direccionModel->actualizarPorConsultorio(
                $clvCons,
                $datosDireccion
            );

            foreach ($caracteristicas as $clave => $datosCar) {
                if (
                    !$this->caracteristicaModel->perteneceAlConsultorio(
                        $clave,
                        $clvCons
                    )
                ) {
                    continue;
                }

                $this->caracteristicaModel->actualizarPorConsultorio(
                    $clave,
                    $clvCons,
                    $datosCar
                );
            }

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();

            throw $e;
        }
    }

    public function guardarLogotipo(
        string $clvCons,
        array $archivo
    ): string {
        $rutaRelativa = $this->procesarArchivoLogotipo($archivo);
        $logotipoAnterior =
            $this->consultorioModel->obtenerLogotipoActual($clvCons);

        $db = Database::connect();

        $db->beginTransaction();

        try {
            $actualizado = $this->consultorioModel->actualizarLogotipo(
                $clvCons,
                $rutaRelativa
            );

            if (!$actualizado) {
                throw new RuntimeException(
                    'No fue posible actualizar el logotipo.'
                );
            }

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();

            $this->eliminarArchivoLogotipo($rutaRelativa);

            throw $e;
        }

        $this->eliminarLogotipoAnterior($logotipoAnterior, $rutaRelativa);

        return $rutaRelativa;
    }

    public function guardarPortada(
        string $clvCons,
        array $archivo
    ): string {
        $rutaRelativa = $this->procesarArchivoPortada($archivo);
        $portadaAnterior =
            $this->consultorioModel->obtenerImagenPortadaActual($clvCons);

        $db = Database::connect();

        $db->beginTransaction();

        try {
            $actualizado = $this->consultorioModel->actualizarImagenPortada(
                $clvCons,
                $rutaRelativa
            );

            if (!$actualizado) {
                throw new RuntimeException(
                    'No fue posible actualizar la portada.'
                );
            }

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();

            $this->eliminarArchivoPortada($rutaRelativa);

            throw $e;
        }

        $this->eliminarPortadaAnterior($portadaAnterior, $rutaRelativa);

        return $rutaRelativa;
    }

    private function procesarArchivoPortada(array $archivo): string
    {
        if (
            !isset(
                $archivo['tmp_name'],
                $archivo['size'],
                $archivo['error']
            )
        ) {
            throw new RuntimeException(
                'El archivo recibido no es válido.'
            );
        }

        if ($archivo['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException(
                'Ocurrió un error al subir la portada.'
            );
        }

        if ((int) $archivo['size'] > 3 * 1024 * 1024) {
            throw new RuntimeException(
                'La portada no debe superar 3 MB.'
            );
        }

        $tiposPermitidos = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp'
        ];

        $finfo = new \finfo(FILEINFO_MIME_TYPE);

        $tipoMime = $finfo->file($archivo['tmp_name']);

        if (
            !is_string($tipoMime) ||
            !isset($tiposPermitidos[$tipoMime])
        ) {
            throw new RuntimeException(
                'La portada debe ser JPG, JPEG, PNG o WEBP.'
            );
        }

        $infoImagen = @getimagesize($archivo['tmp_name']);

        if ($infoImagen === false) {
            throw new RuntimeException(
                'El archivo no es una imagen válida.'
            );
        }

        $ancho = (int) ($infoImagen[0] ?? 0);
        $alto = (int) ($infoImagen[1] ?? 0);

        if (
            $ancho < 200 ||
            $alto < 200 ||
            $ancho > 6000 ||
            $alto > 6000
        ) {
            throw new RuntimeException(
                'La portada debe medir entre 200 y 6000 píxeles '
                . 'de ancho y alto.'
            );
        }

        $extension = $tiposPermitidos[$tipoMime];

        $nombreArchivo =
            'portada_' .
            bin2hex(random_bytes(16)) .
            '.' .
            $extension;

        $directorio =
            Paths::publicPath() . '/uploads/consultorios/portadas';

        if (
            !is_dir($directorio) &&
            !mkdir($directorio, 0775, true) &&
            !is_dir($directorio)
        ) {
            throw new RuntimeException(
                'No fue posible crear la carpeta de portadas.'
            );
        }

        $rutaDestino =
            $directorio .
            DIRECTORY_SEPARATOR .
            $nombreArchivo;

        if (
            !move_uploaded_file(
                $archivo['tmp_name'],
                $rutaDestino
            )
        ) {
            throw new RuntimeException(
                'No fue posible guardar la portada.'
            );
        }

        return 'uploads/consultorios/portadas/' . $nombreArchivo;
    }

    private function eliminarPortadaAnterior(
        ?string $portadaAnterior,
        string $nuevaPortada
    ): void {
        if (
            $portadaAnterior === null ||
            $portadaAnterior === $nuevaPortada
        ) {
            return;
        }

        $this->eliminarArchivoPortada($portadaAnterior);
    }

    private function eliminarArchivoPortada(string $rutaRelativa): void
    {
        if (
            !str_starts_with(
                $rutaRelativa,
                'uploads/consultorios/portadas/'
            )
        ) {
            return;
        }

        $nombre = basename($rutaRelativa);

        if (
            $nombre === '' ||
            str_contains($nombre, '..')
        ) {
            return;
        }

        $rutaCompleta =
            Paths::publicPath() . '/uploads/consultorios/portadas' .
            DIRECTORY_SEPARATOR .
            $nombre;

        if (is_file($rutaCompleta)) {
            @unlink($rutaCompleta);
        }
    }

    private function procesarArchivoLogotipo(array $archivo): string
    {
        if (
            !isset(
                $archivo['tmp_name'],
                $archivo['size'],
                $archivo['error']
            )
        ) {
            throw new RuntimeException(
                'El archivo recibido no es válido.'
            );
        }

        if ($archivo['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException(
                'Ocurrió un error al subir el logotipo.'
            );
        }

        if ((int) $archivo['size'] > 2 * 1024 * 1024) {
            throw new RuntimeException(
                'El logotipo no debe superar 2 MB.'
            );
        }

        $tiposPermitidos = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp'
        ];

        $finfo = new \finfo(FILEINFO_MIME_TYPE);

        $tipoMime = $finfo->file($archivo['tmp_name']);

        if (
            !is_string($tipoMime) ||
            !isset($tiposPermitidos[$tipoMime])
        ) {
            throw new RuntimeException(
                'El logotipo debe ser JPG, JPEG, PNG o WEBP.'
            );
        }

        $extension = $tiposPermitidos[$tipoMime];

        $nombreArchivo =
            'logo_' .
            bin2hex(random_bytes(12)) .
            '.' .
            $extension;

        $directorio =
            Paths::publicPath() . '/uploads/consultorios';

        if (
            !is_dir($directorio) &&
            !mkdir($directorio, 0775, true) &&
            !is_dir($directorio)
        ) {
            throw new RuntimeException(
                'No fue posible crear la carpeta de logotipos.'
            );
        }

        $rutaDestino =
            $directorio .
            DIRECTORY_SEPARATOR .
            $nombreArchivo;

        if (
            !move_uploaded_file(
                $archivo['tmp_name'],
                $rutaDestino
            )
        ) {
            throw new RuntimeException(
                'No fue posible guardar el logotipo.'
            );
        }

        return 'uploads/consultorios/' . $nombreArchivo;
    }

    private function eliminarLogotipoAnterior(
        ?string $logotipoAnterior,
        string $nuevoLogotipo
    ): void {
        if (
            $logotipoAnterior === null ||
            $logotipoAnterior === $nuevoLogotipo
        ) {
            return;
        }

        $this->eliminarArchivoLogotipo($logotipoAnterior);
    }

    private function eliminarArchivoLogotipo(string $rutaRelativa): void
    {
        if (!str_starts_with($rutaRelativa, 'uploads/consultorios/')) {
            return;
        }

        $nombre = basename($rutaRelativa);

        if (
            $nombre === '' ||
            str_contains($nombre, '..')
        ) {
            return;
        }

        $rutaCompleta =
            Paths::publicPath() . '/uploads/consultorios' .
            DIRECTORY_SEPARATOR .
            $nombre;

        if (is_file($rutaCompleta)) {
            @unlink($rutaCompleta);
        }
    }
}
