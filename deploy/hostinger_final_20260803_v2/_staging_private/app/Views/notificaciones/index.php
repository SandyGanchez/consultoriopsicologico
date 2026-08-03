<?php

use App\Helpers\Helper;

$notificaciones =
    $notificaciones ?? [];

$totalNoLeidas =
    (int) ($totalNoLeidas ?? 0);
/*
|--------------------------------------------------------------------------
| DATOS DE LA VISTA
|--------------------------------------------------------------------------
*/

$notificaciones = $notificaciones ?? [];
$totalNoLeidas = (int) ($totalNoLeidas ?? 0);
$csrfToken = (string) (
    $csrfToken
    ?? \App\Core\Session::csrfToken()
);

$usuario = is_array($usuario ?? null) ? $usuario : [];
$rolUsuario = strtoupper(trim(
    (string) ($usuario['RolUsu'] ?? '')
));

/*
|--------------------------------------------------------------------------
| ESCAPAR CONTENIDO
|--------------------------------------------------------------------------
*/

$escapar = static function ($valor): string {
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        'UTF-8'
    );
};

/*
|--------------------------------------------------------------------------
| GENERAR URL
|--------------------------------------------------------------------------
|
| Si tu proyecto ya cuenta con una función url(), se utilizará.
| En caso contrario, se construye una ruta absoluta desde la raíz.
|
*/

$generarUrl = static function (string $ruta): string {
    $ruta = ltrim($ruta, '/');

    if (function_exists('url')) {
        return url($ruta);
    }

    if (defined('BASE_URL')) {
        return rtrim(BASE_URL, '/') . '/' . $ruta;
    }

    return '/' . $ruta;
};

/*
|--------------------------------------------------------------------------
| CONFIGURACIÓN VISUAL POR TIPO
|--------------------------------------------------------------------------
*/

$configuracionTipos = [
    'CITA' => [
        'icono' => 'bi-calendar-check',
        'clase' => 'tipo-cita',
        'nombre' => 'Cita'
    ],

    'CANCELACION' => [
        'icono' => 'bi-calendar-x',
        'clase' => 'tipo-cancelacion',
        'nombre' => 'Cancelación'
    ],

    'RECORDATORIO' => [
        'icono' => 'bi-alarm',
        'clase' => 'tipo-recordatorio',
        'nombre' => 'Recordatorio'
    ],

    'CUENTA' => [
        'icono' => 'bi-person-gear',
        'clase' => 'tipo-cuenta',
        'nombre' => 'Cuenta'
    ],

    'PSICOLOGO' => [
        'icono' => 'bi-person-badge',
        'clase' => 'tipo-psicologo',
        'nombre' => 'Psicólogo'
    ],

    'SISTEMA' => [
        'icono' => 'bi-gear',
        'clase' => 'tipo-sistema',
        'nombre' => 'Sistema'
    ],

    'OTRA' => [
        'icono' => 'bi-bell',
        'clase' => 'tipo-otra',
        'nombre' => 'Otra'
    ]
];

/*
|--------------------------------------------------------------------------
| FORMATEAR FECHA
|--------------------------------------------------------------------------
*/

$formatearFecha = static function (?string $fecha): string {
    if (empty($fecha)) {
        return '';
    }

    try {
        $fechaObjeto = new DateTime($fecha);

        return $fechaObjeto->format('d/m/Y h:i A');
    } catch (Throwable $e) {
        return $fecha;
    }
};

?>

<style>
    .notificaciones-page {
        width: 100%;
    }

    .notificaciones-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .notificaciones-title {
        margin: 0;
        color: #39433b;
        font-size: 1.75rem;
        font-weight: 700;
    }

    .notificaciones-subtitle {
        margin: 0.4rem 0 0;
        color: #788079;
        font-size: 0.95rem;
    }

    .notificaciones-counter {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        margin-left: 0.4rem;
        padding: 0.2rem 0.65rem;
        border-radius: 999px;
        background: #99CDD8;
        color: #25373b;
        font-size: 0.82rem;
        font-weight: 700;
        vertical-align: middle;
    }

    .btn-marcar-todas {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.45rem;
        min-height: 42px;
        padding: 0.65rem 1rem;
        border: 1px solid #657166;
        border-radius: 10px;
        background: #ffffff;
        color: #526055;
        font-size: 0.9rem;
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;
        transition: 0.2s ease;
    }

    .btn-marcar-todas:hover {
        background: #657166;
        color: #ffffff;
    }

    .notificaciones-list {
        display: flex;
        flex-direction: column;
        gap: 0.85rem;
    }

    .notificacion-card {
        position: relative;
        display: grid;
        grid-template-columns: 50px minmax(0, 1fr) auto;
        gap: 1rem;
        align-items: flex-start;
        padding: 1.1rem;
        border: 1px solid #e2e6e2;
        border-radius: 14px;
        background: #ffffff;
        box-shadow: 0 4px 14px rgba(50, 65, 53, 0.05);
        transition:
            transform 0.2s ease,
            box-shadow 0.2s ease,
            border-color 0.2s ease;
    }

    .notificacion-card:hover {
        transform: translateY(-1px);
        border-color: #c6d2c8;
        box-shadow: 0 7px 18px rgba(50, 65, 53, 0.09);
    }

    .notificacion-card.no-leida {
        border-left: 5px solid #99CDD8;
        background: #fbfeff;
    }

    .notificacion-icono {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 48px;
        height: 48px;
        border-radius: 13px;
        font-size: 1.25rem;
    }

    .tipo-cita {
        background: #e8f5ed;
        color: #38734d;
    }

    .tipo-cancelacion {
        background: #fdeceb;
        color: #b24b45;
    }

    .tipo-recordatorio {
        background: #fff4dc;
        color: #9a6817;
    }

    .tipo-cuenta {
        background: #e9f1fb;
        color: #426d9c;
    }

    .tipo-psicologo {
        background: #f0eafa;
        color: #70529c;
    }

    .tipo-sistema {
        background: #edf0ed;
        color: #59635b;
    }

    .tipo-otra {
        background: #e8f5f7;
        color: #387781;
    }

    .notificacion-contenido {
        min-width: 0;
    }

    .notificacion-superior {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.55rem;
        margin-bottom: 0.4rem;
    }

    .notificacion-titulo {
        margin: 0;
        color: #344038;
        font-size: 1rem;
        font-weight: 700;
    }

    .notificacion-tipo {
        display: inline-flex;
        align-items: center;
        padding: 0.17rem 0.55rem;
        border-radius: 999px;
        background: #eef2ef;
        color: #657166;
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
    }

    .indicador-no-leida {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #56a6b5;
    }

    .notificacion-mensaje {
        margin: 0 0 0.65rem;
        color: #606a62;
        font-size: 0.91rem;
        line-height: 1.55;
        overflow-wrap: anywhere;
    }

    .notificacion-fecha {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        color: #8a928b;
        font-size: 0.78rem;
    }

    .notificacion-acciones {
        display: flex;
        align-items: center;
        gap: 0.45rem;
    }

    .notificacion-acciones form {
        margin: 0;
    }

    .btn-notificacion {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.35rem;
        min-height: 36px;
        padding: 0.45rem 0.7rem;
        border-radius: 9px;
        font-size: 0.8rem;
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;
        transition: 0.2s ease;
    }

    .btn-abrir {
        border: 1px solid #657166;
        background: #657166;
        color: #ffffff;
    }

    .btn-abrir:hover {
        background: #515c52;
        color: #ffffff;
    }

    .btn-leida {
        border: 1px solid #cbd4cc;
        background: #ffffff;
        color: #657166;
    }

    .btn-leida:hover {
        background: #eef2ef;
    }

    .btn-eliminar {
        width: 36px;
        padding: 0;
        border: 1px solid #efd0ce;
        background: #ffffff;
        color: #b85b55;
    }

    .btn-eliminar:hover {
        background: #b85b55;
        color: #ffffff;
    }

    .notificaciones-vacio {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-height: 330px;
        padding: 2rem;
        border: 1px dashed #cdd5ce;
        border-radius: 16px;
        background: #ffffff;
        text-align: center;
    }

    .notificaciones-vacio-icono {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 76px;
        height: 76px;
        margin-bottom: 1rem;
        border-radius: 50%;
        background: #eef4ef;
        color: #657166;
        font-size: 2rem;
    }

    .notificaciones-vacio h2 {
        margin: 0 0 0.45rem;
        color: #465249;
        font-size: 1.2rem;
    }

    .notificaciones-vacio p {
        max-width: 420px;
        margin: 0;
        color: #7b847c;
        font-size: 0.92rem;
        line-height: 1.5;
    }

    @media (max-width: 900px) {
        .notificaciones-header {
            flex-direction: column;
        }

        .notificacion-card {
            grid-template-columns: 46px minmax(0, 1fr);
        }

        .notificacion-acciones {
            grid-column: 1 / -1;
            justify-content: flex-end;
            padding-top: 0.4rem;
        }
    }

    @media (max-width: 560px) {
        .notificacion-card {
            grid-template-columns: 1fr;
        }

        .notificacion-icono {
            width: 42px;
            height: 42px;
        }

        .notificacion-acciones {
            grid-column: auto;
            flex-wrap: wrap;
            justify-content: flex-start;
        }

        .btn-notificacion {
            flex: 1;
        }

        .btn-eliminar {
            flex: 0 0 38px;
        }
    }
</style>

<section class="notificaciones-page">

    <header class="notificaciones-header">

        <div>
            <h1 class="notificaciones-title">
                Notificaciones

                <?php if ($totalNoLeidas > 0): ?>
                    <span class="notificaciones-counter">
                        <i class="bi bi-bell-fill"></i>

                        <?= $totalNoLeidas ?>

                        <?= $totalNoLeidas === 1
                            ? 'nueva'
                            : 'nuevas'
                        ?>
                    </span>
                <?php endif; ?>
            </h1>

            <p class="notificaciones-subtitle">
                Consulta los avisos y movimientos importantes de tu cuenta.
            </p>
        </div>

        <?php if ($totalNoLeidas > 0): ?>

            <form
                action="<?= htmlspecialchars(
                    Helper::baseUrl(
                        'notificaciones/marcar-todas-leidas'
                    ),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                method="post"
            >
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= $escapar($csrfToken) ?>"
                >

                <button
                    type="submit"
                    class="btn-marcar-todas"
                >
                    <i class="bi bi-check2-all"></i>
                    Marcar todas como leídas
                </button>
            </form>

        <?php endif; ?>

    </header>

    <?php if (empty($notificaciones)): ?>

        <div class="notificaciones-vacio">

            <div class="notificaciones-vacio-icono">
                <i class="bi bi-bell"></i>
            </div>

            <h2>No tienes notificaciones</h2>

            <p>
                Cuando ocurra alguna actividad importante,
                aparecerá en esta sección.
            </p>

        </div>

    <?php else: ?>

        <div class="notificaciones-list">

            <?php foreach ($notificaciones as $notificacion): ?>

                <?php
                    $clave = (string) (
                        $notificacion['ClvNotif']
                        ?? ''
                    );

                    $tipo = strtoupper(
                        (string) (
                            $notificacion['TipoNotif']
                            ?? 'SISTEMA'
                        )
                    );

                    $configuracion =
                        $configuracionTipos[$tipo]
                        ?? $configuracionTipos['OTRA'];

                    $esLeida =
                        (int) (
                            $notificacion['LeidaNotif']
                            ?? 0
                        ) === 1;

                    $tipoRelacionado = in_array(
                        $tipo,
                        ['CITA', 'CANCELACION'],
                        true
                    );

                    $puedeAbrirModulo =
                        (
                            $tipoRelacionado
                            && in_array(
                                $rolUsuario,
                                [
                                    'PACIENTE',
                                    'PSICOLOGO',
                                    'CONSULTORIO'
                                ],
                                true
                            )
                        )
                        || (
                            $rolUsuario === 'ADMINISTRADOR'
                            && in_array(
                                $tipo,
                                ['SISTEMA', 'CUENTA', 'PSICOLOGO'],
                                true
                            )
                        );
                ?>

                <article
                    class="notificacion-card
                    <?= !$esLeida ? 'no-leida' : '' ?>"
                >

                    <div
                        class="notificacion-icono
                        <?= $escapar(
                            $configuracion['clase']
                        ) ?>"
                    >
                        <i
                            class="bi
                            <?= $escapar(
                                $configuracion['icono']
                            ) ?>"
                        ></i>
                    </div>

                    <div class="notificacion-contenido">

                        <div class="notificacion-superior">

                            <?php if (!$esLeida): ?>
                                <span
                                    class="indicador-no-leida"
                                    title="No leída"
                                ></span>
                            <?php endif; ?>

                            <h2 class="notificacion-titulo">
                                <?= $escapar(
                                    $notificacion['TituloNotif']
                                    ?? 'Notificación'
                                ) ?>
                            </h2>

                            <span class="notificacion-tipo">
                                <?= $escapar(
                                    $configuracion['nombre']
                                ) ?>
                            </span>

                        </div>

                        <p class="notificacion-mensaje">
                            <?= nl2br(
                                $escapar(
                                    $notificacion['MensajeNotif']
                                    ?? ''
                                )
                            ) ?>
                        </p>

                        <span class="notificacion-fecha">
                            <i class="bi bi-clock"></i>

                            <?= $escapar(
                                $formatearFecha(
                                    $notificacion['FechaNotif']
                                    ?? null
                                )
                            ) ?>
                        </span>

                    </div>

                    <div class="notificacion-acciones">

                        <?php if ($puedeAbrirModulo): ?>

                            <a
                                href="<?= htmlspecialchars(
                                    Helper::baseUrl(
                                        'notificaciones/abrir/'
                                        . rawurlencode($clave)
                                    ),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                class="btn-notificacion btn-abrir"
                            >
                                <i class="bi bi-box-arrow-up-right"></i>
                                Ver
                            </a>

                        <?php elseif (!$esLeida): ?>

                            <form
                                action="<?= htmlspecialchars(
                                    Helper::baseUrl(
                                        'notificaciones/marcar-leida/'
                                        . rawurlencode($clave)
                                    ),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                method="post"
                            >
                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= $escapar($csrfToken) ?>"
                                >

                                <button
                                    type="submit"
                                    class="btn-notificacion btn-leida"
                                >
                                    <i class="bi bi-check2"></i>
                                    Marcar como leída
                                </button>
                            </form>

                        <?php endif; ?>

                        <form
                            action="<?= htmlspecialchars(
                                Helper::baseUrl(
                                    'notificaciones/eliminar/'
                                    . rawurlencode($clave)
                                ),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            method="post"
                            onsubmit="return confirm(
                                '¿Deseas eliminar esta notificación?'
                            );"
                        >
                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= $escapar($csrfToken) ?>"
                            >

                            <button
                                type="submit"
                                class="btn-notificacion btn-eliminar"
                                title="Eliminar notificación"
                                aria-label="Eliminar notificación"
                            >
                                <i class="bi bi-trash3"></i>
                            </button>
                        </form>

                    </div>

                </article>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</section>