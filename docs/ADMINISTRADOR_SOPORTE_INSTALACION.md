# Administrador — soporte técnico de la instalación

## Función

El rol `ADMINISTRADOR` es soporte técnico **local** de una instalación
independiente de PsicoMatch. No opera una plataforma multiconsultorio.

```
una instalación → un hosting → un dominio → una BD → un consultorio
→ varios psicólogos → varios pacientes
```

## Instalación única

- Estado `ninguno`: permite configuración inicial.
- Estado `unico`: administra la cuenta principal.
- Estado `multiple`: error controlado; no se usa `LIMIT 1` para elegir.

## Cuenta principal

Se identifica por:

`consultorio` → `consultorio_usuario` (`EsResponsable = 1`, vínculo válido)
→ `usuario` (`RolUsu = CONSULTORIO`).

Si hay 0 o más de una cuenta responsable: inconsistencia; se bloquean
operaciones sensibles.

## Activación vs recuperación

| | Activación (`ALTA_CONSULTORIO`) | Recuperación (`RECUPERACION_CONSULTORIO`) |
|--|--|--|
| Uso | Cuenta nueva / pendiente de contraseña inicial | Cuenta ya activa que perdió el acceso |
| `EstadoUsu` | Pasa a 1 al completar | No cambia |
| `EstatusCons` | No cambia | No cambia |
| Token | Hash SHA-256, 24h, un solo uso | Igual, propósito distinto |
| Ruta | `/activar-cuenta` | `/restablecer-acceso-consultorio` |

Migraciones locales aplicadas:

- `database/migrations/2026_08_03_tipo_recuperacion_consultorio.sql`
- `database/migrations/2026_08_03_incidencia_soporte.sql`
- `database/migrations/2026_08_03_incidencia_soporte_enrutamiento.sql`

No aplicadas en Hostinger / producción todavía.

## Incidencias

El administrador solo ve tickets `RolDestino = ADMINISTRADOR`
(cuenta principal y escaladas). Los de paciente/psicólogo los atiende el
CONSULTORIO. Detalle: `docs/INCIDENCIAS_SOPORTE.md`.

## Sesión tras inactivar

`AccesoSesionService` revalida `usuario.EstadoUsu` desde la BD en cada
petición a controladores protegidos. Si la cuenta fue inactivada con sesión
abierta: destruye sesión, regenera id y redirige a login.

## Inactivación de cuenta

Solo modifica `usuario.EstadoUsu` de la cuenta principal.

No modifica:

- `consultorio.EstatusCons`
- psicólogos / pacientes
- citas, servicios, expedientes, página pública

## Incidencias

Ver `docs/INCIDENCIAS_SOPORTE.md`.

Sidebar: Inicio · Cuenta del consultorio · Incidencias (contador abiertas) ·
Notificaciones · Cerrar sesión.

## Límites de acceso

Sin acceso a pacientes, citas, psicólogos, servicios, expedientes, ARCO,
redes sociales ni sugerencias de servicios.

## Código legacy multiconsultorio

Rutas `/administrador/consultorios/*` con `{id}` redirigen a
`/administrador/consultorio` y no aceptan un `ClvCons` externo para operar.
Vistas legacy documentadas como inactivas en
`app/Views/administrador/consultorios/index.php`.

## Despliegue

El mismo código se despliega en instalaciones distintas; cada una con su BD.
Pendiente: aplicar migraciones de recuperación e incidencias en Hostinger
cuando se autorice el despliegue (sin regenerar ZIP en este paso).
