=== Terramarket ===
Contributors: openai
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.4.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Marketplace agro autónomo para WordPress.

== Description ==
Terramarket es un plugin base para construir un marketplace agro en WordPress.

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
