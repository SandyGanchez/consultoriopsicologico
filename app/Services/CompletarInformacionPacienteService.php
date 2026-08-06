<?php

namespace App\Services;

use App\Models\Cita;
use App\Models\HistorialClinico;
use App\Models\Paciente;
use RuntimeException;
use Throwable;

/**
 * Completa solo campos personales/dirección vacíos del paciente.
 * No administra cuenta (correo, teléfono, contraseña).
 */
class CompletarInformacionPacienteService
{
    public const CAMPOS_PERSONA = [
        'NombrePer',
        'ApPatPer',
        'ApMatPer',
        'FechaNacimiento',
        'GeneroPer'
    ];

    public const CAMPOS_DIRECCION = [
        'PaisDir',
        'EstadoDir',
        'MunicipioDir',
        'ColoniaDir',
        'CalleDir',
        'CodPostDir',
        'NumExtDir',
        'NumIntDir',
        'ReferenciaDir'
    ];

    /** Columnas NOT NULL de direccion al crear registro nuevo. */
    public const CAMPOS_DIRECCION_OBLIGATORIOS_ALTA = [
        'PaisDir',
        'EstadoDir',
        'MunicipioDir',
        'ColoniaDir',
        'CodPostDir'
    ];

    public const GENEROS = ['Masculino', 'Femenino', 'Otro'];

    public const RETORNOS = [
        'detalle',
        'historia_nueva',
        'historia_externa',
        'expediente'
    ];

    private Paciente $pacienteModel;
    private HistorialClinico $historialModel;
    private Cita $citaModel;

    public function __construct(
        ?Paciente $pacienteModel = null,
        ?HistorialClinico $historialModel = null,
        ?Cita $citaModel = null
    ) {
        $this->pacienteModel = $pacienteModel ?? new Paciente();
        $this->historialModel = $historialModel ?? new HistorialClinico();
        $this->citaModel = $citaModel ?? new Cita();
    }

    /**
     * @return array{
     *   ok: bool,
     *   mensaje?: string,
     *   paciente?: array,
     *   faltantes?: array{persona: list<string>, direccion: list<string>},
     *   valores?: array<string, mixed>,
     *   tieneFaltantes?: bool
     * }
     */
    public function prepararFormulario(
        string $clvPac,
        string $clvPsi,
        string $clvCons
    ): array {
        $autorizacion = $this->autorizar($clvPac, $clvPsi, $clvCons);

        if (!$autorizacion['ok']) {
            return $autorizacion;
        }

        $datos = $this->pacienteModel->obtenerDatosPersonalesParaPsicologo(
            $clvPac,
            $clvPsi,
            $clvCons
        );

        if ($datos === null) {
            return [
                'ok' => false,
                'mensaje' => 'No tienes autorización para consultar este paciente.'
            ];
        }

        $faltantes = $this->identificarFaltantes($datos);

        if (
            $faltantes['persona'] === []
            && $faltantes['direccion'] === []
        ) {
            return [
                'ok' => false,
                'mensaje' => 'La información del paciente ya está completa.',
                'paciente' => $datos,
                'faltantes' => $faltantes,
                'tieneFaltantes' => false
            ];
        }

        return [
            'ok' => true,
            'paciente' => $datos,
            'faltantes' => $faltantes,
            'valores' => $this->extraerValoresActuales($datos),
            'tieneFaltantes' => true
        ];
    }

    /**
     * @return array{ok: bool, mensaje?: string, tieneFaltantes?: bool, faltantes?: array}
     */
    public function evaluarFaltantes(
        string $clvPac,
        string $clvPsi,
        string $clvCons
    ): array {
        $autorizacion = $this->autorizar($clvPac, $clvPsi, $clvCons);

        if (!$autorizacion['ok']) {
            return [
                'ok' => false,
                'tieneFaltantes' => false
            ];
        }

        $datos = $this->pacienteModel->obtenerDatosPersonalesParaPsicologo(
            $clvPac,
            $clvPsi,
            $clvCons
        );

        if ($datos === null) {
            return [
                'ok' => false,
                'tieneFaltantes' => false
            ];
        }

        $faltantes = $this->identificarFaltantes($datos);
        $tiene = $faltantes['persona'] !== [] || $faltantes['direccion'] !== [];

        return [
            'ok' => true,
            'tieneFaltantes' => $tiene,
            'faltantes' => $faltantes,
            'paciente' => $datos
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @return array{ok: bool, mensaje: string, clvPac?: string, omitidos?: list<string>}
     */
    public function guardar(
        string $clvPsi,
        string $clvCons,
        array $post
    ): array {
        $clvPac = trim((string) ($post['ClvPac'] ?? ''));
        $retornoSolicitado = strtolower(trim((string) ($post['retorno'] ?? 'detalle')));

        if (!in_array($retornoSolicitado, self::RETORNOS, true)) {
            $retornoSolicitado = 'detalle';
        }

        if ($clvPac === '') {
            return [
                'ok' => false,
                'mensaje' => 'Faltan datos para actualizar la información.'
            ];
        }

        $autorizacion = $this->autorizar($clvPac, $clvPsi, $clvCons);

        if (!$autorizacion['ok']) {
            return [
                'ok' => false,
                'mensaje' => (string) (
                    $autorizacion['mensaje']
                    ?? 'No tienes autorización para esta acción.'
                )
            ];
        }

        $datosActuales = $this->pacienteModel->obtenerDatosPersonalesParaPsicologo(
            $clvPac,
            $clvPsi,
            $clvCons
        );

        if ($datosActuales === null) {
            return [
                'ok' => false,
                'mensaje' => 'No tienes autorización para esta acción.'
            ];
        }

        $faltantes = $this->identificarFaltantes($datosActuales);

        $payloadPersona = [];
        foreach ($faltantes['persona'] as $campo) {
            if (array_key_exists($campo, $post)) {
                $payloadPersona[$campo] = trim((string) $post[$campo]);
            }
        }

        $payloadDireccion = [];
        foreach ($faltantes['direccion'] as $campo) {
            if (array_key_exists($campo, $post)) {
                $payloadDireccion[$campo] = trim((string) $post[$campo]);
            }
        }

        // Campos enviados que ya no están vacíos (concurrencia).
        $omitidosPrevios = [];
        foreach (self::CAMPOS_PERSONA as $campo) {
            if (
                array_key_exists($campo, $post)
                && !in_array($campo, $faltantes['persona'], true)
                && trim((string) $post[$campo]) !== ''
            ) {
                $omitidosPrevios[] = $campo;
            }
        }
        foreach (self::CAMPOS_DIRECCION as $campo) {
            if (
                array_key_exists($campo, $post)
                && !in_array($campo, $faltantes['direccion'], true)
                && trim((string) $post[$campo]) !== ''
            ) {
                $omitidosPrevios[] = $campo;
            }
        }

        $errores = $this->validarPayload(
            $payloadPersona,
            $payloadDireccion,
            $faltantes,
            trim((string) ($datosActuales['ClvDir'] ?? '')) === ''
        );

        if ($errores !== []) {
            return [
                'ok' => false,
                'mensaje' => reset($errores) ?: 'Revisa los datos ingresados.',
                'errores' => $errores,
                'clvPac' => $clvPac
            ];
        }

        $payloadPersona = array_filter(
            $payloadPersona,
            static fn(string $valor): bool => trim($valor) !== ''
        );
        $payloadDireccion = array_filter(
            $payloadDireccion,
            static fn(string $valor): bool => trim($valor) !== ''
        );

        if ($payloadPersona === [] && $payloadDireccion === []) {
            if ($omitidosPrevios !== []) {
                return [
                    'ok' => true,
                    'mensaje' => $this->mensajeConRetornoHistoria(
                        'Algunos datos ya habían sido registrados y no fueron reemplazados.',
                        $retornoSolicitado
                    ),
                    'clvPac' => $clvPac,
                    'omitidos' => $omitidosPrevios,
                    'retorno' => $retornoSolicitado
                ];
            }

            if (
                $faltantes['persona'] !== []
                || $faltantes['direccion'] !== []
            ) {
                return [
                    'ok' => false,
                    'mensaje' => 'Completa al menos un campo pendiente.',
                    'clvPac' => $clvPac
                ];
            }

            return [
                'ok' => true,
                'mensaje' => $this->mensajeConRetornoHistoria(
                    'La información del paciente ya está completa.',
                    $retornoSolicitado
                ),
                'clvPac' => $clvPac,
                'retorno' => $retornoSolicitado
            ];
        }

        try {
            $resultado = $this->pacienteModel->completarInformacionPorPsicologo(
                $clvPac,
                $clvPsi,
                $clvCons,
                $payloadPersona,
                $payloadDireccion
            );
        } catch (RuntimeException $e) {
            $mensaje = trim($e->getMessage());

            if (
                $mensaje !== ''
                && (
                    str_contains($mensaje, 'dirección')
                    || str_contains($mensaje, 'autoriz')
                    || str_contains($mensaje, 'encontrado')
                )
            ) {
                return [
                    'ok' => false,
                    'mensaje' => $mensaje,
                    'clvPac' => $clvPac
                ];
            }

            return [
                'ok' => false,
                'mensaje' =>
                    'No fue posible actualizar la información. Intenta nuevamente.',
                'clvPac' => $clvPac
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'mensaje' =>
                    'No fue posible actualizar la información. Intenta nuevamente.',
                'clvPac' => $clvPac
            ];
        }

        $omitidos = array_values(array_unique(array_merge(
            $omitidosPrevios,
            $resultado['omitidos'] ?? []
        )));

        $actualizados = $resultado['actualizados'] ?? [];

        if ($actualizados === [] && $omitidos !== []) {
            return [
                'ok' => true,
                'mensaje' => $this->mensajeConRetornoHistoria(
                    'Algunos datos ya habían sido registrados y no fueron reemplazados.',
                    $retornoSolicitado
                ),
                'clvPac' => $clvPac,
                'omitidos' => $omitidos,
                'retorno' => $retornoSolicitado
            ];
        }

        if ($omitidos !== []) {
            return [
                'ok' => true,
                'mensaje' => $this->mensajeConRetornoHistoria(
                    'Información del paciente actualizada correctamente. '
                    . 'Algunos datos ya habían sido registrados y no fueron reemplazados.',
                    $retornoSolicitado
                ),
                'clvPac' => $clvPac,
                'omitidos' => $omitidos,
                'retorno' => $retornoSolicitado
            ];
        }

        if ($actualizados === []) {
            return [
                'ok' => true,
                'mensaje' => $this->mensajeConRetornoHistoria(
                    'La información del paciente ya está completa.',
                    $retornoSolicitado
                ),
                'clvPac' => $clvPac,
                'retorno' => $retornoSolicitado
            ];
        }

        return [
            'ok' => true,
            'mensaje' => $this->mensajeConRetornoHistoria(
                'Información del paciente actualizada correctamente.',
                $retornoSolicitado
            ),
            'clvPac' => $clvPac,
            'retorno' => $retornoSolicitado
        ];
    }

    private function mensajeConRetornoHistoria(
        string $mensaje,
        string $retorno
    ): string {
        if ($retorno !== 'historia_externa') {
            return $mensaje;
        }

        return $mensaje
            . ' Puedes cerrar esta pestaña y regresar a la historia clínica. '
            . 'Actualiza esa pantalla para refrescar nombre, edad, género y ubicación.';
    }

    public function resolverRutaRetorno(
        string $retorno,
        string $clvPac
    ): string {
        $retorno = strtolower(trim($retorno));

        if (!in_array($retorno, self::RETORNOS, true)) {
            $retorno = 'detalle';
        }

        $clvPac = rawurlencode($clvPac);

        return match ($retorno) {
            'historia_nueva' =>
                'psicologo/pacientes/ver/' . $clvPac . '/historia/nueva',
            'expediente' =>
                'psicologo/pacientes/ver/' . $clvPac . '/expediente?tab=ficha',
            // Pestaña nueva desde historia: no navegar la historia original.
            'historia_externa' =>
                'psicologo/pacientes/ver/' . $clvPac,
            default =>
                'psicologo/pacientes/ver/' . $clvPac
        };
    }

    /**
     * @return array{ok: bool, mensaje?: string}
     */
    private function autorizar(
        string $clvPac,
        string $clvPsi,
        string $clvCons
    ): array {
        $clvPac = trim($clvPac);
        $clvPsi = trim($clvPsi);
        $clvCons = trim($clvCons);

        if ($clvPac === '' || $clvPsi === '' || $clvCons === '') {
            return [
                'ok' => false,
                'mensaje' => 'No tienes autorización para consultar este paciente.'
            ];
        }

        if (
            !$this->pacienteModel->perteneceAPsicologo($clvPac, $clvPsi)
        ) {
            return [
                'ok' => false,
                'mensaje' => 'No tienes autorización para consultar este paciente.'
            ];
        }

        if (
            !$this->pacienteModel->perteneceAlConsultorio($clvPac, $clvCons)
        ) {
            return [
                'ok' => false,
                'mensaje' => 'No tienes autorización para consultar este paciente.'
            ];
        }

        $historial = $this->historialModel->obtenerPorPacienteConsultorio(
            $clvPac,
            $clvCons
        );

        if ($historial !== null) {
            if (
                strtoupper(trim((string) ($historial['ClvPsi'] ?? '')))
                !== strtoupper($clvPsi)
            ) {
                return [
                    'ok' => false,
                    'mensaje' => 'No tienes autorización para consultar este paciente.'
                ];
            }

            return ['ok' => true];
        }

        // Sin historial: solo PROGRAMADA o ASISTIDA (no CANCELADA/INASISTENCIA solas).
        if (
            !$this->citaModel->existeCitaProgramadaOAsistida(
                $clvPac,
                $clvPsi,
                $clvCons
            )
        ) {
            return [
                'ok' => false,
                'mensaje' => 'No tienes autorización para consultar este paciente.'
            ];
        }

        return ['ok' => true];
    }

    /**
     * Faltante solo si es null o cadena vacía tras trim.
     * No usa empty(): conserva "0", "Otro" y textos numéricos válidos.
     */
    public static function esValorFaltante(mixed $valor): bool
    {
        if ($valor === null) {
            return true;
        }

        return trim((string) $valor) === '';
    }

    /**
     * @param array<string, mixed> $datos
     * @return array{persona: list<string>, direccion: list<string>}
     */
    public function identificarFaltantes(array $datos): array
    {
        $persona = [];
        foreach (self::CAMPOS_PERSONA as $campo) {
            if ($this->estaVacio($datos[$campo] ?? null)) {
                $persona[] = $campo;
            }
        }

        $direccion = [];
        $clvDir = trim((string) ($datos['ClvDir'] ?? ''));

        if ($clvDir === '') {
            // Sin dirección: solicitar obligatorios de alta + opcionales útiles.
            $direccion = self::CAMPOS_DIRECCION;
        } else {
            foreach (self::CAMPOS_DIRECCION as $campo) {
                if ($this->estaVacio($datos[$campo] ?? null)) {
                    $direccion[] = $campo;
                }
            }
        }

        return [
            'persona' => $persona,
            'direccion' => $direccion
        ];
    }

    /**
     * @param array<string, mixed> $datos
     * @return array<string, string>
     */
    private function extraerValoresActuales(array $datos): array
    {
        $valores = [];

        foreach (array_merge(self::CAMPOS_PERSONA, self::CAMPOS_DIRECCION) as $campo) {
            $valores[$campo] = trim((string) ($datos[$campo] ?? ''));
        }

        return $valores;
    }

    private function estaVacio(mixed $valor): bool
    {
        return self::esValorFaltante($valor);
    }

    /**
     * @param array<string, string> $persona
     * @param array<string, string> $direccion
     * @param array{persona: list<string>, direccion: list<string>} $faltantes
     * @return array<string, string>
     */
    private function validarPayload(
        array $persona,
        array $direccion,
        array $faltantes,
        bool $sinDireccion
    ): array {
        $errores = [];
        $etiquetas = $this->etiquetasCampos();

        foreach ($faltantes['persona'] as $campo) {
            $valor = trim((string) ($persona[$campo] ?? ''));

            if ($valor === '') {
                $errores[$campo] = 'Completa '
                    . ($etiquetas[$campo] ?? 'este campo')
                    . '.';
                continue;
            }

            if (in_array($campo, ['NombrePer', 'ApPatPer', 'ApMatPer'], true)) {
                if (mb_strlen($valor) > 50) {
                    $errores[$campo] = 'Máximo 50 caracteres.';
                } elseif (preg_match('/\d/u', $valor)) {
                    $errores[$campo] = 'No debe contener números.';
                }
            }

            if ($campo === 'FechaNacimiento') {
                $validacionFecha = (new EdadService())->validarFechaNacimiento(
                    $valor,
                    'paciente'
                );

                if (empty($validacionFecha['ok'])) {
                    $errores[$campo] = (string) (
                        $validacionFecha['mensaje']
                        ?? EdadService::MENSAJE_FORMATO
                    );
                }
            }

            if ($campo === 'GeneroPer' && !in_array($valor, self::GENEROS, true)) {
                $errores[$campo] = 'Selecciona un género válido.';
            }
        }

        $opcionalesDir = [
            'CalleDir',
            'NumExtDir',
            'NumIntDir',
            'ReferenciaDir'
        ];

        $hayAlgunaDireccion = false;
        foreach ($faltantes['direccion'] as $campo) {
            if (trim((string) ($direccion[$campo] ?? '')) !== '') {
                $hayAlgunaDireccion = true;
                break;
            }
        }

        if ($sinDireccion && $faltantes['direccion'] !== []) {
            foreach (self::CAMPOS_DIRECCION_OBLIGATORIOS_ALTA as $campo) {
                if (!in_array($campo, $faltantes['direccion'], true)) {
                    continue;
                }

                $valor = trim((string) ($direccion[$campo] ?? ''));

                if ($valor === '') {
                    $errores[$campo] = 'Completa '
                        . ($etiquetas[$campo] ?? 'este campo')
                        . '.';
                }
            }
        } elseif ($hayAlgunaDireccion) {
            foreach (self::CAMPOS_DIRECCION_OBLIGATORIOS_ALTA as $campo) {
                if (!in_array($campo, $faltantes['direccion'], true)) {
                    continue;
                }

                // Si completa dirección parcial existente, exigir obligatorios faltantes.
                $valor = trim((string) ($direccion[$campo] ?? ''));
                if ($valor === '' && isset($errores[$campo]) === false) {
                    // Solo exigir si el usuario empezó a llenar dirección.
                    $errores[$campo] = 'Completa '
                        . ($etiquetas[$campo] ?? 'este campo')
                        . '.';
                }
            }
        }

        foreach ($faltantes['direccion'] as $campo) {
            $valor = trim((string) ($direccion[$campo] ?? ''));

            if ($valor === '') {
                if (
                    !$sinDireccion
                    && in_array($campo, $opcionalesDir, true)
                ) {
                    continue;
                }

                if (
                    $sinDireccion
                    && in_array($campo, $opcionalesDir, true)
                ) {
                    continue;
                }

                if (!isset($errores[$campo])) {
                    // Obligatorios ya marcados arriba; opcionales pueden quedar vacíos.
                    continue;
                }

                continue;
            }

            $max = match ($campo) {
                'CalleDir' => 70,
                'CodPostDir' => 5,
                'NumExtDir', 'NumIntDir' => 10,
                'ReferenciaDir' => 255,
                default => 50
            };

            if (mb_strlen($valor) > $max) {
                $errores[$campo] = 'Excede la longitud permitida.';
            }

            if (
                $campo === 'CodPostDir'
                && !preg_match('/^\d{5}$/', $valor)
            ) {
                $errores[$campo] = 'El código postal debe tener 5 dígitos.';
            }
        }

        return $errores;
    }

    /**
     * @return array<string, string>
     */
    public function etiquetasCampos(): array
    {
        return [
            'NombrePer' => 'el nombre',
            'ApPatPer' => 'el apellido paterno',
            'ApMatPer' => 'el apellido materno',
            'FechaNacimiento' => 'la fecha de nacimiento',
            'GeneroPer' => 'el género',
            'PaisDir' => 'el país',
            'EstadoDir' => 'el estado',
            'MunicipioDir' => 'el municipio',
            'ColoniaDir' => 'la colonia',
            'CalleDir' => 'la calle',
            'CodPostDir' => 'el código postal',
            'NumExtDir' => 'el número exterior',
            'NumIntDir' => 'el número interior',
            'ReferenciaDir' => 'la referencia'
        ];
    }
}
