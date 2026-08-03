# Privacidad, consentimiento y conservación de expedientes

## Responsable

El responsable del tratamiento de datos personales es el **consultorio** de la
instalación (titular único), no PsicoMatch. PsicoMatch es únicamente el sistema
utilizado para gestionar la información.

## Consentimiento y versiones del aviso

- Tabla inmutable `aviso_privacidad_version` conserva el `ContenidoAviso`
  exacto publicado. Una versión publicada no modifica su contenido/hash.
- El hash se calcula sobre `ContenidoAviso` normalizado (UTF-8, LF, trim)
  antes de publicar.
- `consentimiento_datos_personales` referencia `IdAvisoPrivacidad` (FK) y
  conserva `VersionAviso`/`HashContenidoAviso` denormalizados al aceptar.
- PK de consentimiento: `IdConsentimiento` BIGINT AUTO_INCREMENT (sin
  ClaveService / MAX+1).
- Sin UNIQUE `(ClvUsu, VersionAviso)`: permite reaceptar tras revocación.
- Una sola fila `VIGENTE` por usuario: transacción + `FOR UPDATE` + reconsulta.
- `FechaAceptacion` y `MedioAceptacion` se resuelven en servidor.
- `FechaRevocacion` solo para `REVOCADO`; `SUPERSEDIDO` usa `FechaCambioEstado`.
- Publicación de la versión 1.0: seeder
  `database/seeders/publicar_aviso_privacidad.php` solo si hay datos legales
  reales (sin marcadores `[NOMBRE DEL RESPONSABLE]`, `[DOMICILIO]`, `[CORREO]`).

## Solicitudes ARCO / privacidad

**Decisión funcional (vigente):** el paciente **no** registra solicitudes ARCO
ni revocación dentro de PsicoMatch. El ejercicio de derechos y la revocación
se atienden directamente ante el consultorio por los medios del Aviso de
Privacidad.

- PACIENTE (configuración): solo ve versión aceptada, fecha, estado del
  consentimiento, enlace al aviso y datos de contacto del responsable.
- `POST /privacidad/solicitud` y `PrivacidadService::solicitarRevocacionOArco`
  **no insertan** nuevas filas (redirección / rechazo controlado).
- CONSULTORIO: vista histórica temporal
  (`/consultorio/privacidad/solicitudes`) para registros ya existentes.
  No es un módulo operativo nuevo; sin contador en sidebar.
- ADMINISTRADOR / PSICÓLOGO: sin acceso al detalle histórico.
- Tablas y filas existentes (`solicitud_privacidad`, etc.) se conservan hasta
  una limpieza expresa (no ejecutada todavía).

### Aviso 1.0 vs futura versión

- La fila publicada `aviso_privacidad_version` **1.0** es inmutable y no se
  modifica in-place.
- El generador `construirContenidoDefinitivoAviso` ya incluye procedimiento
  externo (contacto, datos mínimos, medio de respuesta) para una **nueva**
  versión (p. ej. 1.1) cuando se apruebe su publicación.
- No publicar 1.1 sin aprobación explícita.

## Menores de edad (política temporal — alternativa A)

Hasta implementar consentimiento de representante legal:

1. Edad desde `FechaNacimiento`; menor de 18 no autoconsiente.
2. Se bloquea la creación de historia clínica de menores.
3. Se informa que se requiere representante legal.
4. Soporte de representantes: pendiente (alternativa B futura).

## Ciclo de conservación del expediente

Estados reales de `EstatusHist`: `ACTIVO`, `INACTIVO`, `CERRADO`, `ARCHIVADO`.

Ciclo conceptual (sin añadir valores al ENUM todavía):

1. **ACTIVO** — atención en curso.
2. **CERRADO** — cierre terapéutico / fin de atención activa.
3. **ARCHIVADO** — expediente archivado (sigue conservado).
4. **Retención mínima de 5 años** — contados desde el **último acto clínico**.
5. **Elegible para revisión** — cálculo lógico; no es un valor de `EstatusHist`.
6. **Bloqueo** — decisión administrativa/legal previa a cualquier depuración.
7. **Eliminación segura** — solo tras verificación de obligaciones legales;
   **no implementada** (sin cron, sin DELETE automático, sin cascadas).

Notas:

- Inactivar al paciente (`EstadoActivoPac = 0`) **no** elimina el expediente.
- No se usa plazo de 3 años.
- No se ejecuta `DELETE` automático ni `ON DELETE CASCADE` para depurar
  expedientes.

## Cálculo del último acto clínico

Sin inventar columnas. Se toma el máximo entre candidatos reales:

1. Fecha de la última cita con `EstadoCita = 'ASISTIDA'`
   (`FechaCita` + hora de fin/inicio disponible).
2. `historial_clinico.FechaActualizacionHist` (o `FechaAperturaHist` si no hay
   actualización).
3. Último `seguimiento_sesion.FechaRegistroSeg` con estatus
   `FINALIZADO` o `CORREGIDO`.
4. Como proxy de cierre terapéutico: cuando `EstatusHist` es `CERRADO` o
   `ARCHIVADO`, se usa `FechaActualizacionHist` (no existe columna
   `FechaCierreTerapeutico`).

Un expediente es **elegible para revisión** si:

- su estatus es `CERRADO` o `ARCHIVADO`, y
- han transcurrido al menos **5 años** desde el último acto clínico.

La elegibilidad no autoriza borrado; solo identifica candidatos a revisión
humana.
