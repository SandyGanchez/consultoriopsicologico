# Estructura del paquete `deploy/hostinger`

```
deploy/hostinger/
├── public_html/                 # Subir al document root
│   ├── index.php                # Punto de entrada
│   ├── .htaccess                # HTTPS, www→apex, front controller
│   ├── assets/
│   └── uploads/                 # vacío (writable)
├── private/                     # Subir FUERA de public_html
│   ├── app/
│   ├── routes/
│   ├── vendor/
│   ├── storage/
│   ├── scripts/
│   ├── composer.json
│   ├── composer.lock
│   └── .env.production.example
├── sql/
│   └── psicomatch_demo_hostinger.sql
├── scripts/
│   ├── empaquetar_hostinger.ps1
│   ├── generar_sql_demo.php
│   ├── set_admin_password.php
│   └── validar_urls_produccion.php
├── GUIA_SUBIDA_HOSTINGER.md
├── EXTENSIONES_PHP.md
├── PERMISOS.md
├── PRUEBA_RUTAS.md
└── ESTRUCTURA.md
```

## Qué va a public_html

- `index.php`
- `.htaccess`
- `assets/`
- `uploads/` (vacío al inicio)
- favicon / imágenes públicas ya incluidas en `assets`

## Qué queda fuera (private)

- `app/`
- `routes/`
- `vendor/`
- `storage/`
- `composer.json` / `composer.lock`
- `.env` / `.env.production.example`
- scripts de mantenimiento

## No incluir nunca en el hosting público

- `.env` real en el ZIP
- backups `.sql` fuera de `sql/` demo
- `.git/`
- `database/backups/`
- `storage/tmp/last_activation_url.json`
- seeds de desarrollo
- logs
