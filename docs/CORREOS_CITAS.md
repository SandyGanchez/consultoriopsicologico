# Correos de cita: confirmación y recordatorio 24h

## Estado

| Entorno | Estado |
|---------|--------|
| BD de prueba | Validación de migración |
| `consultorio_psicologico` (local) | **Pendiente de aplicar** (no aplicada en esta entrega) |
| Hostinger / ZIP | **No aplicados** |

Migración: `database/migrations/2026_08_02_correo_cita.sql`

## Canales

- **Correo:** tabla `correo_cita` + `CorreoCitaService` + `MailService`.
- **Campana:** `NotificacionService` (independiente; no controla idempotencia SMTP).

## Flujos

### Confirmación inmediata

Tras `COMMIT` de una cita `PROGRAMADA`:

1. Filas `CONFIRMACION` (paciente y psicólogo).
2. Envío post-commit; fallo SMTP no revierte la cita.

### Recordatorio 24h

- `FechaProgramada = FechaHoraInicio - 24h` (`America/Mexico_City`).
- Si al crear la cita faltan menos de 24h: filas `RECORDATORIO_24H` con `OMITIDO` / `CITA_CREADA_CON_MENOS_DE_24H` (sin segundo correo inmediato).
- CLI omite si la cita ya no está `PROGRAMADA`.

### Paciente nuevo (activación)

- Paciente: `CONFIRMACION` queda `OMITIDO` con `ACTIVACION_COMBINADA_PRIMERA_CITA`.
- Se conserva `enviarActivacionPacienteConCita`.
- Psicólogo: confirmación normal.
- Recordatorio 24h según política.

## CLI

```bash
php database/scripts/procesar_correos_citas.php
```

Cron sugerido (producción, no aplicar aún):

```cron
0 * * * * php /ruta/private/database/scripts/procesar_correos_citas.php >> /ruta/logs/correos_citas.log 2>&1
```

Pruebas locales sin SMTP real:

```env
MAIL_CITA_DRY_RUN=1
```

## CSRF

`PacienteController::guardarCita` valida `csrf_token`.  
`PsicologoController::guardarCitaAgenda` ya lo validaba.
