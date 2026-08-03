<?php

use App\Core\Session;
use App\Helpers\Helper;
use App\Services\NotificacionService;

$usuarioSesion = Session::get('usuario');
$clvUsuCampana = is_array($usuarioSesion)
    ? trim((string) ($usuarioSesion['ClvUsu'] ?? ''))
    : '';

$totalNoLeidasCampana = 0;
$notificacionesRecientesCampana = [];
$csrfCampana = Session::csrfToken();

if ($clvUsuCampana !== '') {
    try {
        $servicioCampana = new NotificacionService();
        $totalNoLeidasCampana = $servicioCampana->contarNoLeidas(
            $clvUsuCampana
        );
        $notificacionesRecientesCampana =
            $servicioCampana->obtenerRecientes($clvUsuCampana, 5);
    } catch (Throwable $e) {
        $totalNoLeidasCampana = 0;
        $notificacionesRecientesCampana = [];
    }
}

$formatearRelativo = static function (?string $fecha): string {
    if ($fecha === null || trim($fecha) === '') {
        return '';
    }

    try {
        $zona = new DateTimeZone('America/Mexico_City');
        $momento = new DateTimeImmutable($fecha, $zona);
        $ahora = new DateTimeImmutable('now', $zona);
        $segundos = $ahora->getTimestamp() - $momento->getTimestamp();

        if ($segundos < 60) {
            return 'Hace un momento';
        }

        if ($segundos < 3600) {
            $mins = (int) floor($segundos / 60);

            return $mins === 1
                ? 'Hace 1 minuto'
                : "Hace {$mins} minutos";
        }

        if ($segundos < 86400) {
            $horas = (int) floor($segundos / 3600);

            return $horas === 1
                ? 'Hace 1 hora'
                : "Hace {$horas} horas";
        }

        $dias = (int) floor($segundos / 86400);

        return $dias === 1
            ? 'Hace 1 día'
            : "Hace {$dias} días";
    } catch (Throwable $e) {
        return '';
    }
};

?>

<div
    class="pm-notif-campana"
    data-notif-campana
    data-url-recientes="<?= htmlspecialchars(
        Helper::baseUrl('notificaciones/recientes'),
        ENT_QUOTES,
        'UTF-8'
    ); ?>"
    data-url-marcar-todas="<?= htmlspecialchars(
        Helper::baseUrl('notificaciones/marcar-todas-leidas'),
        ENT_QUOTES,
        'UTF-8'
    ); ?>"
    data-url-login="<?= htmlspecialchars(
        Helper::baseUrl('login'),
        ENT_QUOTES,
        'UTF-8'
    ); ?>"
    data-csrf="<?= htmlspecialchars(
        $csrfCampana,
        ENT_QUOTES,
        'UTF-8'
    ); ?>"
>

    <button
        type="button"
        class="pm-notif-toggle"
        data-notif-toggle
        aria-expanded="false"
        aria-haspopup="true"
        aria-label="Notificaciones"
        title="Notificaciones"
    >
        <i class="bi bi-bell"></i>

        <span
            class="pm-notif-badge<?= $totalNoLeidasCampana > 0 ? '' : ' is-hidden'; ?>"
            data-notif-badge
            <?= $totalNoLeidasCampana > 0
                ? 'aria-label="' . (int) $totalNoLeidasCampana . ' no leídas"'
                : 'aria-hidden="true"'; ?>
        >
            <?= (int) $totalNoLeidasCampana > 99
                ? '99+'
                : (int) $totalNoLeidasCampana; ?>
        </span>
    </button>

    <div
        class="pm-notif-dropdown"
        data-notif-dropdown
        hidden
    >

        <div class="pm-notif-dropdown-header">
            <strong>Notificaciones</strong>

            <?php if ($totalNoLeidasCampana > 0): ?>
                <button
                    type="button"
                    class="pm-notif-marcar-todas"
                    data-notif-marcar-todas
                >
                    Marcar todas como leídas
                </button>
            <?php else: ?>
                <button
                    type="button"
                    class="pm-notif-marcar-todas is-hidden"
                    data-notif-marcar-todas
                    hidden
                >
                    Marcar todas como leídas
                </button>
            <?php endif; ?>
        </div>

        <div class="pm-notif-lista" data-notif-lista>

            <?php if (empty($notificacionesRecientesCampana)): ?>

                <div class="pm-notif-vacio" data-notif-vacio>
                    No tienes notificaciones.
                </div>

            <?php else: ?>

                <?php foreach ($notificacionesRecientesCampana as $n): ?>

                    <?php
                        $claveN = (string) ($n['ClvNotif'] ?? '');
                        $leidaN = (int) ($n['LeidaNotif'] ?? 0) === 1;
                        $tipoN = strtolower(trim(
                            (string) ($n['TipoNotif'] ?? 'otra')
                        ));
                        $claseTipoN = 'tipo-' . preg_replace(
                            '/[^a-z0-9_-]/',
                            '',
                            $tipoN
                        );
                    ?>

                    <a
                        href="<?= htmlspecialchars(
                            Helper::baseUrl(
                                'notificaciones/abrir/'
                                . rawurlencode($claveN)
                            ),
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>"
                        class="pm-notif-item<?= $leidaN ? '' : ' is-unread'; ?> <?= htmlspecialchars($claseTipoN, ENT_QUOTES, 'UTF-8'); ?>"
                    >
                        <span class="pm-notif-item-titulo">
                            <?= htmlspecialchars(
                                (string) ($n['TituloNotif'] ?? 'Notificación'),
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>
                        </span>

                        <span class="pm-notif-item-mensaje">
                            <?= htmlspecialchars(
                                (string) ($n['MensajeNotif'] ?? ''),
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>
                        </span>

                        <span class="pm-notif-item-fecha">
                            <?= htmlspecialchars(
                                $formatearRelativo(
                                    $n['FechaNotif'] ?? null
                                ),
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>
                        </span>
                    </a>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>

        <a
            href="<?= htmlspecialchars(
                Helper::baseUrl('notificaciones'),
                ENT_QUOTES,
                'UTF-8'
            ); ?>"
            class="pm-notif-ver-todas"
        >
            Ver todas las notificaciones
        </a>

    </div>

</div>
