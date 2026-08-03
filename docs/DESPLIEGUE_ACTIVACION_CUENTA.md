# Despliegue: activación de cuentas y publicación del consultorio

Migraciones:

1. `database/migrations/2026_08_02_activacion_cuenta.sql`
2. `database/migrations/2026_08_02_publicacion_consultorio.sql`

## Checklist

1. **Respaldo** de la base de datos completa.
2. **Ejecutar la migración de activación** solo desde CLI/SSH (nunca desde una ruta web):

```bash
mysql -u USUARIO -p NOMBRE_BD < database/migrations/2026_08_02_activacion_cuenta.sql
```

3. **Ejecutar la migración de publicación** (verificar antes que las columnas no existan):

```sql
SHOW COLUMNS FROM consultorio LIKE 'PublicadoCons';
SHOW COLUMNS FROM consultorio LIKE 'FechaPublicacionCons';
SHOW INDEX FROM consultorio WHERE Key_name = 'IDX_Consultorio_Publicado';
```

Si no existen, aplicar:

```bash
mysql -u USUARIO -p NOMBRE_BD < database/migrations/2026_08_02_publicacion_consultorio.sql
```

4. **Verificar** columnas e índice:

```sql
SHOW COLUMNS FROM consultorio LIKE 'PublicadoCons';
SHOW COLUMNS FROM consultorio LIKE 'FechaPublicacionCons';
SHOW INDEX FROM consultorio WHERE Key_name = 'IDX_Consultorio_Publicado';
SELECT ClvCons, PublicadoCons, FechaPublicacionCons FROM consultorio;
```

Los consultorios existentes deben quedar con `PublicadoCons = 0` y `FechaPublicacionCons = NULL`.

5. Configurar **BASE_URL / APP_URL** con HTTPS en producción.
6. Configurar **SMTP** (`MAIL_*` en `.env`).
7. Probar **vista previa** (`/consultorio/vista-previa`).
8. Probar **publicar / ocultar** (`POST /consultorio/publicacion/publicar` y `/ocultar`).
9. Confirmar que **ningún consultorio queda publicado automáticamente** tras la migración.
10. Probar altas con activación (psicólogo, paciente, consultorio).

## Notas

- No ejecutar migraciones desde rutas públicas.
- No modifica el dump oficial `database/consultorio_psicologico.sql` sin autorización.
- No inserta datos de prueba.
- No contiene credenciales.
- `EstatusCons` no se usa como estado de publicación; solo `PublicadoCons` / `FechaPublicacionCons`.
- La recuperación de contraseña (`forgot-password`) es un proceso distinto.

## Migraciones posteriores (local; pendientes Hostinger)

Cuando se autorice producción, además de las anteriores:

```bash
mysql -u USUARIO -p NOMBRE_BD < database/migrations/2026_08_03_tipo_recuperacion_consultorio.sql
mysql -u USUARIO -p NOMBRE_BD < database/migrations/2026_08_03_incidencia_soporte.sql
mysql -u USUARIO -p NOMBRE_BD < database/migrations/2026_08_03_incidencia_soporte_enrutamiento.sql
```

Validar estructura en BD de prueba antes de producción. Ver
`docs/INCIDENCIAS_SOPORTE.md` y `docs/CITAS_COSTOS_CANCELACION.md`.
