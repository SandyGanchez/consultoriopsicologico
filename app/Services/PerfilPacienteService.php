<?php

namespace App\Services;

use App\Config\Database;
use App\Models\Notificacion;
use App\Models\Paciente;
use PDO;
use Throwable;

/**
 * Detección central de perfil incompleto del paciente (Fase 3C).
 * No mezcla consentimiento de menores ni datos clínicos.
 */
class PerfilPacienteService
{
    public const TITULO_ACTIVO = 'Completa tu perfil';
    public const TITULO_RESUELTO = 'Perfil actualizado';
    public const TIPO_NOTIF = 'CUENTA';
    public const MENSAJE_NOTIF =
        'Completa la información pendiente de tu perfil para mantener tus datos actualizados.';

    /** @var list<string> */
    public const CAMPOS_DATOS_PERSONALES = [
        'FechaNacimiento',
        'GeneroPer'
    ];

    /** @var list<string> */
    public const CAMPOS_CONTACTO = [
        'TelefonoUsu'
    ];

    /** @var list<string> */
    public const CAMPOS_DIRECCION = [
        'PaisDir',
        'EstadoDir',
        'MunicipioDir',
        'ColoniaDir',
        'CalleDir',
        'CodPostDir',
        'NumExtDir'
    ];

    private Paciente $pacienteModel;
    private Notificacion $notificacionModel;
    private NotificacionService $notificacionService;

    public function __construct(
        ?Paciente $pacienteModel = null,
        ?Notificacion $notificacionModel = null,
        ?NotificacionService $notificacionService = null
    ) {
        $this->pacienteModel = $pacienteModel ?? new Paciente();
        $this->notificacionModel = $notificacionModel ?? new Notificacion();
        $this->notificacionService = $notificacionService
            ?? new NotificacionService();
    }

    /**
     * @return list<string>
     */
    public function listarCamposEsenciales(): array
    {
        return array_merge(
            self::CAMPOS_DATOS_PERSONALES,
            self::CAMPOS_CONTACTO,
            self::CAMPOS_DIRECCION
        );
    }

    public function esValorFaltante(mixed $valor): bool
    {
        if ($valor === null) {
            return true;
        }

        if (is_string($valor)) {
            $trim = trim($valor);

            if ($trim === '') {
                return true;
            }

            $marcadores = [
                'null',
                'undefined',
                'n/a',
                'na',
                '-',
                '--',
                '0000000000',
                '00000000000'
            ];

            return in_array(mb_strtolower($trim, 'UTF-8'), $marcadores, true);
        }

        return false;
    }

    /**
     * @param array<string, mixed> $datos
     * @return list<string>
     */
    public function obtenerCamposEsencialesFaltantes(array $datos): array
    {
        $faltantes = [];

        foreach ($this->listarCamposEsenciales() as $campo) {
            if ($this->esValorFaltante($datos[$campo] ?? null)) {
                $faltantes[] = $campo;
            }
        }

        return $faltantes;
    }

    /**
     * @param array<string, mixed> $datos
     */
    public function estaCompleto(array $datos): bool
    {
        return $this->obtenerCamposEsencialesFaltantes($datos) === [];
    }

    /**
     * @param array<string, mixed> $datos
     * @return list<string> claves de sección: DATOS_PERSONALES|CONTACTO|DIRECCION
     */
    public function obtenerSeccionesPendientes(array $datos): array
    {
        $faltantes = $this->obtenerCamposEsencialesFaltantes($datos);
        $secciones = [];

        foreach (self::CAMPOS_DATOS_PERSONALES as $campo) {
            if (in_array($campo, $faltantes, true)) {
                $secciones[] = 'DATOS_PERSONALES';
                break;
            }
        }

        foreach (self::CAMPOS_CONTACTO as $campo) {
            if (in_array($campo, $faltantes, true)) {
                $secciones[] = 'CONTACTO';
                break;
            }
        }

        foreach (self::CAMPOS_DIRECCION as $campo) {
            if (in_array($campo, $faltantes, true)) {
                $secciones[] = 'DIRECCION';
                break;
            }
        }

        return $secciones;
    }

    /**
     * @return array<string, string>
     */
    public function etiquetasSecciones(): array
    {
        return [
            'DATOS_PERSONALES' => 'Datos personales',
            'CONTACTO' => 'Información de contacto',
            'DIRECCION' => 'Dirección'
        ];
    }

    /**
     * @return array{
     *   completo: bool,
     *   camposFaltantes: list<string>,
     *   seccionesPendientes: list<string>,
     *   etiquetasSecciones: list<string>,
     *   perfil: ?array
     * }
     */
    public function evaluarPorUsuario(string $clvUsu): array
    {
        $clvUsu = trim($clvUsu);
        $perfil = $clvUsu !== ''
            ? $this->pacienteModel->obtenerPerfilCompleto($clvUsu)
            : null;

        if ($perfil === null) {
            return [
                'completo' => true,
                'camposFaltantes' => [],
                'seccionesPendientes' => [],
                'etiquetasSecciones' => [],
                'perfil' => null
            ];
        }

        $faltantes = $this->obtenerCamposEsencialesFaltantes($perfil);
        $secciones = $this->obtenerSeccionesPendientes($perfil);
        $etiquetasMap = $this->etiquetasSecciones();
        $etiquetas = [];

        foreach ($secciones as $clave) {
            $etiquetas[] = $etiquetasMap[$clave] ?? $clave;
        }

        return [
            'completo' => $faltantes === [],
            'camposFaltantes' => $faltantes,
            'seccionesPendientes' => $secciones,
            'etiquetasSecciones' => $etiquetas,
            'perfil' => $perfil
        ];
    }

    /**
     * Crea o resuelve el ciclo de notificación sin duplicar.
     * Ciclo activo = TituloNotif exacto TITULO_ACTIVO (leída o no).
     * Al completar: renombra a TITULO_RESUELTO y marca leída.
     * Nuevo ciclo: solo si no queda ningún TITULO_ACTIVO.
     *
     * @return array{
     *   completo: bool,
     *   camposFaltantes: list<string>,
     *   seccionesPendientes: list<string>,
     *   etiquetasSecciones: list<string>,
     *   notificacionCreada: bool,
     *   notificacionResuelta: bool
     * }
     */
    public function sincronizarAvisoPerfilIncompleto(
        string $clvPac,
        string $clvUsu
    ): array {
        $clvPac = trim($clvPac);
        $clvUsu = trim($clvUsu);

        $vacio = [
            'completo' => true,
            'camposFaltantes' => [],
            'seccionesPendientes' => [],
            'etiquetasSecciones' => [],
            'notificacionCreada' => false,
            'notificacionResuelta' => false
        ];

        if ($clvPac === '' || $clvUsu === '') {
            return $vacio;
        }

        $pdo = Database::connect();
        $propia = !$pdo->inTransaction();

        if ($propia) {
            $pdo->beginTransaction();
        }

        try {
            $lock = $pdo->prepare(
                'SELECT ClvPac
                 FROM paciente
                 WHERE ClvPac = :pac
                   AND ClvUsu = :usu
                 LIMIT 1
                 FOR UPDATE'
            );
            $lock->execute([
                'pac' => $clvPac,
                'usu' => $clvUsu
            ]);

            if (!$lock->fetch(PDO::FETCH_ASSOC)) {
                if ($propia && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                return $vacio;
            }

            $estado = $this->evaluarPorUsuario($clvUsu);
            $creada = false;
            $resuelta = false;

            if ($estado['completo']) {
                $resuelta = $this->notificacionModel
                    ->resolverCicloPerfilIncompleto(
                        $clvUsu,
                        self::TITULO_ACTIVO,
                        self::TITULO_RESUELTO,
                        self::TIPO_NOTIF
                    ) > 0;
            } else {
                $existeActivo = $this->notificacionModel
                    ->existePorUsuarioTipoYTitulo(
                        $clvUsu,
                        self::TIPO_NOTIF,
                        self::TITULO_ACTIVO
                    );

                if (!$existeActivo) {
                    $creada = $this->notificacionService->crearParaUsuario(
                        $clvUsu,
                        self::TITULO_ACTIVO,
                        self::MENSAJE_NOTIF,
                        self::TIPO_NOTIF
                    );
                }
            }

            if ($propia) {
                $pdo->commit();
            }

            return [
                'completo' => $estado['completo'],
                'camposFaltantes' => $estado['camposFaltantes'],
                'seccionesPendientes' => $estado['seccionesPendientes'],
                'etiquetasSecciones' => $estado['etiquetasSecciones'],
                'notificacionCreada' => $creada,
                'notificacionResuelta' => $resuelta
            ];
        } catch (Throwable $e) {
            if ($propia && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                'PerfilPacienteService::sincronizarAvisoPerfilIncompleto: '
                . $e->getMessage()
            );

            $estado = $this->evaluarPorUsuario($clvUsu);

            return [
                'completo' => $estado['completo'],
                'camposFaltantes' => $estado['camposFaltantes'],
                'seccionesPendientes' => $estado['seccionesPendientes'],
                'etiquetasSecciones' => $estado['etiquetasSecciones'],
                'notificacionCreada' => false,
                'notificacionResuelta' => false
            ];
        }
    }
}
