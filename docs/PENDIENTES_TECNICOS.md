# Pendientes técnicos

Registro de deuda técnica y trabajo diferido acordado entre etapas.

---

## Administrador — alcance de cuentas (2026-08-02)

**Estado:** aplicado en UI y controladores (sin borrar servicios legacy).

**Alcance vigente:** altas de cuenta, consulta administrativa, activar/inactivar,
reenviar activación, restablecer acceso, notificaciones de cuenta.

**Legacy conservado (sin uso de UI; rutas redirigen al listado):**

- `AdministradorController::editarConsultorio`
- `AdministradorController::actualizarConsultorio`
- `AdministradorController::vistaPreviaConsultorio`
- `AdministradorService::actualizarConsultorio` y helpers de actualización

**Mejora futura — `EstatusCons = BLOQUEADO`:** el ENUM ya existe en BD; no hay
acción administrativa “Bloquear” separada de inactivar. Evaluar en etapa futura
si se requiere un estado distinto (p. ej. sanción) sin borrar datos.

**No implementado a propósito:** incidencias, edición comercial desde admin,
vista previa pública administrativa.

---

## Horario consultorio — seed inicial para consultorios nuevos

**Estado:** implementado.

**Solución:** `HorarioConsultorio::crearDiasFaltantes()` crea solo los días ausentes (LUNES–VIERNES `ACTIVO` 09:00–18:00; sábado/domingo `INACTIVO`). Se invoca en el alta administrativa del consultorio y al abrir `consultorio/horario`.

---

## Disponibilidad vs. cambio de horario del consultorio

**Estado:** pendiente (post Etapa 3, sin implementar).

**Problema:** si el consultorio reduce su horario o inactiva un día en `horario_consultorio` después de que uno o más psicólogos ya registraron bloques en `disponibilidad_psicologo`, pueden quedar bloques existentes (incluso `ACTIVA`) fuera del nuevo horario o en días que el consultorio ya no atiende.

**Comportamiento actual:** la Etapa 3 valida contra `horario_consultorio` al crear, editar o **activar** bloques, pero no revisa ni ajusta bloques ya guardados cuando el consultorio cambia su horario después.

**Decisión pendiente para etapa futura** (elegir una estrategia):

1. **Impedir** que el consultorio reduzca o inactive un horario mientras existan disponibilidades incompatibles (`ACTIVA` o cualquier estatus fuera del nuevo rango).
2. **Inactivar automáticamente** las disponibilidades afectadas al guardar el nuevo horario del consultorio.
3. **Solicitar confirmación** al rol CONSULTORIO mostrando los bloques afectados antes de aplicar el cambio.

**Fuera de alcance actual:** jobs de reconciliación, triggers en BD, cambios en Etapa 2 aprobada, cambios en Etapa 3 aprobada.

---

## Generación de claves — migración a `consecutivos`

**Estado:** deuda técnica transversal (aceptada temporalmente en Etapa 3).

**Situación actual:** `ClaveService::generar()` usa `MAX(SUBSTRING(...)) + 1` sobre la tabla destino. Es el mecanismo vigente en `AuthService`, `AdministradorService`, `NotificacionService`, `DisponibilidadPsicologo`, etc.

**Riesgo:** bajo concurrencia, dos peticiones simultáneas pueden calcular el mismo consecutivo antes del `INSERT`. La PK de la tabla destino rechaza el duplicado, pero no hay reintento automático.

**Tabla disponible:** `consecutivos (NombreTabla, UltimoNumero)` existe en BD pero **no está integrada** en PHP.

**Etapa futura:** migrar `ClaveService` (y consumidores) a un flujo transaccional con bloqueo sobre `consecutivos`, por ejemplo:

1. `BEGIN` transacción.
2. `SELECT UltimoNumero FROM consecutivos WHERE NombreTabla = :tabla FOR UPDATE`.
3. Incrementar y `UPDATE consecutivos`.
4. Formatear clave con prefijo.
5. `INSERT` en tabla destino.
6. `COMMIT`.

**Fuera de alcance actual:** no modificar `ClaveService` ni módulos existentes hasta la etapa dedicada.

---

## Agendamiento — intervalo de inicio entre espacios

**Estado:** decisión temporal (Etapa 4).

**Comportamiento actual:** `AgendaService::INTERVALO_INICIO_MINUTOS = 30`. Los candidatos de hora de inicio se generan cada 30 minutos dentro de cada bloque válido (`horario_consultorio` ∩ `disponibilidad_psicologo`).

**Duración de la cita:** proviene de `psicologo_servicio.DuracionMinutos`, no del intervalo.

**Etapa futura:** evaluar intervalo dinámico según duración del servicio o política del consultorio.

---

## Perfil público del especialista

**Estado:** pendiente (post rediseño sección especialistas).

**Situación actual:** la ruta `GET /especialista/perfil` está desactivada porque `HomeController::perfilEspecialista()` no existe. El botón "Ver perfil" se ocultó en la sección pública.

**Etapa futura:** implementar vista pública con datos no sensibles (nombre, especialidad, descripción, servicios, consultorio) filtrando `MostrarEnPagina = 1` y estatus activos, sin correo ni teléfono personal.

---

## Home pública — consultorio único de instalación

**Estado:** convertido (instalación independiente de un solo consultorio).

**Comportamiento:** `Consultorio::obtenerUnicoDeInstalacion()` / `obtenerEstadoInstalacion()`:
cero → sitio en configuración; uno → consultorio actual; más de uno → error controlado + log.
`GET /` renderiza la página pública del único consultorio cuando `PublicadoCons = 1`.
No se usa `ORDER BY ... LIMIT 1` como selección silenciosa entre varios.

---

## Política de cancelación pública con LimiteCancHoras = 0

**Estado:** texto informativo provisional en home.

**Comportamiento:** si `LimiteCancHoras` es 0, la home invita a contactar al consultorio. La lógica efectiva de cancelación del paciente queda para etapa dedicada.

---

## Notificaciones internas — limitaciones de esquema y post-commit

**Estado:** documentado en la etapa de notificaciones in-app (sin alterar BD).

**Tabla real `notificacion`:** `ClvNotif`, `TituloNotif`, `MensajeNotif`, `TipoNotif` ENUM(`CITA`,`CANCELACION`,`RECORDATORIO`,`CUENTA`,`PSICOLOGO`,`SISTEMA`,`OTRA`), `FechaNotif`, `LeidaNotif`, `FechaLecturaNotif`, `ClvUsu`.

**Sin columnas:** `RutaNotif`, `ClvCita`. El código legacy que insertaba `RutaNotif` se alineó al esquema real. Los enlaces “Ver” se resuelven por rol + `TipoNotif` hacia módulos seguros (`paciente/mis-citas`, `psicologo/agenda`).

**Mapeo conceptual → ENUM:** `CITA_CREADA` / `CITA_ASISTIDA` / `CITA_INASISTENCIA` → `CITA`; `CITA_CANCELADA` → `CANCELACION`.

**Deduplicación:** sin `ClvCita` no hay UK por cita+tipo. Se evita duplicar creando notificaciones solo tras insert/update exitoso (`rowCount === 1` / cita insertada).

**Transacciones:**
- Alta de cita (paciente/psicólogo): notificación dentro de la misma transacción (mismo PDO singleton), antes del `commit`.
- Cancelación paciente y resultado asistencia/inasistencia: la acción principal hace `commit` en el modelo; la notificación corre después. Riesgo: la acción queda persistida aunque falle la notificación.

**Fuera de alcance actual:** correo, WhatsApp, SMS, push, WebSockets, Firebase, cron, recordatorios previos a cita, columnas nuevas.

**Consultorio / administrador (etapa notificaciones ampliadas):**
- Destinatarios CONSULTORIO vía `consultorio_usuario` (`EstatusConsUsu = ACTIVO`) + `usuario.RolUsu = CONSULTORIO` + `EstadoUsu = 1`. La UK `(ClvCons, ClvUsu)` permite varios usuarios por consultorio; se notifica a todos los activos sin duplicar `ClvUsu`.
- Payload consultorio: consulta operativa sin JOIN a paciente/persona del paciente; anonimización estructural (no comparación de nombres).
- Todas las notificaciones de cita (paciente, psicólogo, consultorio) se crean **después del commit** de la acción principal; son auxiliares y no provocan rollback. Fallos → `error_log` técnico sin datos clínicos.
- Admin: no hay flujo de “psicólogo pendiente”. Eventos admin reales: alta/activación/desactivación de consultorio y restablecimiento de acceso. El administrador actor también recibe su propia notificación SISTEMA/CUENTA.
- Tabla `incidencia` existe en BD pero no hay flujo PHP que la alimente → sin notificaciones de incidencias aún.
