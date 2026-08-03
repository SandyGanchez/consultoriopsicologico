# Prueba de rutas — dominio real (instalación de consultorio único)

Base: `https://consultoriospsicologicospsicomatch.com`

## Públicas

| Ruta | Esperado |
|------|----------|
| `/` | Página pública del único consultorio (si `PublicadoCons = 1`) o mensaje de sitio en configuración |
| `/especialistas/{psicologo}` | Perfil público del especialista del consultorio único |
| `/agendar-cita` | Intención de agendar → login / panel paciente |
| `/login` | Formulario de acceso |
| `/registro` | Registro de paciente |
| `/forgot-password` | Recuperación |
| `/activar-cuenta` | Formulario (sin token: error controlado) |
| `/consultorios/{ClvCons}` | Compatibilidad: 301 a `/` si es el único; 404 si no |
| `/consultorios/{ClvCons}/especialistas/{psicologo}` | Compatibilidad: 301 a `/especialistas/{psicologo}` si coincide; 404 si no |
| `/assets/css/style.css` | CSS 200 |
| Ruta inexistente | 404 |

## Protegidas (requieren sesión)

| Ruta | Rol |
|------|-----|
| `/administrador` | ADMINISTRADOR (cuenta del consultorio; sin listado múltiple) |
| `/consultorio` | CONSULTORIO |
| `/psicologo` | PSICOLOGO |
| `/paciente` | PACIENTE |

## Redirecciones

| Origen | Destino |
|--------|---------|
| `http://consultoriospsicologicospsicomatch.com/...` | HTTPS apex |
| `https://www.consultoriospsicologicospsicomatch.com/...` | HTTPS apex |

## Validación local de URLs (sin DNS)

```bash
php deploy/hostinger/scripts/validar_urls_produccion.php
```

Simula `APP_URL=https://consultoriospsicologicospsicomatch.com` y comprueba
que no se generen `localhost`, `/public` ni dobles barras.

## No incluir en despliegue

- `database/seeds/dev_multiconsultorio_con002.sql` (solo desarrollo; no ejecutar en Hostinger)
