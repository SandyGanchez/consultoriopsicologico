# Migraciones aplicadas / pendientes

Registro técnico de migraciones revisables en `database/migrations/`.

| Archivo | Estado | Notas |
|---------|--------|-------|
| `20260808_verificacion_correo.sql` | APLICADA (local) | OTP + `usuario.CorreoVerificado` |
| `20260809_pacientes_dependientes.sql` | **APLICADA LOCAL** (2026-08-08) | `paciente.ClvPer`, `ClvUsu` nullable, `paciente_responsable`, consentimiento sujeto |
| `20260809_cita_responsable.sql` | **APLICADA LOCAL** (2026-08-08) | `cita.ClvUsuCreador`, `OrigenCita`, `IdRelacionResponsable`; `correo_cita.RolDestinatario` + `RESPONSABLE`. No producción. |

## Notas `20260809_pacientes_dependientes.sql`

- No ejecutar automáticamente.
- Incluye guardas `SIGNAL 45000` si hay inconsistencias (pacientes sin usuario/persona, `ClvPer` o `ClvUsu` duplicados).
- BD local real (2026-08-09): existe al menos un `ClvUsu` duplicado en `paciente` (`U009` → `PAC001`,`PAC003`). Debe resolverse **antes** de aplicar en esa BD.
- Prefijo persona para dependientes nuevos: `PER` (misma convención que invitaciones). No migrar claves históricas `P*`.

## Notas `20260809_cita_responsable.sql`

- Estado: **APLICADA LOCAL** (2026-08-08) en `consultorio_psicologico`. **No producción / Hostinger.**
- Agrega `cita.ClvUsuCreador`, `OrigenCita`, `IdRelacionResponsable` + índices/FKs; amplía `correo_cita.RolDestinatario` con `RESPONSABLE`.
- Caso A (Yo): `OrigenCita=PACIENTE`, `IdRelacionResponsable` NULL. Caso B (dependiente): `OrigenCita=RESPONSABLE` + `IdRelacionResponsable`.
