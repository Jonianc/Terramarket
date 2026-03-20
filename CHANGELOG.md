# Changelog

Todos los cambios relevantes de este plugin se documentan en este archivo.

## [1.5.6] - 2026-03-20
- UI/UX admin (Branding): integración de `wp-color-picker` para los campos de color en Ajustes > Branding.
- Carga condicional de assets del color picker solo en `admin.php?page=tm-settings&tab=branding`.

## [1.5.5] - 2026-03-20
- UI/UX admin (Ajustes): se muestran notices explícitos de guardado en `Ajustes`.
- Validación con feedback: emails inválidos, comisión por defecto no numérica/fuera de rango y posición de watermark inválida mantienen valor anterior y muestran mensaje.

## [1.5.4] - 2026-03-20
- Bugfix: se elimina whitespace inicial en `includes/class-tm-seeder.php` que generaba salida inesperada en activación.

## [1.5.3] - 2026-03-20
- Se agrega `QA_CHECKLIST.md` con verificación manual operativa y de regresión.
- Se referencia checklist para estandarizar validación post-cambio.

## [1.5.2] - 2026-03-20
- Se agrega `TM_Helpers::debug_log()` condicionado a `WP_DEBUG`.
- Logs técnicos mínimos para fallos de alertas (payload/usuario/correo), bloqueos por permisos y errores de upload.

## [1.5.1] - 2026-03-20
- Hardening SQL preventivo en consultas admin/alertas con `wpdb->prepare` cuando aplica.
- Se agrega este archivo `CHANGELOG.md` para trazabilidad de cambios.

## [1.5.0] - 2026-03-20
- Validación server-side de consistencia categoría/subcategoría y región/comuna en guardado de avisos y alertas.
- Accesos rápidos en Ajustes > Generales para abrir rutas públicas del marketplace.

## [1.4.1] - 2026-03-20
- Endurecimiento de permisos por capability en operaciones de vendedor y alertas.

## [1.4.0]
- Bloque E: pulido UI/UX, responsive fino, accesibilidad base y mejoras de rendimiento.

## [1.3.0]
- Bloque D: alertas diarias, panel vendedor ampliado, admin de leads/vitrina/comisiones.

## [1.2.0]
- Bloque C: standalone, rutas, home, filtros AJAX y acceso visual.

## [1.1.0]
- Bloque B: publicación frontend, galería, compresión/watermark y leads.

## [1.0.0]
- Bloque A: base técnica, CPT, taxonomías, settings, tablas y seeders.
