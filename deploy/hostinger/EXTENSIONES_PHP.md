# Extensiones PHP requeridas — Hostinger

Versión PHP: **8.2 o 8.3** (composer exige `>=8.2`).

## Obligatorias

- `pdo`
- `pdo_mysql`
- `mbstring`
- `openssl`
- `json`
- `session`
- `fileinfo`
- `curl` (recomendado para SMTP/diagnóstico)
- `filter`
- `hash`

## Recomendadas

- `intl` (fechas/locale)
- `gd` o `imagick` (si más adelante se procesan imágenes de uploads)
- `zip`

## Apache / módulos

- `mod_rewrite`
- `mod_headers` (opcional, cabeceras del `.htaccess`)
- `mod_expires` (opcional)
