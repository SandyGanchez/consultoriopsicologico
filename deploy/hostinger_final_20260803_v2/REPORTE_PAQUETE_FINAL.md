# REPORTE PAQUETE FINAL — hostinger_final_20260803_v2

Paquete regenerado **desde cero** (sin reutilizar/parchear los ZIP de `hostinger_final_20260803`).  
Dominio objetivo: `https://consultoriospsicologicospsicomatch.com`  
Fecha de empaquetado local: 2026-08-03  
PHP: **8.2.12** · Composer: **2.8.12** · `composer install --no-dev --optimize-autoloader`

**DETENERSE AQUÍ:** no se subió nada a Hostinger; no se modificó la BD; no se enviaron correos reales.

---

## A. ZIP privado

- Ruta: `deploy/hostinger_final_20260803_v2/privado_hostinger.zip`
- Tamaño: **660 207 bytes** (~0.63 MB)
- SHA-256: `ACB377246E96F6A889124E762C64FF5D1E080B8DD62954D73C301A79081F1C20`

## B. ZIP público

- Ruta: `deploy/hostinger_final_20260803_v2/public_html_hostinger.zip`
- Tamaño: **1 696 009 bytes** (~1.62 MB)
- SHA-256: `14E56474E13F916117D6C8B170B0B171B89D2AE7970A0B1A03795D71CE6B4856`

## C. Árbol interno (raíz)

**privado_hostinger.zip**

```text
app/
routes/
vendor/
database/migrations/
database/scripts/procesar_correos_citas.php
storage/logs|tmp|locks/
composer.json
composer.lock
```

**public_html_hostinger.zip**

```text
index.php
.htaccess
assets/
uploads/ (+ subcarpetas con .gitkeep)
```

Sin `public/` anidado ni `public_html/` anidado.

## D. Checksums SHA-256

Ver archivo `CHECKSUMS_SHA256.txt` (hashes nuevos, distintos del paquete anterior).

## E. Archivos recientes verificados en el paquete

Incluidos y confirmados en ZIP / extracción:

- Consultorio: `sidebarConsultorio.php`, `dashboard.php`, `ConsultorioController.php`, `Cita.php`, `consultorio.css`, `consultorio-dashboard.css`, `consultorio.js`
- Paciente: `sidebarPaciente.php` (Página principal → `/`)
- Psicólogo: `psicologo/partials/sidebar.php` (archivo real usado por el layout), `psicologo.css`, `psicologo.js`
- Admin: `navbar_admin.php` (Página principal → `/`)
- Correos/incidencias: `CorreoCita.php`, `CorreoCitaService.php`, `IncidenciaSoporteService.php`, migraciones y script CLI

## F. Composer

- `composer install --no-dev --optimize-autoloader` → OK
- Solo dependencia de producción: `phpmailer/phpmailer`
- **No** existe `vendor/phpunit`

## G. php -l

- 269 archivos PHP en staging → **0 errores**

## H. node --check

- 20 JS propios (sin bootstrap) → **0 errores**

## I. composer validate

- `composer.json` válido (advertencia de licencia ausente; no bloqueante)
- exit 0

## J. Autoload

- `vendor/autoload.php` carga
- Clases: `Helper`, `CorreoCitaService`, `IncidenciaSoporteService`, `PHPMailer` → OK

## K. Extracción simulada

Carpeta: `deploy/hostinger_final_20260803_v2/hostinger_staging_final_v2/`

- Privado → raíz del staging
- Público → `public_html/`
- Sin `.env` en raíz ni en `public_html`
- Sin `public_html/public/` ni `public_html/public_html/`

## L. APP_ROOT resuelto

Con la lógica de `public_html/index.php`:

`.../hostinger_staging_final_v2` (padre de `public_html`, donde están `vendor/` + `app/`)

## M. Storage

Presente: `storage/`, `storage/logs/`, `storage/tmp/`, `storage/locks/` (+ `.gitkeep`)

## N. Uploads

- Solo estructura + `.gitkeep`
- **0** fotografías/documentos de prueba incluidos
- Instrucción explícita: respaldar y no sobrescribir uploads existentes en Hostinger

## O. Inventario de migraciones (9)

1. `2026_08_02_activacion_cuenta.sql`
2. `2026_08_02_publicacion_consultorio.sql`
3. `2026_08_02_consentimiento_datos_personales.sql`
4. `2026_08_02_redes_sociales.sql`
5. `2026_08_02_sugerencia_servicio.sql`
6. `2026_08_02_correo_cita.sql`
7. `2026_08_03_tipo_recuperacion_consultorio.sql`
8. `2026_08_03_incidencia_soporte.sql`
9. `2026_08_03_incidencia_soporte_enrutamiento.sql`

Sin `proposed/`.

## P. Orden de incidencias

1. `2026_08_03_incidencia_soporte.sql`
2. `2026_08_03_incidencia_soporte_enrutamiento.sql`  
**No aplicar la segunda si la tabla base no existe.**

## Q. correo_cita

- Migración incluida
- Modelo/servicio/script CLI incluidos
- Script: solo CLI, lock en `storage/locks`, timezone vía `APP_TIMEZONE` / `America/Mexico_City`
- Cron sugerido cada 15 min (ruta Hostinger a completar en el servidor)
- Humo inicial con `MAIL_CITA_DRY_RUN=1`

## R. Página principal por rol

| Rol | Archivo | Ruta | Helper |
|--|--|--|--|
| PACIENTE | `app/Views/paciente/sidebarPaciente.php` | `/` | `Helper::baseUrl('/')` |
| PSICOLOGO | `app/Views/psicologo/partials/sidebar.php` | `/` | `Helper::baseUrl('/')` |
| CONSULTORIO | `app/Views/layouts/sidebarConsultorio.php` | `/` | `Helper::baseUrl('/')` |
| ADMINISTRADOR | `app/Views/layouts/navbar_admin.php` | `/` | `Helper::baseUrl('/')` |

Sin `localhost` hardcodeado. Misma pestaña; no logout.

## S. Scroll / logout CONSULTORIO

Incluido en CSS/JS/HTML: `100dvh`, menú `overflow-y: auto`, brand/home/footer fijos, logout siempre visible, móvil con backdrop/Escape.

## T. Scroll / logout PSICOLOGO

Incluido: identidad fija, home fuera del scroll, menú scrolleable, footer logout fijo, JS con `scrollIntoView` del activo.

## U. Dashboard visual CONSULTORIO

Incluido: `dashboard.php` + `consultorio-dashboard.css` (carga condicional tras Bootstrap/`consultorio.css`).

## V. Referencias locales

- Hallazgo único en código de app (comentario documental): `app/Config/Paths.php` menciona ejemplo `/consultorio_psicologico/public` — **no afecta runtime** (`APP_URL` viene del `.env`).
- No hay `consultorio_psicologico.test`, `C:\laragon`, dumps ni cuentas de humo en los ZIP.

## W. Secretos

- Ningún `.env` real en los ZIP
- `.env.production.example` sin credenciales
- Sin passwords embebidos detectados en el empaquetado

## X. BD

**No se modificó** la base de datos. **No se ejecutaron migraciones.**

## Y. Hostinger

**No se conectó** ni se modificó Hostinger. Los ZIP **no se subieron**.

## Z. Correos reales

**No se enviaron** correos reales. `MAIL_CITA_DRY_RUN=1` en el ejemplo de producción.

---

## Artefactos del paquete

```text
deploy/hostinger_final_20260803_v2/
├── privado_hostinger.zip
├── public_html_hostinger.zip
├── .env.production.example
├── INSTRUCCIONES_DESPLIEGUE.md
├── MIGRACIONES_HOSTINGER.md
├── PRUEBAS_POST_DESPLIEGUE.md
├── INVENTARIO_PAQUETE.txt
├── CHECKSUMS_SHA256.txt
├── REPORTE_PAQUETE_FINAL.md
├── _staging_private/          (trabajo local)
├── _staging_public/           (trabajo local)
└── hostinger_staging_final_v2/ (extracción simulada)
```

El paquete anterior `deploy/hostinger_final_20260803/` **no fue sobrescrito**.
