# Permisos recomendados

## Directorios

| Ruta | Permiso | Notas |
|------|---------|--------|
| `public_html/` | 755 | Document root |
| `public_html/assets/` | 755 | Solo lectura web |
| `public_html/uploads/` | 775 | Escritura PHP (logos/portadas/fotos) |
| `public_html/uploads/**` | 775 | Subcarpetas creadas por la app |
| `private/` | 750 | Fuera del document root |
| `private/storage/` | 775 | tmp/logs si se usan |
| `private/app/` | 750 | Sin escritura web |
| `private/vendor/` | 750 | Sin escritura web |

## Archivos

| Ruta | Permiso |
|------|---------|
| `public_html/index.php` | 644 |
| `public_html/.htaccess` | 644 |
| `private/.env` | 640 (o 600) |
| PHP/código | 644 |

## Propietario

El usuario del pool PHP de Hostinger debe poder escribir en `uploads` y `storage`.
No dejes `.env` con 777.
