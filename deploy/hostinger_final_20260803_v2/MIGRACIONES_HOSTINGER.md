# Migraciones Hostinger â€” inventario y orden

Paquete: `deploy/hostinger_final_20260803_v2/` (regenerado desde cero)  
Origen de archivos: `database/migrations/*.sql` (sin `proposed/`).

**Esta preparaciÃ³n no aplica migraciones** ni en local ni en Hostinger.

Estado observado en BD local `consultorio_psicologico` (verificaciÃ³n 2026-08-03):

| Objeto | Â¿Presente en local? |
|--|--|
| `sugerencia_servicio` | SÃ­ |
| `red_social_psicologo` / tablas redes | SÃ­ (hay tablas de redes) |
| `correo_cita` | **SÃ­** (aplicada en verificaciÃ³n final; antes solo existÃ­a en `consultorio_psicologico_correos_prueba`) |
| `activacion_cuenta.TipoActivacion` incluye `RECUPERACION_CONSULTORIO` | SÃ­ |
| `incidencia_soporte` | SÃ­ |
| `incidencia_soporte.RolDestino` (+ enrutamiento) | SÃ­ |
| Privacidad / consentimiento | Tablas presentes |

### ContradicciÃ³n aclarada (`correo_cita`)

Una detecciÃ³n previa informÃ³ â€œausente en localâ€ de forma **correcta** para
`consultorio_psicologico`. Las pruebas de correos se habÃ­an ejecutado sobre la
BD de ensayo `consultorio_psicologico_correos_prueba`, donde la tabla **sÃ­**
existÃ­a. Por eso reportes anteriores de â€œmigraciÃ³n aplicada y probadaâ€ y el
inventario del paquete no coincidÃ­an: hablaban de bases distintas.

En Hostinger hay que **comprobar antes** con las consultas de cada secciÃ³n; no
asumir el mismo estado.

---

## Orden recomendado global

1. `2026_08_02_activacion_cuenta.sql` (si faltan columnas/tablas de activaciÃ³n)
2. `2026_08_02_publicacion_consultorio.sql`
3. `2026_08_02_consentimiento_datos_personales.sql` (**privacidad / consentimiento**, cuando falte)
4. `2026_08_02_redes_sociales.sql`
5. `2026_08_02_sugerencia_servicio.sql`
6. `2026_08_02_correo_cita.sql`
7. `2026_08_03_tipo_recuperacion_consultorio.sql`
8. **`2026_08_03_incidencia_soporte.sql`** â† crea la tabla
9. **`2026_08_03_incidencia_soporte_enrutamiento.sql`** â† requiere tabla existente

Este orden **no sustituye** las consultas previas de comprobaciÃ³n de cada archivo.

### Orden crÃ­tico de incidencias

```text
1) Crear incidencia_soporte
2) Aplicar incidencia_soporte_enrutamiento
```

**No ejecutar la segunda si la tabla base no existe.**

---

## 1. ActivaciÃ³n de cuenta

**Archivo:** `2026_08_02_activacion_cuenta.sql`  
**PropÃ³sito:** tokens/activaciÃ³n de cuentas.  
**Dependencia:** tablas `usuario` / consultorio existentes.

**Antes:**
```sql
SHOW TABLES LIKE 'activacion_cuenta';
SHOW COLUMNS FROM activacion_cuenta LIKE 'TipoActivacion';
```

**Riesgo doble ejecuciÃ³n:** suele usar `IF NOT EXISTS` / comprobaciones; revisar el SQL antes.  
**Local:** tabla existe.

---

## 2. PublicaciÃ³n del consultorio

**Archivo:** `2026_08_02_publicacion_consultorio.sql`  
**PropÃ³sito:** `PublicadoCons`, `FechaPublicacionCons`.  
**Dependencia:** tabla `consultorio`.

**Antes:**
```sql
SHOW COLUMNS FROM consultorio LIKE 'PublicadoCons';
SHOW COLUMNS FROM consultorio LIKE 'FechaPublicacionCons';
```

**DespuÃ©s:** mismas consultas + Ã­ndice si el SQL lo crea.  
**Riesgo doble:** puede fallar si columnas ya existen (ALTER sin IF NOT EXISTS). Comprobar antes.

---

## 3. Privacidad / consentimiento

**Archivo:** `2026_08_02_consentimiento_datos_personales.sql`  
**PropÃ³sito:** consentimiento y soporte ARCO/privacidad.  
**Dependencia:** esquema base de usuarios/consultorio.

**Antes:**
```sql
SHOW TABLES LIKE 'consentimiento_datos_personales';
SHOW TABLES LIKE 'solicitud_privacidad';
SHOW TABLES LIKE 'aviso_privacidad_version';
```

**Local:** tablas de privacidad/consentimiento presentes.  
**Riesgo doble:** `CREATE TABLE IF NOT EXISTS` suele ser seguro; ALTERs no.

---

## 4. Redes sociales

**Archivo:** `2026_08_02_redes_sociales.sql`  
**PropÃ³sito:** redes del psicÃ³logo / consultorio para portada.  
**Dependencia:** psicÃ³logo / consultorio.

**Antes:**
```sql
SHOW TABLES LIKE 'red_social_psicologo';
SHOW TABLES LIKE '%red%';
```

**Local:** presentes tablas de redes.  
**Riesgo doble:** IF NOT EXISTS preferible; verificar.

---

## 5. Sugerencia de servicio

**Archivo:** `2026_08_02_sugerencia_servicio.sql`  
**PropÃ³sito:** flujo de sugerencias psicÃ³logo â†’ consultorio.  
**Dependencia:** `servicios`, `psicologo`, `consultorio`.

**Antes / despuÃ©s:**
```sql
SHOW TABLES LIKE 'sugerencia_servicio';
DESCRIBE sugerencia_servicio;
```

**Local:** sÃ­.  
**Riesgo doble:** IF NOT EXISTS.

---

## 6. Correo de cita

**Archivo:** `2026_08_02_correo_cita.sql`  
**PropÃ³sito:** cola `correo_cita` (confirmaciÃ³n/recordatorio, estados, reintentos).  
**Dependencia:** tabla `cita`, tabla `usuario` (`ClvCita`/`ClvUsu` utf8mb4_unicode_ci VARCHAR(10)).

**Antes (obligatorio):**
```sql
SHOW TABLES LIKE 'correo_cita';
SELECT TABLE_SCHEMA, TABLE_NAME
FROM information_schema.TABLES
WHERE TABLE_NAME = 'correo_cita';
```

**DespuÃ©s:**
```sql
SHOW CREATE TABLE correo_cita\G
```

**Local (tras verificaciÃ³n final):** presente en `consultorio_psicologico` y en
`consultorio_psicologico_correos_prueba`.  
**Riesgo doble:** `CREATE TABLE IF NOT EXISTS` (seguro relativo).  
**Cron:** `database/scripts/procesar_correos_citas.php` (solo CLI).

### Comportamiento del cÃ³digo si la tabla NO existe

El alta de cita **no falla**: `persistenciaDisponible()` detecta la ausencia y
omite preparar/enviar correos. La cita queda guardada; no hay rollback por
correos; el mensaje es de Ã©xito genÃ©rico; no se muestra SQLSTATE.  
**Advertencia de despliegue:** en producciÃ³n debe existir `correo_cita` antes
del humo de correos; de lo contrario el agendamiento opera â€œsordoâ€ a confirmaciones.

---

## 7. RecuperaciÃ³n CONSULTORIO

**Archivo:** `2026_08_03_tipo_recuperacion_consultorio.sql`  
**PropÃ³sito:** valor `RECUPERACION_CONSULTORIO` en enum de activaciÃ³n.  
**Dependencia:** `activacion_cuenta` / columna enum existente.

**Antes:**
```sql
SHOW COLUMNS FROM activacion_cuenta LIKE 'TipoActivacion';
```

**DespuÃ©s:** el enum debe listar `RECUPERACION_CONSULTORIO`.  
**Local:** ya incluye el valor.  
**Riesgo doble:** ALTER ENUM puede ser sensible; no repetir si ya estÃ¡.

---

## 8. Tabla incidencia_soporte (base)

**Archivo:** `2026_08_03_incidencia_soporte.sql`  
**PropÃ³sito:** tickets de acceso (tabla base).  
**Dependencia:** `consultorio`, `usuario`.

**Antes:**
```sql
SHOW TABLES LIKE 'incidencia_soporte';
```

**DespuÃ©s:**
```sql
SHOW CREATE TABLE incidencia_soporte\G
```

**Local:** sÃ­.  
**Riesgo doble:** `CREATE TABLE IF NOT EXISTS` (seguro relativo).  
**Importante:** esta migraciÃ³n **no** incluye `RolDestino` / enrutamiento.

---

## 9. Enrutamiento de incidencias

**Archivo:** `2026_08_03_incidencia_soporte_enrutamiento.sql`  
**PropÃ³sito:** `RolDestino`, `NivelAtencion`, `IdIncidenciaOrigen`, `ObservacionConsultorio`.  
**Dependencia obligatoria:** tabla `incidencia_soporte` ya creada.

**Antes:**
```sql
SHOW TABLES LIKE 'incidencia_soporte';
SHOW COLUMNS FROM incidencia_soporte LIKE 'RolDestino';
SHOW COLUMNS FROM incidencia_soporte LIKE 'NivelAtencion';
SHOW COLUMNS FROM incidencia_soporte LIKE 'IdIncidenciaOrigen';
SHOW COLUMNS FROM incidencia_soporte LIKE 'ObservacionConsultorio';
```

Si la tabla no existe â†’ **detener** y aplicar primero el punto 8.

**DespuÃ©s:** las cuatro columnas presentes + Ã­ndices/FK.  
**Local:** sÃ­.  
**Riesgo doble:** `ADD COLUMN` fallarÃ¡ si ya existen. Comprobar antes.  
**Backfill:** no hacer `UPDATE ... SET RolDestino='ADMINISTRADOR'` masivo.

---

## Excluido del paquete

- `database/migrations/proposed/` (propuesta antigua de incidencias)
- `database/backups/`
- Scripts `humo_*`, `aplicar_*`

---

## Procedimiento seguro en Hostinger

1. Respaldo BD.
2. Abrir phpMyAdmin / CLI.
3. Ejecutar consulta **Antes**.
4. Si ya aplicada â†’ saltar.
5. Si falta â†’ ejecutar el `.sql` correspondiente del ZIP privado.
6. Ejecutar consulta **DespuÃ©s**.
7. Continuar con la siguiente.

Documentar que durante una actualizaciÃ³n en Hostinger **no se debe eliminar**
`public_html/uploads/` existente sin respaldo: contiene logotipos, portadas y
fotos de perfil publicados. El ZIP solo aporta estructura y archivos pÃºblicos
necesarios para que la portada no quede rota en una instalaciÃ³n nueva.

