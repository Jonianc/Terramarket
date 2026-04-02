# Plugin Map v2 — Terramarket 1.15.3

Documento técnico-operativo duro, basado en el código real del ZIP `Terramarket-v1.15.3-admin-priority3-fixes.zip`.

## Propósito

Este documento existe para que otra IA o desarrollador pueda intervenir el plugin con el menor riesgo posible.

No es un README comercial.
No es una guía superficial.
No asume arquitectura ideal.
Describe cómo está construido realmente el plugin hoy, dónde vive cada responsabilidad y qué zonas no conviene tocar sin revisar impacto.

---

# 1. Lectura rápida

## Qué es Terramarket hoy

Terramarket es un marketplace agro standalone sobre WordPress.
Su núcleo funcional se apoya en:

- CPT `tm_listing`
- taxonomías propias para clasificación y ubicación
- tablas custom para leads, alertas, media, comisiones, vitrina y campos dinámicos
- un frontend standalone con template propio
- un admin con páginas custom y herramientas operativas
- procesamiento de imágenes y watermark
- flujos mixtos basados en post meta, taxonomías, options y tablas custom

## Conclusión arquitectónica directa

El plugin **no es un plugin simple de CPT + template**.
Tiene varias capas con acoplamiento real:

- bootstrap
- routing standalone
- dominio / persistencia
- admin propio
- frontend operativo
- media pipeline
- cron/alertas

Por eso **no conviene intervenir “solo tocando una vista” sin revisar el flujo completo**.

---

# 2. Resumen ejecutivo de riesgo

## Riesgo alto si se toca sin revisar impacto

1. `includes/class-tm-template.php`
2. `public/class-tm-public.php`
3. `admin/class-tm-admin.php`
4. `includes/class-tm-settings.php`
5. `includes/class-tm-db.php`
6. `includes/class-tm-post-types.php`
7. `includes/class-tm-taxonomies.php`

## Riesgo medio

1. `includes/class-tm-helpers.php`
2. `includes/class-tm-alerts.php`
3. `assets/js/admin.js`
4. `assets/js/frontend.js`

## Riesgo bajo

1. `assets/css/admin.css`
2. `assets/css/frontend.css`
3. `readme.txt`
4. `CHANGELOG.md`
5. `QA_CHECKLIST.md`

Regla práctica:

- **CSS**: normalmente seguro
- **templates**: medianamente seguros, pero no asumir que controlan todo el render
- **`TM_Public` / `TM_Admin` / routing / settings / DB**: alto riesgo

---

# 3. Árbol real del plugin con criticidad

```text
terramarket.php                                  [CORE | ALTA]
uninstall.php                                    [SOPORTE | MEDIA]
CHANGELOG.md                                     [DOC | BAJA]
QA_CHECKLIST.md                                  [DOC | BAJA]
readme.txt                                       [DOC | BAJA]

admin/
  class-tm-admin.php                             [CORE ADMIN | MUY ALTA]
  partials/
    settings-general.php                         [ADMIN UI | MEDIA]
    settings-branding.php                        [ADMIN UI | MEDIA]
    settings-marketplace.php                     [ADMIN UI | MEDIA]

includes/
  class-tm-activator.php                         [BOOTSTRAP | ALTA]
  class-tm-alerts.php                            [CRON/EMAIL | ALTA]
  class-tm-db.php                                [DB/SCHEMA | MUY ALTA]
  class-tm-deactivator.php                       [BOOTSTRAP | MEDIA]
  class-tm-helpers.php                           [UTILIDAD TRANSVERSAL | ALTA]
  class-tm-i18n.php                              [I18N | BAJA]
  class-tm-loader.php                            [ORQUESTADOR | MUY ALTA]
  class-tm-post-types.php                        [CPT/REWRITE | MUY ALTA]
  class-tm-roles.php                             [ROLES/CAPS | ALTA]
  class-tm-seeder.php                            [DATOS BASE | ALTA]
  class-tm-settings.php                          [SETTINGS/FLUSH | MUY ALTA]
  class-tm-taxonomies.php                        [TAXONOMÍAS | MUY ALTA]
  class-tm-template.php                          [ROUTING STANDALONE | MUY ALTA]

public/
  class-tm-public.php                            [CORE FRONTEND | MUY ALTA]

templates/
  archive-tm_listing.php                         [TEMPLATE LEGACY/APOYO | MEDIA]
  single-tm_listing.php                          [TEMPLATE LEGACY/APOYO | MEDIA]
  standalone.php                                 [WRAPPER STANDALONE | ALTA]

assets/
  css/
    admin.css                                    [PRESENTACIÓN ADMIN | BAJA]
    frontend.css                                 [PRESENTACIÓN FRONTEND | BAJA]
  js/
    admin.js                                     [UX ADMIN | MEDIA]
    frontend.js                                  [UX FRONTEND/AJAX | MEDIA]
```

---

# 4. Secuencia real de carga

## 4.1 Bootstrap de plugin

Archivo: `terramarket.php`

Hace esto:

1. define constantes globales
2. carga todas las clases con `require_once`
3. registra activación y desactivación
4. ejecuta `TM_Loader::init()`

No debería contener:

- lógica de negocio nueva
- render de UI
- handlers
- SQL

## 4.2 Orquestación de hooks

Archivo: `includes/class-tm-loader.php`

Orden actual real:

- `plugins_loaded` → `TM_I18n::load_textdomain`
- `init` prio 5 → `TM_Post_Types::register`
- `init` prio 6 → `TM_Taxonomies::register`
- `init` prio 20 → `TM_Template::init`
- `init` prio 20 → `TM_Public::init`
- `init` prio 20 → `TM_Alerts::init`
- `init` prio 20 → `TM_Admin::init` solo en admin
- `admin_init` → `TM_Settings::register_settings`

## Lectura operativa

Esto importa porque:

- primero deben existir CPT y taxonomías
- luego se monta routing standalone
- luego frontend/admin/alertas cuelgan de esa base

**Cambiar prioridades aquí puede romper routing, consultas o taxonomías.**

---

# 5. Mapa funcional por capas

# 5.1 Capa de dominio / configuración

## `includes/class-tm-helpers.php`

Responsabilidad real:

- wrapper de options
- resolver URLs públicas del marketplace
- branding vars
- helpers de taxonomías dependientes
- helpers de galería/media
- formato CLP
- ownership
- normalización de archivos subidos
- debug log
- account type / labels

Por qué es sensible:

- lo usan admin y frontend
- concentra decisiones compartidas
- cambiar nombres de options o reglas aquí tiene impacto transversal

### Métodos especialmente sensibles

- `get_market_slug()`
- `get_market_url()`
- `get_submit_page_url()`
- `get_auth_page_url()`
- `get_account_page_url()`
- `is_subcategory_of_category()`
- `is_comuna_of_region()`
- `get_listing_gallery_ids()`
- `normalize_uploaded_files()`
- `get_branding_vars()`

---

## `includes/class-tm-post-types.php`

Responsabilidad real:

- registrar `tm_listing`
- definir rewrite base del listado/single según slug configurable

Riesgo:

- toca URLs públicas e indexables
- depende de `TM_Helpers::get_market_slug()`

No tocar sin revisar:

- slug base
- rewrite del single
- `has_archive`

---

## `includes/class-tm-taxonomies.php`

Responsabilidad real:

- registrar taxonomías:
  - `tm_category`
  - `tm_subcategory`
  - `tm_region`
  - `tm_comuna`
  - `tm_condition`

Riesgo:

- afecta filtros
- afecta formularios
- afecta admin metabox
- afecta alertas
- afecta routing tax archive

Observación estructural:

- hay dependencia lógica `categoría -> subcategoría`
- hay dependencia lógica `región -> comuna`
- esa dependencia no vive solo en la taxonomía; también vive en formularios y validaciones

---

## `includes/class-tm-settings.php`

Responsabilidad real:

- setear defaults
- registrar settings groups
- sanitizar ajustes
- detectar cambios de `market_slug`
- hacer `flush_rewrite_rules()` cuando corresponde

### Options reales

- `tm_settings_general`
- `tm_settings_branding`
- `tm_settings_marketplace`

### Defaults operativos relevantes

General:
- `marketplace_name`
- `market_slug`
- `listings_per_page`
- `from_email`
- `notify_email`

Branding:
- `logo_id`
- `watermark_logo_id`
- `primary_color`
- `secondary_color`
- `button_color`

Marketplace:
- `default_commission`
- `max_images`
- `image_max_width`
- `image_quality`
- `enable_watermark`
- `watermark_position`
- `watermark_opacity`

### Punto crítico real

`maybe_flush_rewrite_rules()` re-registra:

- CPT
- taxonomías
- rewrite standalone
- luego hace flush

Eso significa que **cambiar cómo se guarda `market_slug` o tocar esta secuencia puede dejar al plugin con URLs inconsistentes**.

---

## `includes/class-tm-db.php`

Responsabilidad real:

Crear tablas con `dbDelta()`.

### Tablas reales

- `tm_leads`
- `tm_alerts`
- `tm_listing_media`
- `tm_category_fields`
- `tm_commission_events`
- `tm_vitrine_items`

### Lectura operativa

Este plugin usa persistencia mixta:

- posts
- post meta
- taxonomías
- options
- tablas custom

Por eso un cambio funcional rara vez vive en una sola capa.

Ejemplo:

“guardar un aviso” puede tocar al mismo tiempo:

- `wp_posts`
- `wp_postmeta`
- relaciones taxonómicas
- `tm_listing_media`
- archivos físicos adjuntos en media library

### Regla dura

No editar SQL “porque sí”.
Un cambio de schema requiere:

- diseño de migración
- compatibilidad de lectura
- revisión de inserciones/queries
- checklist de datos existentes

---

## `includes/class-tm-roles.php`

Responsabilidad real:

- registrar roles:
  - `tm_seller`
  - `tm_company`
  - `tm_broker`
- registrar capabilities específicas del marketplace

### Capabilities reales relevantes

- `tm_create_listings`
- `tm_edit_own_listings`
- `tm_pause_own_listings`
- `tm_mark_own_listings_sold`
- `tm_view_own_leads`
- `tm_manage_own_alerts`

Impacto:

- publicación
- edición
- panel de usuario
- gestión de leads/alertas

---

## `includes/class-tm-seeder.php`

Responsabilidad real:

Sembrar datos base del marketplace.

### Bloques de seed observados

- condiciones
- categorías/subcategorías
- regiones/comunas
- campos dinámicos

Riesgo:

- un reseed puede reconstruir estructura base del negocio
- no tratarlo como “acción de mantenimiento inocente”

---

## `includes/class-tm-alerts.php`

Responsabilidad real:

- asegurar cron diario
- procesar alertas vencidas
- construir queries de matching
- enviar email
- crear/eliminar alertas

### Hook cron real

- `tm_daily_alerts_event`

### Flujo real resumido

1. cron corre
2. busca alertas activas diarias
3. arma query filtrada
4. busca avisos nuevos desde `last_sent_at`
5. envía mail
6. actualiza `last_sent_at`

### Riesgos reales

- depende de `from_email`
- depende de taxonomías y meta correctos
- depende del estado `active` del listing
- depende del reloj y cron de WordPress

### Inconsistencia detectada

En `admin/class-tm-admin.php`, el handler manual de alertas llama:

- `TM_Alerts::process_pending_alerts()`

Pero en `includes/class-tm-alerts.php`, el método presente es:

- `TM_Alerts::process_due_alerts()`

**Esto parece un bug o desalineación real del código actual.**
No asumir que “Run alerts” funciona sin revisar.

---

## `includes/class-tm-template.php`

Responsabilidad real:

Routing standalone del marketplace.

### Qué registra

Rewrite rules para:

- `/{market_slug}/publicar`
- `/{market_slug}/mi-cuenta`
- `/{market_slug}/acceso`

### Qué hace además

- registra query var `tm_view`
- decide si la request es standalone
- intercepta `template_include`
- añade `body_class`

### Qué considera request standalone

- `tm_view` presente
- archive de `tm_listing`
- single de `tm_listing`
- tax archive de taxonomías TM

### Conclusión dura

Este archivo **es core de navegación pública**.
Si se rompe, se rompe el frontend real del marketplace.

---

# 5.2 Capa admin

## `admin/class-tm-admin.php`

Responsabilidad real:

Es el núcleo funcional del backoffice del plugin.
No es solo UI.
Contiene:

- menú y submenús
- assets admin
- metabox del listing
- guardado de meta del listing
- columnas y filtros admin del CPT
- shell visual común
- dashboard, leads, vitrina, comisiones, ajustes, herramientas
- handlers `admin_post_*`

### Handlers admin reales

- `tm_reseed_all`
- `tm_reseed_categories`
- `tm_reseed_regions`
- `tm_save_vitrine`
- `tm_run_alerts`

### Zonas especialmente sensibles

- `save_listing_meta()`
- `filter_listing_admin_query()`
- páginas que leen tablas custom
- herramientas de reseed
- vitrina
- ejecución manual de alertas

### Estructura funcional interna observada

Páginas:
- `render_dashboard_page()`
- `render_leads_page()`
- `render_vitrine_page()`
- `render_commissions_page()`
- `render_settings_page()`
- `render_tools_page()`

Shell/layout:
- `render_admin_shell_start()`
- `render_admin_shell_fragment()`
- `render_admin_shell_end()`

Infra de tablas:
- `table_exists()`
- `get_table_columns()`
- `get_leads_column_map()`

### Regla de intervención

Si el cambio es:

- solo look & feel → empezar por `assets/css/admin.css`
- interacción visual admin → revisar `assets/js/admin.js`
- estructura HTML del admin → `class-tm-admin.php`
- guardado de datos admin → `class-tm-admin.php` + settings/meta/tables implicadas

---

## `admin/partials/settings-general.php`

Responsabilidad real:

UI de ajustes generales.
No sanitiza por sí sola.
Renderiza campos que luego procesa `TM_Settings`.

### Campos relevantes

- nombre del marketplace
- slug público
- paginación
- emails operativos
- quick links públicos

---

## `admin/partials/settings-branding.php`

Responsabilidad real:

UI de branding.

### Depende de

- media library
- `wp_enqueue_media()`
- `wpColorPicker`
- `assets/js/admin.js`

### Impacta indirectamente

- logo de frontend
- watermark
- variables visuales inyectadas en frontend

---

## `admin/partials/settings-marketplace.php`

Responsabilidad real:

UI de parámetros operativos del marketplace.

### Campos relevantes

- comisión por defecto
- máximo de imágenes
- límites/calidad de imagen
- watermark

### Impacto real

- publicación
- media pipeline
- experiencia frontend
- branding operativo

---

# 5.3 Capa pública / standalone

## `public/class-tm-public.php`

Responsabilidad real:

Es el corazón del frontend.
Concentra demasiado comportamiento crítico.

### Qué monta en `init()`

- enqueue frontend
- shortcode submit
- handlers `admin_post_*`
- AJAX de filtros
- conteo de vistas
- ajuste de queries de archive

### Handlers públicos/privados reales

- `tm_save_listing`
- `tm_send_lead`
- `tm_login`
- `tm_register`
- `tm_update_profile`
- `tm_manage_listing`
- `tm_save_alert`
- `tm_delete_alert`
- `tm_filter_listings` (AJAX)

### Riesgo máximo

Si tocas este archivo sin mapear el flujo, puedes romper:

- listado
- detalle
- publicar aviso
- login/registro
- mi cuenta
- leads
- alertas
- upload de imágenes
- watermark
- paginación/filtros AJAX

### Subzonas funcionales internas

#### Render global standalone
- `render_standalone_page()`
- `render_current_view()`

#### Filtros y archivo
- `get_active_filters_from_request()`
- `get_listing_query_args()`
- `render_archive_view()`
- `render_filter_bar()`
- `render_results_markup()`
- `render_pagination()`

#### Single
- `render_single_view()`

#### Auth / account
- `render_auth_view()`
- `render_account_view()`

#### Operaciones de usuario
- `handle_login()`
- `handle_register()`
- `handle_update_profile()`
- `handle_manage_listing()`
- `handle_save_alert()`
- `handle_delete_alert()`

#### Publicación y leads
- `handle_save_listing()`
- `handle_send_lead()`

#### Media pipeline
- `upload_listing_images()`
- `attach_listing_media()`
- `delete_listing_media()`
- `duplicate_attachment()`
- `ensure_listing_featured_image()`
- `process_attachment_image()`
- `apply_watermark()`

### Conclusión dura

`TM_Public` es a la vez:

- router interno de vistas
- renderer principal
- controlador de formularios
- AJAX controller
- media processor

Eso lo vuelve **archivo de intervención crítica**.

---

## `templates/standalone.php`

Responsabilidad real:

Es wrapper standalone.
No concentra lógica de negocio.
Delega en:

- `TM_Public::render_standalone_page()`

### Lectura operativa

Si quieres cambiar el layout envolvente global, aquí puede haber cambios.
Si quieres cambiar la lógica real de qué se renderiza, el centro está en `TM_Public`.

---

## `templates/archive-tm_listing.php`

Responsabilidad real:

Template WordPress de apoyo / alternativo.

### Advertencia

No asumir que el archive standalone real vive aquí.
El flujo principal standalone usa:

- `template_include` -> `templates/standalone.php`
- `TM_Public::render_archive_view()`

---

## `templates/single-tm_listing.php`

Responsabilidad real:

Template WordPress de apoyo / alternativo.

### Advertencia

No asumir que el detalle standalone principal vive aquí.
El detalle standalone real se construye desde:

- `TM_Public::render_single_view()`

---

# 5.4 Assets

## `assets/css/admin.css`

Dónde tocar:

- skin admin
- spacing admin
- cards admin
- tabs/subnav
- shells visuales
- tablas y chrome visual

Normalmente seguro mientras no dependa de cambios de markup.

---

## `assets/js/admin.js`

Dónde tocar:

- media picker
- preview branding
- confirmaciones
- copy actions
- UI dinámica admin
- selects dependientes

Riesgo medio.
Puede romper UX, pero usualmente no la persistencia si el backend queda intacto.

---

## `assets/css/frontend.css`

Dónde tocar:

- header/footer standalone
- hero
- cards
- formularios
- auth/account
- single/archive
- responsive

Es el punto natural para rediseños visuales sin tocar PHP.

---

## `assets/js/frontend.js`

Dónde tocar:

- filtros AJAX
- interacción de galería
- comportamientos UI de formularios/listados

Riesgo medio.
Puede romper interacción sin romper del todo el render servidor.

---

# 6. Flujo real por caso de uso

# 6.1 Carga general del marketplace standalone

1. WordPress carga plugin
2. `terramarket.php` define constantes y require clases
3. `TM_Loader::init()` registra hooks
4. en `init` se registran CPT y taxonomías
5. `TM_Template` registra rewrite/query var/template hook
6. una request elegible se considera standalone
7. `template_include` fuerza `templates/standalone.php`
8. `standalone.php` llama `TM_Public::render_standalone_page()`
9. `TM_Public::render_current_view()` decide qué vista interna mostrar

---

# 6.2 Ver listado / archivo

1. request archive o tax archive
2. `TM_Template` marca request standalone
3. entra `standalone.php`
4. `TM_Public::render_archive_view()`
5. lee filtros de `$_GET`
6. arma `WP_Query`
7. renderiza hero, filtros, resultados, paginación, vitrina
8. `frontend.js` puede pedir resultados por AJAX

Puntos sensibles:

- taxonomías
- query args
- AJAX nonce
- paginación
- featured/vitrina

---

# 6.3 Ver detalle de aviso

1. request single `tm_listing`
2. `TM_Template` la captura como standalone
3. `TM_Public::render_single_view($post_id)`
4. carga meta, taxonomías, galería, contacto, vistas
5. muestra formulario de lead y acciones de compartir
6. `template_redirect` puede incrementar contador de vistas

Puntos sensibles:

- estado del aviso (`tm_listing_status`)
- owner/capabilities
- galería e imágenes
- lead form

---

# 6.4 Publicar o editar aviso

1. usuario entra a `/{market_slug}/publicar`
2. rewrite → `tm_view=submit`
3. `TM_Public::render_submit_listing_shortcode()` construye form
4. submit va a `admin-post.php?action=tm_save_listing`
5. `TM_Public::handle_save_listing()` procesa payload
6. crea o actualiza post/meta/tax/media
7. procesa imágenes y watermark
8. redirige con notice o error

Puntos sensibles:

- capability del usuario
- nonce
- taxonomías dependientes
- media pipeline
- límites de imagen
- datos de contacto

---

# 6.5 Login / registro / cuenta

1. usuario entra a `/{market_slug}/acceso` o `/{market_slug}/mi-cuenta`
2. routing standalone decide vista
3. formularios envían a `admin-post.php`
4. handlers en `TM_Public` resuelven login/registro/update profile
5. cuenta renderiza tabs y operaciones del usuario

Puntos sensibles:

- redirecciones
- roles asignados
- persistencia de perfil
- seguridad y nonces

---

# 6.6 Guardar lead

1. desde single se envía formulario lead
2. `admin-post.php?action=tm_send_lead`
3. `TM_Public::handle_send_lead()` valida
4. inserta en `tm_leads`
5. probablemente notifica o deja el lead disponible para admin/vendedor
6. redirige con notice/error

Puntos sensibles:

- inserción DB
- email/contacto
- sanitización
- ownership y listing status

---

# 6.7 Alertas

1. usuario crea alerta desde su cuenta
2. `TM_Public::handle_save_alert()` inserta en `tm_alerts`
3. cron diario ejecuta `TM_Alerts::process_due_alerts()`
4. query de matching encuentra avisos recientes
5. se envía email y actualiza `last_sent_at`

Puntos sensibles:

- cron real de WP
- from email
- query filters
- estatus activo
- bug potencial del botón admin “run alerts”

---

# 6.8 Vitrina

1. admin gestiona página vitrina
2. formulario envía `admin-post.php?action=tm_save_vitrine`
3. `TM_Admin::handle_save_vitrine()` borra tabla y reinserta orden actual
4. archivo frontend usa esos IDs si existen
5. si no hay vitrina, cae a fallback de destacados/recientes

Punto sensible:

- `DELETE FROM tm_vitrine_items` + reinserción completa
- no tratar como update incremental

---

# 7. Matriz de persistencia

## 7.1 Options

### `tm_settings_general`
Usada para:
- nombre marketplace
- slug público
- paginación
- email remitente
- email notificaciones

### `tm_settings_branding`
Usada para:
- logo
- watermark logo
- colores base

### `tm_settings_marketplace`
Usada para:
- comisión
- máximo imágenes
- parámetros de imagen
- watermark

---

## 7.2 Post type

### `tm_listing`
Entidad principal del marketplace.

---

## 7.3 Taxonomías

- `tm_category`
- `tm_subcategory`
- `tm_region`
- `tm_comuna`
- `tm_condition`

---

## 7.4 Post meta relevante observada

- `tm_listing_status`
- `tm_price_clp`
- `tm_contact_name`
- `tm_contact_email`
- `tm_contact_phone`
- `tm_views_count`
- `tm_featured`

Puede haber más, pero estos son los más operativos en los flujos revisados.

---

## 7.5 Tablas custom

### `tm_leads`
Leads enviados a avisos.

### `tm_alerts`
Alertas guardadas por usuario.

### `tm_listing_media`
Orden y featured image de media adjunta al aviso.

### `tm_category_fields`
Campos dinámicos por categoría/subcategoría.

### `tm_commission_events`
Eventos de comisión / liquidación.

### `tm_vitrine_items`
Selección/orden de vitrina.

---

# 8. Qué tocar según objetivo

## Quiero cambiar solo UI admin
Tocar primero:
- `assets/css/admin.css`

Tal vez:
- `admin/class-tm-admin.php`
- `assets/js/admin.js`

No empezar por:
- `TM_Settings`
- `TM_DB`
- `TM_Template`

---

## Quiero cambiar solo UI frontend
Tocar primero:
- `assets/css/frontend.css`

Tal vez:
- `public/class-tm-public.php`
- `assets/js/frontend.js`
- `templates/standalone.php`

No asumir que:
- `archive-tm_listing.php` controla el archive real
- `single-tm_listing.php` controla el single real

---

## Quiero cambiar el flujo de publicación
Revisar sí o sí:
- `public/class-tm-public.php`
- `includes/class-tm-helpers.php`
- `includes/class-tm-settings.php`
- tablas/media implicadas

También revisar:
- capacidades en `class-tm-roles.php`
- taxonomías dependientes

---

## Quiero cambiar URLs o slugs
Revisar sí o sí:
- `includes/class-tm-post-types.php`
- `includes/class-tm-taxonomies.php`
- `includes/class-tm-template.php`
- `includes/class-tm-settings.php`

Y luego validar:
- flush rewrite
- enlaces internos
- accesos standalone
- archive/single/tax

---

## Quiero tocar leads
Revisar sí o sí:
- `public/class-tm-public.php`
- `admin/class-tm-admin.php`
- `includes/class-tm-db.php`

---

## Quiero tocar alertas
Revisar sí o sí:
- `includes/class-tm-alerts.php`
- `public/class-tm-public.php`
- `admin/class-tm-admin.php`
- `includes/class-tm-db.php`
- `includes/class-tm-settings.php`

---

## Quiero tocar vitrina
Revisar sí o sí:
- `admin/class-tm-admin.php`
- `public/class-tm-public.php`
- `includes/class-tm-db.php`

---

## Quiero tocar branding / logos / watermark
Revisar sí o sí:
- `admin/partials/settings-branding.php`
- `assets/js/admin.js`
- `includes/class-tm-settings.php`
- `includes/class-tm-helpers.php`
- `public/class-tm-public.php`

---

# 9. Qué NO asumir

1. No asumir que los templates `archive/single` gobiernan el standalone real.
2. No asumir que cambiar CSS basta si el markup está generado dentro de `TM_Public` o `TM_Admin`.
3. No asumir que settings solo afectan UI.
4. No asumir que los datos viven solo en post meta.
5. No asumir que el botón de “run alerts” está correcto.
6. No asumir que un cambio de slug es inocuo.
7. No asumir que reseed es seguro en cualquier entorno productivo.

---

# 10. Zonas que no conviene romper

## 10.1 Routing standalone
Archivos:
- `includes/class-tm-template.php`
- `templates/standalone.php`
- `public/class-tm-public.php`

## 10.2 Persistencia híbrida del listing
Archivos:
- `public/class-tm-public.php`
- `admin/class-tm-admin.php`
- `includes/class-tm-helpers.php`
- `includes/class-tm-db.php`

## 10.3 Slug público y rewrite rules
Archivos:
- `includes/class-tm-settings.php`
- `includes/class-tm-post-types.php`
- `includes/class-tm-taxonomies.php`
- `includes/class-tm-template.php`

## 10.4 Media pipeline
Archivos:
- `public/class-tm-public.php`
- settings marketplace
- branding watermark

## 10.5 Admin tools destructivos
Archivos:
- `admin/class-tm-admin.php`
- `includes/class-tm-seeder.php`

---

# 11. Riesgos conocidos / inconsistencias observadas

## 11.1 Posible bug en ejecución manual de alertas

`TM_Admin::handle_run_alerts()` llama `TM_Alerts::process_pending_alerts()`.

En la clase revisada existe `TM_Alerts::process_due_alerts()`.

Conclusión:

- o falta un alias/método no presente
- o el handler admin quedó desalineado

Esto debe revisarse antes de confiar en esa herramienta.

## 11.2 Concentración excesiva de responsabilidades en `TM_Public`

No es un bug puntual, pero sí una deuda estructural.
Hace demasiadas cosas críticas.
Cualquier cambio ahí requiere test amplio.

## 11.3 `TM_Admin` también mezcla UI, consultas, shell y handlers

De nuevo: no necesariamente roto, pero sí sensible para cambios futuros.

---

# 12. Protocolo seguro de intervención

## Cambios visuales

1. tocar CSS primero
2. tocar JS si falta interacción
3. tocar markup PHP solo si CSS no alcanza
4. no mezclar refactor de lógica en la misma tarea

## Cambios funcionales

1. mapear flujo completo
2. ubicar persistencia afectada
3. ubicar handlers implicados
4. revisar nonces/caps
5. revisar redirects/notices
6. probar archive/single/account/submit si el cambio es transversal

## Cambios de datos o schema

1. identificar tabla/meta/tax afectada
2. revisar lecturas e inserciones existentes
3. definir migración
4. probar sobre datos existentes

---

# 13. Checklist de revisión antes de dar por bueno un cambio

## Si tocaste frontend
- archive carga
- single carga
- publicar carga
- acceso carga
- mi cuenta carga
- filtros AJAX siguen funcionando
- galería sigue funcionando
- mobile no se rompió

## Si tocaste admin
- menú carga
- páginas admin cargan
- settings guardan
- metabox listing guarda
- list table no se rompió
- herramientas admin no explotan

## Si tocaste slugs/routing
- archive responde
- single responde
- publicar responde
- acceso responde
- mi cuenta responde
- tax archives responden
- enlaces internos siguen bien

## Si tocaste media
- sube imágenes
- respeta límite máximo
- featured se conserva
- watermark se aplica si corresponde
- imágenes eliminadas no quedan colgadas en UI

## Si tocaste alertas
- crear alerta funciona
- listar alertas funciona
- borrar alerta funciona
- cron/manual run no rompe
- email remitente correcto

---

# 14. Mapa corto de intervención por archivo

## `terramarket.php`
Tocar solo si necesitas bootstrap global.

## `class-tm-loader.php`
Tocar solo si necesitas cambiar orden de carga o registrar módulos nuevos.

## `class-tm-template.php`
Tocar solo si necesitas cambiar routing standalone.

## `class-tm-settings.php`
Tocar si cambias ajustes, slugs, sanitización o reglas de flush.

## `class-tm-db.php`
Tocar si cambias schema real.

## `class-tm-helpers.php`
Tocar si cambias reglas compartidas o utilidades transversales.

## `class-tm-admin.php`
Tocar si cambias admin real, handlers admin o markup admin.

## `class-tm-public.php`
Tocar si cambias comportamiento real del marketplace.

## `admin.css`
Punto natural para rediseño admin.

## `frontend.css`
Punto natural para rediseño frontend.

---

# 15. Conclusión dura

Terramarket 1.15.3 ya tiene suficiente complejidad como para necesitar este plugin map.

La lectura correcta del código es esta:

- **el frontend real no vive principalmente en los templates clásicos, vive en `TM_Public` + routing standalone**
- **los settings no son solo formularios, afectan URLs y comportamiento operativo**
- **el admin no es una capa cosmética, también ejecuta operaciones sensibles**
- **la persistencia está repartida en varias capas, no en un solo lugar**
- **un cambio pequeño puede tener impacto en routing, queries, media o capacidades**

Regla final:

Si una intervención toca algo más que CSS puro, conviene revisar al menos:

- archivo principal implicado
- helpers compartidos
- persistencia afectada
- routing/handlers asociados
- checklist mínimo posterior

