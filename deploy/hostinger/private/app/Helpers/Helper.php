<?php

namespace App\Helpers;

use App\Config\Config;

class Helper
{
    public static function baseUrl(string $path = ''): string
    {
        $base = rtrim((string) Config::get('APP_URL', ''), '/');
        $path = ltrim(str_replace('\\', '/', $path), '/');

        if ($path === '') {
            return $base;
        }

        return $base . '/' . $path;
    }

    /**
     * URL de asset público con versión por filemtime (anti-caché al cambiar el archivo).
     */
    public static function assetUrl(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        $url = self::baseUrl($path);
        $fisica = self::rutaPublicaFisica($path);

        if ($fisica !== null && is_file($fisica)) {
            return $url . '?v=' . filemtime($fisica);
        }

        return $url;
    }

    /**
     * Logo general de PsicoMatch (sin consultorio implícito).
     */
    public static function logotipoPlataformaUrl(): string
    {
        $candidatos = [
            'assets/img/logo/psicomatch.png',
            'assets/img/logo/logo-psicomatch.png',
            'assets/img/logo/logo-temporal.png'
        ];

        foreach ($candidatos as $ruta) {
            if (self::archivoPublicoExiste($ruta)) {
                return self::baseUrl($ruta);
            }
        }

        return '';
    }

    /**
     * Ruta de panel según rol autenticado.
     */
    public static function rutaPanelPorRol(string $rol): string
    {
        return match (strtoupper(trim($rol))) {
            'ADMINISTRADOR' => 'administrador',
            'CONSULTORIO' => 'consultorio',
            'PSICOLOGO' => 'psicologo',
            'PACIENTE' => 'paciente',
            default => 'login'
        };
    }

    public static function logotipoConsultorioUrl(
        ?string $logotipo,
        bool $incluirPredeterminada = false
    ): string {
        $logotipo = trim((string) $logotipo);

        if ($logotipo !== '') {
            if (!$incluirPredeterminada) {
                if (str_starts_with($logotipo, 'uploads/')) {
                    return self::baseUrl($logotipo);
                }

                return self::baseUrl(
                    'assets/img/logo/' . rawurlencode($logotipo)
                );
            }

            $rutaRelativa = str_starts_with($logotipo, 'uploads/')
                ? str_replace('\\', '/', $logotipo)
                : 'assets/img/logo/' . basename($logotipo);

            if (self::archivoPublicoExiste($rutaRelativa)) {
                return self::baseUrl($rutaRelativa);
            }
        } elseif (!$incluirPredeterminada) {
            return '';
        }

        $predeterminado = 'assets/img/logo/logo-temporal.png';

        if (self::archivoPublicoExiste($predeterminado)) {
            return self::baseUrl($predeterminado);
        }

        return '';
    }

    private static function archivoPublicoExiste(string $rutaRelativa): bool
    {
        $rutaFisica = self::rutaPublicaFisica($rutaRelativa);

        return $rutaFisica !== null && is_file($rutaFisica);
    }

    /**
     * Resuelve la URL pública de la portada del consultorio.
     *
     * Orden: portada configurada válida → imagen predeterminada local → null.
     */
    public static function imagenPortadaConsultorioUrl(
        ?string $ruta,
        bool $incluirPredeterminada = true
    ): ?string {
        $rutaValidada = self::validarRutaPortadaRelativa($ruta);

        if ($rutaValidada !== null) {
            $rutaFisica = self::rutaPublicaFisica($rutaValidada);

            if ($rutaFisica !== null && is_file($rutaFisica)) {
                return self::baseUrl($rutaValidada);
            }
        }

        if (!$incluirPredeterminada) {
            return null;
        }

        $predeterminada = 'assets/img/portada/hero-temporal.png';
        $rutaPredeterminada = self::rutaPublicaFisica($predeterminada);

        if (
            $rutaPredeterminada !== null &&
            is_file($rutaPredeterminada)
        ) {
            return self::baseUrl($predeterminada);
        }

        return null;
    }

    private static function validarRutaPortadaRelativa(
        ?string $ruta
    ): ?string {
        $ruta = trim((string) $ruta);

        if ($ruta === '') {
            return null;
        }

        if (preg_match('#^(https?://|data:|javascript:)#i', $ruta)) {
            return null;
        }

        if (str_contains($ruta, '..')) {
            return null;
        }

        $ruta = str_replace('\\', '/', $ruta);

        $prefijosPermitidos = [
            'uploads/consultorios/portadas/',
            'assets/img/portada/'
        ];

        $permitida = false;

        foreach ($prefijosPermitidos as $prefijo) {
            if (str_starts_with($ruta, $prefijo)) {
                $permitida = true;

                break;
            }
        }

        if (!$permitida) {
            return null;
        }

        $nombre = basename($ruta);

        if (
            $nombre === '' ||
            $nombre === '.' ||
            $nombre === '..'
        ) {
            return null;
        }

        return $ruta;
    }

    private static function rutaPublicaFisica(string $rutaRelativa): ?string
    {
        $rutaRelativa = ltrim(
            str_replace('\\', '/', $rutaRelativa),
            '/'
        );

        $directorioPublico =
            \App\Config\Paths::publicPath() .
            DIRECTORY_SEPARATOR;

        $rutaCompleta =
            $directorioPublico .
            str_replace('/', DIRECTORY_SEPARATOR, $rutaRelativa);

        $realPublico = realpath($directorioPublico);

        if ($realPublico === false) {
            return null;
        }

        $realArchivo = realpath($rutaCompleta);

        if ($realArchivo !== false) {
            if (
                !str_starts_with(
                    $realArchivo,
                    $realPublico . DIRECTORY_SEPARATOR
                )
            ) {
                return null;
            }

            return $realArchivo;
        }

        $directorioPublicoNormalizado = rtrim(
            str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $directorioPublico),
            DIRECTORY_SEPARATOR
        );

        $rutaCompletaNormalizada = str_replace(
            ['/', '\\'],
            DIRECTORY_SEPARATOR,
            $directorioPublico . $rutaRelativa
        );

        if (
            !str_starts_with(
                $rutaCompletaNormalizada,
                $directorioPublicoNormalizado . DIRECTORY_SEPARATOR
            )
        ) {
            return null;
        }

        return $rutaCompletaNormalizada;
    }

    public static function etiquetaDiaHorario(string $dia): string
    {
        $etiquetas = [
            'LUNES' => 'Lunes',
            'MARTES' => 'Martes',
            'MIERCOLES' => 'Miércoles',
            'JUEVES' => 'Jueves',
            'VIERNES' => 'Viernes',
            'SABADO' => 'Sábado',
            'DOMINGO' => 'Domingo'
        ];

        return $etiquetas[strtoupper(trim($dia))] ?? $dia;
    }

    /**
     * Construye dirección legible omitiendo segmentos vacíos.
     *
     * @param array<string, mixed> $datos
     */
    public static function direccionPublicaLegible(array $datos): string
    {
        $segmentos = [];

        $calle = trim((string) ($datos['CalleDir'] ?? ''));

        if ($calle !== '') {
            $lineaCalle = $calle;

            $numExt = trim((string) ($datos['NumExtDir'] ?? ''));

            if ($numExt !== '') {
                $lineaCalle .= ' No. ' . $numExt;
            }

            $numInt = trim((string) ($datos['NumIntDir'] ?? ''));

            if ($numInt !== '') {
                $lineaCalle .= ', Int. ' . $numInt;
            }

            $segmentos[] = $lineaCalle;
        }

        $colonia = trim((string) ($datos['ColoniaDir'] ?? ''));

        if ($colonia !== '') {
            $segmentos[] = 'Col. ' . $colonia;
        }

        $municipio = trim((string) ($datos['MunicipioDir'] ?? ''));
        $estado = trim((string) ($datos['EstadoDir'] ?? ''));

        if ($municipio !== '' && $estado !== '') {
            $segmentos[] = $municipio . ', ' . $estado;
        } elseif ($municipio !== '') {
            $segmentos[] = $municipio;
        } elseif ($estado !== '') {
            $segmentos[] = $estado;
        }

        $pais = trim((string) ($datos['PaisDir'] ?? ''));

        if ($pais !== '' && strcasecmp($pais, 'México') !== 0) {
            $segmentos[] = $pais;
        }

        $codPost = trim((string) ($datos['CodPostDir'] ?? ''));

        if ($codPost !== '') {
            $segmentos[] = 'C.P. ' . $codPost;
        }

        return implode(', ', $segmentos);
    }

    public static function direccionPublicaResumida(array $datos): string
    {
        $completa = self::direccionPublicaLegible($datos);

        if (mb_strlen($completa) <= 120) {
            return $completa;
        }

        return mb_substr($completa, 0, 117) . '...';
    }

    public static function coordenadasPublicasValidas(
        mixed $latitud,
        mixed $longitud
    ): bool {
        if (!is_numeric($latitud) || !is_numeric($longitud)) {
            return false;
        }

        $lat = (float) $latitud;
        $lng = (float) $longitud;

        return $lat >= -90
            && $lat <= 90
            && $lng >= -180
            && $lng <= 180;
    }

    public static function iconoBootstrapSeguro(?string $icono): string
    {
        $icono = trim((string) $icono);

        if (
            $icono !== '' &&
            preg_match('/^bi-[a-z0-9-]+$/', $icono)
        ) {
            return $icono;
        }

        return 'bi-check2-circle';
    }

    public static function textoPoliticaCancelacionPublica(
        int $limiteHoras
    ): string {
        if ($limiteHoras > 0) {
            $textoHoras = $limiteHoras === 1
                ? '1 hora'
                : $limiteHoras . ' horas';

            return 'Las citas pueden cancelarse con al menos '
                . $textoHoras
                . ' de anticipación.';
        }

        return 'Para conocer las condiciones de cancelación, '
            . 'comunícate directamente con el consultorio.';
    }

    public static function formatearHoraPublica(?string $hora): string
    {
        $hora = trim((string) $hora);

        if ($hora === '') {
            return '';
        }

        $partes = explode(':', $hora);

        return sprintf(
            '%02d:%02d',
            (int) ($partes[0] ?? 0),
            (int) ($partes[1] ?? 0)
        );
    }

    /**
     * URL pública de fotografía de perfil (persona.FotoPerfilPer).
     * Acepta nombre de archivo o ruta relativa bajo uploads/perfiles/.
     * Devuelve null si la ruta es inválida o el archivo no existe.
     */
    public static function fotoPerfilUrl(?string $ruta): ?string
    {
        $ruta = trim((string) $ruta);

        if ($ruta === '') {
            return null;
        }

        $ignoradas = [
            'default.png',
            'perfil-default.png',
            'assets/img/default.png'
        ];

        if (in_array($ruta, $ignoradas, true)) {
            return null;
        }

        if (
            preg_match('#^(javascript:|data:)#i', $ruta)
            || str_contains($ruta, '..')
            || preg_match('#^(https?:)?//#i', $ruta)
        ) {
            return null;
        }

        $ruta = str_replace('\\', '/', $ruta);

        if (str_starts_with($ruta, 'public/')) {
            $ruta = substr($ruta, 7);
        }

        $ruta = ltrim($ruta, '/');

        if (str_starts_with($ruta, 'uploads/perfiles/')) {
            $relativa = $ruta;
        } elseif (preg_match(
            '/^[A-Za-z0-9._-]+\.(jpe?g|png|webp)$/i',
            basename($ruta)
        )) {
            $relativa = 'uploads/perfiles/' . basename($ruta);
        } else {
            return null;
        }

        if (
            !preg_match(
                '#^uploads/perfiles/[A-Za-z0-9._-]+\.(jpe?g|png|webp)$#i',
                $relativa
            )
        ) {
            return null;
        }

        $fisica = self::rutaPublicaFisica($relativa);

        if ($fisica === null || !is_file($fisica)) {
            return null;
        }

        $version = (string) filemtime($fisica);

        return self::baseUrl($relativa) . '?v=' . rawurlencode($version);
    }

    /**
     * Hasta dos iniciales a partir de nombre y apellidos.
     */
    public static function inicialesPersona(
        ?string $nombre = '',
        ?string $apPat = '',
        ?string $apMat = ''
    ): string {
        $partes = [];

        foreach ([$nombre, $apPat, $apMat] as $parte) {
            $parte = trim((string) $parte);

            if ($parte !== '') {
                $partes[] = $parte;
            }
        }

        if (
            count($partes) === 1
            && str_contains($partes[0], ' ')
        ) {
            $partes = preg_split('/\s+/u', $partes[0]) ?: [];
            $partes = array_values(
                array_filter(
                    $partes,
                    static fn(string $p): bool => $p !== ''
                )
            );
        }

        $iniciales = '';

        foreach (array_slice($partes, 0, 2) as $parte) {
            $iniciales .= mb_strtoupper(
                mb_substr($parte, 0, 1, 'UTF-8'),
                'UTF-8'
            );
        }

        return $iniciales !== '' ? $iniciales : '?';
    }
}