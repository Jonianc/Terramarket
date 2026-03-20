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
        add_action('add_meta_boxes_tm_listing', array(__CLASS__, 'register_listing_meta_boxes'));
        add_action('save_post_tm_listing', array(__CLASS__, 'save_listing_meta'));
        add_action('admin_post_tm_reseed_all', array(__CLASS__, 'handle_reseed_all'));
        add_action('admin_post_tm_reseed_categories', array(__CLASS__, 'handle_reseed_categories'));
        add_action('admin_post_tm_reseed_regions', array(__CLASS__, 'handle_reseed_regions'));
        add_action('admin_post_tm_save_vitrine', array(__CLASS__, 'handle_save_vitrine'));
        add_action('admin_post_tm_run_alerts', array(__CLASS__, 'handle_run_alerts'));
    }

    public static function register_menu(): void
    {
        add_menu_page(
            __('Terramarket', 'terramarket'),
            __('Terramarket', 'terramarket'),
            'manage_options',
            'terramarket',
            array(__CLASS__, 'render_dashboard_page'),
            'dashicons-store',
            26
        );

        add_submenu_page('terramarket', __('Dashboard', 'terramarket'), __('Dashboard', 'terramarket'), 'manage_options', 'terramarket', array(__CLASS__, 'render_dashboard_page'));
        add_submenu_page('terramarket', __('Avisos', 'terramarket'), __('Avisos', 'terramarket'), 'edit_posts', 'edit.php?post_type=tm_listing');
        add_submenu_page('terramarket', __('Leads', 'terramarket'), __('Leads', 'terramarket'), 'manage_options', 'tm-leads', array(__CLASS__, 'render_leads_page'));
        add_submenu_page('terramarket', __('Vitrina', 'terramarket'), __('Vitrina', 'terramarket'), 'manage_options', 'tm-vitrine', array(__CLASS__, 'render_vitrine_page'));
        add_submenu_page('terramarket', __('Comisiones', 'terramarket'), __('Comisiones', 'terramarket'), 'manage_options', 'tm-commissions', array(__CLASS__, 'render_commissions_page'));
        add_submenu_page('terramarket', __('Ajustes', 'terramarket'), __('Ajustes', 'terramarket'), 'manage_options', 'tm-settings', array(__CLASS__, 'render_settings_page'));
        add_submenu_page('terramarket', __('Herramientas', 'terramarket'), __('Herramientas', 'terramarket'), 'manage_options', 'tm-tools', array(__CLASS__, 'render_tools_page'));
    }

    public static function enqueue_assets(string $hook): void
    {
        if (strpos($hook, 'terramarket') === false && strpos($hook, 'tm_listing') === false && strpos($hook, 'tm-leads') === false && strpos($hook, 'tm-vitrine') === false && strpos($hook, 'tm-commissions') === false && strpos($hook, 'post.php') === false && strpos($hook, 'post-new.php') === false) {
            return;
        }

        wp_enqueue_style('tm-admin', TM_PLUGIN_URL . 'assets/css/admin.css', array(), TM_VERSION);
        wp_enqueue_script('tm-admin', TM_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), TM_VERSION, true);

        $current_page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        $current_tab  = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : '';
        if ('tm-settings' === $current_page && 'branding' === $current_tab) {
            wp_enqueue_media();
            wp_enqueue_style('wp-color-picker');
            wp_enqueue_script('wp-color-picker');
        }
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
            'tm_listing_status'       => get_post_meta($post->ID, 'tm_listing_status', true) ?: 'active',
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
        ?>
        <div class="tm-admin-grid">
            <div class="tm-field">
                <label for="tm_price_clp"><strong><?php esc_html_e('Precio CLP', 'terramarket'); ?></strong></label>
                <input type="number" class="widefat" id="tm_price_clp" name="tm_price_clp" value="<?php echo esc_attr((string) $meta['tm_price_clp']); ?>" min="0" step="1">
            </div>
            <div class="tm-field">
                <label for="tm_listing_status"><strong><?php esc_html_e('Estado del aviso', 'terramarket'); ?></strong></label>
                <select class="widefat" id="tm_listing_status" name="tm_listing_status">
                    <?php foreach (TM_Helpers::get_listing_statuses() as $status_key => $status_label) : ?>
                        <option value="<?php echo esc_attr($status_key); ?>" <?php selected($meta['tm_listing_status'], $status_key); ?>><?php echo esc_html($status_label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
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
            <div class="tm-field">
                <label for="tm_condition_term_id"><strong><?php esc_html_e('Condición', 'terramarket'); ?></strong></label>
                <select class="widefat" id="tm_condition_term_id" name="tm_condition_term_id">
                    <option value="0"><?php esc_html_e('Seleccionar condición', 'terramarket'); ?></option>
                    <?php foreach ($conditions as $term) : ?>
                        <option value="<?php echo esc_attr((string) $term->term_id); ?>" <?php selected($selected_condition, $term->term_id); ?>><?php echo esc_html($term->name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="tm-field">
                <label for="tm_featured"><strong><?php esc_html_e('Destacado', 'terramarket'); ?></strong></label>
                <select class="widefat" id="tm_featured" name="tm_featured">
                    <option value="0" <?php selected($meta['tm_featured'], 0); ?>><?php esc_html_e('No', 'terramarket'); ?></option>
                    <option value="1" <?php selected($meta['tm_featured'], 1); ?>><?php esc_html_e('Sí', 'terramarket'); ?></option>
                </select>
            </div>
            <div class="tm-field">
                <label for="tm_contact_name"><strong><?php esc_html_e('Nombre contacto', 'terramarket'); ?></strong></label>
                <input type="text" class="widefat" id="tm_contact_name" name="tm_contact_name" value="<?php echo esc_attr((string) $meta['tm_contact_name']); ?>">
            </div>
            <div class="tm-field">
                <label for="tm_contact_email"><strong><?php esc_html_e('Email contacto', 'terramarket'); ?></strong></label>
                <input type="email" class="widefat" id="tm_contact_email" name="tm_contact_email" value="<?php echo esc_attr((string) $meta['tm_contact_email']); ?>">
            </div>
            <div class="tm-field">
                <label for="tm_contact_phone"><strong><?php esc_html_e('Teléfono', 'terramarket'); ?></strong></label>
                <input type="text" class="widefat" id="tm_contact_phone" name="tm_contact_phone" value="<?php echo esc_attr((string) $meta['tm_contact_phone']); ?>">
            </div>
            <div class="tm-field">
                <label for="tm_views_count"><strong><?php esc_html_e('Visitas', 'terramarket'); ?></strong></label>
                <input type="number" class="widefat" id="tm_views_count" name="tm_views_count" value="<?php echo esc_attr((string) $meta['tm_views_count']); ?>" min="0" step="1">
            </div>
            <div class="tm-field">
                <label for="tm_expiration_date"><strong><?php esc_html_e('Vence el', 'terramarket'); ?></strong></label>
                <input type="date" class="widefat" id="tm_expiration_date" name="tm_expiration_date" value="<?php echo esc_attr((string) $meta['tm_expiration_date']); ?>">
            </div>
            <div class="tm-field">
                <label for="tm_sold_at"><strong><?php esc_html_e('Fecha venta', 'terramarket'); ?></strong></label>
                <input type="date" class="widefat" id="tm_sold_at" name="tm_sold_at" value="<?php echo esc_attr((string) $meta['tm_sold_at']); ?>">
            </div>
            <div class="tm-field">
                <label for="tm_declared_sale_amount"><strong><?php esc_html_e('Monto final declarado', 'terramarket'); ?></strong></label>
                <input type="number" class="widefat" id="tm_declared_sale_amount" name="tm_declared_sale_amount" value="<?php echo esc_attr((string) $meta['tm_declared_sale_amount']); ?>" min="0" step="1">
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

        $meta_fields = array(
            'tm_price_clp'            => 'absint',
            'tm_contact_name'         => 'sanitize_text_field',
            'tm_contact_email'        => 'sanitize_email',
            'tm_contact_phone'        => 'sanitize_text_field',
            'tm_listing_status'       => 'sanitize_text_field',
            'tm_featured'             => 'absint',
            'tm_views_count'          => 'absint',
            'tm_expiration_date'      => 'sanitize_text_field',
            'tm_sold_at'              => 'sanitize_text_field',
            'tm_declared_sale_amount' => 'absint',
        );

        foreach ($meta_fields as $key => $sanitize_callback) {
            $raw = $_POST[$key] ?? '';
            $raw = is_string($raw) ? wp_unslash($raw) : $raw;
            $value = is_callable($sanitize_callback) ? call_user_func($sanitize_callback, $raw) : sanitize_text_field((string) $raw);
            update_post_meta($post_id, $key, $value);
        }

        self::maybe_set_single_term($post_id, 'tm_category', absint($_POST['tm_category_term_id'] ?? 0));
        self::maybe_set_single_term($post_id, 'tm_subcategory', absint($_POST['tm_subcategory_term_id'] ?? 0));
        self::maybe_set_single_term($post_id, 'tm_region', absint($_POST['tm_region_term_id'] ?? 0));
        self::maybe_set_single_term($post_id, 'tm_comuna', absint($_POST['tm_comuna_term_id'] ?? 0));
        self::maybe_set_single_term($post_id, 'tm_condition', absint($_POST['tm_condition_term_id'] ?? 0));

        update_post_meta($post_id, 'tm_category_term_id', TM_Helpers::selected_term_id($post_id, 'tm_category'));
        update_post_meta($post_id, 'tm_subcategory_term_id', TM_Helpers::selected_term_id($post_id, 'tm_subcategory'));
        update_post_meta($post_id, 'tm_region_term_id', TM_Helpers::selected_term_id($post_id, 'tm_region'));
        update_post_meta($post_id, 'tm_comuna_term_id', TM_Helpers::selected_term_id($post_id, 'tm_comuna'));
        update_post_meta($post_id, 'tm_condition_term_id', TM_Helpers::selected_term_id($post_id, 'tm_condition'));
    }

    protected static function maybe_set_single_term(int $post_id, string $taxonomy, int $term_id): void
    {
        if ($term_id > 0) {
            wp_set_object_terms($post_id, array($term_id), $taxonomy, false);
        } else {
            wp_set_object_terms($post_id, array(), $taxonomy, false);
        }
    }

    public static function render_dashboard_page(): void
    {
        global $wpdb;
        $listing_count = wp_count_posts('tm_listing');
        $published = $listing_count && isset($listing_count->publish) ? (int) $listing_count->publish : 0;
        $drafts = $listing_count && isset($listing_count->draft) ? (int) $listing_count->draft : 0;
        $lead_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}tm_leads");
        $alert_count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}tm_alerts WHERE is_active = %d", 1));
        $vitrine_count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}tm_vitrine_items WHERE is_active = %d", 1));
        $commission_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}tm_commission_events");
        ?>
        <div class="wrap tm-wrap">
            <h1><?php esc_html_e('Terramarket', 'terramarket'); ?></h1>
            <div class="tm-cards">
                <div class="tm-card"><h2><?php esc_html_e('Versión', 'terramarket'); ?></h2><p><strong><?php echo esc_html(TM_VERSION); ?></strong></p></div>
                <div class="tm-card"><h2><?php esc_html_e('Avisos publicados', 'terramarket'); ?></h2><p><strong><?php echo esc_html((string) $published); ?></strong></p></div>
                <div class="tm-card"><h2><?php esc_html_e('Borradores', 'terramarket'); ?></h2><p><strong><?php echo esc_html((string) $drafts); ?></strong></p></div>
                <div class="tm-card"><h2><?php esc_html_e('Leads', 'terramarket'); ?></h2><p><strong><?php echo esc_html((string) $lead_count); ?></strong></p></div>
                <div class="tm-card"><h2><?php esc_html_e('Alertas activas', 'terramarket'); ?></h2><p><strong><?php echo esc_html((string) $alert_count); ?></strong></p></div>
                <div class="tm-card"><h2><?php esc_html_e('Vitrina activa', 'terramarket'); ?></h2><p><strong><?php echo esc_html((string) $vitrine_count); ?>/5</strong></p></div>
                <div class="tm-card"><h2><?php esc_html_e('Cierres declarados', 'terramarket'); ?></h2><p><strong><?php echo esc_html((string) $commission_count); ?></strong></p></div>
            </div>
            <div class="tm-card">
                <h2><?php esc_html_e('Bloque D incluido', 'terramarket'); ?></h2>
                <ul>
                    <li><?php esc_html_e('Panel vendedor con duplicar, renovar, marcar vendido y alertas diarias.', 'terramarket'); ?></li>
                    <li><?php esc_html_e('Página admin para leads, vitrina manual y cierres/comisiones.', 'terramarket'); ?></li>
                    <li><?php esc_html_e('Motor diario de alertas por email con cron de WordPress.', 'terramarket'); ?></li>
                    <li><?php esc_html_e('Persistencia de leads y cierres declarados para operación comercial.', 'terramarket'); ?></li>
                </ul>
                <p>
                    <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=tm-leads')); ?>"><?php esc_html_e('Ver leads', 'terramarket'); ?></a>
                    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=tm-vitrine')); ?>"><?php esc_html_e('Configurar vitrina', 'terramarket'); ?></a>
                    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=tm-commissions')); ?>"><?php esc_html_e('Revisar cierres', 'terramarket'); ?></a>
                </p>
            </div>
        </div>
        <?php
    }

    public static function render_leads_page(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }
        global $wpdb;
        $table = $wpdb->prefix . 'tm_leads';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d", 200));
        ?>
        <div class="wrap tm-wrap">
            <h1><?php esc_html_e('Leads', 'terramarket'); ?></h1>
            <div class="tm-card tm-table-wrap">
                <?php if ($rows) : ?>
                    <table class="widefat striped tm-admin-table">
                        <thead><tr><th><?php esc_html_e('Fecha', 'terramarket'); ?></th><th><?php esc_html_e('Aviso', 'terramarket'); ?></th><th><?php esc_html_e('Vendedor', 'terramarket'); ?></th><th><?php esc_html_e('Comprador', 'terramarket'); ?></th><th><?php esc_html_e('Mensaje', 'terramarket'); ?></th></tr></thead>
                        <tbody>
                        <?php foreach ($rows as $row) : $seller = get_userdata((int) $row->seller_user_id); ?>
                            <tr>
                                <td><?php echo esc_html(mysql2date('d/m/Y H:i', $row->created_at)); ?></td>
                                <td><a href="<?php echo esc_url(get_edit_post_link((int) $row->listing_id)); ?>"><?php echo esc_html(get_the_title((int) $row->listing_id)); ?></a></td>
                                <td><?php echo esc_html($seller ? ($seller->display_name . ' · ' . $seller->user_email) : '-'); ?></td>
                                <td><?php echo esc_html($row->buyer_name . ' · ' . $row->buyer_email . (! empty($row->buyer_phone) ? ' · ' . $row->buyer_phone : '')); ?></td>
                                <td><?php echo esc_html(wp_trim_words((string) $row->message, 28)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p><?php esc_html_e('Aún no hay leads registrados.', 'terramarket'); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    public static function render_vitrine_page(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }
        global $wpdb;
        $table = $wpdb->prefix . 'tm_vitrine_items';
        $active_rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE is_active = %d ORDER BY sort_order ASC, id ASC", 1));
        $selected_ids = array();
        foreach ((array) $active_rows as $row) {
            $selected_ids[(int) $row->sort_order] = (int) $row->listing_id;
        }
        $listings = get_posts(array(
            'post_type'      => 'tm_listing',
            'post_status'    => 'publish',
            'posts_per_page' => 200,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => array(array('key' => 'tm_listing_status', 'value' => 'active', 'compare' => '=')),
        ));
        $notice = isset($_GET['tm_notice']) ? sanitize_text_field(wp_unslash($_GET['tm_notice'])) : '';
        ?>
        <div class="wrap tm-wrap">
            <h1><?php esc_html_e('Vitrina destacada', 'terramarket'); ?></h1>
            <?php if ($notice) : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div><?php endif; ?>
            <div class="tm-card">
                <p><?php esc_html_e('Selecciona hasta 5 avisos para la vitrina rotativa de la home.', 'terramarket'); ?></p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('tm_save_vitrine_action', 'tm_save_vitrine_nonce'); ?>
                    <input type="hidden" name="action" value="tm_save_vitrine">
                    <div class="tm-admin-grid">
                        <?php for ($i = 1; $i <= 5; $i++) : ?>
                            <div class="tm-field">
                                <label for="tm_vitrine_<?php echo esc_attr((string) $i); ?>"><strong><?php echo esc_html(sprintf(__('Posición %d', 'terramarket'), $i)); ?></strong></label>
                                <select class="widefat" id="tm_vitrine_<?php echo esc_attr((string) $i); ?>" name="tm_vitrine_items[]">
                                    <option value="0"><?php esc_html_e('Vacío', 'terramarket'); ?></option>
                                    <?php foreach ($listings as $listing) : ?>
                                        <option value="<?php echo esc_attr((string) $listing->ID); ?>" <?php selected($selected_ids[$i] ?? 0, $listing->ID); ?>><?php echo esc_html($listing->post_title); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endfor; ?>
                    </div>
                    <p><button type="submit" class="button button-primary"><?php esc_html_e('Guardar vitrina', 'terramarket'); ?></button></p>
                </form>
            </div>
        </div>
        <?php
    }

    public static function render_commissions_page(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }
        global $wpdb;
        $table = $wpdb->prefix . 'tm_commission_events';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d", 200));
        ?>
        <div class="wrap tm-wrap">
            <h1><?php esc_html_e('Cierres y comisiones', 'terramarket'); ?></h1>
            <div class="tm-card tm-table-wrap">
                <?php if ($rows) : ?>
                    <table class="widefat striped tm-admin-table">
                        <thead><tr><th><?php esc_html_e('Fecha', 'terramarket'); ?></th><th><?php esc_html_e('Aviso', 'terramarket'); ?></th><th><?php esc_html_e('Vendedor', 'terramarket'); ?></th><th><?php esc_html_e('Monto declarado', 'terramarket'); ?></th><th><?php esc_html_e('Comisión', 'terramarket'); ?></th><th><?php esc_html_e('Estado', 'terramarket'); ?></th></tr></thead>
                        <tbody>
                        <?php foreach ($rows as $row) : $seller = get_userdata((int) $row->seller_user_id); ?>
                            <tr>
                                <td><?php echo esc_html(mysql2date('d/m/Y H:i', $row->created_at)); ?></td>
                                <td><a href="<?php echo esc_url(get_edit_post_link((int) $row->listing_id)); ?>"><?php echo esc_html(get_the_title((int) $row->listing_id)); ?></a></td>
                                <td><?php echo esc_html($seller ? ($seller->display_name . ' · ' . $seller->user_email) : '-'); ?></td>
                                <td><?php echo esc_html(TM_Helpers::format_price_clp((int) $row->declared_sale_amount)); ?></td>
                                <td><?php echo esc_html(number_format_i18n((float) $row->commission_rate, 2) . '% · ' . TM_Helpers::format_price_clp((int) round((float) $row->commission_amount))); ?></td>
                                <td><?php echo esc_html(ucfirst((string) $row->settlement_status)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p><?php esc_html_e('Aún no hay cierres declarados.', 'terramarket'); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    public static function render_settings_page(): void
    {
        $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'general';
        $allowed = array('general', 'branding', 'marketplace');
        if (! in_array($tab, $allowed, true)) {
            $tab = 'general';
        }

        if (isset($_GET['settings-updated'])) {
            add_settings_error('tm_settings', 'tm_settings_saved', __('Ajustes guardados.', 'terramarket'), 'updated');
        }
        ?>
        <div class="wrap tm-wrap">
            <h1><?php esc_html_e('Ajustes Terramarket', 'terramarket'); ?></h1>
            <?php settings_errors('tm_settings'); ?>
            <nav class="nav-tab-wrapper">
                <a href="<?php echo esc_url(admin_url('admin.php?page=tm-settings&tab=general')); ?>" class="nav-tab <?php echo 'general' === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Generales', 'terramarket'); ?></a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=tm-settings&tab=branding')); ?>" class="nav-tab <?php echo 'branding' === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Branding', 'terramarket'); ?></a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=tm-settings&tab=marketplace')); ?>" class="nav-tab <?php echo 'marketplace' === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Marketplace', 'terramarket'); ?></a>
            </nav>
            <?php if ('general' === $tab) : ?>
                <?php include TM_PLUGIN_DIR . 'admin/partials/settings-general.php'; ?>
            <?php elseif ('branding' === $tab) : ?>
                <?php include TM_PLUGIN_DIR . 'admin/partials/settings-branding.php'; ?>
            <?php else : ?>
                <?php include TM_PLUGIN_DIR . 'admin/partials/settings-marketplace.php'; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function render_tools_page(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }
        $notice = isset($_GET['tm_notice']) ? sanitize_text_field(wp_unslash($_GET['tm_notice'])) : '';
        ?>
        <div class="wrap tm-wrap">
            <h1><?php esc_html_e('Herramientas Terramarket', 'terramarket'); ?></h1>
            <?php if ($notice) : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div><?php endif; ?>
            <div class="tm-card">
                <h2><?php esc_html_e('Seeders', 'terramarket'); ?></h2>
                <p><?php esc_html_e('Reejecuta las cargas base del plugin para categorías, territorios y campos dinámicos.', 'terramarket'); ?></p>
                <div class="tm-tools-grid">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('tm_reseed_all_action', 'tm_reseed_all_nonce'); ?><input type="hidden" name="action" value="tm_reseed_all"><button type="submit" class="button button-primary"><?php esc_html_e('Reseed completo', 'terramarket'); ?></button></form>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('tm_reseed_categories_action', 'tm_reseed_categories_nonce'); ?><input type="hidden" name="action" value="tm_reseed_categories"><button type="submit" class="button"><?php esc_html_e('Recrear categorías y subcategorías', 'terramarket'); ?></button></form>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('tm_reseed_regions_action', 'tm_reseed_regions_nonce'); ?><input type="hidden" name="action" value="tm_reseed_regions"><button type="submit" class="button"><?php esc_html_e('Recrear regiones y comunas', 'terramarket'); ?></button></form>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('tm_run_alerts_action', 'tm_run_alerts_nonce'); ?><input type="hidden" name="action" value="tm_run_alerts"><button type="submit" class="button"><?php esc_html_e('Ejecutar alertas ahora', 'terramarket'); ?></button></form>
                </div>
            </div>
        </div>
        <?php
    }

    public static function handle_reseed_all(): void
    {
        self::guard_admin_post('tm_reseed_all_nonce', 'tm_reseed_all_action');
        TM_Seeder::seed_all();
        self::redirect_tools_notice(__('Seeder completo ejecutado.', 'terramarket'));
    }

    public static function handle_reseed_categories(): void
    {
        self::guard_admin_post('tm_reseed_categories_nonce', 'tm_reseed_categories_action');
        TM_Seeder::seed_conditions();
        TM_Seeder::seed_categories_and_subcategories();
        TM_Seeder::seed_dynamic_fields();
        self::redirect_tools_notice(__('Categorías, subcategorías y campos dinámicos recreados.', 'terramarket'));
    }

    public static function handle_reseed_regions(): void
    {
        self::guard_admin_post('tm_reseed_regions_nonce', 'tm_reseed_regions_action');
        TM_Seeder::seed_regions_and_communes();
        self::redirect_tools_notice(__('Regiones y comunas recreadas.', 'terramarket'));
    }

    public static function handle_save_vitrine(): void
    {
        self::guard_admin_post('tm_save_vitrine_nonce', 'tm_save_vitrine_action');
        global $wpdb;
        $table = $wpdb->prefix . 'tm_vitrine_items';
        $wpdb->query("DELETE FROM {$table}");
        $items = isset($_POST['tm_vitrine_items']) && is_array($_POST['tm_vitrine_items']) ? array_map('absint', wp_unslash($_POST['tm_vitrine_items'])) : array();
        $seen = array();
        foreach (array_values($items) as $index => $listing_id) {
            if (! $listing_id || in_array($listing_id, $seen, true)) {
                continue;
            }
            $seen[] = $listing_id;
            $wpdb->insert(
                $table,
                array(
                    'listing_id'  => $listing_id,
                    'sort_order'  => $index + 1,
                    'is_active'   => 1,
                    'created_at'  => current_time('mysql'),
                    'updated_at'  => current_time('mysql'),
                ),
                array('%d', '%d', '%d', '%s', '%s')
            );
        }
        wp_safe_redirect(admin_url('admin.php?page=tm-vitrine&tm_notice=' . rawurlencode(__('Vitrina actualizada.', 'terramarket'))));
        exit;
    }

    public static function handle_run_alerts(): void
    {
        self::guard_admin_post('tm_run_alerts_nonce', 'tm_run_alerts_action');
        TM_Alerts::process_due_alerts();
        self::redirect_tools_notice(__('Proceso de alertas ejecutado.', 'terramarket'));
    }

    protected static function guard_admin_post(string $nonce_field, string $nonce_action): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('No autorizado.', 'terramarket'));
        }

        $nonce = isset($_POST[$nonce_field]) ? sanitize_text_field(wp_unslash($_POST[$nonce_field])) : '';
        if (! wp_verify_nonce($nonce, $nonce_action)) {
            wp_die(esc_html__('Nonce inválido.', 'terramarket'));
        }
    }

    protected static function redirect_tools_notice(string $message): void
    {
        wp_safe_redirect(admin_url('admin.php?page=tm-tools&tm_notice=' . rawurlencode($message)));
        exit;
    }
}
