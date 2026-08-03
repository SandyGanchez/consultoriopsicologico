# Incidencias de soporte de acceso

## Arquitectura de atención

| Destino | Quién atiende | Casos |
|--|--|--|
| `CONSULTORIO` | Cuenta principal / usuarios CONSULTORIO | Acceso de pacientes y psicólogos; correo no localizado (primer nivel) |
| `ADMINISTRADOR` | Soporte técnico de la instalación | Cuenta principal CONSULTORIO; incidencias escaladas |

El `ADMINISTRADOR` no atiende tickets ordinarios de paciente/psicólogo ni opera clínica, citas, expedientes ni ARCO.

## Campos de enrutamiento

Tabla: `incidencia_soporte`

- `RolDestino`: `CONSULTORIO` | `ADMINISTRADOR`
- `NivelAtencion`: `PRIMER_NIVEL` | `ESCALADA`
- `IdIncidenciaOrigen`: FK autorreferencial (hijo escalado → original)
- `ObservacionConsultorio`: notas operativas del consultorio
- `ObservacionAdministrador`: notas técnicas (solo admin)

## Enrutamiento desde login

El formulario público siempre responde con mensaje neutro.
El rol se resuelve solo desde BD por correo (nunca desde POST).

| Correo corresponde a | Acción |
|--|--|
| PACIENTE | Ticket `CONSULTORIO` / `PRIMER_NIVEL` |
| PSICOLOGO | Ticket `CONSULTORIO` / `PRIMER_NIVEL` |
| CONSULTORIO | Ticket `ADMINISTRADOR` / `PRIMER_NIVEL` |
| ADMINISTRADOR | No crea ticket; log `admin_self_help`; canal externo documentado |
| Inexistente | Mismo mensaje; si se registra, destino `CONSULTORIO` |

## Escalamiento

El CONSULTORIO no cambia `RolDestino` de la incidencia original.

Al escalar (transacción):

1. Bloquea original `FOR UPDATE`
2. Confirma pertenencia, no `RESUELTA`, sin escalada activa
3. Crea hija: `ADMINISTRADOR` + `ESCALADA` + `IdIncidenciaOrigen`
4. Original queda `EN_PROCESO` con observación “Escalada a soporte técnico”
5. Notifica solo al administrador

Al resolver la hija, se notifica operativamente al consultorio (sin notas internas del admin).

## Paneles

- CONSULTORIO: `/consultorio/incidencias` — solo `RolDestino=CONSULTORIO`
- ADMINISTRADOR: `/administrador/incidencias` — solo `RolDestino=ADMINISTRADOR`

## Tipos y estados

Tipos: `AUTENTICACION`, `CUENTA_BLOQUEADA`, `ACTIVACION`, `RECUPERACION`, `CAMBIO_CORREO`, `OTRO_ACCESO`

Estados: `PENDIENTE` → `EN_PROCESO` | `RESUELTA`; `EN_PROCESO` → `RESUELTA`

## Migraciones locales

1. `database/migrations/2026_08_03_incidencia_soporte.sql`
2. `database/migrations/2026_08_03_incidencia_soporte_enrutamiento.sql`

Pendientes de despliegue en Hostinger (no aplicadas en producción).

## Formulario público

- CSRF
- rate limit (IP hash 5/15 min)
- anti doble envío
- anti duplicado 60s
- sin archivos / sin HTML
- sin revelar existencia de cuenta, rol ni estado
