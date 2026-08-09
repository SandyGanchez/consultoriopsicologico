<?php

namespace App\Services;

use App\Config\Config;
use App\Config\Database;
use App\Models\AvisoPrivacidadVersion;
use App\Models\ConsentimientoDatosPersonales;
use App\Models\Paciente;
use App\Models\SolicitudPrivacidad;
use DateTimeImmutable;
use PDO;
use RuntimeException;

class PrivacidadService
{
    public const VERSION_DEFAULT = '1.0';

    public const MENSAJE_SIN_CONSENTIMIENTO_HISTORIA =
        'El paciente debe aceptar el Aviso de Privacidad y otorgar su consentimiento '
        . 'para el tratamiento de datos sensibles antes de integrar la historia clínica.';

    public const MENSAJE_MENOR_EDAD =
        'El consentimiento electrónico está disponible solo para mayores de edad. '
        . 'Para pacientes menores de 18 años se requiere consentimiento del representante '
        . 'legal (función pendiente). No se puede continuar ni integrar historia clínica '
        . 'hasta contar con ese representante.';

    public const MENSAJE_EN_CONFIGURACION =
        'El aviso de privacidad se encuentra en proceso de configuración.';

    public const MENSAJE_ERROR_TEMPORAL =
        'No fue posible verificar tu consentimiento de privacidad en este momento. '
        . 'Intenta nuevamente o cierra sesión.';

    public const MEDIOS_ACEPTACION = [
        'REGISTRO',
        'ACTIVACION',
        'REACEPTACION',
        'PANEL'
    ];

    private ConsentimientoDatosPersonales $consentimientoModel;

    private SolicitudPrivacidad $solicitudModel;

    private AvisoPrivacidadVersion $avisoModel;

    private Paciente $pacienteModel;

    private InstalacionConsultorioService $instalacionService;

    private PDO $db;

    public function __construct(
        ?ConsentimientoDatosPersonales $consentimientoModel = null,
        ?SolicitudPrivacidad $solicitudModel = null,
        ?AvisoPrivacidadVersion $avisoModel = null,
        ?Paciente $pacienteModel = null,
        ?InstalacionConsultorioService $instalacionService = null
    ) {
        $this->consentimientoModel = $consentimientoModel
            ?? new ConsentimientoDatosPersonales();
        $this->solicitudModel = $solicitudModel ?? new SolicitudPrivacidad();
        $this->avisoModel = $avisoModel ?? new AvisoPrivacidadVersion();
        $this->pacienteModel = $pacienteModel ?? new Paciente();
        $this->instalacionService = $instalacionService
            ?? new InstalacionConsultorioService();
        $this->db = Database::connect();
    }

    public function persistenciaDisponible(): bool
    {
        return $this->avisoModel->tablaDisponible()
            && $this->consentimientoModel->tablaDisponible();
    }

    public function obtenerAvisoVigente(): ?array
    {
        if (!$this->avisoModel->tablaDisponible()) {
            return null;
        }

        return $this->avisoModel->obtenerVigente();
    }

    public function versionVigente(): string
    {
        $aviso = $this->obtenerAvisoVigente();

        if ($aviso !== null) {
            return (string) $aviso['VersionAviso'];
        }

        $version = trim((string) Config::get(
            'PRIVACY_NOTICE_VERSION',
            self::VERSION_DEFAULT
        ));

        return $version !== '' ? $version : self::VERSION_DEFAULT;
    }

    public function fechaAviso(): string
    {
        $aviso = $this->obtenerAvisoVigente();

        if ($aviso !== null && !empty($aviso['FechaPublicacion'])) {
            return substr((string) $aviso['FechaPublicacion'], 0, 10);
        }

        $fecha = trim((string) Config::get('PRIVACY_NOTICE_DATE', ''));

        return $fecha !== '' ? $fecha : '2026-08-02';
    }

    public function esDesarrollo(): bool
    {
        $env = strtolower(trim((string) Config::get('APP_ENV', 'production')));

        return in_array($env, ['development', 'local', 'dev', 'testing'], true);
    }

    /**
     * Normaliza ContenidoAviso antes de hashear/publicar.
     */
    public function normalizarContenidoAviso(string $contenido): string
    {
        $contenido = str_replace(["\r\n", "\r"], "\n", $contenido);
        $contenido = preg_replace("/^\xEF\xBB\xBF/", '', $contenido) ?? $contenido;

        $lineas = explode("\n", $contenido);
        $lineas = array_map(static function (string $linea): string {
            return rtrim($linea, " \t");
        }, $lineas);

        return trim(implode("\n", $lineas));
    }

    public function calcularHashContenidoAviso(string $contenidoNormalizado): string
    {
        return hash('sha256', $contenidoNormalizado);
    }

    /**
     * @return array{
     *   completo: bool,
     *   faltantes: list<string>,
     *   nombre_responsable: string,
     *   nombre_consultorio: string,
     *   domicilio: string,
     *   correo_privacidad: string,
     *   telefono: string,
     *   clv_cons: string,
     *   consultorio: ?array
     * }
     */
    public function obtenerDatosResponsable(): array
    {
        $estado = $this->instalacionService->resolver();
        $faltantes = [];
        $consultorio = null;

        if ($estado['estado'] === InstalacionConsultorioService::ESTADO_NINGUNO) {
            $faltantes[] = 'Consultorio de la instalación (aún no registrado)';
        } elseif ($estado['estado'] === InstalacionConsultorioService::ESTADO_MULTIPLE) {
            $faltantes[] = 'Instalación con más de un consultorio (requiere corrección)';
        } else {
            $consultorio = is_array($estado['consultorio'] ?? null)
                ? $estado['consultorio']
                : null;
        }

        $nombreConsultorio = trim((string) ($consultorio['NombreCons'] ?? ''));
        $telefono = trim((string) ($consultorio['TelefonoCons'] ?? ''));
        $correo = trim((string) (
            $consultorio['CorreoElectronico']
            ?? $consultorio['CorreoUsu']
            ?? ''
        ));
        $correoOverride = trim((string) Config::get('PRIVACY_CONTACT_EMAIL', ''));

        if ($correoOverride !== '') {
            $correo = $correoOverride;
        }

        $nombreResponsable = trim(implode(' ', array_filter([
            trim((string) ($consultorio['NombrePer'] ?? '')),
            trim((string) ($consultorio['ApPatPer'] ?? '')),
            trim((string) ($consultorio['ApMatPer'] ?? ''))
        ])));

        $domicilio = $this->formatearDomicilio($consultorio);

        if ($nombreConsultorio === '') {
            $faltantes[] = 'Nombre del consultorio';
        }

        if ($nombreResponsable === '') {
            $faltantes[] = 'Nombre del responsable (usuario CONSULTORIO con EsResponsable=1)';
        }

        if ($domicilio === '') {
            $faltantes[] = 'Domicilio del consultorio';
        }

        if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $faltantes[] = 'Correo de privacidad';
        }

        if ($telefono === '') {
            $faltantes[] = 'Teléfono del consultorio';
        }

        if ($this->contieneMarcadorProhibido(implode(' ', [
            $nombreResponsable,
            $nombreConsultorio,
            $domicilio,
            $correo,
            $telefono
        ]))) {
            $faltantes[] = 'Datos con marcadores no permitidos';
        }

        return [
            'completo' => $faltantes === [],
            'faltantes' => $faltantes,
            'nombre_responsable' => $nombreResponsable,
            'nombre_consultorio' => $nombreConsultorio,
            'domicilio' => $domicilio,
            'correo_privacidad' => $correo,
            'telefono' => $telefono,
            'clv_cons' => strtoupper(trim((string) ($consultorio['ClvCons'] ?? ''))),
            'consultorio' => $consultorio
        ];
    }

    /**
     * Construye el contenido definitivo del aviso con datos reales (sin marcadores).
     *
     * @param array<string, mixed> $responsable
     */
    public function construirContenidoDefinitivoAviso(
        array $responsable,
        string $version,
        string $fechaPublicacion
    ): string {
        if (empty($responsable['completo'])) {
            throw new RuntimeException(
                'No se puede construir el aviso: faltan datos legales reales.'
            );
        }

        $contenido = implode("\n", [
            'AVISO DE PRIVACIDAD INTEGRAL',
            'Versión ' . $version,
            'Fecha de publicación: ' . $fechaPublicacion,
            '',
            '1. RESPONSABLE',
            'Responsable: ' . $responsable['nombre_responsable'],
            'Consultorio: ' . $responsable['nombre_consultorio'],
            'Domicilio: ' . $responsable['domicilio'],
            'Correo de privacidad: ' . $responsable['correo_privacidad'],
            'Teléfono: ' . $responsable['telefono'],
            '',
            'PsicoMatch es únicamente el sistema informático utilizado para gestionar la información; no es el responsable del tratamiento.',
            '',
            '2. DATOS PERSONALES TRATADOS',
            'Datos de identificación y contacto, cuenta de acceso, agenda y citas, y datos administrativos de operación del consultorio.',
            '',
            '3. DATOS PERSONALES SENSIBLES',
            'Información relativa a la salud física o mental, antecedentes, evolución clínica, diagnósticos, notas de sesión, reactivos y demás contenido del expediente clínico. Su tratamiento requiere consentimiento expreso.',
            '',
            '4. FINALIDADES',
            'Prestación de atención psicológica; gestión del expediente; agenda de citas; comunicaciones relacionadas con la atención; cumplimiento de obligaciones legales y de conservación.',
            '',
            '5. CONSENTIMIENTO',
            'El tratamiento de datos sensibles requiere consentimiento expreso e independiente. No se marcan casillas por defecto. El psicólogo no puede otorgar el consentimiento en nombre del paciente.',
            '',
            '6. ACCESO CLÍNICO',
            'El acceso clínico está restringido al personal autorizado del consultorio mediante roles, sesión y controles de autorización.',
            '',
            '7. TRANSFERENCIAS Y ENCARGADOS',
            'Los datos se tratan en la instalación del consultorio. PsicoMatch actúa como sistema tecnológico. Pueden existir encargados técnicos (alojamiento/correo) con obligaciones de confidencialidad.',
            '',
            '8. CONSERVACIÓN',
            'Conservación mínima de 5 años desde el último acto clínico. Inactivar al paciente no elimina el expediente. Tras el plazo, solo es elegible para revisión. No hay eliminación automática.',
            '',
            '9. DERECHOS SOBRE DATOS PERSONALES',
            'Para ejercer derechos de acceso, rectificación, cancelación u oposición, comunícate directamente con el responsable del consultorio mediante el correo de privacidad o el teléfono indicados en este aviso. No se reciben estas solicitudes dentro de la aplicación.',
            'Información mínima a proporcionar: nombre completo, medio de contacto, derecho que deseas ejercer y una descripción clara de tu solicitud.',
            'Recibirás respuesta por el medio de contacto que indiques o por el correo asociado a tu atención, conforme a los plazos y excepciones legales aplicables a expedientes clínicos.',
            '',
            '10. REVOCACIÓN DEL CONSENTIMIENTO',
            'Para solicitar la revocación de tu consentimiento, comunícate directamente con el responsable del consultorio por el correo de privacidad o el teléfono de este aviso. La revocación se atiende fuera de la aplicación; no implica el borrado inmediato del expediente clínico, que puede conservarse por obligaciones legales.',
            '',
            '11. SEGURIDAD',
            'Se aplican medidas razonables de seguridad administrativa, técnica y física.',
            '',
            '12. MODIFICACIONES',
            'Los cambios sustanciales generan nueva versión publicada de forma inmutable. Se conserva el historial de aceptaciones.',
            '',
            '13. VERSION Y FECHA',
            'Versión vigente: ' . $version . '. Fecha: ' . $fechaPublicacion . '.',
            '',
            'Política temporal menores de edad: el autoconsentimiento electrónico está limitado a mayores de 18 años. El soporte de representante legal queda pendiente.'
        ]);

        if ($this->contieneMarcadorProhibido($contenido)) {
            throw new RuntimeException(
                'El contenido del aviso contiene marcadores prohibidos.'
            );
        }

        return $this->normalizarContenidoAviso($contenido);
    }

    /**
     * Publica una versión solo con datos legales reales.
     *
     * @return array{ok: bool, id?: int, hash?: string, mensaje?: string}
     */
    public function publicarVersionAviso(string $version): array
    {
        $version = trim($version);

        // Idempotencia: si la versión ya existe, no regenerar contenido
        // (la fecha embebida cambiaría el hash en cada ejecución).
        $existente = $this->avisoModel->tablaDisponible()
            ? $this->avisoModel->obtenerPorVersion($version)
            : null;

        if ($existente !== null) {
            return [
                'ok' => true,
                'creado' => false,
                'id' => (int) $existente['IdAvisoPrivacidad'],
                'hash' => (string) $existente['HashContenidoAviso'],
                'mensaje' =>
                    'La versión ya estaba publicada y no se modifica (idempotente).'
            ];
        }

        $responsable = $this->obtenerDatosResponsable();

        if (!$responsable['completo']) {
            return [
                'ok' => false,
                'mensaje' =>
                    'No se publica la versión '
                    . $version
                    . ': faltan datos legales reales. '
                    . implode('; ', $responsable['faltantes'])
            ];
        }

        $fecha = date('Y-m-d H:i:s');
        $contenido = $this->construirContenidoDefinitivoAviso(
            $responsable,
            $version,
            $fecha
        );
        $hash = $this->calcularHashContenidoAviso($contenido);

        return $this->avisoModel->publicarVersion(
            $version,
            $contenido,
            $hash,
            $fecha
        );
    }

    /**
     * @return array{ok: bool, publicar: bool, mensaje?: string, responsable: array, version: string, fecha: string, contenido?: string, id_aviso?: int}
     */
    public function prepararAvisoIntegral(): array
    {
        $aviso = $this->obtenerAvisoVigente();

        if ($aviso !== null) {
            $responsable = $this->obtenerDatosResponsable();

            return [
                'ok' => true,
                'publicar' => true,
                'responsable' => $responsable,
                'version' => (string) $aviso['VersionAviso'],
                'fecha' => substr((string) $aviso['FechaPublicacion'], 0, 10),
                'contenido' => (string) $aviso['ContenidoAviso'],
                'id_aviso' => (int) $aviso['IdAvisoPrivacidad']
            ];
        }

        $responsable = $this->obtenerDatosResponsable();

        if (!$responsable['completo']) {
            return [
                'ok' => false,
                'publicar' => false,
                'mensaje' =>
                    'El Aviso de Privacidad no puede publicarse: no hay versión vigente y faltan datos legales del responsable.',
                'responsable' => $responsable,
                'version' => $this->versionVigente(),
                'fecha' => $this->fechaAviso()
            ];
        }

        // Sin versión publicada: no inventar publicación. Solo vista previa en desarrollo.
        if ($this->esDesarrollo()) {
            $contenido = $this->construirContenidoDefinitivoAviso(
                $responsable,
                self::VERSION_DEFAULT,
                date('Y-m-d H:i:s')
            );

            return [
                'ok' => true,
                'publicar' => true,
                'mensaje' =>
                    'Vista previa de desarrollo: aún no existe versión publicada en BD.',
                'responsable' => $responsable,
                'version' => self::VERSION_DEFAULT,
                'fecha' => date('Y-m-d'),
                'contenido' => $contenido
            ];
        }

        return [
            'ok' => false,
            'publicar' => false,
            'mensaje' =>
                'No existe una versión publicada del Aviso de Privacidad.',
            'responsable' => $responsable,
            'version' => self::VERSION_DEFAULT,
            'fecha' => $this->fechaAviso()
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @return array{ok: bool, mensaje?: string}
     */
    public function validarCheckboxesConsentimiento(array $post): array
    {
        $aviso = !empty($post['aviso_leido']) || !empty($post['AvisoLeido']);
        $sensibles = !empty($post['consentimiento_sensibles'])
            || !empty($post['ConsentimientoDatosSensibles']);

        if (!$aviso) {
            return [
                'ok' => false,
                'mensaje' =>
                    'Debes confirmar que leíste el Aviso de Privacidad Integral.'
            ];
        }

        if (!$sensibles) {
            return [
                'ok' => false,
                'mensaje' =>
                    'Debes otorgar tu consentimiento expreso para el tratamiento de datos personales sensibles.'
            ];
        }

        return ['ok' => true];
    }

    /**
     * Estado del gate de privacidad para un paciente.
     *
     * @return array{
     *   estado: 'tablas_ausentes'|'en_configuracion'|'error_temporal'|'requiere_aceptacion'|'vigente',
     *   puede_usar_panel: bool,
     *   puede_aceptar: bool,
     *   mensaje?: string
     * }
     */
    public function evaluarGatePaciente(string $clvUsu): array
    {
        try {
            if (!$this->persistenciaDisponible()) {
                return [
                    'estado' => 'tablas_ausentes',
                    'puede_usar_panel' => true,
                    'puede_aceptar' => false
                ];
            }

            $aviso = $this->obtenerAvisoVigente();

            if ($aviso === null) {
                return [
                    'estado' => 'en_configuracion',
                    'puede_usar_panel' => false,
                    'puede_aceptar' => false,
                    'mensaje' => self::MENSAJE_EN_CONFIGURACION
                ];
            }

            $vigente = $this->consentimientoModel->obtenerVigentePorUsuarioYAviso(
                $clvUsu,
                (int) $aviso['IdAvisoPrivacidad']
            );

            if ($vigente !== null) {
                return [
                    'estado' => 'vigente',
                    'puede_usar_panel' => true,
                    'puede_aceptar' => false
                ];
            }

            return [
                'estado' => 'requiere_aceptacion',
                'puede_usar_panel' => false,
                'puede_aceptar' => true
            ];
        } catch (\Throwable $e) {
            return [
                'estado' => 'error_temporal',
                'puede_usar_panel' => false,
                'puede_aceptar' => false,
                'mensaje' => self::MENSAJE_ERROR_TEMPORAL
            ];
        }
    }

    public function tieneConsentimientoVigente(string $clvUsu): bool
    {
        $gate = $this->evaluarGatePaciente($clvUsu);

        return $gate['estado'] === 'vigente'
            || $gate['estado'] === 'tablas_ausentes';
    }

    public function pacienteDebeResolverPrivacidad(string $clvUsu): bool
    {
        $gate = $this->evaluarGatePaciente($clvUsu);

        return in_array(
            $gate['estado'],
            ['en_configuracion', 'requiere_aceptacion', 'error_temporal'],
            true
        );
    }

    /**
     * @return array{ok: bool, creado?: bool, mensaje?: string, id?: int}
     */
    /**
     * @param array{
     *   ClvPacSujeto?: string|null,
     *   IdRelacionResponsable?: int|null
     * } $contextoSujeto
     * @return array{ok: bool, creado?: bool, mensaje?: string, id?: int}
     */
    public function registrarConsentimiento(
        string $clvUsu,
        string $medio,
        array $post,
        ?string $fechaNacimiento = null,
        array $contextoSujeto = []
    ): array {
        $medio = strtoupper(trim($medio));

        // VersionAviso / HashContenidoAviso nunca se toman de POST.
        unset(
            $post['VersionAviso'],
            $post['HashContenidoAviso'],
            $post['version_aviso'],
            $post['hash_contenido_aviso'],
            $post['IdAvisoPrivacidad']
        );

        if (!in_array($medio, self::MEDIOS_ACEPTACION, true)) {
            return [
                'ok' => false,
                'mensaje' => 'Medio de aceptación no válido.'
            ];
        }

        $validacion = $this->validarCheckboxesConsentimiento($post);

        if (!$validacion['ok']) {
            return $validacion;
        }

        $edad = $this->validarMayoriaDeEdad($fechaNacimiento);

        if (!$edad['ok']) {
            return $edad;
        }

        return $this->persistirAceptacion(
            $clvUsu,
            $medio,
            $contextoSujeto
        );
    }

    /**
     * Consentimiento otorgado por un responsable autenticado (adulto)
     * respecto de los datos de un paciente dependiente (puede ser menor).
     *
     * @return array{ok: bool, creado?: bool, mensaje?: string, id?: int}
     */
    public function registrarConsentimientoDependiente(
        string $clvUsuResponsable,
        string $clvPacSujeto,
        int $idRelacionResponsable,
        array $post
    ): array {
        $validacion = $this->validarCheckboxesConsentimiento($post);

        if (!$validacion['ok']) {
            return $validacion;
        }

        if (trim($clvPacSujeto) === '' || $idRelacionResponsable <= 0) {
            return [
                'ok' => false,
                'mensaje' => 'Falta el paciente sujeto o la relación de responsabilidad.'
            ];
        }

        return $this->persistirAceptacion(
            $clvUsuResponsable,
            'PANEL',
            [
                'ClvPacSujeto' => $clvPacSujeto,
                'IdRelacionResponsable' => $idRelacionResponsable,
            ]
        );
    }

    /**
     * @param array{
     *   ClvPacSujeto?: string|null,
     *   IdRelacionResponsable?: int|null
     * } $contextoSujeto
     * @return array{ok: bool, creado?: bool, mensaje?: string, id?: int}
     */
    private function persistirAceptacion(
        string $clvUsu,
        string $medio,
        array $contextoSujeto = []
    ): array {
        if (!$this->persistenciaDisponible()) {
            return [
                'ok' => false,
                'mensaje' => self::MENSAJE_EN_CONFIGURACION
            ];
        }

        $aviso = $this->obtenerAvisoVigente();

        if ($aviso === null) {
            return [
                'ok' => false,
                'mensaje' => self::MENSAJE_EN_CONFIGURACION
            ];
        }

        return $this->consentimientoModel->registrarAceptacion([
            'ClvUsu' => $clvUsu,
            'IdAvisoPrivacidad' => (int) $aviso['IdAvisoPrivacidad'],
            'AvisoLeido' => 1,
            'ConsentimientoDatosSensibles' => 1,
            'MedioAceptacion' => $medio,
            'ClvPacSujeto' => $contextoSujeto['ClvPacSujeto'] ?? null,
            'IdRelacionResponsable' => $contextoSujeto['IdRelacionResponsable'] ?? null,
        ]);
    }

    /**
     * @return array{ok: bool, mensaje?: string, edad?: int}
     */
    public function validarMayoriaDeEdad(?string $fechaNacimiento): array
    {
        $fechaNacimiento = trim((string) $fechaNacimiento);

        if ($fechaNacimiento === '') {
            return [
                'ok' => false,
                'mensaje' => self::MENSAJE_MENOR_EDAD
            ];
        }

        $validacion = (new EdadService())->validarFechaNacimiento(
            $fechaNacimiento,
            'adulto'
        );

        if (empty($validacion['ok'])) {
            $mensajeBase = (string) ($validacion['mensaje'] ?? '');

            if (
                $mensajeBase === EdadService::MENSAJE_MAYORIA
                || ($validacion['clasificacion'] ?? '') === EdadService::CLASIFICACION_MENOR
            ) {
                return [
                    'ok' => false,
                    'mensaje' => self::MENSAJE_MENOR_EDAD,
                    'edad' => isset($validacion['edad'])
                        ? (int) $validacion['edad']
                        : null
                ];
            }

            return [
                'ok' => false,
                'mensaje' => $mensajeBase !== ''
                    ? $mensajeBase
                    : self::MENSAJE_MENOR_EDAD,
                'edad' => isset($validacion['edad'])
                    ? (int) $validacion['edad']
                    : null
            ];
        }

        return [
            'ok' => true,
            'edad' => (int) $validacion['edad']
        ];
    }

    public function pacienteTieneConsentimientoVigente(string $clvPac): bool
    {
        $paciente = $this->pacienteModel->obtenerPorClaveBasico($clvPac);

        if ($paciente === null) {
            return false;
        }

        $clvUsu = trim((string) ($paciente['ClvUsu'] ?? ''));

        return $clvUsu !== '' && $this->tieneConsentimientoVigente($clvUsu);
    }

    /**
     * @return array{ok: bool, mensaje?: string}
     */
    public function validarConsentimientoParaHistoria(string $clvPac): array
    {
        try {
            if (!$this->persistenciaDisponible()) {
                return [
                    'ok' => false,
                    'mensaje' => self::MENSAJE_SIN_CONSENTIMIENTO_HISTORIA
                ];
            }

            if ($this->obtenerAvisoVigente() === null) {
                return [
                    'ok' => false,
                    'mensaje' => self::MENSAJE_EN_CONFIGURACION
                ];
            }

            $paciente = $this->pacienteModel->obtenerPorClaveBasico($clvPac);

            if ($paciente === null) {
                return [
                    'ok' => false,
                    'mensaje' => self::MENSAJE_SIN_CONSENTIMIENTO_HISTORIA
                ];
            }

            $fechaNacimiento = $this->obtenerFechaNacimientoPorUsuario(
                (string) ($paciente['ClvUsu'] ?? '')
            );

            if (!$this->validarMayoriaDeEdad($fechaNacimiento)['ok']) {
                return [
                    'ok' => false,
                    'mensaje' => self::MENSAJE_MENOR_EDAD
                ];
            }

            if (!$this->pacienteTieneConsentimientoVigente($clvPac)) {
                return [
                    'ok' => false,
                    'mensaje' => self::MENSAJE_SIN_CONSENTIMIENTO_HISTORIA
                ];
            }

            return ['ok' => true];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'mensaje' => self::MENSAJE_EN_CONFIGURACION
            ];
        }
    }

    public function obtenerFechaNacimientoPorUsuario(string $clvUsu): ?string
    {
        $clvUsu = trim($clvUsu);

        if ($clvUsu === '') {
            return null;
        }

        $sql = "
            SELECT p.FechaNacimiento
            FROM usuario u
            INNER JOIN persona p ON p.ClvPer = u.ClvPer
            WHERE u.ClvUsu = :clvUsu
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['clvUsu' => $clvUsu]);
        $valor = $stmt->fetchColumn();

        if ($valor === false || $valor === null || trim((string) $valor) === '') {
            return null;
        }

        return (string) $valor;
    }

    /**
     * LEGACY: alta in-app de solicitudes ARCO/revocación deshabilitada.
     * No inserta filas. Consulta/respuesta histórica: SolicitudPrivacidad +
     * ConsultorioController. Ver docs/PRIVACIDAD_Y_CONSERVACION.md.
     *
     * @return array{ok: bool, mensaje?: string}
     */
    public function solicitarRevocacionOArco(
        string $clvUsu,
        string $tipo,
        string $detalle
    ): array {
        unset($clvUsu, $tipo, $detalle);

        return [
            'ok' => false,
            'mensaje' =>
                'Las solicitudes relacionadas con datos personales se reciben directamente por '
                . 'los medios indicados en el Aviso de Privacidad.'
        ];
    }

    /**
     * @return array{ok: bool, mensaje?: string, data?: mixed}
     */
    public function consultasSolicitudesPorRol(
        string $rol,
        string $clvUsu
    ): array {
        $rol = strtoupper(trim($rol));

        if ($rol === 'PACIENTE') {
            // Canal ARCO in-app retirado: el paciente no consulta solicitudes aquí.
            unset($clvUsu);
            return [
                'ok' => true,
                'data' => []
            ];
        }

        if ($rol === 'CONSULTORIO') {
            return [
                'ok' => true,
                'data' => $this->solicitudModel->listarParaConsultorio()
            ];
        }

        if ($rol === 'ADMINISTRADOR') {
            return [
                'ok' => true,
                'data' => $this->solicitudModel->listarResumenAdministrador(),
                'mensaje' =>
                    'Resumen sin DetalleSolicitud, datos personales ni NotasInternas.'
            ];
        }

        return [
            'ok' => false,
            'mensaje' => 'El rol no tiene acceso a solicitudes de privacidad.'
        ];
    }

    /**
     * @return array{ok: bool, mensaje?: string}
     */
    public function responderSolicitudComoConsultorio(
        string $rol,
        string $clvUsuAtencion,
        int $idSolicitud,
        string $estado,
        string $respuestaTitular,
        ?string $notasInternas = null
    ): array {
        if (strtoupper(trim($rol)) !== 'CONSULTORIO') {
            return [
                'ok' => false,
                'mensaje' => 'Solo el consultorio puede responder solicitudes ARCO.'
            ];
        }

        return $this->solicitudModel->responderComoConsultorio(
            $idSolicitud,
            $clvUsuAtencion,
            $estado,
            $respuestaTitular,
            $notasInternas
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function resumenPrivacidadPaciente(string $clvUsu): array
    {
        $responsable = $this->obtenerDatosResponsable();
        $ultimo = $this->persistenciaDisponible()
            ? $this->consentimientoModel->obtenerUltimoPorUsuario($clvUsu)
            : null;
        $vigente = $this->tieneConsentimientoVigente($clvUsu);

        return [
            'version_vigente' => $this->versionVigente(),
            'version_aceptada' => $ultimo['VersionAviso'] ?? null,
            'id_aviso_aceptado' => $ultimo['IdAvisoPrivacidad'] ?? null,
            'fecha_aceptacion' => $ultimo['FechaAceptacion'] ?? null,
            'estado' => $vigente
                ? 'VIGENTE'
                : (string) ($ultimo['EstadoConsentimiento'] ?? 'SIN_REGISTRO'),
            'tiene_vigente' => $vigente,
            'correo_privacidad' => $responsable['correo_privacidad'],
            'telefono' => $responsable['telefono'],
            'nombre_consultorio' => $responsable['nombre_consultorio'],
            'nombre_responsable' => $responsable['nombre_responsable']
        ];
    }

    /**
     * @return array{
     *   fecha: ?string,
     *   fuentes: array<string, ?string>,
     *   elegible_revision: bool,
     *   anios_desde_ultimo_acto: ?float,
     *   estatus_hist: ?string
     * }
     */
    public function evaluarConservacionExpediente(
        string $clvPac,
        string $clvCons
    ): array {
        $fuentes = [
            'ultima_cita_asistida' => null,
            'actualizacion_historial' => null,
            'ultimo_seguimiento' => null,
            'cierre_terapeutico_proxy' => null
        ];

        $sqlCita = "
            SELECT MAX(
                TIMESTAMP(
                    c.FechaCita,
                    COALESCE(NULLIF(c.HraFinCita, ''), NULLIF(c.HraInicioCita, ''), '00:00:00')
                )
            ) AS ultimo
            FROM cita c
            WHERE c.ClvPac = :clvPac
              AND c.ClvCons = :clvCons
              AND c.EstadoCita = 'ASISTIDA'
        ";
        $stmt = $this->db->prepare($sqlCita);
        $stmt->execute([
            'clvPac' => $clvPac,
            'clvCons' => $clvCons
        ]);
        $fuentes['ultima_cita_asistida'] = $this->normalizarFecha(
            $stmt->fetchColumn()
        );

        $sqlHist = "
            SELECT EstatusHist, FechaAperturaHist, FechaActualizacionHist
            FROM historial_clinico
            WHERE ClvPac = :clvPac AND ClvCons = :clvCons
            LIMIT 1
        ";
        $stmt = $this->db->prepare($sqlHist);
        $stmt->execute([
            'clvPac' => $clvPac,
            'clvCons' => $clvCons
        ]);
        $hist = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $estatus = $hist ? (string) ($hist['EstatusHist'] ?? '') : null;

        if ($hist) {
            $fuentes['actualizacion_historial'] = $this->normalizarFecha(
                $hist['FechaActualizacionHist'] ?? $hist['FechaAperturaHist'] ?? null
            );

            if (in_array($estatus, ['CERRADO', 'ARCHIVADO'], true)) {
                $fuentes['cierre_terapeutico_proxy'] = $this->normalizarFecha(
                    $hist['FechaActualizacionHist'] ?? $hist['FechaAperturaHist'] ?? null
                );
            }
        }

        $sqlSeg = "
            SELECT MAX(s.FechaRegistroSeg) AS ultimo
            FROM seguimiento_sesion s
            INNER JOIN historial_clinico h ON h.ClvHist = s.ClvHist
            WHERE h.ClvPac = :clvPac
              AND h.ClvCons = :clvCons
              AND s.EstatusSeg IN ('FINALIZADO', 'CORREGIDO')
        ";
        $stmt = $this->db->prepare($sqlSeg);
        $stmt->execute([
            'clvPac' => $clvPac,
            'clvCons' => $clvCons
        ]);
        $fuentes['ultimo_seguimiento'] = $this->normalizarFecha(
            $stmt->fetchColumn()
        );

        $candidatos = array_values(array_filter($fuentes));
        $fechaUltimo = null;

        foreach ($candidatos as $fecha) {
            if ($fechaUltimo === null || strcmp($fecha, $fechaUltimo) > 0) {
                $fechaUltimo = $fecha;
            }
        }

        $anios = null;
        $elegible = false;

        if ($fechaUltimo !== null) {
            try {
                $inicio = new DateTimeImmutable($fechaUltimo);
                $ahora = new DateTimeImmutable('now');
                $diff = $inicio->diff($ahora);
                $anios = $diff->y + ($diff->m / 12) + ($diff->d / 365);
                $elegible = in_array($estatus, ['CERRADO', 'ARCHIVADO'], true)
                    && $anios >= 5.0;
            } catch (\Exception $e) {
                $anios = null;
                $elegible = false;
            }
        }

        return [
            'fecha' => $fechaUltimo,
            'fuentes' => $fuentes,
            'elegible_revision' => $elegible,
            'anios_desde_ultimo_acto' => $anios,
            'estatus_hist' => $estatus
        ];
    }

    private function contieneMarcadorProhibido(string $texto): bool
    {
        return (bool) preg_match(
            '/\[(NOMBRE DEL RESPONSABLE|DOMICILIO|CORREO|FALTA:[^\]]*)\]/iu',
            $texto
        );
    }

    /**
     * @param array<string, mixed>|null $consultorio
     */
    private function formatearDomicilio(?array $consultorio): string
    {
        if ($consultorio === null) {
            return '';
        }

        $partes = array_filter([
            trim((string) ($consultorio['CalleDir'] ?? '')),
            trim((string) (
                ($consultorio['NumExtDir'] ?? '') !== ''
                    ? 'No. Ext. ' . $consultorio['NumExtDir']
                    : ''
            )),
            trim((string) (
                ($consultorio['NumIntDir'] ?? '') !== ''
                    ? 'Int. ' . $consultorio['NumIntDir']
                    : ''
            )),
            trim((string) ($consultorio['ColoniaDir'] ?? '')),
            trim((string) ($consultorio['MunicipioDir'] ?? '')),
            trim((string) ($consultorio['EstadoDir'] ?? '')),
            trim((string) (
                ($consultorio['CodPostDir'] ?? '') !== ''
                    ? 'C.P. ' . $consultorio['CodPostDir']
                    : ''
            )),
            trim((string) ($consultorio['PaisDir'] ?? ''))
        ]);

        return implode(', ', $partes);
    }

    private function normalizarFecha(mixed $valor): ?string
    {
        if ($valor === null || $valor === false || $valor === '') {
            return null;
        }

        $texto = trim((string) $valor);

        return $texto !== '' ? $texto : null;
    }
}
