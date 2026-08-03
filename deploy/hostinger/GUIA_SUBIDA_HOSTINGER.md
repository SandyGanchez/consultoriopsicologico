# Guía de subida — PsicoMatch en Hostinger

Dominio canónico:

`https://consultoriospsicologicospsicomatch.com`

No uses `www` como URL principal. El `.htaccess` de producción redirige
`https://www.consultoriospsicologicospsicomatch.com` al apex.

---

## Estructura recomendada (separada)

```
/home/USUARIO/domains/consultoriospsicologicospsicomatch.com/
├── public_html/          ← contenido de deploy/hostinger/public_html
│   ├── index.php
│   ├── .htaccess
│   ├── assets/
│   ├── uploads/
│   └── favicon (si aplica)
└── private/              ← contenido de deploy/hostinger/private
    ├── app/
    ├── routes/
    ├── vendor/
    ├── storage/
    ├── scripts/
    ├── composer.json
    ├── composer.lock
    ├── .env.production.example
    └── .env              ← lo creas tú en el servidor (nunca en el ZIP)
```

`index.php` busca `APP_ROOT` en este orden:

1. `../private` (recomendado)
2. directorio padre
3. el propio document root (solo si Hostinger obliga a todo-en-uno)

---

## Alternativa: todo dentro de public_html

Si la cuenta no permite carpetas hermanas:

1. Sube `public_html` + el contenido de `private` dentro de `public_html`.
2. Conserva el `.htaccess` de producción (bloquea `app`, `vendor`, `.env`, etc.).
3. Crea `.env` fuera del alcance web si puedes; si no, dentro de `public_html` pero bloqueado por `.htaccess` (menos ideal).

---

## Pasos exactos

1. **Asociar el dominio** al hosting en el panel de Hostinger.
2. **Confirmar DNS** (A/CNAME) apuntando al hosting. Espera propagación.
3. **Activar SSL** (Let's Encrypt) para el dominio apex. Verifica también el certificado de `www` si existe.
4. **Elegir versión de PHP**: **8.2 o 8.3** (mínimo 8.2).
5. **Activar extensiones** listadas en `EXTENSIONES_PHP.md`.
6. **Crear base MySQL** (utf8mb4) y anotar host, nombre, usuario y contraseña.
7. **Importar SQL**: `sql/psicomatch_demo_hostinger.sql` (o tu dump propio).
8. **Subir archivos públicos** de `public_html/` al document root del dominio.
9. **Colocar archivos privados** en `../private/` (hermana de `public_html`).
10. **Crear `.env`** copiando `.env.production.example` dentro de `private/`.
11. **Establecer la URL HTTPS**:
    ```
    APP_URL=https://consultoriospsicologicospsicomatch.com
    APP_ENV=production
    APP_DEBUG=false
    ```
    Sin `/public`, sin `www`, sin `http://`.
12. **Configurar SMTP** (Hostinger u otro) en las variables `MAIL_*`.
13. **Revisar permisos** (`PERMISOS.md`): escritura en `public_html/uploads` y `private/storage`.
14. **Probar la raíz**: `https://consultoriospsicologicospsicomatch.com/` debe mostrar el único consultorio publicado (no un directorio de varios).
15. **Probar compatibilidad**: `/consultorios/CON001` debe redirigir a `/` si es el único; otro ID → 404.
16. **Probar especialista**: `/especialistas/{ClvPsi}` del demo.
17. **Probar login**: `/login` (tras fijar contraseña admin).
18. **Probar correo**: activación / recuperación con enlace HTTPS del dominio real.
19. **Probar uploads**: logo/portada del consultorio.
20. **Revisar logs** de PHP/Hostinger y `private/storage/logs` si aplica.
21. **Retirar temporales**: scripts de prueba, SQL locales, copias `.env.bak`, `last_activation_url.json`, backups públicos.
22. **No importar** `database/seeds/dev_multiconsultorio_con002.sql` ni ningún segundo consultorio.

---

## Contraseña del administrador (demo)

El SQL de demostración incluye un administrador activo, pero **las contraseñas no son operativas a propósito**.

Desde SSH o terminal con PHP del hosting, en `private/`:

```bash
php scripts/set_admin_password.php "TuClaveSegura123"
```

Correo del administrador demo:

`administrador@consultoriospsicologicospsicomatch.com`

Cambia también las contraseñas de consultorio y psicólogo demo antes de producción real, o desactívalos.

---

## Variable oficial de URL

Única fuente: **`APP_URL`** en `.env`.

`Helper::baseUrl()` la usa para:

- vistas y formularios
- redirecciones (`Response::redirect`)
- assets
- activación de cuentas
- enlaces de correo

No existe una segunda variable `BASE_URL` de configuración. La constante PHP `BASE_URL` solo se define en `index.php` como compatibilidad legacy = `APP_URL + '/'`.

---

## Checklist rápido post-despliegue

- [ ] `https://consultoriospsicologicospsicomatch.com/` carga la portada
- [ ] `www` redirige al apex
- [ ] HTTP redirige a HTTPS
- [ ] `/login`, `/registro`, `/forgot-password` responden
- [ ] `/consultorios/{id}` muestra solo consultorios publicados
- [ ] CSS/JS cargan desde el dominio HTTPS
- [ ] Correo de activación usa `https://consultoriospsicologicospsicomatch.com/activar-cuenta?token=...`
- [ ] No aparecen `localhost`, `/public` ni rutas de Laragon
