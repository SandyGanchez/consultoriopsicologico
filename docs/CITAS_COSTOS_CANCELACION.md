# Citas, costos y cancelación

## Precio actual vs costo histórico

| Campo | Tabla | Significado |
|--|--|--|
| `PrecioServicio` | `psicologo_servicio` | Tarifa actual que ofrece el especialista |
| `CostoAplicado` | `cita` | Tarifa congelada al crear la cita |
| `DuracionAplicadaMin` | `cita` | Duración congelada al crear la cita |

Modificar `PrecioServicio` no cambia citas existentes.

No existe módulo de pagos. Terminología permitida:

- Costo de la consulta (paciente)
- Tarifa aplicada (psicólogo)
- Costo programado / importe programado (consultorio)

No usar “Pagado”, “Pago recibido”, “Saldo pendiente” sin evidencia persistente.

## Validación de reserva

Una asignación es agendable solo si:

- `EstatusAsignacion = ACTIVA`
- servicio institucional `ACTIVO`
- `PrecioServicio > 0`
- `DuracionMinutos` entre 1 y 480

La autoridad es el servidor (`AgendaService` + transacción).

## Cancelación del paciente

- Solo el paciente dueño, estado `PROGRAMADA`, cita no iniciada.
- Zona: `America/Mexico_City`.
- `FechaHoraLimite = FechaHoraInicioCita - consultorio.LimiteCancHoras`.
- Permitir si `ahora <= FechaHoraLimite` (igualdad exacta permite).
- UI muestra fecha absoluta (“Puedes cancelar hasta el …”).
- Backend es la autoridad final.

## Estados de cita

`PROGRAMADA` | `ASISTIDA` | `CANCELADA` | `INASISTENCIA`

## Supervisión del consultorio

- Agenda operativa: costo programado sin nombre de paciente.
- Actividad de especialistas: `/consultorio/actividad-especialistas`.
- Tarifas actuales: `consultorio/servicios/ver` (`PrecioServicio`).
