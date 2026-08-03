# Pruebas post-despliegue (paquete v2)

Dominio: `https://consultoriospsicologicospsicomatch.com`  
Inicio con `MAIL_CITA_DRY_RUN=1`. Cambiar a `0` solo tras SMTP validado.

Marcar cada ítem: [ ] pendiente · [x] ok · [!] fallo

---

## VISITANTE

- [ ] Home carga por HTTPS
- [ ] Navbar pública
- [ ] Especialistas: CTA “Ver información”
- [ ] Modal: foto, datos, servicios, precio, duración, redes, Agendar, Cerrar
- [ ] No aparece “Ver página completa”
- [ ] Ubicación / mapa
- [ ] Login y registro accesibles
- [ ] Assets CSS/JS sin 404 y sin localhost

---

## PACIENTE

- [ ] Login
- [ ] Sidebar: **Página principal** → `/` (sesión se conserva)
- [ ] Inicio del panel distinto de la portada
- [ ] Agendar cita (precio/duración servidor; duración 1–480)
- [ ] Costo de la consulta visible (`CostoAplicado`)
- [ ] Límite absoluto de cancelación
- [ ] Cancelar antes/igual al límite: permite; después: bloquea
- [ ] Correo de cita en dry-run

---

## PSICÓLOGO

- [ ] Login
- [ ] Sidebar: **Página principal** → `/` (sesión se conserva)
- [ ] Identidad fija + menú con scroll interno + Cerrar sesión fijo
- [ ] Inicio abre dashboard privado
- [ ] Agenda: tarifa aplicada (`CostoAplicado`)
- [ ] Pendientes clínicos / pacientes / expedientes / disponibilidad / servicios
- [ ] Menú móvil: toggle, backdrop, Escape, cierre al enlace

---

## CONSULTORIO

- [ ] Login
- [ ] Sidebar: **Página principal** → `/`
- [ ] Scroll interno del menú; Cerrar sesión siempre visible
- [ ] Dashboard visual: bienvenida, métricas, próximas citas, actividad, acciones, alertas
- [ ] Costos programados + aclaración de no cobro
- [ ] Sin datos clínicos en dashboard/agenda
- [ ] CSS `consultorio.css` + `consultorio-dashboard.css` cargan (Bootstrap primero)
- [ ] Actividad de especialistas
- [ ] Incidencias (primer nivel) + escalar
- [ ] Configuración / horario / servicios

---

## ADMINISTRADOR

- [ ] Login
- [ ] **Página principal** → `/` (sesión se conserva)
- [ ] Cuenta del consultorio / activación / recuperación
- [ ] Incidencias solo destino ADMINISTRADOR
- [ ] Sin acceso a expediente clínico

---

## CORREOS / CRON

- [ ] Tabla `correo_cita` presente
- [ ] `MAIL_CITA_DRY_RUN=1` durante humo
- [ ] Script CLI `database/scripts/procesar_correos_citas.php` (sin endpoint web)
- [ ] Cron cada 15 min con ruta real privada
- [ ] Tras SMTP OK: `MAIL_CITA_DRY_RUN=0`

---

## SEGURIDAD / INFRA

- [ ] `APP_DEBUG=0`
- [ ] `.env` fuera de `public_html`
- [ ] HTTPS / cookies Secure + HttpOnly
- [ ] Sin directory listing
- [ ] Sin SQLSTATE visible al usuario
- [ ] Uploads existentes conservados

---

## ROLLBACK DE PRUEBA

Si falla un flujo crítico: restaurar archivos + BD + uploads según `INSTRUCCIONES_DESPLIEGUE.md`.
