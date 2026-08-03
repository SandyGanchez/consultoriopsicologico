<?php

namespace App\Services;

class RedSocialUrlValidator
{
    public const PLATAFORMAS = [
        'Facebook',
        'Instagram',
        'WhatsApp',
        'TikTok',
        'YouTube',
        'LinkedIn',
        'Página Web'
    ];

    /**
     * @return array{ok: bool, url?: string, mensaje?: string}
     */
    public function validar(string $url, string $plataforma): array
    {
        $url = trim($url);
        $plataforma = trim($plataforma);

        if ($url === '') {
            return ['ok' => false, 'mensaje' => 'La URL es obligatoria.'];
        }

        if (mb_strlen($url) > 255) {
            return ['ok' => false, 'mensaje' => 'La URL no puede superar 255 caracteres.'];
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $url)) {
            return ['ok' => false, 'mensaje' => 'La URL contiene caracteres no permitidos.'];
        }

        if ($url !== strip_tags($url)) {
            return ['ok' => false, 'mensaje' => 'La URL no puede contener HTML.'];
        }

        $lower = strtolower($url);
        foreach (['javascript:', 'data:', 'file:', 'vbscript:'] as $esquema) {
            if (str_starts_with($lower, $esquema)) {
                return ['ok' => false, 'mensaje' => 'Esquema de URL no permitido.'];
            }
        }

        $partes = parse_url($url);
        if ($partes === false || empty($partes['scheme']) || empty($partes['host'])) {
            return ['ok' => false, 'mensaje' => 'La URL debe incluir esquema y host válidos.'];
        }

        $scheme = strtolower((string) $partes['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return ['ok' => false, 'mensaje' => 'Solo se permiten URLs http o https.'];
        }

        $host = strtolower((string) $partes['host']);
        if ($host === '' || str_contains($host, ' ') || !preg_match('/^[a-z0-9.-]+$/i', $host)) {
            return ['ok' => false, 'mensaje' => 'El host de la URL no es válido.'];
        }

        if ($plataforma === 'WhatsApp') {
            $hostOk = $host === 'wa.me'
                || $host === 'api.whatsapp.com'
                || $host === 'www.whatsapp.com'
                || $host === 'whatsapp.com'
                || str_ends_with($host, '.whatsapp.com');

            if (!$hostOk) {
                return [
                    'ok' => false,
                    'mensaje' => 'WhatsApp solo admite enlaces wa.me o del dominio oficial whatsapp.com.'
                ];
            }
        }

        if ($scheme === 'http') {
            // Preferir https: se acepta http, se normaliza a https cuando el host lo permite.
            $url = 'https://' . substr($url, strlen('http://'));
        }

        return ['ok' => true, 'url' => $url];
    }

    public function plataformaValida(string $plataforma): bool
    {
        return in_array(trim($plataforma), self::PLATAFORMAS, true);
    }
}
