## [1.24.3] - 2026-03-23
- UI amigable para categorías en admin: se agrega acceso directo “Categorías” dentro de Terramarket y se reemplaza la experiencia nativa dura por una pantalla mucho más guiada para crear/editar taxonomías visuales.
- La lista de categorías ahora muestra columna “Visual” con preview y acceso rápido a “Editar visual”, haciendo evidente qué rubros tienen imagen cargada y cuáles siguen usando fallback.
- La edición de categoría se reorganiza con una tarjeta visual más clara para el icono, mejor jerarquía en nombre/slug y una presentación administrativa consistente con el resto del sistema, evitando depender de la UI estándar de WordPress.

## [1.24.2] - 2026-03-23
- Íconos visuales por categoría: `tm_category` ahora permite subir una imagen propia desde la edición del término, con fallback al SVG anterior cuando no exista imagen cargada.
- El hero “Encuentra lo que buscas fácilmente” pasa a tener fondo editable desde Ajustes > Marketplace, con imagen desktop, imagen móvil, color de overlay y opacidad configurables.
- El frontend refuerza la referencia visual aprobada: bloques de categoría con mayor presencia para mini-ilustraciones y hero preparado para fondos administrables sin tocar filtros ni lógica comercial.

## [1.24.1] - 2026-03-23
- Frontend simple: home/standalone se reordena para priorizar navegación fácil, con header más limpio, categorías visuales primero y hero compacto alineado a las referencias aprobadas.
- Los filtros pasan a una lectura más clara: precio, ubicación y condición quedan visibles, mientras el resto vive en "Más filtros" sin romper el filtrado AJAX.
- Las cards de avisos refuerzan el enfoque comercial con foto dominante, precio antes del título y CTA principal "Ver detalle" más fuerte en desktop, tablet y móvil.

## [1.24.0] - 2026-03-22
- UI-D responsive: publicar/editar aviso y Mi Terramarket se pulen para tablet y móvil con mejor apilado, stepper horizontal desplazable, acciones más accesibles y cards más compactas.
- El wizard gana mejor comportamiento en pantallas pequeñas: el paso activo se centra automáticamente, los footers del paso quedan más accesibles y el sidebar deja de depender de sticky fuera de desktop.
- La preview modal y el módulo de imágenes mejoran en móvil con fullscreen usable, botones más tocables y grillas que bajan de forma progresiva según ancho.

## [1.23.0] - 2026-03-22
- UI-C en Mi Terramarket: la pestaña "Mis avisos" pasa a un layout de cards premium con filtros por estado, badges más notorios y quick actions mejor jerarquizadas por cada aviso.
- Cada card separa acciones principales, secundarias y de cierre para evitar errores operativos; "Marcar vendido" queda en un bloque visual propio y más seguro.
- Se agregan avisos de confirmación legibles para renovar y duplicar, y cada tarjeta ahora muestra mejor contexto comercial: ubicación, rubro, vigencia, visitas y precio.

## [1.22.0] - 2026-03-22
- UI-B avisos: el paso de imágenes en publicar/editar pasa a un módulo más premium con dropzone, contador activo, selección de imagen principal, reorden visual y opción de quitar antes de guardar.
- Las imágenes existentes ahora permiten marcar principal, mover izquierda/derecha y dejar eliminación diferida, manteniendo la lógica real de featured/sort order al guardar el aviso.
- Se agrega vista previa modal del aviso desde el sidebar del wizard para revisar imagen, título, precio, meta principal, descripción y contacto sin salir del flujo de edición.

## [1.21.0] - 2026-03-22
- UI-A avisos: crear/editar en frontend pasa a un wizard de 4 pasos con stepper superior, mejor jerarquía visual, resumen sticky en desktop y navegación persistente entre pasos.
- Se agrega validación inline por campo/paso en publicar/editar, con foco automático al primer error y resumen lateral que se actualiza en vivo con título, precio, clasificación, ubicación, estado e imágenes.
- El admin de `tm_listing` mejora su metabox principal con stepper/tabs internas para Comercial, Clasificación, Ubicación y Contacto, manteniendo intacta la lógica de guardado de WordPress.

## [1.20.1] - 2026-03-22
- Fase 1: el CPT `tm_listing` pasa a usar capacidades propias y el admin del plugin deja de depender de `manage_options`/`edit_posts` en menús, callbacks y acciones clave.
- Se sincronizan roles/capabilities en runtime para instalaciones ya activas, sin exigir reactivar el plugin para que los permisos nuevos queden operativos.
- La expiración de avisos pasa a ser efectiva en queries públicas, vitrina, alertas y vistas individuales; se agrega mantenimiento diario para marcar avisos vencidos como `expired`.
- Se corrige el disparo manual de alertas desde Herramientas y las renovaciones/reactivaciones ahora reponen vigencia comercial cuando corresponde.

## [1.20.0] - 2026-03-22
- Fase E UX categorías: en categoría/subcategoría el flujo pasa a ser hero opcional, pasillos, toolbar, avisos y luego filtros para acelerar navegación real.
- Se elimina el bloque “Explora por necesidad” dentro de categoría/subcategoría y se mantiene solo en home para evitar duplicidad.
- Los pasillos internos se refuerzan como botones de navegación y se agrega ajuste Marketplace para mostrar/ocultar el hero en categorías.

## [1.20.0] - 2026-03-22
- Frontend Fase D: se agrega exploración por necesidad en portada y contexto de rubro/subrubro.
- Nuevos recorridos rápidos: Para viña, Para frutales, Para ganadería, Para maíz, Para riego y Para apicultura.
- Se mantiene intacta la navegación principal por rubro y se suma una capa comercial por intención.

## [1.18.0] - 2026-03-22
- Frontend Fase C: el listado de avisos pasa a un look más premium/comercial con cards enfocadas en foto, precio y una lectura más limpia.
- Cada card incorpora contador de fotos, bloque de precio más dominante, meta comprimida y CTA principal más claro sin tocar filtros, leads ni lógica de avisos.
- Ajustes visuales de hover, spacing y footer para mejorar escaneo y percepción de valor en boards grandes.

## [1.17.1] - 2026-03-22
- Se agrega ajuste en Marketplace para mostrar/ocultar el menú sticky de categorías del header standalone.
- El frontend standalone ahora respeta ese setting y no renderiza la barra sticky cuando está desactivada, evitando redundancia con la navegación por rubros.
- Default del nuevo ajuste queda en oculto para el enfoque actual de mall agrícola online, sin tocar filtros, leads ni publicación.

## [1.17.0] - 2026-03-22
- Frontend Fase B: las categorías y subcategorías pasan a comportarse como landings reales de rubro con breadcrumbs, bloque contextual y accesos rápidos por orden/comercialización.
- Se agrega una capa de navegación interna para boards grandes con cards de subcategoría/pasillo y mejor contexto al bajar desde categoría a subcategoría.
- Toolbar superior de resultados reforzada con orden rápido, chips de contexto y limpieza sin sacar al usuario de la landing actual.
- El botón Limpiar en filtros ahora respeta la página/taxonomía actual en vez de devolver siempre al archivo global.

## [1.16.1] - 2026-03-22
- Fix crítico: `templates/standalone.php` vuelve a renderizar correctamente el frontend standalone al exponer `TM_Public::render_standalone_page()` como método público estático.
- Sin cambios de lógica funcional en navegación, filtros, leads ni publicación; ajuste de compatibilidad/visibilidad PHP.

## [1.16.0] - 2026-03-22
- Frontend Fase A: header standalone con navegación por rubros tipo mega menú y accesos rápidos comerciales.
- Home del marketplace reforzada como mall agrícola: entrada por categorías, bloque “Explora por rubro” con icono/texto/conteo y hero más orientado a navegación.
- Categorías ahora se presentan como vitrinas premium con subcategorías visibles; archivos de categoría muestran un rail superior de subcategorías para boards grandes.
- Sin cambios en leads, publicación, alertas, filtros AJAX ni permisos.

## [1.15.3] - 2026-03-21
- Prioridad 3: se pule la jerarquia visual del admin con mejores contrastes, secciones mas distinguibles y metrica principal mas facil de escanear sin cambiar logica ni datos.
- Leads, Comisiones y Vitrina refinan la banda superior de filtros con grilla mas estricta, busqueda dominante y botones/alineaciones consistentes.
- Subnavegacion y acciones superiores quedan mas coherentes entre pantallas: mejor altura, estados activos mas claros y soporte `aria-current` para la vista activa.

## [1.15.2] - 2026-03-21
- Prioridad 2: acciones superiores pasan a ser contextuales por pantalla y se marca una accion principal para reducir ruido y duplicidad en el shell admin.
- Ajustes corrige incoherencias del resumen superior y tabs: notificaciones deja de romper la card, los pills pasan a describir la seccion con semantica de negocio y los valores largos se leen mejor.
- Empty states mejorados en Avisos, Leads, Vitrina y Comisiones con CTA utiles y ocultando acciones masivas cuando no hay resultados visibles.

## [1.15.1] - 2026-03-21
- Corrige la carga de estilos/scripts del sistema visual en el listado nativo del CPT `tm_listing` (`edit.php?post_type=tm_listing`), alineando Avisos al shell común del admin.
- Compacta hero, KPIs, subnav y cards base del admin para reducir altura inicial y mostrar antes el contenido útil en desktop.
- Dashboard deja de duplicar métricas: el resumen superior pasa a concentrar KPIs y se elimina el bloque repetido de tarjetas ejecutivas.

## [1.15.0] - 2026-03-21
- UI/UX admin fase 4: el apartado Avisos (`tm_listing`) recibe shell visual propio en la lista admin con hero, resumen operativo y acciones rapidas alineadas al sistema comun.
- Lista de avisos: columnas comerciales personalizadas para estado, precio, clasificacion, ubicacion y contacto; se agregan filtros directos por estado, destacado, categoria y region.
- Editor/metabox de aviso: `Detalles del aviso` pasa a una composicion premium sobria con resumen superior, enlaces de vista previa y cards por bloques comerciales, clasificacion, ubicacion y contacto.
- Sin cambios en CPT, guardado de metadatos, taxonomias ni logica de persistencia.

# Changelog

Todos los cambios relevantes de este plugin se documentan en este archivo.

## [1.14.0] - 2026-03-21
- UI/UX admin fase 3: Vitrina pasa de tabla simple a grilla visual con tarjetas de aviso, seleccion múltiple más clara y contador dinámico de destacados.
- Vitrina ahora preserva destacados ya guardados aunque no estén visibles por el filtro actual al momento de guardar.
- Herramientas se rediseña con estado operativo, accesos relacionados y tarjetas separadas por acciones seguras vs sensibles con mejor contexto de impacto.
- Ajustes recibe alineación fina al sistema común: tabs con meta visual y bloque de contexto rápido por sección activa.

## [1.13.0] - 2026-03-21
- UI/UX admin fase 2: Dashboard gana resumen ejecutivo real con KPIs clickeables, acciones rapidas, estado del sistema y actividad reciente.
- Leads incorpora toolbar de filtros, tarjetas de lectura rapida, tabla mas jerarquizada por contacto/aviso/fecha y acciones de copiar datos.
- Comisiones incorpora filtros por estado/periodo, resumen comercial superior y tabla con lectura mas clara de monto, porcentaje, vendedor y estado.
- Hardening admin: deteccion flexible de tablas/campos para leads y comisiones (`tm_leads`, `tm_commission_events`/`tm_commissions`) sin cambiar persistencia ni modelo base.

## [1.12.0] - 2026-03-21
- UI/UX admin fase 1: se agrega sistema visual comun para las paginas propias del plugin con hero superior, resumenes KPI, acciones rapidas y subnavegacion interna.
- Dashboard, Leads, Vitrina, Comisiones y Herramientas se alinean a una misma base visual sin cambiar guardados, consultas ni permisos existentes.
- Herramientas separa acciones seguras vs sensibles y agrega confirmacion ligera en acciones destructivas.
- Ajustes se integra al nuevo shell comun del admin manteniendo tabs, previews y logica de opciones existente.

## [1.11.0] - 2026-03-21
- UI/UX admin (Ajustes): rediseño premium sobrio de la pantalla con hero superior, resumen de configuración y tabs visuales más claras.
- Ajustes > Generales: accesos rápidos pasan a tarjetas con copiar/abrir, mejor jerarquía para nombre, slug, paginación y correos.
- Ajustes > Branding: selector de medios con preview más grande, colores con preview viva y campos técnicos movidos a avanzados plegables.
- Ajustes > Marketplace: se ordenan reglas de publicación e imagen en cards y opciones avanzadas plegables, sin cambiar persistencia ni sanitización.

## [1.10.0] - 2026-03-21
- UI/UX frontend: header, hero, barra de filtros, cards de avisos y vista single reciben una pasada visual más compacta, clara y comercial.
- Single: se agregan accesos rápidos de contacto (llamar/email cuando existe dato), contador visual de galería y sidebar más firme.
- Sin cambios en permisos, consultas base, persistencia ni estructura de datos.

## [1.9.0] - 2026-03-21
- UI/UX frontend: galeria de aviso con navegacion anterior/siguiente y estado accesible del thumbnail activo.
- Sin cambios en uploads, attachments, persistencia ni guardado de media.

## [1.8.0] - 2026-03-21
- UI/UX frontend: vitrina destacada con controles manuales de anterior, siguiente y pausa/reanudar.
- El autoplay se sincroniza con la interaccion del usuario sin cambiar el origen de datos de la vitrina.

## [1.7.0] - 2026-03-21
- UI/UX frontend: resumen de filtros activos con chips removibles directamente desde el listado.
- Sin cambios en permisos, nonces, persistencia ni logica de consulta.

## [1.6.0] - 2026-03-21
- UI/UX frontend: barra de filtros del marketplace con toggle movil plegable y contador visible de filtros activos.
- Sin cambios en permisos, nonces, persistencia ni logica de consulta.

## [1.5.7] - 2026-03-20
- UI/UX admin (Branding): selector de medios nativo para `logo_id` y `watermark_logo_id` (seleccionar/quitar) conservando almacenamiento por ID.
- Vista previa de imagen en Ajustes > Branding para logo principal y watermark.

## [1.5.6] - 2026-03-20
- UI/UX admin (Branding): integracion de `wp-color-picker` para los campos de color en Ajustes > Branding.
- Carga condicional de assets del color picker solo en `admin.php?page=tm-settings&tab=branding`.

## [1.5.5] - 2026-03-20
- UI/UX admin (Ajustes): se muestran notices explicitos de guardado en `Ajustes`.
- Validacion con feedback: emails invalidos, comision por defecto no numerica/fuera de rango y posicion de watermark invalida mantienen valor anterior y muestran mensaje.

## [1.5.4] - 2026-03-20
- Bugfix: se elimina whitespace inicial en `includes/class-tm-seeder.php` que generaba salida inesperada en activacion.

## [1.5.3] - 2026-03-20
- Se agrega `QA_CHECKLIST.md` con verificacion manual operativa y de regresion.
- Se referencia checklist para estandarizar validacion post-cambio.

## [1.5.2] - 2026-03-20
- Se agrega `TM_Helpers::debug_log()` condicionado a `WP_DEBUG`.
- Logs tecnicos minimos para fallos de alertas (payload/usuario/correo), bloqueos por permisos y errores de upload.

## [1.5.1] - 2026-03-20
- Hardening SQL preventivo en consultas admin/alertas con `wpdb->prepare` cuando aplica.
- Se agrega este archivo `CHANGELOG.md` para trazabilidad de cambios.

## [1.5.0] - 2026-03-20
- Validacion server-side de consistencia categoria/subcategoria y region/comuna en guardado de avisos y alertas.
- Accesos rapidos en Ajustes > Generales para abrir rutas publicas del marketplace.

## [1.4.1] - 2026-03-20
- Endurecimiento de permisos por capability en operaciones de vendedor y alertas.

## [1.4.0]
- Bloque E: pulido UI/UX, responsive fino, accesibilidad base y mejoras de rendimiento.

## [1.3.0]
- Bloque D: alertas diarias, panel vendedor ampliado, admin de leads/vitrina/comisiones.

## [1.2.0]
- Bloque C: standalone, rutas, home, filtros AJAX y acceso visual.

## [1.1.0]
- Bloque B: publicacion frontend, galeria, compresion/watermark y leads.

## [1.0.0]
- Bloque A: base tecnica, CPT, taxonomias, settings, tablas y seeders.
