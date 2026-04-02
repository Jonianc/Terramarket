<?php

if (! defined('ABSPATH')) {
    exit;
}

class TM_Admin
{
    public static function init(): void
    {
        add_action('admin_menu', array(__CLASS__, 'register_menu'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
        add_action('tm_category_add_form_fields', array(__CLASS__, 'render_category_media_add_fields'));
        add_action('tm_category_edit_form_fields', array(__CLASS__, 'render_category_media_edit_fields'));
        add_action('created_tm_category', array(__CLASS__, 'save_category_media_fields'));
        add_action('edited_tm_category', array(__CLASS__, 'save_category_media_fields'));
        add_action('add_meta_boxes_tm_listing', array(__CLASS__, 'register_listing_meta_boxes'));
        add_action('save_post_tm_listing', array(__CLASS__, 'save_listing_meta'));
        add_action('admin_post_tm_reseed_all', array(__CLASS__, 'handle_reseed_all'));
        add_action('admin_post_tm_reseed_categories', array(__CLASS__, 'handle_reseed_categories'));
        add_action('admin_post_tm_reseed_regions', array(__CLASS__, 'handle_reseed_regions'));
        add_action('admin_post_tm_save_vitrine', array(__CLASS__, 'handle_save_vitrine'));
        add_action('admin_post_tm_run_alerts', array(__CLASS__, 'handle_run_alerts'));
        add_filter('manage_tm_listing_posts_columns', array(__CLASS__, 'filter_listing_columns'));
        add_action('manage_tm_listing_posts_custom_column', array(__CLASS__, 'render_listing_column'), 10, 2);
        add_filter('manage_edit-tm_listing_sortable_columns', array(__CLASS__, 'get_sortable_listing_columns'));
        add_action('restrict_manage_posts', array(__CLASS__, 'render_listing_filters'));
        add_action('pre_get_posts', array(__CLASS__, 'filter_listing_admin_query'));
        add_action('all_admin_notices', array(__CLASS__, 'render_listing_admin_chrome'));
        add_action('all_admin_notices', array(__CLASS__, 'render_category_admin_chrome'));
        add_filter('manage_edit-tm_category_columns', array(__CLASS__, 'filter_category_columns'));
        add_filter('manage_tm_category_custom_column', array(__CLASS__, 'render_category_column'), 10, 3);
        add_filter('term_row_actions', array(__CLASS__, 'filter_category_row_actions'), 10, 2);
        add_filter('post_row_actions', array(__CLASS__, 'filter_listing_row_actions'), 10, 2);
        add_filter('enter_title_here', array(__CLASS__, 'filter_listing_title_placeholder'), 10, 2);
    }

    protected static function get_admin_page_capability(string $page): string
    {
        switch ($page) {
            case 'dashboard':
            case 'listings':
                return 'tm_edit_listings';
            case 'leads':
                return 'tm_manage_leads';
            case 'vitrine':
                return 'tm_manage_vitrine';
            case 'commissions':
                return 'tm_manage_commissions';
            case 'categories':
                return 'tm_manage_categories';
            case 'settings':
            case 'tools':
            default:
                return 'tm_manage_settings';
        }
    }

    protected static function current_user_can_access_page(string $page): bool
    {
        return current_user_can(self::get_admin_page_capability($page));
    }

    protected static function current_user_can_manage_categories(): bool
    {
        return current_user_can('tm_manage_categories') || current_user_can('tm_manage_settings') || current_user_can('manage_categories');
    }

    protected static function get_categories_admin_url(int $term_id = 0, string $anchor = ''): string
    {
        $url = $term_id > 0
            ? admin_url('term.php?taxonomy=tm_category&post_type=tm_listing&tag_ID=' . $term_id)
            : admin_url('edit-tags.php?taxonomy=tm_category&post_type=tm_listing');

        if ('' !== $anchor) {
            $url .= '#' . ltrim($anchor, '#');
        }

        return $url;
    }

    protected static function get_category_icon_count(): int
    {
        $terms = get_terms(array(
            'taxonomy'   => 'tm_category',
            'hide_empty' => false,
            'fields'     => 'ids',
        ));

        if (is_wp_error($terms) || empty($terms)) {
            return 0;
        }

        $count = 0;
        foreach ($terms as $term_id) {
            if ((int) get_term_meta((int) $term_id, 'tm_category_icon_id', true) > 0) {
                $count++;
            }
        }

        return $count;
    }

    public static function register_menu(): void
    {
        add_menu_page(
            __('Terramarket', 'terramarket'),
            __('Terramarket', 'terramarket'),
            'tm_edit_listings',
            'terramarket',
            array(__CLASS__, 'render_dashboard_page'),
            'dashicons-store',
            26
        );

        add_submenu_page('terramarket', __('Dashboard', 'terramarket'), __('Dashboard', 'terramarket'), self::get_admin_page_capability('dashboard'), 'terramarket', array(__CLASS__, 'render_dashboard_page'));
        add_submenu_page('terramarket', __('Avisos', 'terramarket'), __('Avisos', 'terramarket'), self::get_admin_page_capability('listings'), 'edit.php?post_type=tm_listing');
        add_submenu_page('terramarket', __('Categorías', 'terramarket'), __('Categorías', 'terramarket'), self::get_admin_page_capability('categories'), 'edit-tags.php?taxonomy=tm_category&post_type=tm_listing');
        add_submenu_page('terramarket', __('Leads', 'terramarket'), __('Leads', 'terramarket'), self::get_admin_page_capability('leads'), 'tm-leads', array(__CLASS__, 'render_leads_page'));
        add_submenu_page('terramarket', __('Vitrina', 'terramarket'), __('Vitrina', 'terramarket'), self::get_admin_page_capability('vitrine'), 'tm-vitrine', array(__CLASS__, 'render_vitrine_page'));
        add_submenu_page('terramarket', __('Comisiones', 'terramarket'), __('Comisiones', 'terramarket'), self::get_admin_page_capability('commissions'), 'tm-commissions', array(__CLASS__, 'render_commissions_page'));
        add_submenu_page('terramarket', __('Ajustes', 'terramarket'), __('Ajustes', 'terramarket'), self::get_admin_page_capability('settings'), 'tm-settings', array(__CLASS__, 'render_settings_page'));
        add_submenu_page('terramarket', __('Herramientas', 'terramarket'), __('Herramientas', 'terramarket'), self::get_admin_page_capability('tools'), 'tm-tools', array(__CLASS__, 'render_tools_page'));
    }

    public static function enqueue_assets(string $hook): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $is_tm_listing_screen = $screen && 'tm_listing' === $screen->post_type;
        $is_tm_category_screen = $screen && 'tm_category' === ($screen->taxonomy ?? '');

        if (
            strpos($hook, 'terramarket') === false
            && strpos($hook, 'tm_listing') === false
            && strpos($hook, 'tm-leads') === false
            && strpos($hook, 'tm-vitrine') === false
            && strpos($hook, 'tm-commissions') === false
            && strpos($hook, 'post.php') === false
            && strpos($hook, 'post-new.php') === false
            && ! $is_tm_listing_screen
            && ! $is_tm_category_screen
        ) {
            return;
        }

        wp_enqueue_style('tm-admin', TM_PLUGIN_URL . 'assets/css/admin.css', array(), TM_VERSION);
        wp_enqueue_script('tm-admin', TM_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), TM_VERSION, true);

        $current_page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        $current_tab  = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : '';
        $needs_media = $is_tm_category_screen || ('tm-settings' === $current_page && in_array($current_tab, array('branding', 'marketplace'), true));
        $needs_color_picker = 'tm-settings' === $current_page && in_array($current_tab, array('branding', 'marketplace'), true);

        if ($needs_media) {
            wp_enqueue_media();
        }

        if ($needs_color_picker) {
            wp_enqueue_style('wp-color-picker');
            wp_enqueue_script('wp-color-picker');
        }
    }


    public static function render_category_media_add_fields(string $taxonomy): void
    {
        wp_nonce_field('tm_save_category_media', 'tm_category_media_nonce');
        ?>
        <div id="tm_category_icon_card_add" class="form-field term-group tm-setting-field tm-setting-field--media tm-category-media-card">
            <div class="tm-category-media-card__header">
                <h3><?php esc_html_e('Icono visual de la categoría', 'terramarket'); ?></h3>
                <p><?php esc_html_e('Sube aquí la mini-ilustración principal que se verá en la grilla visual del frontend.', 'terramarket'); ?></p>
            </div>
            <div class="tm-media-control">
                <div class="tm-media-control__actions">
                    <button type="button" class="button button-secondary tm-media-select" data-target-input="#tm_category_icon_id" data-target-preview="#tm_category_icon_preview" data-media-title="<?php esc_attr_e('Seleccionar icono de categoría', 'terramarket'); ?>" data-media-button="<?php esc_attr_e('Usar esta imagen', 'terramarket'); ?>"><?php esc_html_e('Seleccionar imagen', 'terramarket'); ?></button>
                    <button type="button" class="button tm-media-remove" data-target-input="#tm_category_icon_id" data-target-preview="#tm_category_icon_preview"><?php esc_html_e('Quitar', 'terramarket'); ?></button>
                </div>
                <div class="tm-media-preview-wrap tm-media-preview-wrap--large">
                    <img id="tm_category_icon_preview" class="tm-media-preview tm-media-preview--large is-hidden" src="" alt="">
                    <div class="tm-media-empty" data-empty-for="#tm_category_icon_preview"><?php esc_html_e('Sin imagen cargada', 'terramarket'); ?></div>
                </div>
            </div>
            <input class="tm-media-id" type="hidden" id="tm_category_icon_id" name="tm_category_icon_id" value="0">
            <ul class="tm-category-media-card__tips">
                <li><?php esc_html_e('Formato recomendado: PNG/WebP cuadrado.', 'terramarket'); ?></li>
                <li><?php esc_html_e('Ideal: 768x768 o 1024x1024.', 'terramarket'); ?></li>
                <li><?php esc_html_e('Sin texto, sin marco y con fondo transparente.', 'terramarket'); ?></li>
            </ul>
        </div>
        <?php
    }

    public static function render_category_media_edit_fields(
        \WP_Term $term): void
    {
        $icon_id = (int) get_term_meta($term->term_id, 'tm_category_icon_id', true);
        $icon_preview = $icon_id ? wp_get_attachment_image_url($icon_id, 'medium') : '';
        wp_nonce_field('tm_save_category_media', 'tm_category_media_nonce');
        ?>
        <tr id="tm_category_icon_card" class="form-field term-group-wrap tm-setting-field tm-setting-field--media tm-category-media-row">
            <th scope="row">
                <label for="tm_category_icon_id"><?php esc_html_e('Icono visual', 'terramarket'); ?></label>
            </th>
            <td>
                <div class="tm-category-media-card tm-category-media-card--edit">
                    <div class="tm-category-media-card__header">
                        <h3><?php esc_html_e('Visual principal de la categoría', 'terramarket'); ?></h3>
                        <p><?php esc_html_e('Esta imagen reemplaza el ícono simple y da a la categoría un look más cercano a la referencia visual aprobada.', 'terramarket'); ?></p>
                    </div>
                    <div class="tm-media-control">
                        <div class="tm-media-control__actions">
                            <button type="button" class="button button-secondary tm-media-select" data-target-input="#tm_category_icon_id" data-target-preview="#tm_category_icon_preview" data-media-title="<?php esc_attr_e('Seleccionar icono de categoría', 'terramarket'); ?>" data-media-button="<?php esc_attr_e('Usar esta imagen', 'terramarket'); ?>"><?php esc_html_e('Seleccionar imagen', 'terramarket'); ?></button>
                            <button type="button" class="button tm-media-remove" data-target-input="#tm_category_icon_id" data-target-preview="#tm_category_icon_preview"><?php esc_html_e('Quitar', 'terramarket'); ?></button>
                        </div>
                        <div class="tm-media-preview-wrap tm-media-preview-wrap--large">
                            <img id="tm_category_icon_preview" class="tm-media-preview tm-media-preview--large<?php echo $icon_preview ? '' : ' is-hidden'; ?>" src="<?php echo esc_url($icon_preview ?: ''); ?>" alt="">
                            <div class="tm-media-empty<?php echo $icon_preview ? ' is-hidden' : ''; ?>" data-empty-for="#tm_category_icon_preview"><?php esc_html_e('Sin imagen cargada', 'terramarket'); ?></div>
                        </div>
                    </div>
                    <input class="tm-media-id" type="hidden" id="tm_category_icon_id" name="tm_category_icon_id" value="<?php echo esc_attr((string) $icon_id); ?>">
                    <ul class="tm-category-media-card__tips">
                        <li><?php esc_html_e('Usa mini-ilustración cuadrada y consistente con el resto del pack.', 'terramarket'); ?></li>
                        <li><?php esc_html_e('Evita texto, marcos y escenas completas.', 'terramarket'); ?></li>
                        <li><?php esc_html_e('Si la quitas, el frontend vuelve al fallback del sistema.', 'terramarket'); ?></li>
                    </ul>
                </div>
            </td>
        </tr>
        <?php
    }

    public static function save_category_media_fields(int $term_id): void
    {
        if (! self::current_user_can_manage_categories()) {
            return;
        }

        $nonce = isset($_POST['tm_category_media_nonce']) ? sanitize_text_field(wp_unslash($_POST['tm_category_media_nonce'])) : '';
        if (! $nonce || ! wp_verify_nonce($nonce, 'tm_save_category_media')) {
            return;
        }

        update_term_meta($term_id, 'tm_category_icon_id', absint($_POST['tm_category_icon_id'] ?? 0));
    }

    public static function register_listing_meta_boxes(): void
    {
        add_meta_box(
            'tm-listing-details',
            __('Detalles del aviso', 'terramarket'),
            array(__CLASS__, 'render_listing_meta_box'),
            'tm_listing',
            'normal',
            'high'
        );
    }

    public static function render_listing_meta_box(\WP_Post $post): void
    {
        wp_nonce_field('tm_save_listing_meta', 'tm_listing_meta_nonce');

        $selected_category    = TM_Helpers::selected_term_id($post->ID, 'tm_category');
        $selected_subcategory = TM_Helpers::selected_term_id($post->ID, 'tm_subcategory');
        $selected_region      = TM_Helpers::selected_term_id($post->ID, 'tm_region');
        $selected_comuna      = TM_Helpers::selected_term_id($post->ID, 'tm_comuna');
        $selected_condition   = TM_Helpers::selected_term_id($post->ID, 'tm_condition');

        $meta = array(
            'tm_price_clp'            => get_post_meta($post->ID, 'tm_price_clp', true),
            'tm_contact_name'         => get_post_meta($post->ID, 'tm_contact_name', true),
            'tm_contact_email'        => get_post_meta($post->ID, 'tm_contact_email', true),
            'tm_contact_phone'        => get_post_meta($post->ID, 'tm_contact_phone', true),
            'tm_listing_status'       => TM_Helpers::get_effective_listing_status($post->ID),
            'tm_featured'             => (int) get_post_meta($post->ID, 'tm_featured', true),
            'tm_views_count'          => get_post_meta($post->ID, 'tm_views_count', true),
            'tm_expiration_date'      => get_post_meta($post->ID, 'tm_expiration_date', true),
            'tm_sold_at'              => get_post_meta($post->ID, 'tm_sold_at', true),
            'tm_declared_sale_amount' => get_post_meta($post->ID, 'tm_declared_sale_amount', true),
        );

        $categories    = TM_Helpers::get_terms_for_select('tm_category');
        $subcategories = TM_Helpers::get_terms_for_select('tm_subcategory');
        $regions       = TM_Helpers::get_regions_ordered();
        $comunas       = TM_Helpers::get_terms_for_select('tm_comuna');
        $conditions    = TM_Helpers::get_terms_for_select('tm_condition');
        $image_count   = TM_Helpers::get_listing_image_count($post->ID);
        $status_label  = TM_Helpers::get_listing_status_label((string) $meta['tm_listing_status']);
        $listing_url   = get_permalink($post);
        $preview_url   = get_preview_post_link($post);
        ?>
        <div class="tm-listing-meta">
            <div class="tm-listing-meta__summary">
                <div class="tm-listing-meta__metric">
                    <span class="tm-listing-meta__metric-label"><?php esc_html_e('Estado', 'terramarket'); ?></span>
                    <strong class="tm-listing-meta__metric-value"><?php echo wp_kses_post(self::render_ui_badge($status_label, 'info')); ?></strong>
                </div>
                <div class="tm-listing-meta__metric">
                    <span class="tm-listing-meta__metric-label"><?php esc_html_e('Fotos', 'terramarket'); ?></span>
                    <strong class="tm-listing-meta__metric-value"><?php echo esc_html((string) $image_count); ?></strong>
                </div>
                <div class="tm-listing-meta__metric">
                    <span class="tm-listing-meta__metric-label"><?php esc_html_e('Visualizaciones', 'terramarket'); ?></span>
                    <strong class="tm-listing-meta__metric-value"><?php echo esc_html(number_format_i18n((int) $meta['tm_views_count'])); ?></strong>
                </div>
                <div class="tm-listing-meta__metric">
                    <span class="tm-listing-meta__metric-label"><?php esc_html_e('Última actualización', 'terramarket'); ?></span>
                    <strong class="tm-listing-meta__metric-value"><?php echo esc_html(get_the_modified_date(get_option('date_format'), $post)); ?></strong>
                </div>
            </div>

            <div class="tm-listing-meta__actions">
                <?php if ($listing_url && 'publish' === $post->post_status) : ?>
                    <a class="button" href="<?php echo esc_url($listing_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Ver aviso', 'terramarket'); ?></a>
                <?php endif; ?>
                <?php if ($preview_url) : ?>
                    <a class="button" href="<?php echo esc_url($preview_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Vista previa', 'terramarket'); ?></a>
                <?php endif; ?>
            </div>
            <nav class="tm-admin-stepper tm-admin-stepper--listing" data-tm-admin-listing-stepper aria-label="<?php esc_attr_e('Pasos del aviso en admin', 'terramarket'); ?>">
                <button type="button" class="tm-admin-stepper__button is-active" data-tm-admin-step="commercial" aria-current="step">
                    <span>1</span>
                    <strong><?php esc_html_e('Comercial', 'terramarket'); ?></strong>
                </button>
                <button type="button" class="tm-admin-stepper__button" data-tm-admin-step="classification" aria-current="false">
                    <span>2</span>
                    <strong><?php esc_html_e('Clasificación', 'terramarket'); ?></strong>
                </button>
                <button type="button" class="tm-admin-stepper__button" data-tm-admin-step="location" aria-current="false">
                    <span>3</span>
                    <strong><?php esc_html_e('Ubicación', 'terramarket'); ?></strong>
                </button>
                <button type="button" class="tm-admin-stepper__button" data-tm-admin-step="contact" aria-current="false">
                    <span>4</span>
                    <strong><?php esc_html_e('Contacto', 'terramarket'); ?></strong>
                </button>
            </nav>

            <div class="tm-listing-meta__grid tm-listing-meta__grid--wizard">
                <section class="tm-listing-meta__card is-active" data-tm-admin-step-panel="commercial">
                    <div class="tm-listing-meta__card-header">
                        <h3><?php esc_html_e('Datos comerciales', 'terramarket'); ?></h3>
                        <p><?php esc_html_e('Controla precio, estado visible y cierre comercial del aviso.', 'terramarket'); ?></p>
                    </div>
                    <div class="tm-listing-meta__intro-note">
                        <strong><?php esc_html_e('Tip', 'terramarket'); ?></strong>
                        <span><?php esc_html_e('El título y la descripción principal siguen en el editor nativo de WordPress, arriba de este bloque.', 'terramarket'); ?></span>
                    </div>
                    <div class="tm-admin-grid tm-admin-grid--single-gap">
                        <div class="tm-field">
                            <label for="tm_price_clp"><strong><?php esc_html_e('Precio CLP', 'terramarket'); ?></strong></label>
                            <input type="number" class="widefat" id="tm_price_clp" name="tm_price_clp" value="<?php echo esc_attr((string) $meta['tm_price_clp']); ?>" min="0" step="1">
                            <p class="description"><?php esc_html_e('Se usa en la ficha pública del aviso.', 'terramarket'); ?></p>
                        </div>
                        <div class="tm-field">
                            <label for="tm_listing_status"><strong><?php esc_html_e('Estado del aviso', 'terramarket'); ?></strong></label>
                            <select class="widefat" id="tm_listing_status" name="tm_listing_status">
                                <?php foreach (TM_Helpers::get_listing_statuses() as $status_key => $status_label) : ?>
                                    <option value="<?php echo esc_attr($status_key); ?>" <?php selected($meta['tm_listing_status'], $status_key); ?>><?php echo esc_html($status_label); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e('Afecta la visibilidad y lectura comercial del aviso.', 'terramarket'); ?></p>
                        </div>
                        <div class="tm-field">
                            <label for="tm_expiration_date"><strong><?php esc_html_e('Fecha expiración', 'terramarket'); ?></strong></label>
                            <input type="date" class="widefat" id="tm_expiration_date" name="tm_expiration_date" value="<?php echo esc_attr((string) $meta['tm_expiration_date']); ?>">
                        </div>
                        <div class="tm-field">
                            <label for="tm_declared_sale_amount"><strong><?php esc_html_e('Monto venta declarado', 'terramarket'); ?></strong></label>
                            <input type="number" class="widefat" id="tm_declared_sale_amount" name="tm_declared_sale_amount" value="<?php echo esc_attr((string) $meta['tm_declared_sale_amount']); ?>" min="0" step="1">
                        </div>
                        <div class="tm-field tm-field--checkbox-inline">
                            <label><strong><?php esc_html_e('Destacado', 'terramarket'); ?></strong></label>
                            <label class="tm-toggle-field"><input type="checkbox" name="tm_featured" value="1" <?php checked($meta['tm_featured'], 1); ?>> <span><?php esc_html_e('Marcar aviso como destacado', 'terramarket'); ?></span></label>
                        </div>
                    </div>
                    <div class="tm-listing-meta__card-footer">
                        <button type="button" class="button button-primary" data-tm-admin-next-step><?php esc_html_e('Siguiente', 'terramarket'); ?></button>
                    </div>
                </section>

                <section class="tm-listing-meta__card" data-tm-admin-step-panel="classification" hidden>
                    <div class="tm-listing-meta__card-header">
                        <h3><?php esc_html_e('Clasificación', 'terramarket'); ?></h3>
                        <p><?php esc_html_e('Organiza el aviso para filtros, vitrina y navegación del marketplace.', 'terramarket'); ?></p>
                    </div>
                    <div class="tm-admin-grid tm-admin-grid--single-gap">
                        <div class="tm-field">
                            <label for="tm_category_term_id"><strong><?php esc_html_e('Categoría', 'terramarket'); ?></strong></label>
                            <select class="widefat" id="tm_category_term_id" name="tm_category_term_id">
                                <option value="0"><?php esc_html_e('Seleccionar categoría', 'terramarket'); ?></option>
                                <?php foreach ($categories as $term) : ?>
                                    <option value="<?php echo esc_attr((string) $term->term_id); ?>" <?php selected($selected_category, $term->term_id); ?>><?php echo esc_html($term->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="tm-field">
                            <label for="tm_subcategory_term_id"><strong><?php esc_html_e('Subcategoría', 'terramarket'); ?></strong></label>
                            <select class="widefat" id="tm_subcategory_term_id" name="tm_subcategory_term_id">
                                <option value="0"><?php esc_html_e('Seleccionar subcategoría', 'terramarket'); ?></option>
                                <?php foreach ($subcategories as $term) : $parent_category_id = (int) get_term_meta($term->term_id, 'tm_parent_category_id', true); ?>
                                    <option value="<?php echo esc_attr((string) $term->term_id); ?>" data-parent-category="<?php echo esc_attr((string) $parent_category_id); ?>" <?php selected($selected_subcategory, $term->term_id); ?>><?php echo esc_html($term->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="tm-field">
                            <label for="tm_condition_term_id"><strong><?php esc_html_e('Condición', 'terramarket'); ?></strong></label>
                            <select class="widefat" id="tm_condition_term_id" name="tm_condition_term_id">
                                <option value="0"><?php esc_html_e('Seleccionar condición', 'terramarket'); ?></option>
                                <?php foreach ($conditions as $term) : ?>
                                    <option value="<?php echo esc_attr((string) $term->term_id); ?>" <?php selected($selected_condition, $term->term_id); ?>><?php echo esc_html($term->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="tm-listing-meta__card-footer tm-listing-meta__card-footer--split">
                        <button type="button" class="button" data-tm-admin-prev-step><?php esc_html_e('Anterior', 'terramarket'); ?></button>
                        <button type="button" class="button button-primary" data-tm-admin-next-step><?php esc_html_e('Siguiente', 'terramarket'); ?></button>
                    </div>
                </section>

                <section class="tm-listing-meta__card" data-tm-admin-step-panel="location" hidden>
                    <div class="tm-listing-meta__card-header">
                        <h3><?php esc_html_e('Ubicación', 'terramarket'); ?></h3>
                        <p><?php esc_html_e('Mantén región y comuna consistentes para filtros y alertas.', 'terramarket'); ?></p>
                    </div>
                    <div class="tm-admin-grid tm-admin-grid--single-gap">
                        <div class="tm-field">
                            <label for="tm_region_term_id"><strong><?php esc_html_e('Región', 'terramarket'); ?></strong></label>
                            <select class="widefat" id="tm_region_term_id" name="tm_region_term_id">
                                <option value="0"><?php esc_html_e('Seleccionar región', 'terramarket'); ?></option>
                                <?php foreach ($regions as $term) : ?>
                                    <option value="<?php echo esc_attr((string) $term->term_id); ?>" <?php selected($selected_region, $term->term_id); ?>><?php echo esc_html($term->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="tm-field">
                            <label for="tm_comuna_term_id"><strong><?php esc_html_e('Comuna', 'terramarket'); ?></strong></label>
                            <select class="widefat" id="tm_comuna_term_id" name="tm_comuna_term_id">
                                <option value="0"><?php esc_html_e('Seleccionar comuna', 'terramarket'); ?></option>
                                <?php foreach ($comunas as $term) : $region_id = (int) get_term_meta($term->term_id, 'tm_region_id', true); ?>
                                    <option value="<?php echo esc_attr((string) $term->term_id); ?>" data-region-id="<?php echo esc_attr((string) $region_id); ?>" <?php selected($selected_comuna, $term->term_id); ?>><?php echo esc_html($term->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="tm-listing-meta__card-footer tm-listing-meta__card-footer--split">
                        <button type="button" class="button" data-tm-admin-prev-step><?php esc_html_e('Anterior', 'terramarket'); ?></button>
                        <button type="button" class="button button-primary" data-tm-admin-next-step><?php esc_html_e('Siguiente', 'terramarket'); ?></button>
                    </div>
                </section>

                <section class="tm-listing-meta__card" data-tm-admin-step-panel="contact" hidden>
                    <div class="tm-listing-meta__card-header">
                        <h3><?php esc_html_e('Contacto', 'terramarket'); ?></h3>
                        <p><?php esc_html_e('Estos datos se usan para conectar comprador y vendedor.', 'terramarket'); ?></p>
                    </div>
                    <div class="tm-admin-grid tm-admin-grid--single-gap">
                        <div class="tm-field">
                            <label for="tm_contact_name"><strong><?php esc_html_e('Nombre contacto', 'terramarket'); ?></strong></label>
                            <input type="text" class="widefat" id="tm_contact_name" name="tm_contact_name" value="<?php echo esc_attr((string) $meta['tm_contact_name']); ?>">
                        </div>
                        <div class="tm-field">
                            <label for="tm_contact_email"><strong><?php esc_html_e('Email contacto', 'terramarket'); ?></strong></label>
                            <input type="email" class="widefat" id="tm_contact_email" name="tm_contact_email" value="<?php echo esc_attr((string) $meta['tm_contact_email']); ?>">
                        </div>
                        <div class="tm-field">
                            <label for="tm_contact_phone"><strong><?php esc_html_e('Teléfono contacto', 'terramarket'); ?></strong></label>
                            <input type="text" class="widefat" id="tm_contact_phone" name="tm_contact_phone" value="<?php echo esc_attr((string) $meta['tm_contact_phone']); ?>">
                        </div>
                    </div>
                    <div class="tm-listing-meta__card-footer tm-listing-meta__card-footer--split">
                        <button type="button" class="button" data-tm-admin-prev-step><?php esc_html_e('Anterior', 'terramarket'); ?></button>
                        <span class="description"><?php esc_html_e('Guarda o actualiza el aviso desde el botón principal de WordPress.', 'terramarket'); ?></span>
                    </div>
                </section>
            </div>
        </div>
        <?php
    }

    public static function save_listing_meta(int $post_id): void
    {
        if (! isset($_POST['tm_listing_meta_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tm_listing_meta_nonce'])), 'tm_save_listing_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (! current_user_can('edit_post', $post_id)) {
            return;
        }

        $price = isset($_POST['tm_price_clp']) ? absint(wp_unslash($_POST['tm_price_clp'])) : 0;
        update_post_meta($post_id, 'tm_price_clp', $price);

        $contact_name  = isset($_POST['tm_contact_name']) ? sanitize_text_field(wp_unslash($_POST['tm_contact_name'])) : '';
        $contact_email = isset($_POST['tm_contact_email']) ? sanitize_email(wp_unslash($_POST['tm_contact_email'])) : '';
        $contact_phone = isset($_POST['tm_contact_phone']) ? sanitize_text_field(wp_unslash($_POST['tm_contact_phone'])) : '';
        update_post_meta($post_id, 'tm_contact_name', $contact_name);
        update_post_meta($post_id, 'tm_contact_email', $contact_email);
        update_post_meta($post_id, 'tm_contact_phone', $contact_phone);

        $listing_status = isset($_POST['tm_listing_status']) ? sanitize_key(wp_unslash($_POST['tm_listing_status'])) : 'active';
        if (! array_key_exists($listing_status, TM_Helpers::get_listing_statuses())) {
            $listing_status = 'active';
        }
        update_post_meta($post_id, 'tm_listing_status', $listing_status);

        $featured = isset($_POST['tm_featured']) ? 1 : 0;
        update_post_meta($post_id, 'tm_featured', $featured);

        $expiration = isset($_POST['tm_expiration_date']) ? sanitize_text_field(wp_unslash($_POST['tm_expiration_date'])) : '';
        if ('active' === $listing_status && '' === $expiration) {
            $expiration = TM_Helpers::get_default_listing_expiration_date();
        } elseif ('expired' === $listing_status && '' === $expiration) {
            $expiration = date('Y-m-d', current_time('timestamp') - DAY_IN_SECONDS);
        }
        update_post_meta($post_id, 'tm_expiration_date', $expiration);

        $declared_sale_amount = isset($_POST['tm_declared_sale_amount']) ? absint(wp_unslash($_POST['tm_declared_sale_amount'])) : 0;
        update_post_meta($post_id, 'tm_declared_sale_amount', $declared_sale_amount);

        $category_id    = isset($_POST['tm_category_term_id']) ? absint(wp_unslash($_POST['tm_category_term_id'])) : 0;
        $subcategory_id = isset($_POST['tm_subcategory_term_id']) ? absint(wp_unslash($_POST['tm_subcategory_term_id'])) : 0;
        if ($subcategory_id && $category_id && ! TM_Helpers::is_subcategory_of_category($subcategory_id, $category_id)) {
            $subcategory_id = 0;
        }
        self::set_terms_from_admin($post_id, 'tm_category', $category_id);
        self::set_terms_from_admin($post_id, 'tm_subcategory', $subcategory_id);

        $region_id = isset($_POST['tm_region_term_id']) ? absint(wp_unslash($_POST['tm_region_term_id'])) : 0;
        $comuna_id = isset($_POST['tm_comuna_term_id']) ? absint(wp_unslash($_POST['tm_comuna_term_id'])) : 0;
        if ($comuna_id && $region_id && ! TM_Helpers::is_comuna_of_region($comuna_id, $region_id)) {
            $comuna_id = 0;
        }
        self::set_terms_from_admin($post_id, 'tm_region', $region_id);
        self::set_terms_from_admin($post_id, 'tm_comuna', $comuna_id);

        $condition_id = isset($_POST['tm_condition_term_id']) ? absint(wp_unslash($_POST['tm_condition_term_id'])) : 0;
        self::set_terms_from_admin($post_id, 'tm_condition', $condition_id);
    }

    protected static function set_terms_from_admin(int $post_id, string $taxonomy, int $term_id): void
    {
        if ($term_id > 0) {
            wp_set_object_terms($post_id, array($term_id), $taxonomy, false);
        } else {
            wp_set_object_terms($post_id, array(), $taxonomy, false);
        }
    }

    public static function filter_listing_columns(array $columns): array
    {
        return array(
            'cb'                => $columns['cb'] ?? '<input type="checkbox" />',
            'title'             => __('Aviso', 'terramarket'),
            'tm_status'         => __('Estado', 'terramarket'),
            'tm_price'          => __('Precio', 'terramarket'),
            'tm_classification' => __('Clasificación', 'terramarket'),
            'tm_location'       => __('Ubicación', 'terramarket'),
            'tm_contact'        => __('Contacto', 'terramarket'),
            'date'              => $columns['date'] ?? __('Fecha', 'terramarket'),
        );
    }

    public static function render_listing_column(string $column, int $post_id): void
    {
        $status = TM_Helpers::get_effective_listing_status($post_id);
        $price = (int) get_post_meta($post_id, 'tm_price_clp', true);
        $featured = (int) get_post_meta($post_id, 'tm_featured', true) === 1;
        $views = (int) get_post_meta($post_id, 'tm_views_count', true);
        $contact_name = (string) get_post_meta($post_id, 'tm_contact_name', true);
        $contact_email = (string) get_post_meta($post_id, 'tm_contact_email', true);
        $contact_phone = (string) get_post_meta($post_id, 'tm_contact_phone', true);
        $category = TM_Helpers::get_term_name_for_post($post_id, 'tm_category');
        $subcategory = TM_Helpers::get_term_name_for_post($post_id, 'tm_subcategory');
        $condition = TM_Helpers::get_term_name_for_post($post_id, 'tm_condition');
        $region = TM_Helpers::get_term_name_for_post($post_id, 'tm_region');
        $comuna = TM_Helpers::get_term_name_for_post($post_id, 'tm_comuna');
        $expiration = (string) get_post_meta($post_id, 'tm_expiration_date', true);

        switch ($column) {
            case 'tm_status':
                echo '<div class="tm-table-stack tm-table-stack--badges">';
                echo wp_kses_post(self::render_ui_badge(TM_Helpers::get_listing_status_label($status), 'info'));
                if ($featured) {
                    echo ' ' . wp_kses_post(self::render_ui_badge(__('Destacado', 'terramarket'), 'warning'));
                }
                if ($expiration) {
                    echo '<span class="tm-inline-meta">' . esc_html__('Expira:', 'terramarket') . ' ' . esc_html(mysql2date(get_option('date_format'), $expiration)) . '</span>';
                }
                echo '</div>';
                break;

            case 'tm_price':
                echo '<div class="tm-table-stack">';
                echo '<strong>' . esc_html($price > 0 ? TM_Helpers::format_price_clp($price) : __('Sin precio', 'terramarket')) . '</strong>';
                echo '<span>' . esc_html(sprintf(_n('%s vista', '%s vistas', max(0, $views), 'terramarket'), number_format_i18n(max(0, $views)))) . '</span>';
                echo '</div>';
                break;

            case 'tm_classification':
                echo '<div class="tm-table-stack">';
                echo '<strong>' . esc_html($category ?: __('Sin categoría', 'terramarket')) . '</strong>';
                if ('' !== $subcategory) {
                    echo '<span>' . esc_html($subcategory) . '</span>';
                }
                if ('' !== $condition) {
                    echo '<span>' . esc_html($condition) . '</span>';
                }
                echo '</div>';
                break;

            case 'tm_location':
                echo '<div class="tm-table-stack">';
                echo '<strong>' . esc_html($region ?: __('Sin región', 'terramarket')) . '</strong>';
                echo '<span>' . esc_html($comuna ?: __('Sin comuna', 'terramarket')) . '</span>';
                echo '</div>';
                break;

            case 'tm_contact':
                echo '<div class="tm-table-stack">';
                echo '<strong>' . esc_html($contact_name ?: __('Sin contacto', 'terramarket')) . '</strong>';
                if ('' !== $contact_email) {
                    echo '<span><a href="mailto:' . esc_attr($contact_email) . '">' . esc_html($contact_email) . '</a></span>';
                }
                if ('' !== $contact_phone) {
                    echo '<span><a href="tel:' . esc_attr(preg_replace('/[^0-9\+]/', '', $contact_phone)) . '">' . esc_html($contact_phone) . '</a></span>';
                }
                echo '</div>';
                break;
        }
    }

    public static function get_sortable_listing_columns(array $columns): array
    {
        $columns['tm_price'] = 'tm_price';
        $columns['tm_status'] = 'tm_status';
        return $columns;
    }

    public static function render_listing_filters(string $post_type): void
    {
        if ('tm_listing' !== $post_type) {
            return;
        }

        $status_filter = isset($_GET['tm_listing_status_filter']) ? sanitize_key(wp_unslash($_GET['tm_listing_status_filter'])) : '';
        $featured_filter = isset($_GET['tm_listing_featured_filter']) ? sanitize_key(wp_unslash($_GET['tm_listing_featured_filter'])) : '';
        $category_filter = isset($_GET['tm_listing_category_filter']) ? absint(wp_unslash($_GET['tm_listing_category_filter'])) : 0;
        $region_filter = isset($_GET['tm_listing_region_filter']) ? absint(wp_unslash($_GET['tm_listing_region_filter'])) : 0;
        ?>
        <select name="tm_listing_status_filter">
            <option value=""><?php esc_html_e('Todos los estados', 'terramarket'); ?></option>
            <?php foreach (TM_Helpers::get_listing_statuses() as $status_key => $status_label) : ?>
                <option value="<?php echo esc_attr($status_key); ?>" <?php selected($status_filter, $status_key); ?>><?php echo esc_html($status_label); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="tm_listing_featured_filter">
            <option value=""><?php esc_html_e('Todos los avisos', 'terramarket'); ?></option>
            <option value="featured" <?php selected($featured_filter, 'featured'); ?>><?php esc_html_e('Solo destacados', 'terramarket'); ?></option>
            <option value="standard" <?php selected($featured_filter, 'standard'); ?>><?php esc_html_e('Sin destacar', 'terramarket'); ?></option>
        </select>
        <?php
        wp_dropdown_categories(array(
            'show_option_all' => __('Todas las categorías', 'terramarket'),
            'taxonomy' => 'tm_category',
            'name' => 'tm_listing_category_filter',
            'orderby' => 'name',
            'selected' => $category_filter,
            'hide_empty' => false,
            'value_field' => 'term_id',
        ));
        wp_dropdown_categories(array(
            'show_option_all' => __('Todas las regiones', 'terramarket'),
            'taxonomy' => 'tm_region',
            'name' => 'tm_listing_region_filter',
            'orderby' => 'name',
            'selected' => $region_filter,
            'hide_empty' => false,
            'value_field' => 'term_id',
        ));
    }

    public static function filter_listing_admin_query($query): void
    {
        if (! is_admin() || ! $query instanceof \WP_Query || ! $query->is_main_query()) {
            return;
        }

        $post_type = (string) $query->get('post_type');
        if ('tm_listing' !== $post_type) {
            return;
        }

        $orderby = (string) $query->get('orderby');
        if ('tm_price' === $orderby) {
            $query->set('meta_key', 'tm_price_clp');
            $query->set('orderby', 'meta_value_num');
        } elseif ('tm_status' === $orderby) {
            $query->set('meta_key', 'tm_listing_status');
            $query->set('orderby', 'meta_value');
        }

        $meta_query = $query->get('meta_query');
        $meta_query = is_array($meta_query) ? $meta_query : array();

        $status_filter = isset($_GET['tm_listing_status_filter']) ? sanitize_key(wp_unslash($_GET['tm_listing_status_filter'])) : '';
        if ('active' === $status_filter) {
            $meta_query[] = TM_Helpers::get_public_active_listings_meta_query();
        } elseif ('expired' === $status_filter) {
            $meta_query[] = array(
                'relation' => 'OR',
                array(
                    'key'   => 'tm_listing_status',
                    'value' => 'expired',
                ),
                array(
                    'relation' => 'AND',
                    array(
                        'key'     => 'tm_listing_status',
                        'value'   => 'active',
                        'compare' => '=',
                    ),
                    array(
                        'key'     => 'tm_expiration_date',
                        'value'   => date('Y-m-d', current_time('timestamp')),
                        'compare' => '<',
                        'type'    => 'DATE',
                    ),
                ),
            );
        } elseif ('' !== $status_filter) {
            $meta_query[] = array(
                'key' => 'tm_listing_status',
                'value' => $status_filter,
            );
        }

        $featured_filter = isset($_GET['tm_listing_featured_filter']) ? sanitize_key(wp_unslash($_GET['tm_listing_featured_filter'])) : '';
        if ('featured' === $featured_filter) {
            $meta_query[] = array(
                'key' => 'tm_featured',
                'value' => '1',
            );
        } elseif ('standard' === $featured_filter) {
            $meta_query[] = array(
                'relation' => 'OR',
                array(
                    'key' => 'tm_featured',
                    'compare' => 'NOT EXISTS',
                ),
                array(
                    'key' => 'tm_featured',
                    'value' => '0',
                ),
            );
        }

        if ($meta_query) {
            $query->set('meta_query', $meta_query);
        }

        $tax_query = $query->get('tax_query');
        $tax_query = is_array($tax_query) ? $tax_query : array();

        $category_filter = isset($_GET['tm_listing_category_filter']) ? absint(wp_unslash($_GET['tm_listing_category_filter'])) : 0;
        if ($category_filter > 0) {
            $tax_query[] = array(
                'taxonomy' => 'tm_category',
                'field' => 'term_id',
                'terms' => array($category_filter),
            );
        }

        $region_filter = isset($_GET['tm_listing_region_filter']) ? absint(wp_unslash($_GET['tm_listing_region_filter'])) : 0;
        if ($region_filter > 0) {
            $tax_query[] = array(
                'taxonomy' => 'tm_region',
                'field' => 'term_id',
                'terms' => array($region_filter),
            );
        }

        if ($tax_query) {
            $query->set('tax_query', $tax_query);
        }
    }

    public static function render_listing_admin_chrome(): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (! $screen || 'tm_listing' !== $screen->post_type || 'edit' !== $screen->base) {
            return;
        }

        $counts = wp_count_posts('tm_listing');
        $featured_query = new \WP_Query(array(
            'post_type' => 'tm_listing',
            'post_status' => array('publish', 'draft', 'pending', 'future', 'private'),
            'fields' => 'ids',
            'posts_per_page' => 1,
            'no_found_rows' => false,
            'meta_key' => 'tm_featured',
            'meta_value' => '1',
        ));
        $active_query = new \WP_Query(array(
            'post_type'   => 'tm_listing',
            'post_status' => array('publish', 'draft', 'pending', 'future', 'private'),
            'fields'      => 'ids',
            'posts_per_page' => 1,
            'no_found_rows'  => false,
            'meta_query'     => TM_Helpers::get_public_active_listings_meta_query(),
        ));
        ?>
        <div class="tm-listing-screen-shell tm-admin-page tm-wrap">
            <div class="tm-admin-shell">
            <?php self::render_admin_shell_fragment('listings', array(
                'title' => __('Avisos', 'terramarket'),
                'lead' => __('Administra la oferta publicada, filtra por estado y edita la ficha comercial sin salir del flujo nativo de WordPress.', 'terramarket'),
                'summary_items' => array(
                    array('label' => __('Publicados', 'terramarket'), 'value' => number_format_i18n((int) ($counts->publish ?? 0))),
                    array('label' => __('Borradores', 'terramarket'), 'value' => number_format_i18n((int) ($counts->draft ?? 0))),
                    array('label' => __('Activos', 'terramarket'), 'value' => number_format_i18n((int) $active_query->found_posts)),
                    array('label' => __('Destacados', 'terramarket'), 'value' => number_format_i18n((int) $featured_query->found_posts)),
                ),
                'actions' => self::get_admin_actions_for_page('listings'),
            )); ?>
            <?php if (0 === (int) ($counts->publish ?? 0) && 0 === (int) ($counts->draft ?? 0) && 0 === (int) ($counts->pending ?? 0)) : ?>
                <section class="tm-page-section tm-listing-screen-shell__empty">
                    <div class="tm-empty-state tm-empty-state--compact">
                        <h3><?php esc_html_e('Aún no hay avisos cargados', 'terramarket'); ?></h3>
                        <p><?php esc_html_e('Crea el primer aviso para empezar a poblar el marketplace y usar filtros, vitrina y flujo comercial.', 'terramarket'); ?></p>
                        <div class="tm-empty-state__actions">
                            <a class="button button-primary" href="<?php echo esc_url(admin_url('post-new.php?post_type=tm_listing')); ?>"><?php esc_html_e('Crear aviso', 'terramarket'); ?></a>
                            <a class="button button-secondary" href="<?php echo esc_url(admin_url('admin.php?page=tm-settings')); ?>"><?php esc_html_e('Revisar ajustes', 'terramarket'); ?></a>
                        </div>
                    </div>
                </section>
            <?php endif; ?>
            </div>
        </div>
        <?php
        wp_reset_postdata();
    }

    public static function filter_category_columns(array $columns): array
    {
        $updated = array();
        foreach ($columns as $key => $label) {
            $updated[$key] = $label;
            if ('name' === $key) {
                $updated['tm_category_visual'] = __('Visual', 'terramarket');
            }
        }

        if (! isset($updated['tm_category_visual'])) {
            $updated['tm_category_visual'] = __('Visual', 'terramarket');
        }

        return $updated;
    }

    public static function render_category_column(string $content, string $column_name, int $term_id): string
    {
        if ('tm_category_visual' !== $column_name) {
            return $content;
        }

        $icon_id = (int) get_term_meta($term_id, 'tm_category_icon_id', true);
        $preview = $icon_id ? (wp_get_attachment_image_url($icon_id, 'thumbnail') ?: '') : '';
        $status = $icon_id > 0 ? __('Imagen cargada', 'terramarket') : __('Fallback activo', 'terramarket');
        $tone = $icon_id > 0 ? 'success' : 'neutral';

        ob_start();
        ?>
        <div class="tm-taxonomy-visual-cell">
            <div class="tm-taxonomy-visual-cell__preview<?php echo $preview ? '' : ' is-empty'; ?>">
                <?php if ($preview) : ?>
                    <img src="<?php echo esc_url($preview); ?>" alt="">
                <?php else : ?>
                    <span class="dashicons dashicons-format-image" aria-hidden="true"></span>
                <?php endif; ?>
            </div>
            <div class="tm-taxonomy-visual-cell__meta">
                <?php echo self::render_ui_badge($status, $tone); ?>
                <a href="<?php echo esc_url(self::get_categories_admin_url($term_id, 'tm_category_icon_card')); ?>"><?php esc_html_e('Editar visual', 'terramarket'); ?></a>
            </div>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    public static function filter_category_row_actions(array $actions, $term): array
    {
        if (! ($term instanceof \WP_Term) || 'tm_category' !== $term->taxonomy) {
            return $actions;
        }

        $actions['tm_visual'] = '<a href="' . esc_url(self::get_categories_admin_url((int) $term->term_id, 'tm_category_icon_card')) . '">' . esc_html__('Editar visual', 'terramarket') . '</a>';

        $term_link = get_term_link($term);
        if (! is_wp_error($term_link)) {
            $actions['tm_front'] = '<a href="' . esc_url((string) $term_link) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Ver categoría', 'terramarket') . '</a>';
        }

        return $actions;
    }

    public static function render_category_admin_chrome(): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (! $screen || 'tm_category' !== ($screen->taxonomy ?? '')) {
            return;
        }

        $total_categories = (int) wp_count_terms(array(
            'taxonomy'   => 'tm_category',
            'hide_empty' => false,
        ));
        $categories_with_icon = self::get_category_icon_count();
        $categories_without_icon = max(0, $total_categories - $categories_with_icon);
        $market_url = trailingslashit(home_url('/' . ltrim(TM_Helpers::get_market_slug(), '/')));

        echo '<div class="tm-category-screen-shell tm-admin-page tm-wrap"><div class="tm-admin-shell">';

        if ('edit-tags' === $screen->base) {
            self::render_admin_shell_fragment('categories', array(
                'title' => __('Categorías visuales', 'terramarket'),
                'lead'  => __('Gestiona la estructura comercial del marketplace y sube imágenes por categoría desde una UI más clara, sin depender de pantallas nativas difíciles de ubicar.', 'terramarket'),
                'summary_items' => array(
                    array('label' => __('Categorías', 'terramarket'), 'value' => number_format_i18n($total_categories)),
                    array('label' => __('Con imagen', 'terramarket'), 'value' => number_format_i18n($categories_with_icon)),
                    array('label' => __('Con fallback', 'terramarket'), 'value' => number_format_i18n($categories_without_icon)),
                    array('label' => __('Frontend', 'terramarket'), 'value' => __('Activo', 'terramarket'), 'title' => $market_url),
                ),
                'actions' => self::get_admin_actions_for_page('categories'),
            ));
            ?>
            <section class="tm-page-section tm-category-screen-guide">
                <div class="tm-page-section__header">
                    <div>
                        <h2><?php esc_html_e('Flujo recomendado', 'terramarket'); ?></h2>
                        <p><?php esc_html_e('Desde aquí puedes crear categorías, asignar su visual y revisar en un solo lugar qué rubros aún siguen con fallback.', 'terramarket'); ?></p>
                    </div>
                </div>
                <div class="tm-quick-actions-grid">
                    <a class="tm-quick-action" href="#col-left">
                        <strong><?php esc_html_e('1. Crear categoría', 'terramarket'); ?></strong>
                        <span><?php esc_html_e('Usa el panel izquierdo para nombre y slug. Después podrás cargar la imagen visual de inmediato.', 'terramarket'); ?></span>
                    </a>
                    <a class="tm-quick-action" href="#col-right">
                        <strong><?php esc_html_e('2. Revisar grilla actual', 'terramarket'); ?></strong>
                        <span><?php esc_html_e('La tabla derecha ahora muestra el estado visual de cada categoría y acceso directo a editar su imagen.', 'terramarket'); ?></span>
                    </a>
                    <a class="tm-quick-action" href="<?php echo esc_url(admin_url('admin.php?page=tm-settings&tab=marketplace')); ?>">
                        <strong><?php esc_html_e('3. Ajustar hero', 'terramarket'); ?></strong>
                        <span><?php esc_html_e('El fondo del hero vive en Ajustes > Marketplace y complementa el look de estas categorías visuales.', 'terramarket'); ?></span>
                    </a>
                    <a class="tm-quick-action" href="<?php echo esc_url($market_url); ?>" target="_blank" rel="noopener noreferrer">
                        <strong><?php esc_html_e('4. Validar frontend', 'terramarket'); ?></strong>
                        <span><?php esc_html_e('Comprueba si la lectura por bloques visuales ya se siente clara en desktop y móvil.', 'terramarket'); ?></span>
                    </a>
                </div>
            </section>
            <?php
        } elseif ('term' === $screen->base) {
            $term_id = isset($_GET['tag_ID']) ? absint(wp_unslash($_GET['tag_ID'])) : 0;
            $term = $term_id ? get_term($term_id, 'tm_category') : null;
            $term_name = ($term instanceof \WP_Term) ? $term->name : __('Categoría', 'terramarket');
            $term_slug = ($term instanceof \WP_Term) ? $term->slug : '';
            $term_link = ($term instanceof \WP_Term) ? get_term_link($term) : '';
            $icon_id = $term_id > 0 ? (int) get_term_meta($term_id, 'tm_category_icon_id', true) : 0;

            self::render_admin_shell_fragment('categories', array(
                'title' => sprintf(__('Editar categoría: %s', 'terramarket'), $term_name),
                'lead'  => __('La edición visual queda integrada dentro de la misma pantalla para que el nombre, slug y el icono comercial se gestionen con menos fricción.', 'terramarket'),
                'summary_items' => array(
                    array('label' => __('Slug', 'terramarket'), 'value' => $term_slug ?: __('Pendiente', 'terramarket')),
                    array('label' => __('Avisos asociados', 'terramarket'), 'value' => number_format_i18n((int) (($term instanceof \WP_Term) ? $term->count : 0))),
                    array('label' => __('Visual', 'terramarket'), 'value' => $icon_id > 0 ? __('Imagen cargada', 'terramarket') : __('Fallback activo', 'terramarket')),
                    array('label' => __('Frontend', 'terramarket'), 'value' => ! is_wp_error($term_link) && $term_link ? __('Disponible', 'terramarket') : __('Sin URL', 'terramarket'), 'title' => is_wp_error($term_link) ? '' : (string) $term_link),
                ),
                'actions' => self::get_admin_actions_for_page('categories'),
            ));
            ?>
            <section class="tm-page-section tm-category-screen-guide tm-category-screen-guide--edit">
                <div class="tm-page-section__header">
                    <div>
                        <h2><?php esc_html_e('Qué revisar aquí', 'terramarket'); ?></h2>
                        <p><?php esc_html_e('Primero valida nombre y slug. Luego usa la tarjeta de visual para subir o reemplazar la mini-ilustración de esta categoría.', 'terramarket'); ?></p>
                    </div>
                    <div class="tm-admin-actions">
                        <a class="button tm-admin-action button-secondary" href="<?php echo esc_url(self::get_categories_admin_url()); ?>"><?php esc_html_e('Volver a categorías', 'terramarket'); ?></a>
                        <?php if (! is_wp_error($term_link) && $term_link) : ?>
                            <a class="button tm-admin-action button-primary" href="<?php echo esc_url((string) $term_link); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Ver categoría', 'terramarket'); ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
            <?php
        }

        echo '</div></div>';
    }

    public static function filter_listing_row_actions(array $actions, \WP_Post $post): array
    {
        if ('tm_listing' !== $post->post_type) {
            return $actions;
        }

        $listing_url = get_permalink($post);
        if ($listing_url && 'publish' === $post->post_status) {
            $actions['tm_view_listing'] = '<a href="' . esc_url($listing_url) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Ver aviso', 'terramarket') . '</a>';
        }

        return $actions;
    }

    public static function filter_listing_title_placeholder(string $title, \WP_Post $post): string
    {
        if ('tm_listing' !== $post->post_type) {
            return $title;
        }

        return __('Ej.: Tractor Lovol TX1104 G3 con pala', 'terramarket');
    }

    public static function render_dashboard_page(): void
    {
        if (! self::current_user_can_access_page('dashboard')) {
            return;
        }

        global $wpdb;

        $general = get_option('tm_settings_general', array());
        $marketplace = get_option('tm_settings_marketplace', array());
        $market_slug = (string) ($general['market_slug'] ?? 'terramarket');
        $notify_email = (string) ($general['notify_email'] ?? get_option('admin_email'));
        $frontend_url = trailingslashit(home_url('/' . ltrim($market_slug, '/')));
        $commissions_table = self::get_commissions_table_name();

        $stats = array(
            'listings'          => (int) wp_count_posts('tm_listing')->publish,
            'featured'          => (int) $wpdb->get_var("SELECT COUNT(1) FROM {$wpdb->postmeta} WHERE meta_key='tm_featured' AND meta_value='1'"),
            'leads'             => self::table_exists($wpdb->prefix . 'tm_leads') ? (int) $wpdb->get_var("SELECT COUNT(1) FROM {$wpdb->prefix}tm_leads") : 0,
            'alerts'            => self::table_exists($wpdb->prefix . 'tm_alerts') ? (int) $wpdb->get_var("SELECT COUNT(1) FROM {$wpdb->prefix}tm_alerts") : 0,
            'commissions'       => $commissions_table ? (int) $wpdb->get_var("SELECT COUNT(1) FROM {$commissions_table}") : 0,
            'commission_total'  => $commissions_table ? (float) $wpdb->get_var("SELECT COALESCE(SUM(commission_amount), 0) FROM {$commissions_table}") : 0,
        );

        $recent_listings = get_posts(array(
            'post_type'      => 'tm_listing',
            'post_status'    => array('publish', 'draft', 'pending'),
            'posts_per_page' => 5,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ));

        $recent_leads = array();
        $leads_table = $wpdb->prefix . 'tm_leads';
        if (self::table_exists($leads_table)) {
            $lead_columns = self::get_leads_column_map();
            $recent_leads = $wpdb->get_results(
                "SELECT l.id, l.listing_id, l.created_at, l.status, {$lead_columns['name']} AS contact_name, {$lead_columns['email']} AS contact_email
                FROM {$leads_table} l
                ORDER BY l.created_at DESC
                LIMIT 5"
            );
        }

        $recent_commissions = array();
        if ($commissions_table) {
            $recent_commissions = $wpdb->get_results(
                "SELECT c.listing_id, c.created_at, c.declared_sale_amount, c.commission_amount, c.settlement_status, p.post_title
                FROM {$commissions_table} c
                LEFT JOIN {$wpdb->posts} p ON p.ID = c.listing_id
                ORDER BY c.created_at DESC
                LIMIT 5"
            );
        }

        self::render_admin_shell_start('dashboard', array(
            'title' => __('Dashboard Terramarket', 'terramarket'),
            'lead'  => __('Vista general más operativa y comercial del plugin para entrar al admin y entender rápido qué revisar, qué mover y dónde actuar.', 'terramarket'),
            'summary_items' => array(
                array('label' => __('Avisos', 'terramarket'), 'value' => number_format_i18n($stats['listings'])),
                array('label' => __('Destacados', 'terramarket'), 'value' => number_format_i18n($stats['featured'])),
                array('label' => __('Leads', 'terramarket'), 'value' => number_format_i18n($stats['leads'])),
                array('label' => __('Comisión acumulada', 'terramarket'), 'value' => TM_Helpers::format_price_clp((int) round($stats['commission_total']))),
                array('label' => __('Versión', 'terramarket'), 'value' => TM_VERSION),
            ),
        ));
        ?>
        <div class="tm-admin-panels tm-admin-panels--top">
            <section class="tm-page-section">
                <div class="tm-page-section__header">
                    <div>
                        <h2><?php esc_html_e('Acciones rápidas', 'terramarket'); ?></h2>
                        <p><?php esc_html_e('Atajos para las tareas más frecuentes del flujo comercial y operativo.', 'terramarket'); ?></p>
                    </div>
                </div>
                <div class="tm-quick-actions-grid">
                    <a class="tm-quick-action" href="<?php echo esc_url(admin_url('post-new.php?post_type=tm_listing')); ?>">
                        <strong><?php esc_html_e('Crear aviso', 'terramarket'); ?></strong>
                        <span><?php esc_html_e('Publicar un nuevo aviso desde el admin.', 'terramarket'); ?></span>
                    </a>
                    <a class="tm-quick-action" href="<?php echo esc_url(admin_url('admin.php?page=tm-leads')); ?>">
                        <strong><?php esc_html_e('Revisar leads', 'terramarket'); ?></strong>
                        <span><?php esc_html_e('Entrar al seguimiento de contactos recibidos.', 'terramarket'); ?></span>
                    </a>
                    <a class="tm-quick-action" href="<?php echo esc_url(admin_url('admin.php?page=tm-vitrine')); ?>">
                        <strong><?php esc_html_e('Editar vitrina', 'terramarket'); ?></strong>
                        <span><?php esc_html_e('Mover destacados y priorizar avisos visibles.', 'terramarket'); ?></span>
                    </a>
                    <a class="tm-quick-action" href="<?php echo esc_url(admin_url('admin.php?page=tm-settings')); ?>">
                        <strong><?php esc_html_e('Abrir ajustes', 'terramarket'); ?></strong>
                        <span><?php esc_html_e('Modificar branding, slug y reglas base del marketplace.', 'terramarket'); ?></span>
                    </a>
                </div>
            </section>

            <section class="tm-page-section">
                <div class="tm-page-section__header">
                    <div>
                        <h2><?php esc_html_e('Estado del sistema', 'terramarket'); ?></h2>
                        <p><?php esc_html_e('Datos clave para validar configuración y operación sin entrar a otras pantallas.', 'terramarket'); ?></p>
                    </div>
                </div>
                <dl class="tm-data-list">
                    <div class="tm-data-list__row">
                        <dt><?php esc_html_e('Marketplace público', 'terramarket'); ?></dt>
                        <dd>
                            <a href="<?php echo esc_url($frontend_url); ?>" target="_blank" rel="noreferrer noopener"><?php echo esc_html($frontend_url); ?></a>
                            <button type="button" class="button-link tm-copy-button" data-copy-text="<?php echo esc_attr($frontend_url); ?>"><?php esc_html_e('Copiar', 'terramarket'); ?></button>
                        </dd>
                    </div>
                    <div class="tm-data-list__row">
                        <dt><?php esc_html_e('Correo de leads', 'terramarket'); ?></dt>
                        <dd><?php echo esc_html($notify_email ?: __('Sin definir', 'terramarket')); ?></dd>
                    </div>
                    <div class="tm-data-list__row">
                        <dt><?php esc_html_e('Watermark', 'terramarket'); ?></dt>
                        <dd><?php echo self::render_ui_badge(! empty($marketplace['enable_watermark']) ? __('Activo', 'terramarket') : __('Inactivo', 'terramarket')); ?></dd>
                    </div>
                    <div class="tm-data-list__row">
                        <dt><?php esc_html_e('Versión plugin', 'terramarket'); ?></dt>
                        <dd><?php echo esc_html(TM_VERSION); ?></dd>
                    </div>
                </dl>
            </section>
        </div>

        <div class="tm-admin-panels">
            <section class="tm-page-section">
                <div class="tm-page-section__header">
                    <div>
                        <h2><?php esc_html_e('Actividad reciente', 'terramarket'); ?></h2>
                        <p><?php esc_html_e('Movimientos recientes de leads y comisiones para detectar trabajo pendiente sin revisar tablas completas.', 'terramarket'); ?></p>
                    </div>
                </div>
                <div class="tm-split-stack">
                    <div>
                        <h3 class="tm-section-mini-title"><?php esc_html_e('Leads', 'terramarket'); ?></h3>
                        <?php if ($recent_leads) : ?>
                            <ul class="tm-activity-list">
                                <?php foreach ($recent_leads as $item) : ?>
                                    <li>
                                        <div>
                                            <strong><?php echo esc_html($item->contact_name ?: __('Sin nombre', 'terramarket')); ?></strong>
                                            <span><?php echo esc_html(get_the_title((int) $item->listing_id) ?: ('#' . (int) $item->listing_id)); ?></span>
                                        </div>
                                        <div>
                                            <?php echo self::render_ui_badge((string) $item->status); ?>
                                            <time datetime="<?php echo esc_attr((string) $item->created_at); ?>"><?php echo esc_html(date_i18n('d/m/Y H:i', strtotime((string) $item->created_at))); ?></time>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else : ?>
                            <p class="tm-muted-note"><?php esc_html_e('Todavía no hay leads recientes para mostrar.', 'terramarket'); ?></p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h3 class="tm-section-mini-title"><?php esc_html_e('Comisiones', 'terramarket'); ?></h3>
                        <?php if ($recent_commissions) : ?>
                            <ul class="tm-activity-list">
                                <?php foreach ($recent_commissions as $item) : ?>
                                    <li>
                                        <div>
                                            <strong><?php echo esc_html((string) ($item->post_title ?: ('#' . (int) $item->listing_id))); ?></strong>
                                            <span><?php echo esc_html(TM_Helpers::format_price_clp((int) round((float) $item->commission_amount))); ?></span>
                                        </div>
                                        <div>
                                            <?php echo self::render_ui_badge(ucfirst((string) $item->settlement_status)); ?>
                                            <time datetime="<?php echo esc_attr((string) $item->created_at); ?>"><?php echo esc_html(date_i18n('d/m/Y H:i', strtotime((string) $item->created_at))); ?></time>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else : ?>
                            <p class="tm-muted-note"><?php esc_html_e('Aún no hay comisiones recientes registradas.', 'terramarket'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <section class="tm-page-section">
                <div class="tm-page-section__header">
                    <div>
                        <h2><?php esc_html_e('Avisos recientes', 'terramarket'); ?></h2>
                        <p><?php esc_html_e('Últimas publicaciones para entrar rápido a edición o validación.', 'terramarket'); ?></p>
                    </div>
                </div>
                <?php if ($recent_listings) : ?>
                    <ul class="tm-activity-list tm-activity-list--roomy">
                        <?php foreach ($recent_listings as $listing) : ?>
                            <?php $status = TM_Helpers::get_effective_listing_status($listing->ID); ?>
                            <li>
                                <div>
                                    <strong><?php echo esc_html($listing->post_title ?: ('#' . (int) $listing->ID)); ?></strong>
                                    <span><?php echo esc_html(get_the_author_meta('display_name', (int) $listing->post_author) ?: __('Sin vendedor', 'terramarket')); ?></span>
                                </div>
                                <div>
                                    <?php echo self::render_ui_badge(TM_Helpers::get_listing_status_label($status)); ?>
                                    <a href="<?php echo esc_url(get_edit_post_link($listing->ID)); ?>"><?php esc_html_e('Editar', 'terramarket'); ?></a>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <div class="tm-empty-state tm-empty-state--compact">
                        <h3><?php esc_html_e('Sin avisos todavía', 'terramarket'); ?></h3>
                        <p><?php esc_html_e('Publica el primer aviso para que el dashboard empiece a mostrar actividad comercial.', 'terramarket'); ?></p>
                    </div>
                <?php endif; ?>
            </section>
        </div>
        <?php
        self::render_admin_shell_end();
    }

    public static function render_leads_page(): void
    {
        if (! self::current_user_can_access_page('leads')) {
            return;
        }

        global $wpdb;

        $table = $wpdb->prefix . 'tm_leads';
        $has_table = self::table_exists($table);
        $columns = $has_table ? self::get_leads_column_map() : array();

        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $status_filter = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : '';
        $when_filter = isset($_GET['when']) ? sanitize_key(wp_unslash($_GET['when'])) : 'all';
        if (! in_array($when_filter, array('all', '7d', '30d'), true)) {
            $when_filter = 'all';
        }

        $where = array('1=1');
        $params = array();

        if ($has_table && '' !== $status_filter) {
            $where[] = 'l.status = %s';
            $params[] = $status_filter;
        }

        if ($has_table && 'all' !== $when_filter) {
            $days = '7d' === $when_filter ? 7 : 30;
            $where[] = 'l.created_at >= %s';
            $params[] = date('Y-m-d H:i:s', current_time('timestamp') - (DAY_IN_SECONDS * $days));
        }

        if ($has_table && '' !== $search) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $search_parts = array(
                "{$columns['name']} LIKE %s",
                "{$columns['email']} LIKE %s",
                "{$columns['phone']} LIKE %s",
                'p.post_title LIKE %s',
            );
            if ("''" !== $columns['message']) {
                $search_parts[] = "{$columns['message']} LIKE %s";
            }
            $where[] = '(' . implode(' OR ', $search_parts) . ')';
            foreach ($search_parts as $ignored) {
                $params[] = $like;
            }
        }

        $where_sql = implode(' AND ', $where);

        $rows = array();
        $available_statuses = array();
        $total_leads = 0;
        $filtered_total = 0;
        $recent_leads = 0;
        $with_email = 0;
        $with_phone = 0;
        $with_message = 0;
        $new_leads = 0;

        if ($has_table) {
            $select_sql = "SELECT l.id, l.listing_id, l.created_at, l.status, {$columns['name']} AS contact_name, {$columns['email']} AS contact_email, {$columns['phone']} AS contact_phone, {$columns['message']} AS lead_message, {$columns['type']} AS lead_type, p.post_title
                FROM {$table} l
                LEFT JOIN {$wpdb->posts} p ON p.ID = l.listing_id
                WHERE {$where_sql}
                ORDER BY l.created_at DESC
                LIMIT 200";
            $rows = $params ? $wpdb->get_results($wpdb->prepare($select_sql, $params)) : $wpdb->get_results($select_sql);

            $total_leads = (int) $wpdb->get_var("SELECT COUNT(1) FROM {$table}");
            $filtered_total_sql = "SELECT COUNT(1) FROM {$table} l LEFT JOIN {$wpdb->posts} p ON p.ID = l.listing_id WHERE {$where_sql}";
            $filtered_total = (int) ($params ? $wpdb->get_var($wpdb->prepare($filtered_total_sql, $params)) : $wpdb->get_var($filtered_total_sql));
            $recent_leads = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$table} WHERE created_at >= %s", date('Y-m-d H:i:s', current_time('timestamp') - WEEK_IN_SECONDS)));
            $with_email = (int) $wpdb->get_var("SELECT COUNT(1) FROM {$table} WHERE {$columns['email']} <> ''");
            $with_phone = (int) $wpdb->get_var("SELECT COUNT(1) FROM {$table} WHERE {$columns['phone']} <> ''");
            $with_message = "''" !== $columns['message'] ? (int) $wpdb->get_var("SELECT COUNT(1) FROM {$table} WHERE {$columns['message']} <> ''") : 0;
            $new_leads = (int) $wpdb->get_var("SELECT COUNT(1) FROM {$table} WHERE status = 'new'");
            $available_statuses = $wpdb->get_col("SELECT DISTINCT status FROM {$table} WHERE status <> '' ORDER BY status ASC");
        }

        self::render_admin_shell_start('leads', array(
            'title' => __('Leads recibidos', 'terramarket'),
            'lead'  => __('Vista operativa más clara para buscar contactos, entender volumen y revisar seguimiento comercial con menos ruido visual.', 'terramarket'),
            'summary_items' => array(
                array('label' => __('Total', 'terramarket'), 'value' => number_format_i18n($total_leads)),
                array('label' => __('Filtrados', 'terramarket'), 'value' => number_format_i18n($filtered_total)),
                array('label' => __('Últimos 7 días', 'terramarket'), 'value' => number_format_i18n($recent_leads)),
                array('label' => __('Nuevos', 'terramarket'), 'value' => number_format_i18n($new_leads)),
                array('label' => __('Con email', 'terramarket'), 'value' => number_format_i18n($with_email)),
                array('label' => __('Con teléfono', 'terramarket'), 'value' => number_format_i18n($with_phone)),
            ),
            'actions' => self::get_admin_actions_for_page('leads'),
        ));
        ?>
        <section class="tm-page-section tm-page-section--emphasis">
            <div class="tm-page-section__header">
                <div>
                    <h2><?php esc_html_e('Filtro y seguimiento', 'terramarket'); ?></h2>
                    <p><?php esc_html_e('Busca por nombre, correo, teléfono, mensaje o aviso. Mantiene la misma data; cambia la presentación y la navegación.', 'terramarket'); ?></p>
                </div>
            </div>
            <form method="get" class="tm-admin-toolbar tm-admin-toolbar--filters">
                <input type="hidden" name="page" value="tm-leads">
                <div class="tm-toolbar-field tm-toolbar-field--search">
                    <label class="screen-reader-text" for="tm-leads-search"><?php esc_html_e('Buscar lead', 'terramarket'); ?></label>
                    <input type="search" id="tm-leads-search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Buscar lead, aviso o dato de contacto', 'terramarket'); ?>">
                </div>
                <div class="tm-toolbar-field">
                    <label for="tm-leads-status"><?php esc_html_e('Estado', 'terramarket'); ?></label>
                    <select id="tm-leads-status" name="status">
                        <option value=""><?php esc_html_e('Todos', 'terramarket'); ?></option>
                        <?php foreach ($available_statuses as $status) : ?>
                            <option value="<?php echo esc_attr((string) $status); ?>" <?php selected($status_filter, (string) $status); ?>><?php echo esc_html(ucfirst((string) $status)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="tm-toolbar-field">
                    <label for="tm-leads-when"><?php esc_html_e('Periodo', 'terramarket'); ?></label>
                    <select id="tm-leads-when" name="when">
                        <option value="all" <?php selected($when_filter, 'all'); ?>><?php esc_html_e('Todo', 'terramarket'); ?></option>
                        <option value="7d" <?php selected($when_filter, '7d'); ?>><?php esc_html_e('Últimos 7 días', 'terramarket'); ?></option>
                        <option value="30d" <?php selected($when_filter, '30d'); ?>><?php esc_html_e('Últimos 30 días', 'terramarket'); ?></option>
                    </select>
                </div>
                <div class="tm-toolbar-actions">
                    <button type="submit" class="button button-primary"><?php esc_html_e('Aplicar filtros', 'terramarket'); ?></button>
                    <a class="button button-secondary" href="<?php echo esc_url(admin_url('admin.php?page=tm-leads')); ?>"><?php esc_html_e('Limpiar', 'terramarket'); ?></a>
                </div>
            </form>
            <div class="tm-cards tm-cards--compact">
                <div class="tm-card tm-card--metric tm-card--soft">
                    <span class="tm-card__eyebrow"><?php esc_html_e('Resultados visibles', 'terramarket'); ?></span>
                    <h3><?php echo esc_html(number_format_i18n($filtered_total)); ?></h3>
                    <p><?php esc_html_e('Cantidad según filtros activos en esta vista.', 'terramarket'); ?></p>
                </div>
                <div class="tm-card tm-card--metric tm-card--soft">
                    <span class="tm-card__eyebrow"><?php esc_html_e('Leads nuevos', 'terramarket'); ?></span>
                    <h3><?php echo esc_html(number_format_i18n($new_leads)); ?></h3>
                    <p><?php esc_html_e('Contactos marcados con estado new.', 'terramarket'); ?></p>
                </div>
                <div class="tm-card tm-card--metric tm-card--soft">
                    <span class="tm-card__eyebrow"><?php esc_html_e('Con mensaje', 'terramarket'); ?></span>
                    <h3><?php echo esc_html(number_format_i18n($with_message)); ?></h3>
                    <p><?php esc_html_e('Leads con texto útil para contexto comercial.', 'terramarket'); ?></p>
                </div>
            </div>
        </section>

        <section class="tm-page-section tm-table-wrap">
            <div class="tm-page-section__header">
                <div>
                    <h2><?php esc_html_e('Bandeja de leads', 'terramarket'); ?></h2>
                    <p><?php echo esc_html(sprintf(__('Mostrando hasta %d registros recientes con filtros visuales y mejor jerarquía de lectura.', 'terramarket'), 200)); ?></p>
                </div>
                <div class="tm-results-note"><?php echo esc_html(sprintf(__('Resultados: %s', 'terramarket'), number_format_i18n($filtered_total))); ?></div>
            </div>
            <?php if (! $has_table) : ?>
                <div class="tm-empty-state">
                    <h3><?php esc_html_e('La tabla de leads aún no existe', 'terramarket'); ?></h3>
                    <p><?php esc_html_e('La vista queda lista, pero esta instalación todavía no tiene la tabla operativa de leads disponible.', 'terramarket'); ?></p>
                    <div class="tm-empty-state__actions">
                        <a class="button button-secondary" href="<?php echo esc_url(admin_url('admin.php?page=tm-tools')); ?>"><?php esc_html_e('Revisar herramientas', 'terramarket'); ?></a>
                        <a class="button button-primary" href="<?php echo esc_url(admin_url('edit.php?post_type=tm_listing')); ?>"><?php esc_html_e('Ver avisos', 'terramarket'); ?></a>
                    </div>
                </div>
            <?php elseif ($rows) : ?>
                <table class="widefat striped tm-admin-table tm-admin-table--enhanced">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Contacto', 'terramarket'); ?></th>
                            <th><?php esc_html_e('Aviso', 'terramarket'); ?></th>
                            <th><?php esc_html_e('Fecha', 'terramarket'); ?></th>
                            <th><?php esc_html_e('Estado', 'terramarket'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $row) : ?>
                        <?php $created_ts = strtotime((string) $row->created_at); ?>
                        <tr>
                            <td>
                                <div class="tm-table-stack">
                                    <strong><?php echo esc_html($row->contact_name ?: __('Sin nombre', 'terramarket')); ?></strong>
                                    <?php if (! empty($row->contact_email)) : ?>
                                        <div class="tm-inline-meta">
                                            <span><?php echo esc_html((string) $row->contact_email); ?></span>
                                            <button type="button" class="button-link tm-copy-button" data-copy-text="<?php echo esc_attr((string) $row->contact_email); ?>"><?php esc_html_e('Copiar', 'terramarket'); ?></button>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (! empty($row->contact_phone)) : ?>
                                        <div class="tm-inline-meta">
                                            <span><?php echo esc_html((string) $row->contact_phone); ?></span>
                                            <button type="button" class="button-link tm-copy-button" data-copy-text="<?php echo esc_attr((string) $row->contact_phone); ?>"><?php esc_html_e('Copiar', 'terramarket'); ?></button>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (! empty($row->lead_message)) : ?>
                                        <p class="tm-table-excerpt"><?php echo esc_html(wp_trim_words((string) $row->lead_message, 16, '…')); ?></p>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="tm-table-stack">
                                    <strong><?php echo esc_html((string) ($row->post_title ?: ('#' . (int) $row->listing_id))); ?></strong>
                                    <div class="tm-inline-meta">
                                        <a href="<?php echo esc_url(get_edit_post_link((int) $row->listing_id)); ?>"><?php esc_html_e('Editar aviso', 'terramarket'); ?></a>
                                        <span>·</span>
                                        <a href="<?php echo esc_url(get_permalink((int) $row->listing_id)); ?>" target="_blank" rel="noreferrer noopener"><?php esc_html_e('Ver publicación', 'terramarket'); ?></a>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="tm-table-stack">
                                    <strong><?php echo esc_html(date_i18n('d/m/Y H:i', $created_ts ?: current_time('timestamp'))); ?></strong>
                                    <span><?php echo esc_html($created_ts ? sprintf(__('%s atrás', 'terramarket'), human_time_diff($created_ts, current_time('timestamp'))) : __('Sin fecha', 'terramarket')); ?></span>
                                </div>
                            </td>
                            <td>
                                <div class="tm-table-stack tm-table-stack--badges">
                                    <?php echo self::render_ui_badge((string) $row->status); ?>
                                    <?php if (! empty($row->lead_type)) : ?>
                                        <?php echo self::render_ui_badge((string) $row->lead_type, 'neutral'); ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <div class="tm-empty-state">
                    <?php if ('' === $search && '' === $status_filter && 'all' === $when_filter) : ?>
                        <h3><?php esc_html_e('Aún no hay contactos recibidos', 'terramarket'); ?></h3>
                        <p><?php esc_html_e('Cuando un comprador use el formulario del marketplace, el lead aparecerá aquí con su estado comercial.', 'terramarket'); ?></p>
                        <div class="tm-empty-state__actions">
                            <a class="button button-secondary" href="<?php echo esc_url(trailingslashit(home_url('/' . ltrim((string) (get_option('tm_settings_general', array())['market_slug'] ?? 'terramarket'), '/')))); ?>" target="_blank" rel="noreferrer noopener"><?php esc_html_e('Revisar formulario público', 'terramarket'); ?></a>
                            <a class="button button-primary" href="<?php echo esc_url(admin_url('edit.php?post_type=tm_listing')); ?>"><?php esc_html_e('Ver avisos', 'terramarket'); ?></a>
                        </div>
                    <?php else : ?>
                        <h3><?php esc_html_e('No hay leads para este filtro', 'terramarket'); ?></h3>
                        <p><?php esc_html_e('Prueba limpiar filtros o espera nuevos contactos desde el formulario del marketplace.', 'terramarket'); ?></p>
                        <div class="tm-empty-state__actions">
                            <a class="button button-secondary" href="<?php echo esc_url(admin_url('admin.php?page=tm-leads')); ?>"><?php esc_html_e('Limpiar filtros', 'terramarket'); ?></a>
                            <a class="button button-primary" href="<?php echo esc_url(admin_url('edit.php?post_type=tm_listing')); ?>"><?php esc_html_e('Ver avisos', 'terramarket'); ?></a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php
        self::render_admin_shell_end();
    }

    public static function render_vitrine_page(): void
    {
        if (! self::current_user_can_access_page('vitrine')) {
            return;
        }

        global $wpdb;

        $items = array_values(array_filter(array_map('intval', $wpdb->get_col("SELECT listing_id FROM {$wpdb->prefix}tm_vitrine_items ORDER BY sort_order ASC, id ASC"))));
        $selected_lookup = array_fill_keys($items, true);
        $notice = isset($_GET['tm_notice']) ? sanitize_text_field(wp_unslash($_GET['tm_notice'])) : '';
        $query = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $args = array(
            'post_type'      => 'tm_listing',
            'posts_per_page' => 50,
            'post_status'    => array('publish', 'draft', 'pending'),
            's'              => $query,
        );
        $results = get_posts($args);
        $visible_ids = array_map('intval', wp_list_pluck($results, 'ID'));
        $hidden_selected_ids = array_values(array_diff($items, $visible_ids));
        $selected_posts = $items ? get_posts(array(
            'post_type'      => 'tm_listing',
            'posts_per_page' => count($items),
            'post_status'    => array('publish', 'draft', 'pending'),
            'post__in'       => $items,
            'orderby'        => 'post__in',
        )) : array();
        $count_posts = wp_count_posts('tm_listing');
        $total_listings = (int) ($count_posts->publish ?? 0) + (int) ($count_posts->draft ?? 0) + (int) ($count_posts->pending ?? 0);

        self::render_admin_shell_start('vitrine', array(
            'title' => __('Vitrina destacada', 'terramarket'),
            'lead'  => __('Selecciona avisos destacados con una vista más visual, preservando destacados ya guardados incluso cuando estés filtrando resultados.', 'terramarket'),
            'summary_items' => array(
                array('label' => __('Destacados activos', 'terramarket'), 'value' => number_format_i18n(count($items))),
                array('label' => __('Resultados visibles', 'terramarket'), 'value' => number_format_i18n(count($results))),
                array('label' => __('Preservados fuera del filtro', 'terramarket'), 'value' => number_format_i18n(count($hidden_selected_ids))),
                array('label' => __('Avisos totales', 'terramarket'), 'value' => number_format_i18n($total_listings)),
                array('label' => __('Búsqueda', 'terramarket'), 'value' => '' !== $query ? $query : __('Sin filtro', 'terramarket')),
            ),
        ));
        ?>
        <?php if ($notice) : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div><?php endif; ?>

        <section class="tm-page-section tm-page-section--emphasis">
            <div class="tm-page-section__header">
                <div>
                    <h2><?php esc_html_e('Selección visual de vitrina', 'terramarket'); ?></h2>
                    <p><?php esc_html_e('Marca avisos desde una grilla visual. Los destacados ya guardados y no visibles por el filtro actual se conservan al guardar.', 'terramarket'); ?></p>
                </div>
            </div>

            <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="tm-admin-toolbar tm-admin-toolbar--filters">
                <input type="hidden" name="page" value="tm-vitrine">
                <div class="tm-toolbar-field tm-toolbar-field--search">
                    <label for="tm-vitrine-search"><?php esc_html_e('Buscar aviso', 'terramarket'); ?></label>
                    <input type="search" id="tm-vitrine-search" name="s" value="<?php echo esc_attr($query); ?>" placeholder="<?php esc_attr_e('Título, palabra clave o referencia...', 'terramarket'); ?>">
                </div>
                <div class="tm-toolbar-actions">
                    <button type="submit" class="button button-primary"><?php esc_html_e('Buscar', 'terramarket'); ?></button>
                    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=tm-vitrine')); ?>"><?php esc_html_e('Limpiar', 'terramarket'); ?></a>
                    <span class="tm-results-note" data-vitrine-count><?php echo esc_html(sprintf(_n('%s seleccionado', '%s seleccionados', count($items), 'terramarket'), number_format_i18n(count($items)))); ?></span>
                </div>
            </form>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="tm-vitrine-form">
                <?php wp_nonce_field('tm_save_vitrine_action', 'tm_save_vitrine_nonce'); ?>
                <input type="hidden" name="action" value="tm_save_vitrine">

                <?php foreach ($hidden_selected_ids as $hidden_listing_id) : ?>
                    <input type="hidden" name="tm_vitrine_items[]" value="<?php echo esc_attr((string) $hidden_listing_id); ?>">
                <?php endforeach; ?>

                <?php if ($hidden_selected_ids) : ?>
                    <div class="tm-inline-notice">
                        <p><?php echo esc_html(sprintf(_n('%s destacado guardado no está visible con el filtro actual y se conservará al guardar.', '%s destacados guardados no están visibles con el filtro actual y se conservarán al guardar.', count($hidden_selected_ids), 'terramarket'), number_format_i18n(count($hidden_selected_ids)))); ?></p>
                    </div>
                <?php endif; ?>

                <div class="tm-vitrine-form__topbar">
                    <div>
                        <h3 class="tm-section-mini-title"><?php esc_html_e('Destacados actuales', 'terramarket'); ?></h3>
                        <p class="tm-muted-note"><?php esc_html_e('Referencia rápida de los avisos ya presentes en vitrina.', 'terramarket'); ?></p>
                    </div>
                    <div class="tm-toolbar-actions">
                        <button type="button" class="button" data-vitrine-select="visible"><?php esc_html_e('Seleccionar visibles', 'terramarket'); ?></button>
                        <button type="button" class="button" data-vitrine-select="clear"><?php esc_html_e('Limpiar visibles', 'terramarket'); ?></button>
                    </div>
                </div>

                <?php if ($selected_posts) : ?>
                    <div class="tm-vitrine-selected-list">
                        <?php foreach ($selected_posts as $selected_post) : ?>
                            <?php $selected_status = TM_Helpers::get_effective_listing_status($selected_post->ID); ?>
                            <div class="tm-vitrine-selected-pill">
                                <strong><?php echo esc_html(get_the_title($selected_post)); ?></strong>
                                <span><?php echo self::render_ui_badge(TM_Helpers::get_listing_status_label($selected_status)); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <div class="tm-empty-state tm-empty-state--compact">
                        <h3><?php esc_html_e('Aún no hay avisos destacados', 'terramarket'); ?></h3>
                        <p><?php esc_html_e('Selecciona avisos desde la grilla inferior para construir la vitrina principal.', 'terramarket'); ?></p>
                    </div>
                <?php endif; ?>

                <?php if ($results) : ?>
                    <div class="tm-vitrine-grid">
                        <?php foreach ($results as $post) : ?>
                            <?php
                            $listing_id = (int) $post->ID;
                            $price = (int) get_post_meta($listing_id, 'tm_price_clp', true);
                            $status_key = TM_Helpers::get_effective_listing_status($listing_id);
                            $status_label = TM_Helpers::get_listing_status_label($status_key);
                            $thumbnail_url = get_the_post_thumbnail_url($listing_id, 'medium');
                            $category_names = wp_get_post_terms($listing_id, 'tm_category', array('fields' => 'names'));
                            $region_names = wp_get_post_terms($listing_id, 'tm_region', array('fields' => 'names'));
                            $post_status_object = get_post_status_object($post->post_status);
                            $post_status_label = $post_status_object && isset($post_status_object->labels->singular_name) ? $post_status_object->labels->singular_name : ucfirst((string) $post->post_status);
                            $is_selected = isset($selected_lookup[$listing_id]);
                            ?>
                            <article class="tm-vitrine-card <?php echo $is_selected ? 'is-selected' : ''; ?>" data-vitrine-card>
                                <div class="tm-vitrine-card__media <?php echo $thumbnail_url ? '' : 'is-empty'; ?>">
                                    <?php if ($thumbnail_url) : ?>
                                        <img src="<?php echo esc_url($thumbnail_url); ?>" alt="" loading="lazy">
                                    <?php else : ?>
                                        <span><?php esc_html_e('Sin imagen', 'terramarket'); ?></span>
                                    <?php endif; ?>
                                    <div class="tm-vitrine-card__overlay">
                                        <?php echo self::render_ui_badge($status_label); ?>
                                        <?php if ('publish' !== $post->post_status) : ?>
                                            <?php echo self::render_ui_badge($post_status_label, 'warning'); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="tm-vitrine-card__body">
                                    <div class="tm-vitrine-card__select">
                                        <label class="tm-vitrine-toggle">
                                            <input type="checkbox" name="tm_vitrine_items[]" value="<?php echo esc_attr((string) $listing_id); ?>" <?php checked($is_selected); ?> data-vitrine-checkbox>
                                            <span><?php esc_html_e('Destacar en vitrina', 'terramarket'); ?></span>
                                        </label>
                                        <?php if ($is_selected) : ?>
                                            <span class="tm-ui-badge tm-ui-badge--success"><?php esc_html_e('Activo', 'terramarket'); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="tm-table-stack">
                                        <strong><?php echo esc_html(get_the_title($post)); ?></strong>
                                        <div class="tm-inline-meta">
                                            <span><?php echo esc_html(TM_Helpers::format_price_clp($price)); ?></span>
                                            <?php if (! empty($category_names[0])) : ?><span>• <?php echo esc_html($category_names[0]); ?></span><?php endif; ?>
                                            <?php if (! empty($region_names[0])) : ?><span>• <?php echo esc_html($region_names[0]); ?></span><?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="tm-vitrine-card__actions">
                                        <a class="button button-small" href="<?php echo esc_url(get_edit_post_link($listing_id, '')); ?>"><?php esc_html_e('Editar', 'terramarket'); ?></a>
                                        <a class="button button-small" href="<?php echo esc_url(get_permalink($listing_id)); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Ver aviso', 'terramarket'); ?></a>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <div class="tm-empty-state">
                        <?php if ('' === $query) : ?>
                            <h3><?php esc_html_e('Aún no hay avisos publicados para destacar', 'terramarket'); ?></h3>
                            <p><?php esc_html_e('Crea o publica avisos primero y luego podrás seleccionarlos desde esta vitrina.', 'terramarket'); ?></p>
                        <?php else : ?>
                            <h3><?php esc_html_e('No hay avisos para mostrar con el filtro actual', 'terramarket'); ?></h3>
                            <p><?php esc_html_e('Prueba limpiar la búsqueda o crea nuevos avisos para empezar a poblar la vitrina.', 'terramarket'); ?></p>
                        <?php endif; ?>
                        <div class="tm-empty-state__actions">
                            <?php if ('' !== $query) : ?>
                                <a class="button button-secondary" href="<?php echo esc_url(admin_url('admin.php?page=tm-vitrine')); ?>"><?php esc_html_e('Limpiar filtros', 'terramarket'); ?></a>
                            <?php endif; ?>
                            <a class="button button-primary" href="<?php echo esc_url(admin_url('post-new.php?post_type=tm_listing')); ?>"><?php esc_html_e('Crear aviso', 'terramarket'); ?></a>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="tm-form-actions tm-form-actions--split">
                    <p class="tm-muted-note"><?php esc_html_e('El orden de guardado sigue el orden visible de esta pantalla y conserva seleccionados ocultos por filtro.', 'terramarket'); ?></p>
                    <?php submit_button(__('Guardar vitrina', 'terramarket'), 'primary', 'submit', false); ?>
                </div>
            </form>
        </section>
        <?php
        self::render_admin_shell_end();
    }

    public static function render_commissions_page(): void
    {
        if (! self::current_user_can_access_page('commissions')) {
            return;
        }

        global $wpdb;

        $table = self::get_commissions_table_name();
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $status_filter = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : '';
        $when_filter = isset($_GET['when']) ? sanitize_key(wp_unslash($_GET['when'])) : 'all';
        if (! in_array($when_filter, array('all', '30d', '90d'), true)) {
            $when_filter = 'all';
        }

        $rows = array();
        $statuses = array();
        $total_rows = 0;
        $filtered_total = 0;
        $total_declared = 0.0;
        $total_commission = 0.0;
        $pending_total = 0;
        $settled_total = 0;
        $last_created_at = null;

        if ($table) {
            $where = array('1=1');
            $params = array();

            if ('' !== $status_filter) {
                $where[] = 'c.settlement_status = %s';
                $params[] = $status_filter;
            }

            if ('all' !== $when_filter) {
                $days = '30d' === $when_filter ? 30 : 90;
                $where[] = 'c.created_at >= %s';
                $params[] = date('Y-m-d H:i:s', current_time('timestamp') - (DAY_IN_SECONDS * $days));
            }

            if ('' !== $search) {
                $like = '%' . $wpdb->esc_like($search) . '%';
                $where[] = '(p.post_title LIKE %s OR c.notes LIKE %s)';
                $params[] = $like;
                $params[] = $like;
            }

            $where_sql = implode(' AND ', $where);
            $query_sql = "SELECT c.*, p.post_title, p.post_author
                FROM {$table} c
                LEFT JOIN {$wpdb->posts} p ON p.ID = c.listing_id
                WHERE {$where_sql}
                ORDER BY c.created_at DESC
                LIMIT 200";
            $rows = $params ? $wpdb->get_results($wpdb->prepare($query_sql, $params)) : $wpdb->get_results($query_sql);

            $total_rows = (int) $wpdb->get_var("SELECT COUNT(1) FROM {$table}");
            $filtered_total_sql = "SELECT COUNT(1) FROM {$table} c LEFT JOIN {$wpdb->posts} p ON p.ID = c.listing_id WHERE {$where_sql}";
            $filtered_total = (int) ($params ? $wpdb->get_var($wpdb->prepare($filtered_total_sql, $params)) : $wpdb->get_var($filtered_total_sql));
            $total_declared = (float) $wpdb->get_var("SELECT COALESCE(SUM(declared_sale_amount), 0) FROM {$table}");
            $total_commission = (float) $wpdb->get_var("SELECT COALESCE(SUM(commission_amount), 0) FROM {$table}");
            $pending_total = (int) $wpdb->get_var("SELECT COUNT(1) FROM {$table} WHERE settlement_status = 'pending'");
            $settled_total = (int) $wpdb->get_var("SELECT COUNT(1) FROM {$table} WHERE settlement_status IN ('settled', 'paid')");
            $last_created_at = $wpdb->get_var("SELECT created_at FROM {$table} ORDER BY created_at DESC LIMIT 1");
            $statuses = $wpdb->get_col("SELECT DISTINCT settlement_status FROM {$table} WHERE settlement_status <> '' ORDER BY settlement_status ASC");
        }

        self::render_admin_shell_start('commissions', array(
            'title' => __('Comisiones', 'terramarket'),
            'lead'  => __('Panel más claro para revisar cierres, detectar pendientes y leer montos/comisiones con mejor jerarquía visual.', 'terramarket'),
            'summary_items' => array(
                array('label' => __('Registros', 'terramarket'), 'value' => number_format_i18n($total_rows)),
                array('label' => __('Filtrados', 'terramarket'), 'value' => number_format_i18n($filtered_total)),
                array('label' => __('Monto declarado', 'terramarket'), 'value' => TM_Helpers::format_price_clp((int) round($total_declared))),
                array('label' => __('Comisión total', 'terramarket'), 'value' => TM_Helpers::format_price_clp((int) round($total_commission))),
                array('label' => __('Pendientes', 'terramarket'), 'value' => number_format_i18n($pending_total)),
                array('label' => __('Último cierre', 'terramarket'), 'value' => $last_created_at ? date_i18n('d/m/Y H:i', strtotime((string) $last_created_at)) : __('Sin datos', 'terramarket')),
            ),
        ));
        ?>
        <section class="tm-page-section tm-page-section--emphasis">
            <div class="tm-page-section__header">
                <div>
                    <h2><?php esc_html_e('Filtro y lectura comercial', 'terramarket'); ?></h2>
                    <p><?php esc_html_e('Busca cierres por aviso o notas y concentra los estados clave en una sola banda superior.', 'terramarket'); ?></p>
                </div>
            </div>
            <form method="get" class="tm-admin-toolbar tm-admin-toolbar--filters">
                <input type="hidden" name="page" value="tm-commissions">
                <div class="tm-toolbar-field tm-toolbar-field--search">
                    <label class="screen-reader-text" for="tm-commissions-search"><?php esc_html_e('Buscar comisión', 'terramarket'); ?></label>
                    <input type="search" id="tm-commissions-search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Buscar aviso o nota del cierre', 'terramarket'); ?>">
                </div>
                <div class="tm-toolbar-field">
                    <label for="tm-commissions-status"><?php esc_html_e('Estado', 'terramarket'); ?></label>
                    <select id="tm-commissions-status" name="status">
                        <option value=""><?php esc_html_e('Todos', 'terramarket'); ?></option>
                        <?php foreach ($statuses as $status) : ?>
                            <option value="<?php echo esc_attr((string) $status); ?>" <?php selected($status_filter, (string) $status); ?>><?php echo esc_html(ucfirst((string) $status)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="tm-toolbar-field">
                    <label for="tm-commissions-when"><?php esc_html_e('Periodo', 'terramarket'); ?></label>
                    <select id="tm-commissions-when" name="when">
                        <option value="all" <?php selected($when_filter, 'all'); ?>><?php esc_html_e('Todo', 'terramarket'); ?></option>
                        <option value="30d" <?php selected($when_filter, '30d'); ?>><?php esc_html_e('Últimos 30 días', 'terramarket'); ?></option>
                        <option value="90d" <?php selected($when_filter, '90d'); ?>><?php esc_html_e('Últimos 90 días', 'terramarket'); ?></option>
                    </select>
                </div>
                <div class="tm-toolbar-actions">
                    <button type="submit" class="button button-primary"><?php esc_html_e('Aplicar filtros', 'terramarket'); ?></button>
                    <a class="button button-secondary" href="<?php echo esc_url(admin_url('admin.php?page=tm-commissions')); ?>"><?php esc_html_e('Limpiar', 'terramarket'); ?></a>
                </div>
            </form>
            <div class="tm-cards tm-cards--compact">
                <div class="tm-card tm-card--metric tm-card--soft">
                    <span class="tm-card__eyebrow"><?php esc_html_e('Resultados visibles', 'terramarket'); ?></span>
                    <h3><?php echo esc_html(number_format_i18n($filtered_total)); ?></h3>
                    <p><?php esc_html_e('Registros mostrados según filtros activos.', 'terramarket'); ?></p>
                </div>
                <div class="tm-card tm-card--metric tm-card--soft">
                    <span class="tm-card__eyebrow"><?php esc_html_e('Pendientes', 'terramarket'); ?></span>
                    <h3><?php echo esc_html(number_format_i18n($pending_total)); ?></h3>
                    <p><?php esc_html_e('Cierres aún no liquidados o sin cierre administrativo final.', 'terramarket'); ?></p>
                </div>
                <div class="tm-card tm-card--metric tm-card--soft">
                    <span class="tm-card__eyebrow"><?php esc_html_e('Liquidadas', 'terramarket'); ?></span>
                    <h3><?php echo esc_html(number_format_i18n($settled_total)); ?></h3>
                    <p><?php esc_html_e('Registros cerrados como settled o paid.', 'terramarket'); ?></p>
                </div>
            </div>
        </section>

        <section class="tm-page-section tm-table-wrap">
            <div class="tm-page-section__header">
                <div>
                    <h2><?php esc_html_e('Historial de comisiones', 'terramarket'); ?></h2>
                    <p><?php esc_html_e('Montos, porcentaje y estado se leen ahora en bloques más claros para revisión comercial.', 'terramarket'); ?></p>
                </div>
                <div class="tm-results-note"><?php echo esc_html(sprintf(__('Resultados: %s', 'terramarket'), number_format_i18n($filtered_total))); ?></div>
            </div>
            <?php if (! $table) : ?>
                <div class="tm-empty-state">
                    <h3><?php esc_html_e('No hay tabla de comisiones disponible', 'terramarket'); ?></h3>
                    <p><?php esc_html_e('Esta instalación todavía no tiene una tabla compatible de comisiones detectada por el admin.', 'terramarket'); ?></p>
                    <div class="tm-empty-state__actions">
                        <a class="button button-secondary" href="<?php echo esc_url(admin_url('edit.php?post_type=tm_listing')); ?>"><?php esc_html_e('Ver avisos', 'terramarket'); ?></a>
                        <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=tm-tools')); ?>"><?php esc_html_e('Revisar herramientas', 'terramarket'); ?></a>
                    </div>
                </div>
            <?php elseif ($rows) : ?>
                <table class="widefat striped tm-admin-table tm-admin-table--enhanced">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Cierre', 'terramarket'); ?></th>
                            <th><?php esc_html_e('Aviso', 'terramarket'); ?></th>
                            <th><?php esc_html_e('Vendedor', 'terramarket'); ?></th>
                            <th><?php esc_html_e('Estado', 'terramarket'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $row) : ?>
                        <?php
                        $seller = get_userdata((int) $row->seller_user_id ?: (int) $row->post_author);
                        $created_ts = strtotime((string) $row->created_at);
                        ?>
                        <tr>
                            <td>
                                <div class="tm-table-stack">
                                    <strong><?php echo esc_html(TM_Helpers::format_price_clp((int) round((float) $row->declared_sale_amount))); ?></strong>
                                    <span><?php echo esc_html(sprintf(__('%s · %s', 'terramarket'), number_format_i18n((float) $row->commission_rate, 2) . '%', TM_Helpers::format_price_clp((int) round((float) $row->commission_amount)))); ?></span>
                                    <span><?php echo esc_html($created_ts ? date_i18n('d/m/Y H:i', $created_ts) : __('Sin fecha', 'terramarket')); ?></span>
                                    <?php if (! empty($row->notes)) : ?>
                                        <p class="tm-table-excerpt"><?php echo esc_html(wp_trim_words((string) $row->notes, 14, '…')); ?></p>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="tm-table-stack">
                                    <strong><?php echo esc_html((string) ($row->post_title ?: ('#' . (int) $row->listing_id))); ?></strong>
                                    <div class="tm-inline-meta">
                                        <a href="<?php echo esc_url(get_edit_post_link((int) $row->listing_id)); ?>"><?php esc_html_e('Editar aviso', 'terramarket'); ?></a>
                                        <span>·</span>
                                        <a href="<?php echo esc_url(get_permalink((int) $row->listing_id)); ?>" target="_blank" rel="noreferrer noopener"><?php esc_html_e('Ver publicación', 'terramarket'); ?></a>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="tm-table-stack">
                                    <strong><?php echo esc_html($seller ? $seller->display_name : __('Sin vendedor', 'terramarket')); ?></strong>
                                    <?php if ($seller && ! empty($seller->user_email)) : ?>
                                        <div class="tm-inline-meta">
                                            <span><?php echo esc_html($seller->user_email); ?></span>
                                            <button type="button" class="button-link tm-copy-button" data-copy-text="<?php echo esc_attr($seller->user_email); ?>"><?php esc_html_e('Copiar', 'terramarket'); ?></button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="tm-table-stack tm-table-stack--badges">
                                    <?php echo self::render_ui_badge(ucfirst((string) $row->settlement_status)); ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <div class="tm-empty-state">
                    <?php if ('' === $search && '' === $status_filter && 'all' === $when_filter) : ?>
                        <h3><?php esc_html_e('Aún no hay cierres declarados', 'terramarket'); ?></h3>
                        <p><?php esc_html_e('Cuando registres ventas o cierres compatibles con la tabla de comisiones, aparecerán aquí para liquidación y control.', 'terramarket'); ?></p>
                        <div class="tm-empty-state__actions">
                            <a class="button button-secondary" href="<?php echo esc_url(admin_url('edit.php?post_type=tm_listing')); ?>"><?php esc_html_e('Ver avisos', 'terramarket'); ?></a>
                            <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=tm-tools')); ?>"><?php esc_html_e('Revisar flujo base', 'terramarket'); ?></a>
                        </div>
                    <?php else : ?>
                        <h3><?php esc_html_e('No hay cierres para este filtro', 'terramarket'); ?></h3>
                        <p><?php esc_html_e('Prueba otro periodo o limpia filtros para volver al historial completo.', 'terramarket'); ?></p>
                        <div class="tm-empty-state__actions">
                            <a class="button button-secondary" href="<?php echo esc_url(admin_url('admin.php?page=tm-commissions')); ?>"><?php esc_html_e('Limpiar filtros', 'terramarket'); ?></a>
                            <a class="button button-primary" href="<?php echo esc_url(admin_url('edit.php?post_type=tm_listing')); ?>"><?php esc_html_e('Ver avisos', 'terramarket'); ?></a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php
        self::render_admin_shell_end();
    }

    public static function render_settings_page(): void
    {
        if (! self::current_user_can_access_page('settings')) {
            return;
        }

        $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'general';
        if (! in_array($tab, array('general', 'branding', 'marketplace'), true)) {
            $tab = 'general';
        }

        $general = get_option('tm_settings_general', array());
        $branding = get_option('tm_settings_branding', array());
        $marketplace = get_option('tm_settings_marketplace', array());

        $marketplace_name = (string) ($general['marketplace_name'] ?? 'Terramarket');
        $market_slug = (string) ($general['market_slug'] ?? 'terramarket');
        $notify_email = (string) ($general['notify_email'] ?? get_option('admin_email'));
        $from_email = (string) ($general['from_email'] ?? get_option('admin_email'));
        $listings_per_page = (int) ($general['listings_per_page'] ?? 16);
        $default_commission = (string) ($marketplace['default_commission'] ?? '5');
        $max_images = (int) ($marketplace['max_images'] ?? 5);
        $watermark_enabled = ! empty($marketplace['enable_watermark']);
        $logo_id = (int) ($branding['logo_id'] ?? 0);
        $watermark_logo_id = (int) ($branding['watermark_logo_id'] ?? 0);
        $frontend_url = trailingslashit(home_url('/' . ltrim($market_slug, '/')));
        $primary_color = (string) ($branding['primary_color'] ?? '#ffe600');
        $secondary_color = (string) ($branding['secondary_color'] ?? '#34835a');
        $button_color = (string) ($branding['button_color'] ?? '#3483fa');

        $tabs = array(
            'general' => array(
                'label'       => __('Generales', 'terramarket'),
                'description' => __('Nombre, rutas públicas, contacto y operación básica.', 'terramarket'),
                'meta'        => sprintf(__('Slug %s', 'terramarket'), '/' . ltrim($market_slug, '/')),
                'pill'        => __('Slug + contacto', 'terramarket'),
                'context_title' => __('Operación general', 'terramarket'),
                'context_items' => array(
                    array('label' => __('URL pública', 'terramarket'), 'value' => $frontend_url),
                    array('label' => __('Correo emisor', 'terramarket'), 'value' => $from_email),
                    array('label' => __('Correo de notificación', 'terramarket'), 'value' => $notify_email),
                ),
            ),
            'branding' => array(
                'label'       => __('Branding', 'terramarket'),
                'description' => __('Logo, colores y vista previa visual del sistema.', 'terramarket'),
                'meta'        => $logo_id > 0 ? __('Logo principal cargado', 'terramarket') : __('Logo principal pendiente', 'terramarket'),
                'pill'        => __('Logo + colores', 'terramarket'),
                'context_title' => __('Identidad visual', 'terramarket'),
                'context_items' => array(
                    array('label' => __('Logo', 'terramarket'), 'value' => $logo_id > 0 ? __('Cargado', 'terramarket') : __('Pendiente', 'terramarket')),
                    array('label' => __('Watermark logo', 'terramarket'), 'value' => $watermark_logo_id > 0 ? __('Cargado', 'terramarket') : __('Pendiente', 'terramarket')),
                    array('label' => __('Paleta', 'terramarket'), 'value' => $primary_color . ' · ' . $secondary_color . ' · ' . $button_color),
                ),
            ),
            'marketplace' => array(
                'label'       => __('Marketplace', 'terramarket'),
                'description' => __('Comisión, imágenes y reglas de watermark.', 'terramarket'),
                'meta'        => sprintf(__('Comisión base %s%%', 'terramarket'), $default_commission),
                'pill'        => __('Comisión + imágenes', 'terramarket'),
                'context_title' => __('Reglas operativas', 'terramarket'),
                'context_items' => array(
                    array('label' => __('Comisión por defecto', 'terramarket'), 'value' => $default_commission . '%'),
                    array('label' => __('Máximo de imágenes', 'terramarket'), 'value' => number_format_i18n($max_images)),
                    array('label' => __('Watermark', 'terramarket'), 'value' => $watermark_enabled ? __('Activo', 'terramarket') : __('Inactivo', 'terramarket')),
                ),
            ),
        );
        $active_tab = $tabs[$tab];

        self::render_admin_shell_start('settings', array(
            'title' => __('Ajustes Terramarket', 'terramarket'),
            'lead'  => __('Configura identidad, rutas públicas y reglas base del marketplace dentro del nuevo sistema visual común del admin.', 'terramarket'),
            'summary_items' => array(
                array('label' => __('Marketplace', 'terramarket'), 'value' => $marketplace_name),
                array('label' => __('Slug', 'terramarket'), 'value' => '/' . ltrim($market_slug, '/')),
                array('label' => __('Comisión', 'terramarket'), 'value' => $default_commission . '%'),
                array('label' => __('Notificaciones', 'terramarket'), 'value' => __('Activas', 'terramarket'), 'title' => $notify_email),
                array('label' => __('Watermark', 'terramarket'), 'value' => $watermark_enabled ? __('Activo', 'terramarket') : __('Inactivo', 'terramarket')),
                array('label' => __('Logo', 'terramarket'), 'value' => $logo_id > 0 ? __('Cargado', 'terramarket') : __('Pendiente', 'terramarket')),
            ),
            'actions' => self::get_admin_actions_for_page('settings'),
        ));
        ?>
        <?php settings_errors('tm_settings'); ?>

        <nav class="tm-settings-tabs" aria-label="<?php esc_attr_e('Secciones de ajustes', 'terramarket'); ?>">
            <?php foreach ($tabs as $tab_key => $tab_data) : ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=tm-settings&tab=' . $tab_key)); ?>" class="tm-settings-tab <?php echo $tab_key === $tab ? 'is-active' : ''; ?>">
                    <span class="tm-settings-tab__topline">
                        <span class="tm-settings-tab__title"><?php echo esc_html($tab_data['label']); ?></span>
                        <span class="tm-settings-tab__pill"><?php echo esc_html((string) $tab_data['pill']); ?></span>
                    </span>
                    <span class="tm-settings-tab__description"><?php echo esc_html($tab_data['description']); ?></span>
                    <span class="tm-settings-tab__meta"><?php echo esc_html((string) $tab_data['meta']); ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <section class="tm-page-section tm-settings-context-card">
            <div class="tm-page-section__header">
                <div>
                    <h2><?php echo esc_html((string) $active_tab['context_title']); ?></h2>
                    <p><?php esc_html_e('Resumen rápido del bloque activo para mantener alineado el contexto visual antes de editar campos.', 'terramarket'); ?></p>
                </div>
            </div>
            <div class="tm-settings-context-grid">
                <?php foreach ($active_tab['context_items'] as $context_item) : ?>
                    <div class="tm-settings-context-item">
                        <span class="tm-settings-context-item__label"><?php echo esc_html((string) $context_item['label']); ?></span>
                        <strong class="tm-settings-context-item__value"><?php echo esc_html((string) $context_item['value']); ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <div class="tm-settings-panel">
            <?php if ('general' === $tab) : ?>
                <?php include TM_PLUGIN_DIR . 'admin/partials/settings-general.php'; ?>
            <?php elseif ('branding' === $tab) : ?>
                <?php include TM_PLUGIN_DIR . 'admin/partials/settings-branding.php'; ?>
            <?php else : ?>
                <?php include TM_PLUGIN_DIR . 'admin/partials/settings-marketplace.php'; ?>
            <?php endif; ?>
        </div>
        <?php
        self::render_admin_shell_end();
    }

    public static function render_tools_page(): void
    {
        if (! self::current_user_can_access_page('tools')) {
            return;
        }

        $notice = isset($_GET['tm_notice']) ? sanitize_text_field(wp_unslash($_GET['tm_notice'])) : '';
        global $wpdb;

        $conditions_count = (int) wp_count_terms(array('taxonomy' => 'tm_condition', 'hide_empty' => false));
        $categories_count = (int) wp_count_terms(array('taxonomy' => 'tm_category', 'hide_empty' => false));
        $regions_count = (int) wp_count_terms(array('taxonomy' => 'tm_region', 'hide_empty' => false));
        $alerts_count = (int) $wpdb->get_var("SELECT COUNT(1) FROM {$wpdb->prefix}tm_alerts");

        self::render_admin_shell_start('tools', array(
            'title' => __('Herramientas Terramarket', 'terramarket'),
            'lead'  => __('Acciones administrativas separadas con mayor claridad entre operación segura y mantenimiento sensible, dentro del mismo sistema visual del admin.', 'terramarket'),
            'summary_items' => array(
                array('label' => __('Condiciones', 'terramarket'), 'value' => number_format_i18n($conditions_count)),
                array('label' => __('Categorías', 'terramarket'), 'value' => number_format_i18n($categories_count)),
                array('label' => __('Regiones', 'terramarket'), 'value' => number_format_i18n($regions_count)),
                array('label' => __('Alertas', 'terramarket'), 'value' => number_format_i18n($alerts_count)),
            ),
        ));
        ?>
        <?php if ($notice) : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div><?php endif; ?>

        <div class="tm-admin-panels">
            <section class="tm-page-section tm-card--soft">
                <div class="tm-page-section__header">
                    <div>
                        <h2><?php esc_html_e('Estado operativo', 'terramarket'); ?></h2>
                        <p><?php esc_html_e('Lectura rápida para saber qué tan preparada está la base administrativa antes de ejecutar herramientas.', 'terramarket'); ?></p>
                    </div>
                </div>
                <dl class="tm-data-list">
                    <div class="tm-data-list__row">
                        <dt><?php esc_html_e('Alertas registradas', 'terramarket'); ?></dt>
                        <dd><?php echo self::render_ui_badge(number_format_i18n($alerts_count) . ' ' . __('alertas', 'terramarket'), $alerts_count > 0 ? 'warning' : 'neutral'); ?></dd>
                    </div>
                    <div class="tm-data-list__row">
                        <dt><?php esc_html_e('Cobertura taxonómica', 'terramarket'); ?></dt>
                        <dd><?php echo esc_html(sprintf(__('%1$s categorías · %2$s regiones · %3$s condiciones', 'terramarket'), number_format_i18n($categories_count), number_format_i18n($regions_count), number_format_i18n($conditions_count))); ?></dd>
                    </div>
                    <div class="tm-data-list__row">
                        <dt><?php esc_html_e('Uso recomendado', 'terramarket'); ?></dt>
                        <dd><?php esc_html_e('Empieza por acciones seguras y deja los reseed amplios solo para mantenimiento controlado.', 'terramarket'); ?></dd>
                    </div>
                </dl>
            </section>

            <section class="tm-page-section tm-card--soft">
                <div class="tm-page-section__header">
                    <div>
                        <h2><?php esc_html_e('Accesos relacionados', 'terramarket'); ?></h2>
                        <p><?php esc_html_e('Atajos útiles para revisar el estado del sistema antes o después de ejecutar una herramienta.', 'terramarket'); ?></p>
                    </div>
                </div>
                <div class="tm-quick-actions-grid">
                    <a class="tm-quick-action" href="<?php echo esc_url(admin_url('admin.php?page=tm-settings')); ?>">
                        <strong><?php esc_html_e('Revisar ajustes', 'terramarket'); ?></strong>
                        <span><?php esc_html_e('Valida correos, branding y reglas operativas.', 'terramarket'); ?></span>
                    </a>
                    <a class="tm-quick-action" href="<?php echo esc_url(admin_url('edit.php?post_type=tm_listing')); ?>">
                        <strong><?php esc_html_e('Abrir avisos', 'terramarket'); ?></strong>
                        <span><?php esc_html_e('Comprueba contenido, imágenes y estado de publicaciones.', 'terramarket'); ?></span>
                    </a>
                    <a class="tm-quick-action" href="<?php echo esc_url(admin_url('admin.php?page=tm-vitrine')); ?>">
                        <strong><?php esc_html_e('Ver vitrina', 'terramarket'); ?></strong>
                        <span><?php esc_html_e('Confirma destacados y visibilidad comercial actual.', 'terramarket'); ?></span>
                    </a>
                    <a class="tm-quick-action" href="<?php echo esc_url(admin_url('admin.php?page=tm-leads')); ?>">
                        <strong><?php esc_html_e('Revisar leads', 'terramarket'); ?></strong>
                        <span><?php esc_html_e('Valida que el flujo comercial esté recibiendo contactos.', 'terramarket'); ?></span>
                    </a>
                </div>
            </section>
        </div>

        <div class="tm-tool-panels">
            <section class="tm-page-section tm-page-section--safe">
                <div class="tm-page-section__header">
                    <div>
                        <h2><?php esc_html_e('Acciones seguras', 'terramarket'); ?></h2>
                        <p><?php esc_html_e('Tareas operativas que no rehacen la base completa y sirven para mantener la operación cotidiana.', 'terramarket'); ?></p>
                    </div>
                </div>
                <div class="tm-tool-actions-grid">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="tm-tool-card tm-tool-card--safe">
                        <?php wp_nonce_field('tm_run_alerts_action', 'tm_run_alerts_nonce'); ?>
                        <input type="hidden" name="action" value="tm_run_alerts">
                        <div class="tm-tool-card__header">
                            <h3><?php esc_html_e('Ejecutar alertas ahora', 'terramarket'); ?></h3>
                            <?php echo self::render_ui_badge(__('Seguro', 'terramarket'), 'success'); ?>
                        </div>
                        <p><?php esc_html_e('Procesa alertas pendientes manualmente sin resembrar taxonomías ni tocar publicaciones existentes.', 'terramarket'); ?></p>
                        <ul class="tm-tool-card__list">
                            <li><?php esc_html_e('Útil para pruebas o revisión operativa', 'terramarket'); ?></li>
                            <li><?php esc_html_e('No reinicia estructura base', 'terramarket'); ?></li>
                        </ul>
                        <div class="tm-tool-card__actions">
                            <button type="submit" class="button button-primary"><?php esc_html_e('Procesar alertas', 'terramarket'); ?></button>
                        </div>
                    </form>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="tm-tool-card tm-tool-card--safe">
                        <?php wp_nonce_field('tm_reseed_regions_action', 'tm_reseed_regions_nonce'); ?>
                        <input type="hidden" name="action" value="tm_reseed_regions">
                        <div class="tm-tool-card__header">
                            <h3><?php esc_html_e('Recrear regiones y comunas', 'terramarket'); ?></h3>
                            <?php echo self::render_ui_badge(__('Controlado', 'terramarket'), 'info'); ?>
                        </div>
                        <p><?php esc_html_e('Recarga solo la estructura geográfica, útil si necesitas corregir cobertura territorial sin tocar otras taxonomías.', 'terramarket'); ?></p>
                        <ul class="tm-tool-card__list">
                            <li><?php esc_html_e('Afecta regiones y comunas base', 'terramarket'); ?></li>
                            <li><?php esc_html_e('No rehace categorías ni condiciones', 'terramarket'); ?></li>
                        </ul>
                        <div class="tm-tool-card__actions">
                            <button type="submit" class="button"><?php esc_html_e('Recrear geografía', 'terramarket'); ?></button>
                        </div>
                    </form>
                </div>
            </section>

            <section class="tm-page-section tm-page-section--danger">
                <div class="tm-page-section__header">
                    <div>
                        <h2><?php esc_html_e('Acciones sensibles', 'terramarket'); ?></h2>
                        <p><?php esc_html_e('Reejecutan seeders amplios. Mantienen la lógica general del plugin, pero conviene usarlas con criterio y confirmación.', 'terramarket'); ?></p>
                    </div>
                </div>
                <div class="tm-tool-actions-grid">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="tm-tool-card tm-tool-card--danger">
                        <?php wp_nonce_field('tm_reseed_categories_action', 'tm_reseed_categories_nonce'); ?>
                        <input type="hidden" name="action" value="tm_reseed_categories">
                        <div class="tm-tool-card__header">
                            <h3><?php esc_html_e('Recrear categorías y subcategorías', 'terramarket'); ?></h3>
                            <?php echo self::render_ui_badge(__('Sensible', 'terramarket'), 'warning'); ?>
                        </div>
                        <p><?php esc_html_e('Recarga categorías, subcategorías y campos dinámicos asociados. Úsalo cuando la estructura comercial necesite rearmarse.', 'terramarket'); ?></p>
                        <ul class="tm-tool-card__list">
                            <li><?php esc_html_e('Impacta la estructura de clasificación', 'terramarket'); ?></li>
                            <li><?php esc_html_e('Conviene revisar avisos y filtros después', 'terramarket'); ?></li>
                        </ul>
                        <div class="tm-tool-card__actions">
                            <button type="submit" class="button tm-confirm-action" data-confirm-message="<?php echo esc_attr__('Esto volverá a cargar categorías, subcategorías y campos dinámicos. ¿Continuar?', 'terramarket'); ?>"><?php esc_html_e('Recrear estructura comercial', 'terramarket'); ?></button>
                        </div>
                    </form>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="tm-tool-card tm-tool-card--danger tm-tool-card--danger-strong">
                        <?php wp_nonce_field('tm_reseed_all_action', 'tm_reseed_all_nonce'); ?>
                        <input type="hidden" name="action" value="tm_reseed_all">
                        <div class="tm-tool-card__header">
                            <h3><?php esc_html_e('Reseed completo', 'terramarket'); ?></h3>
                            <?php echo self::render_ui_badge(__('Alto impacto', 'terramarket'), 'danger'); ?>
                        </div>
                        <p><?php esc_html_e('Reejecuta todas las cargas base del plugin. Déjalo como última alternativa para mantenimiento profundo o reset controlado.', 'terramarket'); ?></p>
                        <ul class="tm-tool-card__list">
                            <li><?php esc_html_e('Mayor alcance sobre la base del plugin', 'terramarket'); ?></li>
                            <li><?php esc_html_e('Requiere verificación posterior de taxonomías y vistas', 'terramarket'); ?></li>
                        </ul>
                        <div class="tm-tool-card__actions">
                            <button type="submit" class="button button-primary tm-button-danger tm-confirm-action" data-confirm-message="<?php echo esc_attr__('Esto reejecutará todas las cargas base del plugin. ¿Continuar?', 'terramarket'); ?>"><?php esc_html_e('Ejecutar reseed completo', 'terramarket'); ?></button>
                        </div>
                    </form>
                </div>
            </section>
        </div>
        <?php
        self::render_admin_shell_end();
    }

    protected static function render_admin_shell_start(string $page_key, array $args = array()): void
    {
        $wrap_class = 'wrap tm-wrap tm-admin-page';
        if (! empty($args['wrap_class'])) {
            $wrap_class .= ' ' . sanitize_html_class((string) $args['wrap_class']);
        }
        ?>
        <div class="<?php echo esc_attr($wrap_class); ?>">
            <div class="tm-admin-shell">
                <?php self::render_admin_shell_fragment($page_key, $args); ?>
        <?php
    }

    protected static function render_admin_shell_fragment(string $page_key, array $args = array()): void
    {
        $title = (string) ($args['title'] ?? __('Terramarket', 'terramarket'));
        $lead = (string) ($args['lead'] ?? '');
        $summary_items = is_array($args['summary_items'] ?? null) ? $args['summary_items'] : array();
        $actions = is_array($args['actions'] ?? null) ? $args['actions'] : self::get_admin_actions_for_page($page_key);
        ?>
        <header class="tm-admin-hero">
            <div class="tm-admin-hero__top">
                <div class="tm-admin-hero__content">
                    <p class="tm-admin-eyebrow"><?php esc_html_e('Terramarket · Admin', 'terramarket'); ?></p>
                    <h1><?php echo esc_html($title); ?></h1>
                    <?php if ('' !== $lead) : ?>
                        <p class="tm-admin-hero__lead"><?php echo esc_html($lead); ?></p>
                    <?php endif; ?>
                </div>
                <?php if ($actions) : ?>
                    <div class="tm-admin-actions">
                        <?php foreach ($actions as $action) : ?>
                            <?php $action_classes = 'button tm-admin-action'; ?>
                            <?php $action_classes .= ! empty($action['variant']) && 'primary' === $action['variant'] ? ' button-primary' : ' button-secondary'; ?>
                            <?php if (! empty($action['copy_text'])) : ?>
                                <button type="button" class="<?php echo esc_attr($action_classes); ?> tm-copy-button" data-copy-text="<?php echo esc_attr((string) $action['copy_text']); ?>"><?php echo esc_html((string) $action['label']); ?></button>
                            <?php else : ?>
                                <a class="<?php echo esc_attr($action_classes); ?>" href="<?php echo esc_url((string) ($action['url'] ?? '#')); ?>"><?php echo esc_html((string) $action['label']); ?></a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php if ($summary_items) : ?>
                <div class="tm-admin-summary" aria-label="<?php esc_attr_e('Resumen de la página', 'terramarket'); ?>">
                    <?php foreach ($summary_items as $item) : ?>
                        <div class="tm-admin-summary__item <?php echo esc_attr((string) ($item['class'] ?? '')); ?>">
                            <span class="tm-admin-summary__label"><?php echo esc_html((string) ($item['label'] ?? '')); ?></span>
                            <strong class="tm-admin-summary__value" title="<?php echo esc_attr((string) ($item['title'] ?? ($item['value'] ?? ''))); ?>"><?php echo esc_html((string) ($item['value'] ?? '')); ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </header>

        <nav class="tm-admin-subnav" aria-label="<?php esc_attr_e('Navegación interna Terramarket', 'terramarket'); ?>">
            <?php foreach (self::get_admin_nav_items() as $item) : ?>
                <?php $is_active = $item['key'] === $page_key; ?>
                <a href="<?php echo esc_url($item['url']); ?>" class="tm-admin-subnav__item <?php echo $is_active ? 'is-active' : ''; ?>" <?php echo $is_active ? 'aria-current="page"' : ''; ?>>
                    <?php echo esc_html($item['label']); ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <?php
    }

    protected static function render_admin_shell_end(): void
    {
        ?>
            </div>
        </div>
        <?php
    }

    protected static function get_admin_nav_items(): array
    {
        return array(
            array('key' => 'dashboard', 'label' => __('Dashboard', 'terramarket'), 'url' => admin_url('admin.php?page=terramarket')),
            array('key' => 'listings', 'label' => __('Avisos', 'terramarket'), 'url' => admin_url('edit.php?post_type=tm_listing')),
            array('key' => 'categories', 'label' => __('Categorías', 'terramarket'), 'url' => self::get_categories_admin_url()),
            array('key' => 'leads', 'label' => __('Leads', 'terramarket'), 'url' => admin_url('admin.php?page=tm-leads')),
            array('key' => 'vitrine', 'label' => __('Vitrina', 'terramarket'), 'url' => admin_url('admin.php?page=tm-vitrine')),
            array('key' => 'commissions', 'label' => __('Comisiones', 'terramarket'), 'url' => admin_url('admin.php?page=tm-commissions')),
            array('key' => 'settings', 'label' => __('Ajustes', 'terramarket'), 'url' => admin_url('admin.php?page=tm-settings')),
            array('key' => 'tools', 'label' => __('Herramientas', 'terramarket'), 'url' => admin_url('admin.php?page=tm-tools')),
        );
    }

    protected static function get_default_admin_actions(): array
    {
        return self::get_admin_actions_for_page('dashboard');
    }

    protected static function get_admin_actions_for_page(string $page_key): array
    {
        $general = get_option('tm_settings_general', array());
        $market_slug = (string) ($general['market_slug'] ?? 'terramarket');
        $frontend_url = trailingslashit(home_url('/' . ltrim($market_slug, '/')));

        $maps = array(
            'dashboard' => array(
                array('label' => __('Crear aviso', 'terramarket'), 'url' => admin_url('post-new.php?post_type=tm_listing'), 'variant' => 'primary'),
                array('label' => __('Ver frontend', 'terramarket'), 'url' => $frontend_url),
                array('label' => __('Copiar URL', 'terramarket'), 'copy_text' => $frontend_url),
                array('label' => __('Ajustes', 'terramarket'), 'url' => admin_url('admin.php?page=tm-settings')),
            ),
            'listings' => array(
                array('label' => __('Crear aviso', 'terramarket'), 'url' => admin_url('post-new.php?post_type=tm_listing'), 'variant' => 'primary'),
                array('label' => __('Ver frontend', 'terramarket'), 'url' => $frontend_url),
                array('label' => __('Copiar URL', 'terramarket'), 'copy_text' => $frontend_url),
                array('label' => __('Ajustes', 'terramarket'), 'url' => admin_url('admin.php?page=tm-settings')),
            ),
            'categories' => array(
                array('label' => __('Nueva categoría', 'terramarket'), 'url' => self::get_categories_admin_url(0, 'col-left'), 'variant' => 'primary'),
                array('label' => __('Ver frontend', 'terramarket'), 'url' => $frontend_url),
                array('label' => __('Ajustar hero', 'terramarket'), 'url' => admin_url('admin.php?page=tm-settings&tab=marketplace')),
                array('label' => __('Copiar URL', 'terramarket'), 'copy_text' => $frontend_url),
            ),
            'leads' => array(
                array('label' => __('Avisos', 'terramarket'), 'url' => admin_url('edit.php?post_type=tm_listing'), 'variant' => 'primary'),
                array('label' => __('Ver frontend', 'terramarket'), 'url' => $frontend_url),
                array('label' => __('Ajustes', 'terramarket'), 'url' => admin_url('admin.php?page=tm-settings')),
            ),
            'vitrine' => array(
                array('label' => __('Avisos', 'terramarket'), 'url' => admin_url('edit.php?post_type=tm_listing'), 'variant' => 'primary'),
                array('label' => __('Ver frontend', 'terramarket'), 'url' => $frontend_url),
                array('label' => __('Ajustes', 'terramarket'), 'url' => admin_url('admin.php?page=tm-settings')),
            ),
            'commissions' => array(
                array('label' => __('Leads', 'terramarket'), 'url' => admin_url('admin.php?page=tm-leads'), 'variant' => 'primary'),
                array('label' => __('Avisos', 'terramarket'), 'url' => admin_url('edit.php?post_type=tm_listing')),
                array('label' => __('Ajustes', 'terramarket'), 'url' => admin_url('admin.php?page=tm-settings')),
            ),
            'settings' => array(
                array('label' => __('Ver frontend', 'terramarket'), 'url' => $frontend_url, 'variant' => 'primary'),
                array('label' => __('Copiar URL', 'terramarket'), 'copy_text' => $frontend_url),
                array('label' => __('Avisos', 'terramarket'), 'url' => admin_url('edit.php?post_type=tm_listing')),
            ),
            'tools' => array(
                array('label' => __('Ajustes', 'terramarket'), 'url' => admin_url('admin.php?page=tm-settings'), 'variant' => 'primary'),
                array('label' => __('Avisos', 'terramarket'), 'url' => admin_url('edit.php?post_type=tm_listing')),
            ),
        );

        return $maps[$page_key] ?? array(
            array('label' => __('Ver frontend', 'terramarket'), 'url' => $frontend_url, 'variant' => 'primary'),
            array('label' => __('Copiar URL', 'terramarket'), 'copy_text' => $frontend_url),
            array('label' => __('Crear aviso', 'terramarket'), 'url' => admin_url('post-new.php?post_type=tm_listing')),
            array('label' => __('Ajustes', 'terramarket'), 'url' => admin_url('admin.php?page=tm-settings')),
        );
    }

    protected static function render_ui_badge(string $text, string $fallback_tone = 'neutral'): string
    {
        $label = trim($text);
        if ('' === $label) {
            $label = __('Sin dato', 'terramarket');
        }

        $tone = $fallback_tone;
        $normalized = strtolower(sanitize_title($label));
        if (false !== strpos($normalized, 'active') || false !== strpos($normalized, 'activo') || false !== strpos($normalized, 'new') || false !== strpos($normalized, 'nuevo')) {
            $tone = 'info';
        } elseif (false !== strpos($normalized, 'sold') || false !== strpos($normalized, 'settled') || false !== strpos($normalized, 'paid') || false !== strpos($normalized, 'cerrado')) {
            $tone = 'success';
        } elseif (false !== strpos($normalized, 'pending') || false !== strpos($normalized, 'pend')) {
            $tone = 'warning';
        } elseif (false !== strpos($normalized, 'cancel') || false !== strpos($normalized, 'reject') || false !== strpos($normalized, 'expired')) {
            $tone = 'danger';
        }

        return '<span class="tm-ui-badge tm-ui-badge--' . esc_attr($tone) . '">' . esc_html($label) . '</span>';
    }

    protected static function table_exists(string $table): bool
    {
        global $wpdb;

        static $cache = array();
        if (isset($cache[$table])) {
            return $cache[$table];
        }

        $cache[$table] = ((string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table))) === $table;

        return $cache[$table];
    }

    protected static function get_table_columns(string $table): array
    {
        global $wpdb;

        static $cache = array();
        if (isset($cache[$table])) {
            return $cache[$table];
        }

        if (! self::table_exists($table)) {
            $cache[$table] = array();
            return $cache[$table];
        }

        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}");
        $cache[$table] = is_array($columns) ? array_map('strval', $columns) : array();

        return $cache[$table];
    }

    protected static function get_leads_column_map(): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'tm_leads';
        $columns = self::get_table_columns($table);

        return array(
            'name'    => in_array('buyer_name', $columns, true) ? 'l.buyer_name' : 'l.lead_name',
            'email'   => in_array('buyer_email', $columns, true) ? 'l.buyer_email' : 'l.lead_email',
            'phone'   => in_array('buyer_phone', $columns, true) ? 'l.buyer_phone' : 'l.lead_phone',
            'message' => in_array('message', $columns, true) ? 'l.message' : "''",
            'type'    => in_array('lead_type', $columns, true) ? 'l.lead_type' : "''",
        );
    }

    protected static function get_commissions_table_name(): string
    {
        global $wpdb;

        $candidates = array(
            $wpdb->prefix . 'tm_commission_events',
            $wpdb->prefix . 'tm_commissions',
        );

        foreach ($candidates as $candidate) {
            if (self::table_exists($candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    public static function handle_reseed_all(): void
    {
        self::guard_admin_post('tm_reseed_all_nonce', 'tm_reseed_all_action', self::get_admin_page_capability('tools'));
        TM_Seeder::seed_all();
        self::redirect_tools_notice(__('Seeder completo ejecutado.', 'terramarket'));
    }

    public static function handle_reseed_categories(): void
    {
        self::guard_admin_post('tm_reseed_categories_nonce', 'tm_reseed_categories_action', self::get_admin_page_capability('tools'));
        TM_Seeder::seed_conditions();
        TM_Seeder::seed_categories_and_subcategories();
        TM_Seeder::seed_dynamic_fields();
        self::redirect_tools_notice(__('Categorías, subcategorías y campos dinámicos recreados.', 'terramarket'));
    }

    public static function handle_reseed_regions(): void
    {
        self::guard_admin_post('tm_reseed_regions_nonce', 'tm_reseed_regions_action', self::get_admin_page_capability('tools'));
        TM_Seeder::seed_regions_and_communes();
        self::redirect_tools_notice(__('Regiones y comunas recreadas.', 'terramarket'));
    }

    public static function handle_save_vitrine(): void
    {
        self::guard_admin_post('tm_save_vitrine_nonce', 'tm_save_vitrine_action', self::get_admin_page_capability('vitrine'));
        global $wpdb;
        $table = $wpdb->prefix . 'tm_vitrine_items';
        $wpdb->query("DELETE FROM {$table}");
        $items = isset($_POST['tm_vitrine_items']) && is_array($_POST['tm_vitrine_items']) ? array_map('absint', wp_unslash($_POST['tm_vitrine_items'])) : array();
        $sort  = 1;
        foreach ($items as $listing_id) {
            $wpdb->insert($table, array(
                'listing_id' => $listing_id,
                'sort_order' => $sort,
            ));
            $sort++;
        }
        self::redirect_vitrine_notice(__('Vitrina actualizada.', 'terramarket'));
    }

    public static function handle_run_alerts(): void
    {
        self::guard_admin_post('tm_run_alerts_nonce', 'tm_run_alerts_action', self::get_admin_page_capability('tools'));
        TM_Alerts::process_pending_alerts();
        self::redirect_tools_notice(__('Alertas procesadas manualmente.', 'terramarket'));
    }

    protected static function redirect_vitrine_notice(string $message): void
    {
        wp_safe_redirect(add_query_arg(array(
            'page'      => 'tm-vitrine',
            'tm_notice' => rawurlencode($message),
        ), admin_url('admin.php')));
        exit;
    }

    protected static function redirect_tools_notice(string $message): void
    {
        wp_safe_redirect(add_query_arg(array(
            'page'      => 'tm-tools',
            'tm_notice' => rawurlencode($message),
        ), admin_url('admin.php')));
        exit;
    }

    protected static function guard_admin_post(string $nonce_name, string $action, string $capability): void
    {
        if (! current_user_can($capability)) {
            wp_die(esc_html__('No tienes permisos suficientes para ejecutar esta acción.', 'terramarket'));
        }

        if (! isset($_POST[$nonce_name]) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[$nonce_name])), $action)) {
            wp_die(esc_html__('Nonce inválido.', 'terramarket'));
        }
    }
}
