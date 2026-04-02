=== Terramarket ===
Contributors: openai
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.24.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Marketplace agro autónomo para WordPress.

Versión 1.24.0:
- UI-D responsive aplicada en avisos y Mi Terramarket: el wizard mejora en tablet/móvil con stepper horizontal desplazable, acciones más accesibles y cards mejor apiladas.
- La vista previa modal pasa a un comportamiento más usable en móvil y la carga/gestión de imágenes gana mejor adaptación por ancho.

Versión 1.23.0:
- UI-C aplicada en Mi Terramarket: “Mis avisos” pasa a cards premium con badges de estado más fuertes, filtros por estado y acciones rápidas mejor jerarquizadas.
- Se separa visualmente el cierre del aviso con un bloque propio para “Marcar vendido”, reduciendo errores de operación dentro de la gestión diaria.

Versión 1.22.0:
- UI-B aplicada en avisos: el paso de imágenes mejora con dropzone, imagen principal, reorden visual y vista previa modal sin salir del flujo.

Marketplace agro autónomo para WordPress.

== Description ==
Terramarket es un plugin base para construir un marketplace agro en WordPress.

Versión 1.5.4:
- corrección de salida inesperada en activación eliminando espacios antes de `<?php` en class-tm-seeder.php.

Versión 1.5.3:
- se agrega QA_CHECKLIST.md con guía de validación manual y regresión básica.

Versión 1.5.2:
- logging técnico mínimo bajo WP_DEBUG para eventos críticos (alertas, permisos y uploads).

Versión 1.5.1:
- hardening SQL preventivo en consultas admin/alertas usando prepare cuando aplica.
- se agrega archivo CHANGELOG.md para trazabilidad de versiones.

Versión 1.5.0:
- validación server-side de consistencia categoría/subcategoría y región/comuna en guardado de avisos y alertas.
- accesos rápidos en ajustes generales para abrir rutas públicas del marketplace.

Versión 1.4.1:
- endurecimiento de permisos por capability en operaciones de vendedor y alertas.

Versión 1.4.0 (Bloque E):
- pulido UI/UX del frontend standalone
- responsive más sólido con foco móvil
- mejor feedback de carga y resultados
- accesibilidad base: skip link, foco visible y regiones live
- mejoras de rendimiento visual y lazy loading

Versión 1.3.0 (Bloque D):
- alertas diarias por email con cron de WordPress
- panel Mi Terramarket ampliado con alertas, duplicar, renovar y marcar vendido
- admin con páginas de leads, vitrina y cierres/comisiones
- ejecución manual del proceso de alertas desde herramientas

Versión 1.2.0 (Bloque C):
- frontend standalone sin theme
- slug base configurable y rutas derivadas
- home propia, filtros AJAX y login/registro visual

Versión 1.1.0 (Bloque B):
- publicación de avisos desde frontend mediante shortcode [tm_submit_listing]
- edición básica de avisos del propio usuario
- galería de imágenes con hasta 5 fotos
- watermark automático si se configura logo en ajustes
- compresión/redimensión de imágenes
- ficha individual del aviso con galería, precio, ubicación y contacto
- formulario de leads que guarda el contacto en tabla custom y envía email al vendedor

== Installation ==
1. Sube la carpeta `terramarket` al directorio `/wp-content/plugins/` o instala el ZIP desde WordPress.
2. Activa el plugin.
3. Configura los ajustes del plugin en Terramarket > Ajustes.
4. Usa el frontend standalone en `/{slug-base}/`.

== Changelog ==
= 1.19.0 =
* Frontend Fase D: nueva capa de exploración por necesidad en home y contexto de categoría/subcategoría.
* Atajos comerciales por intención: Para viña, Para frutales, Para ganadería, Para maíz, Para riego y Para apicultura.

= 1.5.4 =
* Bugfix: elimina 4 caracteres de salida inesperada en activación (whitespace inicial en seeder).

= 1.5.3 =
* Documentación operativa: checklist manual de QA/regresión en QA_CHECKLIST.md.

= 1.5.2 =
* Observabilidad: logs técnicos mínimos condicionados a WP_DEBUG para fallos críticos.

= 1.5.1 =
* Hardening SQL preventivo en consultas admin/alertas y trazabilidad con CHANGELOG.md.

= 1.5.0 =
* Seguridad e integridad: validación relacional de términos en avisos/alertas.
* Admin UX: accesos rápidos a rutas frontend desde Ajustes > Generales.

= 1.4.1 =
* Seguridad: validación explícita de capabilities en guardar/gestionar avisos y alertas de usuario.

= 1.4.0 =
* Bloque E: pulido UI/UX, responsive fino, accesibilidad base y mejoras de rendimiento.

= 1.3.0 =
* Bloque D: alertas diarias, panel vendedor ampliado, admin de leads/vitrina/comisiones.

= 1.2.0 =
* Bloque C: standalone, rutas, home, filtros AJAX y acceso visual.

= 1.1.0 =
* Bloque B: publicación frontend, galería, compresión/watermark y leads.

= 1.0.0 =
* Bloque A: base técnica, CPT, taxonomías, settings, tablas y seeders.
