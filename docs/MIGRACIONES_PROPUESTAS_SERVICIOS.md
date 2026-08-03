# Migración: `sugerencia_servicio`

## Estado

| Entorno | Estado |
|---------|--------|
| `consultorio_psicologico` (local) | **Aplicada** |
| Hostinger / producción | **No aplicada** |
| ZIP de producción | **No incluida** |

Archivo definitivo:

`database/migrations/2026_08_02_sugerencia_servicio.sql`

La copia en `database/migrations/proposed/` fue retirada para evitar duplicados.

## Flujo funcional

1. CONSULTORIO crea el catálogo institucional.
2. SISTEMA incorpora `psicologo_servicio` a todos los psicólogos.
3. PSICÓLOGO configura precio, duración y oferta.
4. PSICÓLOGO puede sugerir un servicio nuevo (no crea el catálogo).
5. CONSULTORIO rechaza (observación obligatoria) o inicia aprobación → formulario de alta → confirma → crea servicio, incorpora a todos, marca APROBADA y vincula `ClvServCreado`.
6. PACIENTE ve precio y duración individuales al agendar.

El código mantiene `tablaDisponible()` / `persistenciaDisponible()` como protección para instalaciones antiguas.

## SQL aplicado (resumen)

- PK `IdSugerenciaServicio` BIGINT UNSIGNED AI
- FKs a `psicologo`, `consultorio`, `usuario`, `servicios`
- Índice no único `IDX_Sugerencia_Psi_Estado_Nombre (ClvPsi, EstadoSugerencia, NombreSugerido)`
- `utf8mb4_unicode_ci` / InnoDB
