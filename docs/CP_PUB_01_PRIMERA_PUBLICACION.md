# CP-PUB-01 — Primera configuración y publicación

## Precondición

Administrador dio de alta al consultorio y el consultorio activó su cuenta.

## Pasos

1. Iniciar sesión como consultorio.
2. Observar estado **Borrador**.
3. Intentar publicar incompleto (botón deshabilitado o rechazo con pendientes).
4. Confirmar que el sistema enumera pendientes.
5. Abrir vista previa privada (`/consultorio/vista-previa`).
6. Completar descripción.
7. Completar dirección.
8. Configurar horarios.
9. Crear servicio.
10. Dar de alta y activar un psicólogo.
11. Confirmar progreso completo (100%).
12. Publicar.
13. Cerrar sesión.
14. Abrir la vista pública como visitante.
15. Confirmar que aparece.
16. Iniciar como administrador.
17. Confirmar estado **Publicado**.
18. Volver como consultorio.
19. Ocultar página.
20. Confirmar que desaparece para visitantes.
21. Confirmar que las citas existentes permanecen.
22. Volver a publicar.

## Pruebas negativas de seguridad

| Caso | Resultado esperado |
|------|--------------------|
| Consultorio intenta publicar otro `ClvCons` | Ignorado: `ClvCons` solo desde sesión |
| Manipulación de `ClvCons` en POST | Sin efecto |
| POST sin CSRF | Rechazado |
| Usuario de otro rol | Redirige a login / sin acceso |
| Consultorio `INACTIVO` | No publica |
| Consultorio `BLOQUEADO` | No publica |
| Página incompleta | Rechazo con pendientes |
| Doble clic | Botón se deshabilita; republicar es seguro |
| URL pública en borrador | Mensaje neutral / sin datos |
| Psicólogo de consultorio oculto | No aparece públicamente |
| Servicio de consultorio oculto | No aparece públicamente |
| Agenda pública de consultorio oculto | Sin psicólogos / reserva bloqueada |
