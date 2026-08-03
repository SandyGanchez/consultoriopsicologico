# Arquitectura de instalación independiente

## Modelo

```
una instalación
→ un dominio o subdominio
→ una base de datos
→ un consultorio
→ varios psicólogos
→ varios pacientes
```

El mismo código se despliega en distintos hosting/BD. No hay panel central,
marketplace, planes, pagos ni suscripciones.

## Roles

| Rol | Función |
|-----|---------|
| ADMINISTRADOR | Soporte técnico local: configuración del único consultorio, activación/recuperación de su cuenta principal, inactivación (`EstadoUsu`), incidencias `RolDestino=ADMINISTRADOR` (cuenta principal y escaladas). Sin operación clínica ni ARCO. Ver `docs/ADMINISTRADOR_SOPORTE_INSTALACION.md` e `docs/INCIDENCIAS_SOPORTE.md`. |
| CONSULTORIO | Catálogo institucional, especialistas, agenda, actividad operativa, configuración, incidencias de acceso de pacientes/psicólogos (primer nivel), escalamiento técnico. |
| PSICOLOGO | Precio/duración/oferta de servicios, sugerencias, agenda, expediente. |
| PACIENTE | Agendamiento viendo precio y duración del especialista. |

`ClvCons` y `consultorio_usuario` se conservan. Exactamente un consultorio por instalación.

## Catálogo y ofertas

1. **CONSULTORIO** crea el servicio institucional (`NombreServicio`, `Descripcion`, `EstatusServicio`).
2. **SISTEMA** crea `psicologo_servicio` para todos los psicólogos (activos e inactivos) con `EstatusAsignacion = INACTIVA` y valores técnicos `PrecioServicio = 0`, `DuracionMinutos = 0` (columnas NOT NULL).
3. **PSICÓLOGO** configura precio, duración y activa su oferta.
4. **PSICÓLOGO** puede sugerir un servicio nuevo (tabla `sugerencia_servicio`; aplicada en local, no en Hostinger/ZIP).
5. **CONSULTORIO** aprueba (formulario de alta + confirmación) o rechaza.
6. Perfil público y citas solo usan ofertas `ACTIVA` con precio > 0 y duración 1–480.
7. La cita guarda snapshot en `CostoAplicado` y `DuracionAplicadaMin`.

No hay asignación manual de servicios. No hay motivo de consulta en el agendamiento.

## Activación vs recuperación

- **Activación (`ALTA_CONSULTORIO`):** cuenta nueva; contraseña inicial; token temporal de un solo uso.
- **Recuperación (`RECUPERACION_CONSULTORIO`):** cuenta ya activada; nueva contraseña; no cambia `EstadoUsu` ni `EstatusCons`. Migración aplicada en local; pendiente en Hostinger.
- El administrador nunca conoce, asigna ni envía contraseñas.
- Inactivar/activar desde admin solo toca `usuario.EstadoUsu` de la cuenta principal.

## Incidencias de acceso

Tabla `incidencia_soporte` (local aplicada, con enrutamiento).

- Login: enruta por rol real del correo (paciente/psicólogo → CONSULTORIO;
  cuenta principal → ADMINISTRADOR).
- Panel consultorio: `/consultorio/incidencias` (primer nivel).
- Panel admin: `/administrador/incidencias` (cuenta principal + escaladas).
- Detalle: `docs/INCIDENCIAS_SOPORTE.md`.

## Costos y cancelación

Ver `docs/CITAS_COSTOS_CANCELACION.md`.

- `PrecioServicio` = tarifa actual; `CostoAplicado` = histórico de la cita.
- No existe módulo de pagos; no afirmar “pagado”.
- Cancelación paciente: `America/Mexico_City`, límite absoluto `inicio - LimiteCancHoras`.

## Despliegue

1. BD propia + `.env` propio (DB, APP_URL, SMTP, timezone).
2. Usuario ADMINISTRADOR inicial.
3. Configurar el único consultorio.
4. Activar cuenta del consultorio por enlace.
5. Completar identidad, horarios, servicios y especialistas.
6. Cuando se autorice producción: aplicar migraciones locales pendientes
   (`RECUPERACION_CONSULTORIO`, `incidencia_soporte`,
   `incidencia_soporte_enrutamiento`) en Hostinger.
   El ZIP de producción aún no se regenera en este paso.
