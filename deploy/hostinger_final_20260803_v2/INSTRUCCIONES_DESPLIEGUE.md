# Instrucciones de despliegue — Hostinger (paquete v2)

Paquete: `deploy/hostinger_final_20260803_v2/`  
Dominio: `https://consultoriospsicologicospsicomatch.com`  
PHP de empaquetado: **8.2.12**  
Composer: **2.8.12** (`composer install --no-dev --optimize-autoloader`)

Este paquete se regeneró **desde cero** (no es un parche del ZIP anterior).

Estructura objetivo (conceptual):

```text
directorio_privado_del_dominio/
├── app/                  (incluye app/Config; no hay carpeta raíz config/)
├── routes/
├── vendor/
├── database/
│   ├── migrations/
│   └── scripts/procesar_correos_citas.php
├── storage/
├── composer.json
├── composer.lock
├── .env
└── public_html/          ← document root
    ├── index.php
    ├── .htaccess
    ├── assets/
    └── uploads/
```

`public_html/index.php` resuelve `APP_ROOT` así:

1. `dirname(public_html)/private` (si existe `vendor` + `app`)
2. `dirname(public_html)` (mismo nivel: `app/` + `vendor/` junto a `public_html/`)
3. el propio `public_html` (no recomendado)

---

## Pasos numerados

1. **Respaldar archivos** actuales de Hostinger (File Manager o FTP/SFTP).
2. **Respaldar la BD** de producción (phpMyAdmin → Exportar / o `mysqldump`).
3. **Respaldar `uploads/`** existentes (logotipos, portadas, perfiles). **No asumir que está vacío.**
4. **Crear o revisar `.env` privado** a partir de `.env.production.example` de este paquete.
   - `APP_ENV=production`
   - `APP_DEBUG=0`
   - `APP_URL=https://consultoriospsicologicospsicomatch.com`
   - `MAIL_CITA_DRY_RUN=1` en el primer humo
   - Completar `DB_*` y `MAIL_*` reales (no versionar secretos)
5. **Subir `privado_hostinger.zip`** fuera de `public_html` (directorio privado del dominio).
6. **Extraer** el ZIP privado. Debe quedar `app/`, `routes/`, `vendor/`, `database/`, `storage/`, `composer.json`, `composer.lock` en la raíz privada (**sin** carpeta contenedora `privado_hostinger/`).
7. **Subir `public_html_hostinger.zip`** dentro de `public_html`.
8. **Extraer** el ZIP público. Debe quedar:
   - `public_html/index.php`
   - `public_html/.htaccess`
   - `public_html/assets/`
   - `public_html/uploads/` (estructura)
   - **No** debe quedar `public_html/public/index.php`
9. **Antes de sobrescribir uploads:** conservar los archivos ya publicados. El ZIP solo trae `.gitkeep` y carpetas; **no eliminar ni sobrescribir** uploads existentes sin revisión.
10. **Seleccionar PHP** compatible en el panel (**8.2.x** recomendado; mínimo razonable 8.1+).
11. **Revisar permisos** de escritura (ver tabla abajo). **No usar 777.**
12. **Aplicar migraciones una por una** siguiendo `MIGRACIONES_HOSTINGER.md`.
    - Orden crítico incidencias: primero `incidencia_soporte`, luego `incidencia_soporte_enrutamiento`.
    - Para `correo_cita`: ejecutar `SHOW TABLES LIKE 'correo_cita';` antes.
13. **Mantener `MAIL_CITA_DRY_RUN=1`** durante el primer humo.
14. **Probar todos los roles**: visitante, paciente, psicólogo, consultorio, administrador.
15. **Probar incidencias** (enrutamiento CONSULTORIO / ADMINISTRADOR).
16. **Probar citas, costos programados y cancelación** (límite absoluto).
17. **Probar SMTP** con cuentas controladas.
18. **Cambiar `MAIL_CITA_DRY_RUN=0`** solo tras SMTP validado.
19. **Configurar cron** (cada 15 minutos sugerido). Sustituir la ruta real del hosting:

```text
*/15 * * * * php /RUTA_REAL_PRIVADA/database/scripts/procesar_correos_citas.php >> /RUTA_REAL_PRIVADA/storage/logs/correos_citas.log 2>&1
```

No inventar la ruta del usuario Hostinger. El script **solo CLI** (sin endpoint web).

20. **Verificar SSL** (HTTPS).
21. **Ejecutar humo** según `PRUEBAS_POST_DESPLIEGUE.md`.
22. **Borrar los ZIP del servidor** tras confirmar funcionamiento.

---

## Permisos de escritura

| Ruta | Uso |
|--|--|
| `public_html/uploads/consultorios/` | Logotipo |
| `public_html/uploads/consultorios/portadas/` | Portada |
| `public_html/uploads/perfiles/` | Fotos de perfil |
| `public_html/uploads/personas/` | Reserva histórica de fotos |
| `storage/logs/` | Logs privados |
| `storage/tmp/` | Temporales |
| `storage/locks/` | Lock del cron de correos |

Recomendación: directorios `0755` o `0775`; archivos nuevos `0644`.

---

## Cómo regresar al estado anterior (rollback)

1. Detener tráfico / mantenimiento si es posible.
2. Restaurar el **respaldo de archivos** (paso 1) sobre `public_html` y el directorio privado.
3. Restaurar el **respaldo de BD** (paso 2).
4. Restaurar **uploads** (paso 3) si se alteraron.
5. Verificar `.env` anterior y versión PHP.
6. Probar home + login.
7. Si solo falló el código y **no** se aplicaron migraciones nuevas, se puede restaurar solo archivos; si sí se aplicaron, restaurar BD también.

---

## Notas

- Este paquete **no** se subió a Hostinger desde la preparación local.
- No incluye `.env` real, `.git`, respaldos, scripts `humo_*` / `aplicar_*`, ni `database/migrations/proposed/`.
- Configuración vive en `app/Config/` (no hay carpeta raíz `config/`).
- Incluye dashboard visual del consultorio, scroll de sidebars, “Página principal” en los cuatro roles y módulo de correos/incidencias.
