<?php

if (! defined('ABSPATH')) {
    exit;
}

class TM_Public
{
    public static function init(): void
    {
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
        add_shortcode('tm_submit_listing', array(__CLASS__, 'render_submit_listing_shortcode'));
        add_action('admin_post_tm_save_listing', array(__CLASS__, 'handle_save_listing'));
        add_action('admin_post_tm_send_lead', array(__CLASS__, 'handle_send_lead'));
        add_action('admin_post_nopriv_tm_send_lead', array(__CLASS__, 'handle_send_lead'));
        add_action('admin_post_nopriv_tm_login', array(__CLASS__, 'handle_login'));
        add_action('admin_post_tm_login', array(__CLASS__, 'handle_login'));
        add_action('admin_post_nopriv_tm_register', array(__CLASS__, 'handle_register'));
        add_action('admin_post_tm_register', array(__CLASS__, 'handle_register'));
        add_action('admin_post_tm_update_profile', array(__CLASS__, 'handle_update_profile'));
        add_action('admin_post_tm_manage_listing', array(__CLASS__, 'handle_manage_listing'));
        add_action('admin_post_tm_save_alert', array(__CLASS__, 'handle_save_alert'));
        add_action('admin_post_tm_delete_alert', array(__CLASS__, 'handle_delete_alert'));
        add_action('wp_ajax_tm_filter_listings', array(__CLASS__, 'ajax_filter_listings'));
        add_action('wp_ajax_nopriv_tm_filter_listings', array(__CLASS__, 'ajax_filter_listings'));
        add_action('template_redirect', array(__CLASS__, 'increment_listing_views'));
        add_action('pre_get_posts', array(__CLASS__, 'adjust_archive_queries'));
    }

    public static function enqueue_assets(): void
    {
        global $post;

        $should_load = TM_Template::is_standalone_request();
        if (! $should_load && $post instanceof WP_Post) {
            $should_load = has_shortcode((string) $post->post_content, 'tm_submit_listing');
        }

        if (! $should_load) {
            return;
        }

        wp_enqueue_style('tm-frontend', TM_PLUGIN_URL . 'assets/css/frontend.css', array(), TM_VERSION);
        wp_enqueue_script('tm-frontend', TM_PLUGIN_URL . 'assets/js/frontend.js', array(), TM_VERSION, true);

        $css_vars = ':root{' . implode('', array_map(static function ($key, $value) {
            return $key . ':' . $value . ';';
        }, array_keys(TM_Helpers::get_branding_vars()), TM_Helpers::get_branding_vars())) . '}';
        wp_add_inline_style('tm-frontend', $css_vars);

        wp_localize_script('tm-frontend', 'tmFrontend', array(
            'ajaxUrl'   => admin_url('admin-ajax.php'),
            'nonce'     => wp_create_nonce('tm_filter_listings'),
            'maxImages' => (int) TM_Helpers::get_option('tm_settings_marketplace', 'max_images', 5),
            'strings'   => array(
                'maxImages' => __('Has alcanzado el máximo de imágenes permitidas.', 'terramarket'),
                'loading'   => __('Cargando avisos...', 'terramarket'),
            ),
        ));
    }

    public static function adjust_archive_queries(WP_Query $query): void
    {
        if (is_admin() || ! $query->is_main_query()) {
            return;
        }

        if (! $query->is_post_type_archive('tm_listing') && ! $query->is_tax(array('tm_category', 'tm_subcategory', 'tm_region', 'tm_comuna', 'tm_condition'))) {
            return;
        }

        $query->set('post_type', 'tm_listing');
        $query->set('posts_per_page', (int) TM_Helpers::get_option('tm_settings_general', 'listings_per_page', 16));
        $query->set('meta_query', array(
            array(
                'key'     => 'tm_listing_status',
                'value'   => 'active',
                'compare' => '=',
            ),
        ));
        $query->set('orderby', 'date');
        $query->set('order', 'DESC');
    }

    public static function increment_listing_views(): void
    {
        if (! is_singular('tm_listing') || is_preview()) {
            return;
        }

        $post_id = get_queried_object_id();
        if (! $post_id) {
            return;
        }

        $status = get_post_meta($post_id, 'tm_listing_status', true) ?: 'active';
        if ('active' !== $status && ! current_user_can('edit_post', $post_id) && ! TM_Helpers::is_listing_owner($post_id, get_current_user_id())) {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            nocache_headers();
            return;
        }

        $key = 'tm_viewed_' . $post_id;
        if (isset($_COOKIE[$key])) {
            return;
        }

        $views = (int) get_post_meta($post_id, 'tm_views_count', true);
        update_post_meta($post_id, 'tm_views_count', $views + 1);
        setcookie($key, '1', time() + HOUR_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN ?: '', is_ssl(), true);
    }

    public static function render_standalone_page(): string
    {
        ob_start();
        $marketplace_name = TM_Helpers::get_marketplace_name();
        $archive_url = get_post_type_archive_link('tm_listing');
        $submit_url  = TM_Helpers::get_submit_page_url();
        $account_url = TM_Helpers::get_account_page_url();
        $auth_url    = TM_Helpers::get_auth_page_url();
        $logo_url    = TM_Helpers::get_logo_url();
        ?>
        <a class="tm-skip-link" href="#tm-main-content"><?php esc_html_e('Saltar al contenido', 'terramarket'); ?></a>
        <div class="tm-app">
            <header class="tm-site-header">
                <div class="tm-site-header__inner">
                    <a class="tm-brand" href="<?php echo esc_url($archive_url); ?>">
                        <?php if ($logo_url) : ?>
                            <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($marketplace_name); ?>">
                        <?php else : ?>
                            <span class="tm-brand__mark">TM</span>
                        <?php endif; ?>
                        <span class="tm-brand__text"><?php echo esc_html($marketplace_name); ?></span>
                    </a>
                    <form class="tm-header-search" method="get" action="<?php echo esc_url($archive_url); ?>">
                        <input type="text" name="keyword" value="<?php echo esc_attr(sanitize_text_field(wp_unslash($_GET['keyword'] ?? ''))); ?>" placeholder="<?php esc_attr_e('Busca maquinaria, animales, propiedades…', 'terramarket'); ?>">
                        <button type="submit"><?php esc_html_e('Buscar', 'terramarket'); ?></button>
                    </form>
                    <div class="tm-header-actions">
                        <a class="tm-link-button" href="<?php echo esc_url($submit_url); ?>"><?php esc_html_e('Publicar aviso', 'terramarket'); ?></a>
                        <?php if (is_user_logged_in()) : ?>
                            <a class="tm-link-button tm-link-button--light" href="<?php echo esc_url($account_url); ?>"><?php esc_html_e('Mi Terramarket', 'terramarket'); ?></a>
                        <?php else : ?>
                            <a class="tm-link-button tm-link-button--light" href="<?php echo esc_url($auth_url); ?>"><?php esc_html_e('Entrar / Registrarse', 'terramarket'); ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            </header>
            <main id="tm-main-content" class="tm-main-wrap" tabindex="-1">
                <?php echo self::render_current_view(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </main>
            <footer class="tm-site-footer">
                <div class="tm-site-footer__inner">
                    <div>
                        <strong><?php echo esc_html($marketplace_name); ?></strong>
                        <p><?php esc_html_e('Marketplace agro standalone para publicar, explorar y generar oportunidades comerciales.', 'terramarket'); ?></p>
                    </div>
                    <nav class="tm-footer-links" aria-label="<?php esc_attr_e('Navegación del pie', 'terramarket'); ?>">
                        <a href="<?php echo esc_url($archive_url); ?>"><?php esc_html_e('Inicio', 'terramarket'); ?></a>
                        <a href="<?php echo esc_url($submit_url); ?>"><?php esc_html_e('Publicar', 'terramarket'); ?></a>
                        <a href="<?php echo esc_url($account_url); ?>"><?php esc_html_e('Mi cuenta', 'terramarket'); ?></a>
                    </nav>
                </div>
            </footer>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    protected static function render_current_view(): string
    {
        $tm_view = (string) get_query_var('tm_view');
        if ('submit' === $tm_view) {
            return self::render_submit_listing_shortcode();
        }
        if ('account' === $tm_view) {
            return self::render_account_view();
        }
        if ('auth' === $tm_view) {
            return self::render_auth_view();
        }
        if (is_singular('tm_listing')) {
            return self::render_single_view(get_queried_object_id());
        }
        return self::render_archive_view();
    }

    protected static function get_active_filters_from_request(array $overrides = array()): array
    {
        $filters = array(
            'keyword'       => sanitize_text_field(wp_unslash($_GET['keyword'] ?? '')),
            'category'      => sanitize_text_field(wp_unslash($_GET['category'] ?? '')),
            'subcategory'   => sanitize_text_field(wp_unslash($_GET['subcategory'] ?? '')),
            'region'        => sanitize_text_field(wp_unslash($_GET['region'] ?? '')),
            'comuna'        => sanitize_text_field(wp_unslash($_GET['comuna'] ?? '')),
            'condition'     => sanitize_text_field(wp_unslash($_GET['condition'] ?? '')),
            'price_min'     => absint($_GET['price_min'] ?? 0),
            'price_max'     => absint($_GET['price_max'] ?? 0),
            'featured_only' => ! empty($_GET['featured_only']) ? 1 : 0,
            'with_photo'    => ! empty($_GET['with_photo']) ? 1 : 0,
            'order'         => sanitize_text_field(wp_unslash($_GET['order'] ?? 'featured_recent')),
            'page'          => max(1, absint($_GET['tm_page'] ?? 1)),
        );

        if (is_tax()) {
            $term = get_queried_object();
            if ($term instanceof WP_Term) {
                if ('tm_category' === $term->taxonomy) {
                    $filters['category'] = $term->slug;
                } elseif ('tm_region' === $term->taxonomy) {
                    $filters['region'] = $term->slug;
                } elseif ('tm_comuna' === $term->taxonomy) {
                    $filters['comuna'] = $term->slug;
                } elseif ('tm_condition' === $term->taxonomy) {
                    $filters['condition'] = $term->slug;
                }
            }
        }

        return array_merge($filters, $overrides);
    }

    protected static function has_active_filters(array $filters): bool
    {
        foreach (array('keyword', 'category', 'subcategory', 'region', 'comuna', 'condition', 'price_min', 'price_max') as $key) {
            if (! empty($filters[$key])) {
                return true;
            }
        }

        return ! empty($filters['featured_only']) || ! empty($filters['with_photo']) || ! empty($filters['order']) && 'featured_recent' !== $filters['order'];
    }

    protected static function get_listing_query_args(array $filters): array
    {
        $args = array(
            'post_type'      => 'tm_listing',
            'post_status'    => 'publish',
            'posts_per_page' => (int) TM_Helpers::get_option('tm_settings_general', 'listings_per_page', 16),
            'paged'          => max(1, (int) ($filters['page'] ?? 1)),
            's'              => (string) ($filters['keyword'] ?? ''),
            'meta_query'     => array(
                array(
                    'key'     => 'tm_listing_status',
                    'value'   => 'active',
                    'compare' => '=',
                ),
            ),
            'tax_query'      => array(),
        );

        if (! empty($filters['featured_only'])) {
            $args['meta_query'][] = array('key' => 'tm_featured', 'value' => 1, 'compare' => '=');
        }
        if (! empty($filters['with_photo'])) {
            $args['meta_query'][] = array('key' => '_thumbnail_id', 'compare' => 'EXISTS');
        }
        if (! empty($filters['price_min'])) {
            $args['meta_query'][] = array('key' => 'tm_price_clp', 'value' => (int) $filters['price_min'], 'type' => 'NUMERIC', 'compare' => '>=');
        }
        if (! empty($filters['price_max'])) {
            $args['meta_query'][] = array('key' => 'tm_price_clp', 'value' => (int) $filters['price_max'], 'type' => 'NUMERIC', 'compare' => '<=');
        }

        foreach (array('tm_category' => 'category', 'tm_subcategory' => 'subcategory', 'tm_region' => 'region', 'tm_comuna' => 'comuna', 'tm_condition' => 'condition') as $taxonomy => $key) {
            if (! empty($filters[$key])) {
                $args['tax_query'][] = array('taxonomy' => $taxonomy, 'field' => 'slug', 'terms' => array($filters[$key]));
            }
        }
        if (count($args['tax_query']) > 1) {
            $args['tax_query']['relation'] = 'AND';
        }

        switch ($filters['order'] ?? 'featured_recent') {
            case 'price_asc':
                $args['meta_key'] = 'tm_price_clp';
                $args['orderby'] = 'meta_value_num';
                $args['order'] = 'ASC';
                break;
            case 'price_desc':
                $args['meta_key'] = 'tm_price_clp';
                $args['orderby'] = 'meta_value_num';
                $args['order'] = 'DESC';
                break;
            case 'oldest':
                $args['orderby'] = 'date';
                $args['order'] = 'ASC';
                break;
            case 'recent':
                $args['orderby'] = 'date';
                $args['order'] = 'DESC';
                break;
            default:
                $args['meta_key'] = 'tm_featured';
                $args['orderby'] = array('meta_value_num' => 'DESC', 'date' => 'DESC');
                break;
        }

        return $args;
    }

    protected static function render_archive_view(): string
    {
        $filters = self::get_active_filters_from_request();
        $query = new WP_Query(self::get_listing_query_args($filters));
        $categories = TM_Helpers::get_terms_for_select('tm_category');
        $regions = TM_Helpers::get_regions_ordered();
        $current_term = is_tax() ? get_queried_object() : null;
        $vitrine_items = is_post_type_archive('tm_listing') ? self::get_vitrine_items(5) : array();

        ob_start();
        ?>
        <section class="tm-home-hero">
            <div class="tm-home-hero__copy">
                <span class="tm-home-kicker"><?php esc_html_e('Marketplace agro', 'terramarket'); ?></span>
                <h1><?php echo esc_html($current_term instanceof WP_Term ? $current_term->name : TM_Helpers::get_marketplace_name()); ?></h1>
                <p><?php echo esc_html($current_term instanceof WP_Term ? wp_strip_all_tags($current_term->description ?: __('Explora avisos filtrados por esta vista.', 'terramarket')) : __('Compra, vende y conecta oportunidades del mundo agro en una experiencia standalone rápida y lista para móvil.', 'terramarket')); ?></p>
            </div>
            <form class="tm-hero-search" method="get" action="<?php echo esc_url(get_post_type_archive_link('tm_listing')); ?>">
                <div class="tm-hero-search__grid">
                    <input type="text" name="keyword" value="<?php echo esc_attr($filters['keyword']); ?>" placeholder="<?php esc_attr_e('¿Qué estás buscando?', 'terramarket'); ?>">
                    <select name="category">
                        <option value=""><?php esc_html_e('Categoría', 'terramarket'); ?></option>
                        <?php foreach ($categories as $category) : ?>
                            <option value="<?php echo esc_attr($category->slug); ?>" <?php selected($filters['category'], $category->slug); ?>><?php echo esc_html($category->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="region">
                        <option value=""><?php esc_html_e('Región', 'terramarket'); ?></option>
                        <?php foreach ($regions as $region) : ?>
                            <option value="<?php echo esc_attr($region->slug); ?>" <?php selected($filters['region'], $region->slug); ?>><?php echo esc_html($region->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit"><?php esc_html_e('Buscar', 'terramarket'); ?></button>
                </div>
            </form>
        </section>

        <?php if (is_post_type_archive('tm_listing')) : ?>
            <section class="tm-category-strip">
                <div class="tm-section-head"><h2><?php esc_html_e('Explora por categoría', 'terramarket'); ?></h2></div>
                <div class="tm-category-grid">
                    <?php foreach ($categories as $category) : ?>
                        <a class="tm-category-card" href="<?php echo esc_url(get_term_link($category)); ?>"><strong><?php echo esc_html($category->name); ?></strong><span><?php esc_html_e('Ver avisos', 'terramarket'); ?></span></a>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php if (! empty($vitrine_items)) : ?>
                <section class="tm-vitrine" data-tm-vitrine>
                    <div class="tm-section-head"><h2><?php esc_html_e('Vitrina destacada', 'terramarket'); ?></h2><p><?php esc_html_e('5 avisos rotando automáticamente.', 'terramarket'); ?></p></div>
                    <div class="tm-vitrine__track">
                        <?php foreach ($vitrine_items as $index => $post_id) : $image = TM_Helpers::get_listing_primary_image_url($post_id, 'large'); $price = (int) get_post_meta($post_id, 'tm_price_clp', true); $region = TM_Helpers::get_term_name_for_post($post_id, 'tm_region'); $comuna = TM_Helpers::get_term_name_for_post($post_id, 'tm_comuna'); ?>
                            <article class="tm-vitrine__item <?php echo 0 === $index ? 'is-active' : ''; ?>">
                                <a class="tm-vitrine__link" href="<?php echo esc_url(get_permalink($post_id)); ?>">
                                    <div class="tm-vitrine__media"><?php if ($image) : ?><img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr(get_the_title($post_id)); ?>" loading="lazy" decoding="async"><?php endif; ?></div>
                                    <div class="tm-vitrine__content"><span class="tm-chip"><?php esc_html_e('Destacado', 'terramarket'); ?></span><h3><?php echo esc_html(get_the_title($post_id)); ?></h3><p class="tm-vitrine__price"><?php echo esc_html(TM_Helpers::format_price_clp($price)); ?></p><p><?php echo esc_html(trim($comuna . ', ' . $region, ', ')); ?></p></div>
                                </a>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        <?php endif; ?>

        <section class="tm-market-section">
            <div class="tm-section-head tm-section-head--tight"><div><h2><?php esc_html_e('Avisos disponibles', 'terramarket'); ?></h2><p><?php echo esc_html(sprintf(_n('%d aviso encontrado', '%d avisos encontrados', (int) $query->found_posts, 'terramarket'), (int) $query->found_posts)); ?></p></div><div class="tm-section-actions"><?php if (self::has_active_filters($filters)) : ?><a class="tm-link-button tm-link-button--light" href="<?php echo esc_url(get_post_type_archive_link('tm_listing')); ?>"><?php esc_html_e('Limpiar filtros', 'terramarket'); ?></a><?php endif; ?><a class="tm-link-button" href="<?php echo esc_url(TM_Helpers::get_submit_page_url()); ?>"><?php esc_html_e('Publicar aviso', 'terramarket'); ?></a></div></div>
            <?php echo self::render_filter_bar($filters); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <div id="tm-results" class="tm-results-wrap" data-base-url="<?php echo esc_url(TM_Helpers::current_url(array('tm_page'))); ?>"><?php echo self::render_results_markup($query, $filters); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
        </section>
        <?php
        wp_reset_postdata();
        return (string) ob_get_clean();
    }

    protected static function render_filter_bar(array $filters): string
    {
        $categories = TM_Helpers::get_terms_for_select('tm_category');
        $subcategories = TM_Helpers::get_terms_for_select('tm_subcategory');
        $regions = TM_Helpers::get_regions_ordered();
        $comunas = TM_Helpers::get_terms_for_select('tm_comuna');
        $conditions = TM_Helpers::get_terms_for_select('tm_condition');
        ob_start();
        ?>
        <form id="tm-archive-filters" class="tm-filter-bar" method="get" action="<?php echo esc_url(TM_Helpers::current_url(array('tm_page'))); ?>" data-auto-submit="1">
            <div class="tm-filter-bar__top">
                <div>
                    <strong><?php esc_html_e('Filtrar avisos', 'terramarket'); ?></strong>
                    <p><?php esc_html_e('Ajusta la búsqueda y los resultados se actualizarán sin recargar.', 'terramarket'); ?></p>
                </div>
                <div class="tm-filter-bar__actions">
                    <button type="submit" class="tm-link-button"><?php esc_html_e('Aplicar', 'terramarket'); ?></button>
                    <a class="tm-link-button tm-link-button--light" href="<?php echo esc_url(get_post_type_archive_link('tm_listing')); ?>"><?php esc_html_e('Limpiar', 'terramarket'); ?></a>
                </div>
            </div>
            <input type="hidden" name="tm_page" value="1">
            <div class="tm-filter-bar__row">
                <input type="text" name="keyword" value="<?php echo esc_attr($filters['keyword']); ?>" placeholder="<?php esc_attr_e('Palabra clave', 'terramarket'); ?>">
                <select name="category" id="tm_filter_category"><option value=""><?php esc_html_e('Categoría', 'terramarket'); ?></option><?php foreach ($categories as $category) : ?><option value="<?php echo esc_attr($category->slug); ?>" <?php selected($filters['category'], $category->slug); ?>><?php echo esc_html($category->name); ?></option><?php endforeach; ?></select>
                <select name="subcategory" id="tm_filter_subcategory"><option value=""><?php esc_html_e('Subcategoría', 'terramarket'); ?></option><?php foreach ($subcategories as $term) : $parent_id = (int) get_term_meta($term->term_id, 'tm_parent_category_id', true); $parent = $parent_id ? get_term($parent_id, 'tm_category') : null; ?><option value="<?php echo esc_attr($term->slug); ?>" data-parent-category="<?php echo esc_attr($parent instanceof WP_Term ? $parent->slug : ''); ?>" <?php selected($filters['subcategory'], $term->slug); ?>><?php echo esc_html($term->name); ?></option><?php endforeach; ?></select>
                <select name="region" id="tm_filter_region"><option value=""><?php esc_html_e('Región', 'terramarket'); ?></option><?php foreach ($regions as $region) : ?><option value="<?php echo esc_attr($region->slug); ?>" <?php selected($filters['region'], $region->slug); ?>><?php echo esc_html($region->name); ?></option><?php endforeach; ?></select>
                <select name="comuna" id="tm_filter_comuna"><option value=""><?php esc_html_e('Comuna', 'terramarket'); ?></option><?php foreach ($comunas as $term) : $region_id = (int) get_term_meta($term->term_id, 'tm_region_id', true); $region = $region_id ? get_term($region_id, 'tm_region') : null; ?><option value="<?php echo esc_attr($term->slug); ?>" data-region-id="<?php echo esc_attr($region instanceof WP_Term ? $region->slug : ''); ?>" <?php selected($filters['comuna'], $term->slug); ?>><?php echo esc_html($term->name); ?></option><?php endforeach; ?></select>
                <select name="condition"><option value=""><?php esc_html_e('Condición', 'terramarket'); ?></option><?php foreach ($conditions as $term) : ?><option value="<?php echo esc_attr($term->slug); ?>" <?php selected($filters['condition'], $term->slug); ?>><?php echo esc_html($term->name); ?></option><?php endforeach; ?></select>
                <input type="number" name="price_min" value="<?php echo esc_attr((string) $filters['price_min']); ?>" placeholder="<?php esc_attr_e('Precio mínimo', 'terramarket'); ?>">
                <input type="number" name="price_max" value="<?php echo esc_attr((string) $filters['price_max']); ?>" placeholder="<?php esc_attr_e('Precio máximo', 'terramarket'); ?>">
                <select name="order"><option value="featured_recent" <?php selected($filters['order'], 'featured_recent'); ?>><?php esc_html_e('Destacados primero', 'terramarket'); ?></option><option value="recent" <?php selected($filters['order'], 'recent'); ?>><?php esc_html_e('Más recientes', 'terramarket'); ?></option><option value="oldest" <?php selected($filters['order'], 'oldest'); ?>><?php esc_html_e('Más antiguos', 'terramarket'); ?></option><option value="price_asc" <?php selected($filters['order'], 'price_asc'); ?>><?php esc_html_e('Precio menor a mayor', 'terramarket'); ?></option><option value="price_desc" <?php selected($filters['order'], 'price_desc'); ?>><?php esc_html_e('Precio mayor a menor', 'terramarket'); ?></option></select>
                <label class="tm-check"><input type="checkbox" name="with_photo" value="1" <?php checked(! empty($filters['with_photo'])); ?>><?php esc_html_e('Solo con foto', 'terramarket'); ?></label>
                <label class="tm-check"><input type="checkbox" name="featured_only" value="1" <?php checked(! empty($filters['featured_only'])); ?>><?php esc_html_e('Solo destacados', 'terramarket'); ?></label>
                            </div>
        </form>
        <?php
        return (string) ob_get_clean();
    }

    protected static function render_results_markup(WP_Query $query, array $filters): string
    {
        ob_start();
        if ($query->have_posts()) : ?>
            <div class="tm-results-toolbar" aria-live="polite">
                <p class="tm-results-count"><?php echo esc_html(sprintf(_n('%d resultado', '%d resultados', (int) $query->found_posts, 'terramarket'), (int) $query->found_posts)); ?></p>
                <p class="tm-helper-text"><?php esc_html_e('Actualización en vivo con filtros AJAX.', 'terramarket'); ?></p>
            </div>
            <div class="tm-listing-grid">
                <?php while ($query->have_posts()) : $query->the_post(); $post_id = get_the_ID(); $price = (int) get_post_meta($post_id, 'tm_price_clp', true); $region = TM_Helpers::get_term_name_for_post($post_id, 'tm_region'); $comuna = TM_Helpers::get_term_name_for_post($post_id, 'tm_comuna'); $category = TM_Helpers::get_term_name_for_post($post_id, 'tm_category'); $image_url = TM_Helpers::get_listing_primary_image_url($post_id, 'medium_large'); $is_featured = (int) get_post_meta($post_id, 'tm_featured', true); ?>
                    <article class="tm-listing-card">
                        <a class="tm-listing-card__media" href="<?php the_permalink(); ?>"><?php if ($image_url) : ?><img src="<?php echo esc_url($image_url); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy" decoding="async"><?php else : ?><span class="tm-placeholder"><?php esc_html_e('Sin imagen', 'terramarket'); ?></span><?php endif; ?></a>
                        <div class="tm-listing-card__body">
                            <div class="tm-card-topline"><span class="tm-chip"><?php echo esc_html($category ?: __('Aviso', 'terramarket')); ?></span><?php if ($is_featured) : ?><span class="tm-chip tm-chip--success"><?php esc_html_e('Destacado', 'terramarket'); ?></span><?php endif; ?></div>
                            <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                            <p class="tm-listing-price"><?php echo esc_html(TM_Helpers::format_price_clp($price)); ?></p>
                            <p class="tm-listing-meta"><?php echo esc_html(trim($comuna . ', ' . $region, ', ')); ?></p>
                            <div class="tm-card-footer"><span><?php echo esc_html(get_the_date('d/m/Y', $post_id)); ?></span><a class="tm-text-link" href="<?php the_permalink(); ?>"><?php esc_html_e('Ver detalle', 'terramarket'); ?></a></div>
                        </div>
                    </article>
                <?php endwhile; ?>
            </div>
            <?php echo self::render_pagination($query, $filters); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <?php else : ?>
            <div class="tm-empty-state"><div class="tm-empty-state__icon" aria-hidden="true">⌕</div><h3><?php esc_html_e('No encontramos avisos con esos filtros.', 'terramarket'); ?></h3><p><?php esc_html_e('Prueba ajustando la búsqueda o quitando algunos filtros.', 'terramarket'); ?></p><p><a class="tm-link-button tm-link-button--light" href="<?php echo esc_url(get_post_type_archive_link('tm_listing')); ?>"><?php esc_html_e('Ver todos los avisos', 'terramarket'); ?></a></p></div>
        <?php endif;
        return (string) ob_get_clean();
    }

    protected static function render_pagination(WP_Query $query, array $filters): string
    {
        if ($query->max_num_pages <= 1) {
            return '';
        }
        $current = max(1, (int) ($filters['page'] ?? 1));
        ob_start();
        ?>
        <nav class="tm-pagination" aria-label="<?php esc_attr_e('Paginación de avisos', 'terramarket'); ?>">
            <?php if ($current > 1) : ?>
                <a href="#" class="tm-page-link tm-page-link--nav" data-page="<?php echo esc_attr((string) ($current - 1)); ?>"><?php esc_html_e('Anterior', 'terramarket'); ?></a>
            <?php endif; ?>
            <?php for ($page = 1; $page <= (int) $query->max_num_pages; $page++) : ?>
                <a href="#" class="tm-page-link <?php echo $page === $current ? 'is-active' : ''; ?>" data-page="<?php echo esc_attr((string) $page); ?>" <?php echo $page === $current ? 'aria-current="page"' : ''; ?>><?php echo esc_html((string) $page); ?></a>
            <?php endfor; ?>
            <?php if ($current < (int) $query->max_num_pages) : ?>
                <a href="#" class="tm-page-link tm-page-link--nav" data-page="<?php echo esc_attr((string) ($current + 1)); ?>"><?php esc_html_e('Siguiente', 'terramarket'); ?></a>
            <?php endif; ?>
        </nav>
        <?php
        return (string) ob_get_clean();
    }

    protected static function get_vitrine_items(int $limit = 5): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'tm_vitrine_items';
        $ids = $wpdb->get_col($wpdb->prepare("SELECT listing_id FROM {$table} WHERE is_active = 1 ORDER BY sort_order ASC, id ASC LIMIT %d", $limit));
        $ids = array_values(array_filter(array_map('absint', is_array($ids) ? $ids : array())));
        if (! empty($ids)) {
            return $ids;
        }
        return get_posts(array(
            'post_type'      => 'tm_listing',
            'posts_per_page' => $limit,
            'meta_key'       => 'tm_featured',
            'orderby'        => array('meta_value_num' => 'DESC', 'date' => 'DESC'),
            'fields'         => 'ids',
        ));
    }

    protected static function render_single_view(int $post_id): string
    {
        if (! $post_id || 'tm_listing' !== get_post_type($post_id)) {
            return '<div class="tm-empty-state"><h3>' . esc_html__('Aviso no encontrado.', 'terramarket') . '</h3></div>';
        }
        $price = (int) get_post_meta($post_id, 'tm_price_clp', true);
        $status = get_post_meta($post_id, 'tm_listing_status', true) ?: 'active';
        $contact_name = get_post_meta($post_id, 'tm_contact_name', true);
        $contact_phone = get_post_meta($post_id, 'tm_contact_phone', true);
        $region = TM_Helpers::get_term_name_for_post($post_id, 'tm_region');
        $comuna = TM_Helpers::get_term_name_for_post($post_id, 'tm_comuna');
        $condition = TM_Helpers::get_term_name_for_post($post_id, 'tm_condition');
        $category = TM_Helpers::get_term_name_for_post($post_id, 'tm_category');
        $subcategory = TM_Helpers::get_term_name_for_post($post_id, 'tm_subcategory');
        $views = (int) get_post_meta($post_id, 'tm_views_count', true);
        $gallery = TM_Helpers::get_listing_gallery($post_id);
        $share_link = 'mailto:?subject=' . rawurlencode(sprintf(__('Mira este aviso: %s', 'terramarket'), get_the_title($post_id))) . '&body=' . rawurlencode(get_permalink($post_id));
        $notice = isset($_GET['tm_notice']) ? sanitize_text_field(wp_unslash($_GET['tm_notice'])) : '';
        $error = isset($_GET['tm_error']) ? sanitize_text_field(wp_unslash($_GET['tm_error'])) : '';

        ob_start();
        ?>
        <section class="tm-page-hero tm-page-hero--single"><div><span class="tm-chip"><?php echo esc_html($category ?: __('Aviso', 'terramarket')); ?></span><h1><?php echo esc_html(get_the_title($post_id)); ?></h1><p><?php echo esc_html(trim($subcategory . ' · ' . $comuna . ', ' . $region, ' ·,')); ?></p></div><a class="tm-link-button tm-link-button--light" href="<?php echo esc_url($share_link); ?>"><?php esc_html_e('Compartir por email', 'terramarket'); ?></a></section>
        <?php if ($notice) : ?><div class="tm-notice tm-notice-success"><?php echo esc_html(self::notice_label($notice)); ?></div><?php endif; ?>
        <?php if ($error) : ?><div class="tm-notice tm-notice-error"><?php echo esc_html(self::notice_label($error)); ?></div><?php endif; ?>
        <div class="tm-single-layout">
            <section class="tm-single-main">
                <div class="tm-gallery"><?php if (! empty($gallery)) : ?><div class="tm-gallery-main"><img id="tm-main-image" src="<?php echo esc_url($gallery[0]['full']); ?>" alt="<?php echo esc_attr($gallery[0]['alt']); ?>" decoding="async"></div><?php if (count($gallery) > 1) : ?><div class="tm-gallery-thumbs"><?php foreach ($gallery as $index => $image) : ?><button type="button" class="tm-gallery-thumb <?php echo 0 === $index ? 'is-active' : ''; ?>" data-full-image="<?php echo esc_url($image['full']); ?>"><img src="<?php echo esc_url($image['thumb']); ?>" alt="<?php echo esc_attr($image['alt']); ?>" loading="lazy" decoding="async"></button><?php endforeach; ?></div><?php endif; ?><?php else : ?><div class="tm-gallery-empty"><?php esc_html_e('Este aviso no tiene imágenes.', 'terramarket'); ?></div><?php endif; ?></div>
                <article class="tm-panel"><div class="tm-single-price-row"><div><span class="tm-chip"><?php echo esc_html(TM_Helpers::get_listing_status_label($status)); ?></span><p class="tm-single-price"><?php echo esc_html(TM_Helpers::format_price_clp($price)); ?></p></div><div class="tm-single-stats"><?php echo esc_html($views); ?> <?php esc_html_e('visitas', 'terramarket'); ?></div></div><div class="tm-single-meta-grid"><div><strong><?php esc_html_e('Condición', 'terramarket'); ?></strong><span><?php echo esc_html($condition ?: '-'); ?></span></div><div><strong><?php esc_html_e('Categoría', 'terramarket'); ?></strong><span><?php echo esc_html($category ?: '-'); ?></span></div><div><strong><?php esc_html_e('Subcategoría', 'terramarket'); ?></strong><span><?php echo esc_html($subcategory ?: '-'); ?></span></div><div><strong><?php esc_html_e('Ubicación', 'terramarket'); ?></strong><span><?php echo esc_html(trim($comuna . ', ' . $region, ', ')); ?></span></div></div><div class="tm-content"><?php echo wpautop(wp_kses_post(get_post_field('post_content', $post_id))); ?></div></article>
            </section>
            <aside class="tm-single-sidebar">
                <section class="tm-panel"><h2><?php esc_html_e('Contacto del vendedor', 'terramarket'); ?></h2><ul class="tm-seller-list"><li><strong><?php esc_html_e('Nombre', 'terramarket'); ?>:</strong> <?php echo esc_html($contact_name ?: '-'); ?></li><?php if ($contact_phone) : ?><li><strong><?php esc_html_e('Teléfono', 'terramarket'); ?>:</strong> <?php echo esc_html($contact_phone); ?></li><?php endif; ?></ul></section>
                <section class="tm-panel"><h2><?php esc_html_e('Contactar vendedor', 'terramarket'); ?></h2><form method="post" action="<?php echo esc_url(TM_Helpers::get_submit_form_url()); ?>" class="tm-contact-form"><?php wp_nonce_field('tm_send_lead', 'tm_send_lead_nonce'); ?><input type="hidden" name="action" value="tm_send_lead"><input type="hidden" name="tm_listing_id" value="<?php echo esc_attr((string) $post_id); ?>"><input type="text" name="tm_website" value="" class="tm-honeypot" tabindex="-1" autocomplete="off"><div class="tm-field"><label for="tm_buyer_name"><?php esc_html_e('Nombre', 'terramarket'); ?></label><input type="text" id="tm_buyer_name" name="tm_buyer_name" required></div><div class="tm-field"><label for="tm_buyer_email"><?php esc_html_e('Email', 'terramarket'); ?></label><input type="email" id="tm_buyer_email" name="tm_buyer_email" required></div><div class="tm-field"><label for="tm_buyer_phone"><?php esc_html_e('Teléfono', 'terramarket'); ?></label><input type="text" id="tm_buyer_phone" name="tm_buyer_phone"></div><div class="tm-field"><label for="tm_message"><?php esc_html_e('Mensaje', 'terramarket'); ?></label><textarea id="tm_message" name="tm_message" rows="5" required><?php echo esc_textarea(sprintf(__('Hola, me interesa el aviso "%s".', 'terramarket'), get_the_title($post_id))); ?></textarea></div><button type="submit" class="tm-link-button tm-link-button--block"><?php esc_html_e('Enviar mensaje', 'terramarket'); ?></button></form></section>
            </aside>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    public static function render_submit_listing_shortcode(array $atts = array()): string
    {
        if (! is_user_logged_in()) {
            $auth_url = TM_Helpers::get_auth_page_url(array('redirect_to' => TM_Helpers::current_url()));
            return '<div class="tm-empty-state"><h2>' . esc_html__('Publica tu aviso', 'terramarket') . '</h2><p>' . esc_html__('Debes iniciar sesión para publicar o editar avisos.', 'terramarket') . '</p><p><a class="tm-link-button" href="' . esc_url($auth_url) . '">' . esc_html__('Entrar o registrarte', 'terramarket') . '</a></p></div>';
        }

        $user_id = get_current_user_id();
        $listing_id = isset($_GET['tm_edit_listing']) ? absint($_GET['tm_edit_listing']) : 0;
        $is_edit = $listing_id > 0 && TM_Helpers::is_listing_owner($listing_id, $user_id);
        if ($listing_id > 0 && ! $is_edit) {
            return '<div class="tm-empty-state"><p>' . esc_html__('No tienes permiso para editar este aviso.', 'terramarket') . '</p></div>';
        }

        $notice = isset($_GET['tm_notice']) ? sanitize_text_field(wp_unslash($_GET['tm_notice'])) : '';
        $error = isset($_GET['tm_error']) ? sanitize_text_field(wp_unslash($_GET['tm_error'])) : '';
        $meta = array(
            'title'             => $is_edit ? get_the_title($listing_id) : '',
            'description'       => $is_edit ? get_post_field('post_content', $listing_id) : '',
            'tm_price_clp'      => $is_edit ? get_post_meta($listing_id, 'tm_price_clp', true) : '',
            'tm_contact_name'   => $is_edit ? get_post_meta($listing_id, 'tm_contact_name', true) : wp_get_current_user()->display_name,
            'tm_contact_email'  => $is_edit ? get_post_meta($listing_id, 'tm_contact_email', true) : wp_get_current_user()->user_email,
            'tm_contact_phone'  => $is_edit ? get_post_meta($listing_id, 'tm_contact_phone', true) : (string) get_user_meta($user_id, 'tm_contact_phone', true),
            'tm_listing_status' => $is_edit ? get_post_meta($listing_id, 'tm_listing_status', true) : 'active',
        );
        $selected_category = $is_edit ? TM_Helpers::selected_term_id($listing_id, 'tm_category') : 0;
        $selected_subcategory = $is_edit ? TM_Helpers::selected_term_id($listing_id, 'tm_subcategory') : 0;
        $selected_region = $is_edit ? TM_Helpers::selected_term_id($listing_id, 'tm_region') : 0;
        $selected_comuna = $is_edit ? TM_Helpers::selected_term_id($listing_id, 'tm_comuna') : 0;
        $selected_condition = $is_edit ? TM_Helpers::selected_term_id($listing_id, 'tm_condition') : 0;
        $categories = TM_Helpers::get_terms_for_select('tm_category');
        $subcategories = TM_Helpers::get_terms_for_select('tm_subcategory');
        $regions = TM_Helpers::get_regions_ordered();
        $comunas = TM_Helpers::get_terms_for_select('tm_comuna');
        $conditions = TM_Helpers::get_terms_for_select('tm_condition');
        $gallery = $is_edit ? TM_Helpers::get_listing_gallery($listing_id) : array();
        $max_images = (int) TM_Helpers::get_option('tm_settings_marketplace', 'max_images', 5);

        ob_start();
        ?>
        <section class="tm-page-hero"><div><span class="tm-chip"><?php echo esc_html($is_edit ? __('Editar aviso', 'terramarket') : __('Nuevo aviso', 'terramarket')); ?></span><h1><?php echo esc_html($is_edit ? __('Edita tu publicación', 'terramarket') : __('Publica tu aviso en Terramarket', 'terramarket')); ?></h1><p><?php esc_html_e('Completa los datos base del aviso, sube hasta 5 imágenes y publica directamente.', 'terramarket'); ?></p></div></section>
        <?php if ($notice) : ?><div class="tm-notice tm-notice-success"><?php echo esc_html(self::notice_label($notice)); ?></div><?php endif; ?>
        <?php if ($error) : ?><div class="tm-notice tm-notice-error"><?php echo esc_html(self::notice_label($error)); ?></div><?php endif; ?>
        <form class="tm-submit-form" method="post" action="<?php echo esc_url(TM_Helpers::get_submit_form_url()); ?>" enctype="multipart/form-data">
            <?php wp_nonce_field('tm_save_listing_frontend', 'tm_save_listing_frontend_nonce'); ?>
            <input type="hidden" name="action" value="tm_save_listing">
            <input type="hidden" name="tm_listing_id" value="<?php echo esc_attr((string) $listing_id); ?>">
            <input type="hidden" name="tm_redirect" value="<?php echo esc_url(TM_Helpers::current_url(array('tm_notice', 'tm_error'))); ?>">
            <div class="tm-form-grid">
                <div class="tm-form-main">
                    <section class="tm-panel"><h2><?php esc_html_e('Información principal', 'terramarket'); ?></h2><div class="tm-field"><label for="tm_title"><?php esc_html_e('Título', 'terramarket'); ?></label><input type="text" id="tm_title" name="tm_title" maxlength="140" value="<?php echo esc_attr($meta['title']); ?>" required></div><div class="tm-field"><label for="tm_description"><?php esc_html_e('Descripción', 'terramarket'); ?></label><textarea id="tm_description" name="tm_description" rows="7" required><?php echo esc_textarea($meta['description']); ?></textarea></div><div class="tm-field-row tm-field-row-2"><div class="tm-field"><label for="tm_price_clp"><?php esc_html_e('Precio CLP', 'terramarket'); ?></label><input type="number" id="tm_price_clp" name="tm_price_clp" min="1" step="1" value="<?php echo esc_attr((string) $meta['tm_price_clp']); ?>" required></div><div class="tm-field"><label for="tm_condition_term_id"><?php esc_html_e('Condición', 'terramarket'); ?></label><select id="tm_condition_term_id" name="tm_condition_term_id" required><option value=""><?php esc_html_e('Seleccionar', 'terramarket'); ?></option><?php foreach ($conditions as $term) : ?><option value="<?php echo esc_attr((string) $term->term_id); ?>" <?php selected($selected_condition, $term->term_id); ?>><?php echo esc_html($term->name); ?></option><?php endforeach; ?></select></div></div></section>
                    <section class="tm-panel"><h2><?php esc_html_e('Clasificación y ubicación', 'terramarket'); ?></h2><div class="tm-field-row tm-field-row-2"><div class="tm-field"><label for="tm_category_term_id"><?php esc_html_e('Categoría', 'terramarket'); ?></label><select id="tm_category_term_id" name="tm_category_term_id" required><option value=""><?php esc_html_e('Seleccionar', 'terramarket'); ?></option><?php foreach ($categories as $term) : ?><option value="<?php echo esc_attr((string) $term->term_id); ?>" <?php selected($selected_category, $term->term_id); ?>><?php echo esc_html($term->name); ?></option><?php endforeach; ?></select></div><div class="tm-field"><label for="tm_subcategory_term_id"><?php esc_html_e('Subcategoría', 'terramarket'); ?></label><select id="tm_subcategory_term_id" name="tm_subcategory_term_id" required><option value=""><?php esc_html_e('Seleccionar', 'terramarket'); ?></option><?php foreach ($subcategories as $term) : $parent_id = (int) get_term_meta($term->term_id, 'tm_parent_category_id', true); ?><option value="<?php echo esc_attr((string) $term->term_id); ?>" data-parent-category="<?php echo esc_attr((string) $parent_id); ?>" <?php selected($selected_subcategory, $term->term_id); ?>><?php echo esc_html($term->name); ?></option><?php endforeach; ?></select></div></div><div class="tm-field-row tm-field-row-2"><div class="tm-field"><label for="tm_region_term_id"><?php esc_html_e('Región', 'terramarket'); ?></label><select id="tm_region_term_id" name="tm_region_term_id" required><option value=""><?php esc_html_e('Seleccionar', 'terramarket'); ?></option><?php foreach ($regions as $term) : ?><option value="<?php echo esc_attr((string) $term->term_id); ?>" <?php selected($selected_region, $term->term_id); ?>><?php echo esc_html($term->name); ?></option><?php endforeach; ?></select></div><div class="tm-field"><label for="tm_comuna_term_id"><?php esc_html_e('Comuna', 'terramarket'); ?></label><select id="tm_comuna_term_id" name="tm_comuna_term_id" required><option value=""><?php esc_html_e('Seleccionar', 'terramarket'); ?></option><?php foreach ($comunas as $term) : $region_id = (int) get_term_meta($term->term_id, 'tm_region_id', true); ?><option value="<?php echo esc_attr((string) $term->term_id); ?>" data-region-id="<?php echo esc_attr((string) $region_id); ?>" <?php selected($selected_comuna, $term->term_id); ?>><?php echo esc_html($term->name); ?></option><?php endforeach; ?></select></div></div></section>
                </div>
                <aside class="tm-form-sidebar"><section class="tm-panel"><h2><?php esc_html_e('Contacto y estado', 'terramarket'); ?></h2><div class="tm-field"><label for="tm_contact_name"><?php esc_html_e('Nombre contacto', 'terramarket'); ?></label><input type="text" id="tm_contact_name" name="tm_contact_name" value="<?php echo esc_attr($meta['tm_contact_name']); ?>" required></div><div class="tm-field"><label for="tm_contact_email"><?php esc_html_e('Email contacto', 'terramarket'); ?></label><input type="email" id="tm_contact_email" name="tm_contact_email" value="<?php echo esc_attr($meta['tm_contact_email']); ?>" required></div><div class="tm-field"><label for="tm_contact_phone"><?php esc_html_e('Teléfono', 'terramarket'); ?></label><input type="text" id="tm_contact_phone" name="tm_contact_phone" value="<?php echo esc_attr($meta['tm_contact_phone']); ?>"></div><div class="tm-field"><label for="tm_listing_status"><?php esc_html_e('Estado', 'terramarket'); ?></label><select id="tm_listing_status" name="tm_listing_status"><?php foreach (TM_Helpers::get_listing_statuses() as $key => $label) : ?><option value="<?php echo esc_attr($key); ?>" <?php selected($meta['tm_listing_status'], $key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></div></section><section class="tm-panel"><h2><?php esc_html_e('Imágenes', 'terramarket'); ?></h2><p class="tm-helper-text"><?php echo esc_html(sprintf(__('Sube hasta %d imágenes. La primera quedará destacada.', 'terramarket'), $max_images)); ?></p><div class="tm-field"><label for="tm_images"><?php esc_html_e('Seleccionar imágenes', 'terramarket'); ?></label><input type="file" id="tm_images" name="tm_images[]" multiple accept="image/*"></div><div id="tm-upload-preview" class="tm-upload-preview"></div><?php if (! empty($gallery)) : ?><div class="tm-existing-gallery"><?php foreach ($gallery as $image) : ?><label class="tm-existing-media"><img src="<?php echo esc_url($image['thumb']); ?>" alt="<?php echo esc_attr($image['alt']); ?>" loading="lazy" decoding="async"><span><input type="checkbox" name="tm_delete_media[]" value="<?php echo esc_attr((string) $image['id']); ?>"> <?php esc_html_e('Eliminar', 'terramarket'); ?></span></label><?php endforeach; ?></div><?php endif; ?><button type="submit" class="tm-link-button tm-link-button--block"><?php echo esc_html($is_edit ? __('Guardar cambios', 'terramarket') : __('Publicar aviso', 'terramarket')); ?></button></section></aside>
            </div>
        </form>
        <?php
        return (string) ob_get_clean();
    }

    protected static function render_auth_view(): string
    {
        $notice = isset($_GET['tm_notice']) ? sanitize_text_field(wp_unslash($_GET['tm_notice'])) : '';
        $error = isset($_GET['tm_error']) ? sanitize_text_field(wp_unslash($_GET['tm_error'])) : '';
        $redirect_to = esc_url_raw(wp_unslash($_GET['redirect_to'] ?? TM_Helpers::get_account_page_url()));
        if (is_user_logged_in()) {
            return '<div class="tm-empty-state"><h2>' . esc_html__('Ya tienes una sesión activa.', 'terramarket') . '</h2><p><a class="tm-link-button" href="' . esc_url(TM_Helpers::get_account_page_url()) . '">' . esc_html__('Ir a Mi Terramarket', 'terramarket') . '</a></p></div>';
        }
        ob_start();
        ?>
        <section class="tm-auth-layout">
            <div class="tm-auth-card"><?php if ($notice) : ?><div class="tm-notice tm-notice-success"><?php echo esc_html(self::notice_label($notice)); ?></div><?php endif; ?><?php if ($error) : ?><div class="tm-notice tm-notice-error"><?php echo esc_html(self::notice_label($error)); ?></div><?php endif; ?><h1><?php esc_html_e('Accede a Terramarket', 'terramarket'); ?></h1><form method="post" action="<?php echo esc_url(TM_Helpers::get_submit_form_url()); ?>" class="tm-auth-form"><?php wp_nonce_field('tm_login_action', 'tm_login_nonce'); ?><input type="hidden" name="action" value="tm_login"><input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>"><div class="tm-field"><label><?php esc_html_e('Email o usuario', 'terramarket'); ?></label><input type="text" name="tm_login_user" required></div><div class="tm-field"><label><?php esc_html_e('Contraseña', 'terramarket'); ?></label><input type="password" name="tm_login_pass" required></div><button type="submit" class="tm-link-button tm-link-button--block"><?php esc_html_e('Iniciar sesión', 'terramarket'); ?></button></form></div>
            <div class="tm-auth-card"><h2><?php esc_html_e('Crea tu cuenta', 'terramarket'); ?></h2><form method="post" action="<?php echo esc_url(TM_Helpers::get_submit_form_url()); ?>" class="tm-auth-form"><?php wp_nonce_field('tm_register_action', 'tm_register_nonce'); ?><input type="hidden" name="action" value="tm_register"><input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>"><div class="tm-field"><label><?php esc_html_e('Nombre', 'terramarket'); ?></label><input type="text" name="tm_register_name" required></div><div class="tm-field"><label><?php esc_html_e('Email', 'terramarket'); ?></label><input type="email" name="tm_register_email" required></div><div class="tm-field"><label><?php esc_html_e('Contraseña', 'terramarket'); ?></label><input type="password" name="tm_register_pass" required></div><div class="tm-field"><label><?php esc_html_e('Tipo de cuenta', 'terramarket'); ?></label><select name="tm_account_type"><option value="tm_seller"><?php esc_html_e('Particular', 'terramarket'); ?></option><option value="tm_company"><?php esc_html_e('Empresa', 'terramarket'); ?></option><option value="tm_broker"><?php esc_html_e('Corredor / Intermediario', 'terramarket'); ?></option></select></div><button type="submit" class="tm-link-button tm-link-button--block"><?php esc_html_e('Crear cuenta', 'terramarket'); ?></button></form></div>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    protected static function render_account_view(): string
    {
        if (! is_user_logged_in()) {
            $auth_url = TM_Helpers::get_auth_page_url(array('redirect_to' => TM_Helpers::get_account_page_url()));
            return '<div class="tm-empty-state"><h2>' . esc_html__('Debes iniciar sesión.', 'terramarket') . '</h2><p><a class="tm-link-button" href="' . esc_url($auth_url) . '">' . esc_html__('Entrar o registrarte', 'terramarket') . '</a></p></div>';
        }

        $user_id = get_current_user_id();
        $notice = isset($_GET['tm_notice']) ? sanitize_text_field(wp_unslash($_GET['tm_notice'])) : '';
        $error = isset($_GET['tm_error']) ? sanitize_text_field(wp_unslash($_GET['tm_error'])) : '';
        $current_tab = sanitize_key(wp_unslash($_GET['tab'] ?? 'listings'));
        $tabs = array(
            'listings' => __('Mis avisos', 'terramarket'),
            'leads'    => __('Leads', 'terramarket'),
            'alerts'   => __('Alertas', 'terramarket'),
            'profile'  => __('Perfil', 'terramarket'),
        );
        if (! isset($tabs[$current_tab])) {
            $current_tab = 'listings';
        }

        $listing_counts = array(
            'total'  => 0,
            'active' => 0,
            'paused' => 0,
            'sold'   => 0,
            'views'  => 0,
        );
        $user_listings = get_posts(array(
            'post_type'      => 'tm_listing',
            'author'         => $user_id,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ));
        foreach ($user_listings as $listing) {
            $listing_counts['total']++;
            $status = get_post_meta($listing->ID, 'tm_listing_status', true) ?: 'active';
            if (isset($listing_counts[$status])) {
                $listing_counts[$status]++;
            }
            $listing_counts['views'] += (int) get_post_meta($listing->ID, 'tm_views_count', true);
        }

        global $wpdb;
        $leads_table = $wpdb->prefix . 'tm_leads';
        $lead_count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$leads_table} WHERE seller_user_id = %d", $user_id));
        $alerts = TM_Alerts::get_user_alerts($user_id);
        $alert_count = count($alerts);

        ob_start();
        ?>
        <section class="tm-page-hero">
            <div>
                <span class="tm-chip"><?php echo esc_html(TM_Helpers::get_account_type_label(TM_Helpers::get_user_account_type($user_id))); ?></span>
                <h1><?php esc_html_e('Mi Terramarket', 'terramarket'); ?></h1>
                <p><?php esc_html_e('Gestiona tus avisos, revisa tus leads, controla alertas y mantén tu perfil actualizado.', 'terramarket'); ?></p>
            </div>
            <a class="tm-link-button" href="<?php echo esc_url(TM_Helpers::get_submit_page_url()); ?>"><?php esc_html_e('Nuevo aviso', 'terramarket'); ?></a>
        </section>
        <?php if ($notice) : ?><div class="tm-notice tm-notice-success"><?php echo esc_html(self::notice_label($notice)); ?></div><?php endif; ?>
        <?php if ($error) : ?><div class="tm-notice tm-notice-error"><?php echo esc_html(self::notice_label($error)); ?></div><?php endif; ?>
        <div class="tm-dashboard-cards tm-dashboard-cards--five">
            <div class="tm-panel"><strong><?php echo esc_html((string) $listing_counts['total']); ?></strong><span><?php esc_html_e('Avisos', 'terramarket'); ?></span></div>
            <div class="tm-panel"><strong><?php echo esc_html((string) $listing_counts['active']); ?></strong><span><?php esc_html_e('Activos', 'terramarket'); ?></span></div>
            <div class="tm-panel"><strong><?php echo esc_html((string) $listing_counts['sold']); ?></strong><span><?php esc_html_e('Vendidos', 'terramarket'); ?></span></div>
            <div class="tm-panel"><strong><?php echo esc_html((string) $lead_count); ?></strong><span><?php esc_html_e('Leads', 'terramarket'); ?></span></div>
            <div class="tm-panel"><strong><?php echo esc_html((string) $alert_count); ?></strong><span><?php esc_html_e('Alertas', 'terramarket'); ?></span></div>
        </div>
        <nav class="tm-tabs"><?php foreach ($tabs as $tab => $label) : ?><a class="tm-tab <?php echo $tab === $current_tab ? 'is-active' : ''; ?>" href="<?php echo esc_url(TM_Helpers::get_account_page_url(array('tab' => $tab))); ?>"><?php echo esc_html($label); ?></a><?php endforeach; ?><a class="tm-tab" href="<?php echo esc_url(wp_logout_url(TM_Helpers::get_auth_page_url(array('tm_notice' => 'logged_out')))); ?>"><?php esc_html_e('Salir', 'terramarket'); ?></a></nav>
        <?php if ('listings' === $current_tab) : ?>
            <div class="tm-account-grid">
                <?php if (! empty($user_listings)) : foreach ($user_listings as $listing) :
                    $status = get_post_meta($listing->ID, 'tm_listing_status', true) ?: 'active';
                    $price = (int) get_post_meta($listing->ID, 'tm_price_clp', true);
                    $views = (int) get_post_meta($listing->ID, 'tm_views_count', true);
                    $sold_amount = (int) get_post_meta($listing->ID, 'tm_declared_sale_amount', true);
                    $image = TM_Helpers::get_listing_primary_image_url($listing->ID, 'medium');
                ?>
                    <article class="tm-panel tm-account-card">
                        <?php if ($image) : ?><div class="tm-account-card__media"><img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr($listing->post_title); ?>"></div><?php endif; ?>
                        <div>
                            <span class="tm-chip"><?php echo esc_html(TM_Helpers::get_listing_status_label($status)); ?></span>
                            <h3><?php echo esc_html($listing->post_title); ?></h3>
                            <p><?php echo esc_html(TM_Helpers::format_price_clp($price)); ?></p>
                            <p class="tm-helper-text"><?php echo esc_html(sprintf(__('%1$s visitas · %2$s', 'terramarket'), $views, get_the_date('d/m/Y', $listing))); ?></p>
                            <?php if ('sold' === $status && $sold_amount > 0) : ?><p class="tm-helper-text"><?php echo esc_html(sprintf(__('Venta declarada: %s', 'terramarket'), TM_Helpers::format_price_clp($sold_amount))); ?></p><?php endif; ?>
                        </div>
                        <div class="tm-card-actions">
                            <a class="tm-link-button tm-link-button--light" href="<?php echo esc_url(get_permalink($listing->ID)); ?>"><?php esc_html_e('Ver', 'terramarket'); ?></a>
                            <a class="tm-link-button tm-link-button--light" href="<?php echo esc_url(TM_Helpers::get_submit_page_url(array('tm_edit_listing' => $listing->ID))); ?>"><?php esc_html_e('Editar', 'terramarket'); ?></a>
                            <?php echo self::manage_action_link($listing->ID, 'pause', __('Pausar', 'terramarket')); ?>
                            <?php echo self::manage_action_link($listing->ID, 'activate', __('Reactivar', 'terramarket')); ?>
                            <?php echo self::manage_action_link($listing->ID, 'renew', __('Renovar', 'terramarket')); ?>
                            <?php echo self::manage_action_link($listing->ID, 'duplicate', __('Duplicar', 'terramarket')); ?>
                        </div>
                        <form method="post" action="<?php echo esc_url(TM_Helpers::get_submit_form_url()); ?>" class="tm-inline-form tm-inline-form--sold">
                            <?php wp_nonce_field('tm_manage_listing_' . $listing->ID . '_sold', 'tm_manage_listing_nonce'); ?>
                            <input type="hidden" name="action" value="tm_manage_listing">
                            <input type="hidden" name="tm_listing_id" value="<?php echo esc_attr((string) $listing->ID); ?>">
                            <input type="hidden" name="tm_operation" value="sold">
                            <input type="number" name="tm_sale_amount" min="0" step="1" placeholder="<?php esc_attr_e('Monto final vendido (opcional)', 'terramarket'); ?>">
                            <button type="submit" class="tm-link-button tm-link-button--light"><?php esc_html_e('Marcar vendido', 'terramarket'); ?></button>
                        </form>
                    </article>
                <?php endforeach; else : ?>
                    <div class="tm-empty-state"><p><?php esc_html_e('Todavía no publicas avisos.', 'terramarket'); ?></p></div>
                <?php endif; ?>
            </div>
        <?php elseif ('leads' === $current_tab) : ?>
            <?php $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$leads_table} WHERE seller_user_id = %d ORDER BY created_at DESC LIMIT 100", $user_id)); ?>
            <div class="tm-panel tm-table-wrap"><?php if ($rows) : ?><table class="tm-table"><thead><tr><th><?php esc_html_e('Fecha', 'terramarket'); ?></th><th><?php esc_html_e('Aviso', 'terramarket'); ?></th><th><?php esc_html_e('Contacto', 'terramarket'); ?></th><th><?php esc_html_e('Teléfono', 'terramarket'); ?></th><th><?php esc_html_e('Mensaje', 'terramarket'); ?></th></tr></thead><tbody><?php foreach ($rows as $row) : ?><tr><td><?php echo esc_html(mysql2date('d/m/Y H:i', $row->created_at)); ?></td><td><?php echo esc_html(get_the_title((int) $row->listing_id)); ?></td><td><?php echo esc_html($row->buyer_name . ' · ' . $row->buyer_email); ?></td><td><?php echo esc_html((string) $row->buyer_phone); ?></td><td><?php echo esc_html(wp_trim_words((string) $row->message, 24)); ?></td></tr><?php endforeach; ?></tbody></table><?php else : ?><p><?php esc_html_e('Aún no recibes leads.', 'terramarket'); ?></p><?php endif; ?></div>
        <?php elseif ('alerts' === $current_tab) : ?>
            <?php $categories = TM_Helpers::get_terms_for_select('tm_category'); $subcategories = TM_Helpers::get_terms_for_select('tm_subcategory'); $regions = TM_Helpers::get_regions_ordered(); $comunas = TM_Helpers::get_terms_for_select('tm_comuna'); ?>
            <div class="tm-account-grid tm-account-grid--wide">
                <section class="tm-panel">
                    <h2><?php esc_html_e('Nueva alerta diaria', 'terramarket'); ?></h2>
                    <form method="post" action="<?php echo esc_url(TM_Helpers::get_submit_form_url()); ?>" class="tm-auth-form">
                        <?php wp_nonce_field('tm_save_alert', 'tm_save_alert_nonce'); ?>
                        <input type="hidden" name="action" value="tm_save_alert">
                        <div class="tm-field"><label><?php esc_html_e('Palabra clave', 'terramarket'); ?></label><input type="text" name="tm_alert_keyword"></div>
                        <div class="tm-field-row tm-field-row-2">
                            <div class="tm-field"><label><?php esc_html_e('Categoría', 'terramarket'); ?></label><select name="tm_alert_category_term_id" id="tm_alert_category_term_id"><option value="0"><?php esc_html_e('Todas', 'terramarket'); ?></option><?php foreach ($categories as $term) : ?><option value="<?php echo esc_attr((string) $term->term_id); ?>"><?php echo esc_html($term->name); ?></option><?php endforeach; ?></select></div>
                            <div class="tm-field"><label><?php esc_html_e('Subcategoría', 'terramarket'); ?></label><select name="tm_alert_subcategory_term_id" id="tm_alert_subcategory_term_id"><option value="0"><?php esc_html_e('Todas', 'terramarket'); ?></option><?php foreach ($subcategories as $term) : $parent_id = (int) get_term_meta($term->term_id, 'tm_parent_category_id', true); ?><option value="<?php echo esc_attr((string) $term->term_id); ?>" data-parent-category="<?php echo esc_attr((string) $parent_id); ?>"><?php echo esc_html($term->name); ?></option><?php endforeach; ?></select></div>
                        </div>
                        <div class="tm-field-row tm-field-row-2">
                            <div class="tm-field"><label><?php esc_html_e('Región', 'terramarket'); ?></label><select name="tm_alert_region_term_id" id="tm_alert_region_term_id"><option value="0"><?php esc_html_e('Todas', 'terramarket'); ?></option><?php foreach ($regions as $term) : ?><option value="<?php echo esc_attr((string) $term->term_id); ?>"><?php echo esc_html($term->name); ?></option><?php endforeach; ?></select></div>
                            <div class="tm-field"><label><?php esc_html_e('Comuna', 'terramarket'); ?></label><select name="tm_alert_comuna_term_id" id="tm_alert_comuna_term_id"><option value="0"><?php esc_html_e('Todas', 'terramarket'); ?></option><?php foreach ($comunas as $term) : $region_id = (int) get_term_meta($term->term_id, 'tm_region_id', true); ?><option value="<?php echo esc_attr((string) $term->term_id); ?>" data-region-id="<?php echo esc_attr((string) $region_id); ?>"><?php echo esc_html($term->name); ?></option><?php endforeach; ?></select></div>
                        </div>
                        <div class="tm-field-row tm-field-row-2">
                            <div class="tm-field"><label><?php esc_html_e('Precio mínimo', 'terramarket'); ?></label><input type="number" name="tm_alert_price_min" min="0" step="1"></div>
                            <div class="tm-field"><label><?php esc_html_e('Precio máximo', 'terramarket'); ?></label><input type="number" name="tm_alert_price_max" min="0" step="1"></div>
                        </div>
                        <p class="tm-helper-text"><?php esc_html_e('Las alertas se envían una vez al día por email.', 'terramarket'); ?></p>
                        <button type="submit" class="tm-link-button"><?php esc_html_e('Guardar alerta', 'terramarket'); ?></button>
                    </form>
                </section>
                <section class="tm-panel">
                    <h2><?php esc_html_e('Tus alertas activas', 'terramarket'); ?></h2>
                    <?php if ($alerts) : ?><div class="tm-stack"><?php foreach ($alerts as $alert) : ?>
                        <article class="tm-alert-item">
                            <div>
                                <strong><?php echo esc_html(self::alert_summary($alert)); ?></strong>
                                <p class="tm-helper-text"><?php echo esc_html(sprintf(__('Último envío: %s', 'terramarket'), ! empty($alert->last_sent_at) ? mysql2date('d/m/Y H:i', $alert->last_sent_at) : __('Nunca', 'terramarket'))); ?></p>
                            </div>
                            <form method="post" action="<?php echo esc_url(TM_Helpers::get_submit_form_url()); ?>">
                                <?php wp_nonce_field('tm_delete_alert_' . (int) $alert->id, 'tm_delete_alert_nonce'); ?>
                                <input type="hidden" name="action" value="tm_delete_alert">
                                <input type="hidden" name="tm_alert_id" value="<?php echo esc_attr((string) $alert->id); ?>">
                                <button type="submit" class="tm-link-button tm-link-button--light"><?php esc_html_e('Eliminar', 'terramarket'); ?></button>
                            </form>
                        </article>
                    <?php endforeach; ?></div><?php else : ?><p><?php esc_html_e('Aún no tienes alertas guardadas.', 'terramarket'); ?></p><?php endif; ?>
                </section>
            </div>
        <?php else : ?>
            <?php $current_user = wp_get_current_user(); ?>
            <div class="tm-panel"><form method="post" action="<?php echo esc_url(TM_Helpers::get_submit_form_url()); ?>" class="tm-auth-form"><?php wp_nonce_field('tm_update_profile', 'tm_update_profile_nonce'); ?><input type="hidden" name="action" value="tm_update_profile"><div class="tm-field"><label><?php esc_html_e('Nombre visible', 'terramarket'); ?></label><input type="text" name="tm_profile_name" value="<?php echo esc_attr($current_user->display_name); ?>" required></div><div class="tm-field"><label><?php esc_html_e('Email', 'terramarket'); ?></label><input type="email" name="tm_profile_email" value="<?php echo esc_attr($current_user->user_email); ?>" required></div><div class="tm-field"><label><?php esc_html_e('Teléfono', 'terramarket'); ?></label><input type="text" name="tm_profile_phone" value="<?php echo esc_attr((string) get_user_meta($user_id, 'tm_contact_phone', true)); ?>"></div><button type="submit" class="tm-link-button"><?php esc_html_e('Guardar perfil', 'terramarket'); ?></button></form></div>
        <?php endif; ?>
        <?php
        return (string) ob_get_clean();
    }

    protected static function manage_action_link(int $listing_id, string $operation, string $label): string
    {
        $url = wp_nonce_url(add_query_arg(array('action' => 'tm_manage_listing', 'tm_listing_id' => $listing_id, 'tm_operation' => $operation), TM_Helpers::get_submit_form_url()), 'tm_manage_listing_' . $listing_id . '_' . $operation);
        return '<a class="tm-link-button tm-link-button--light" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
    }

    protected static function alert_summary($alert): string
    {
        $parts = array();
        if (! empty($alert->keyword)) {
            $parts[] = sprintf(__('Palabra clave: %s', 'terramarket'), $alert->keyword);
        }
        foreach (array('tm_category' => 'category_term_id', 'tm_subcategory' => 'subcategory_term_id', 'tm_region' => 'region_term_id', 'tm_comuna' => 'comuna_term_id') as $taxonomy => $key) {
            $term_id = (int) ($alert->{$key} ?? 0);
            if ($term_id > 0) {
                $term = get_term($term_id, $taxonomy);
                if ($term && ! is_wp_error($term)) {
                    $parts[] = $term->name;
                }
            }
        }
        $price_min = (int) ($alert->price_min ?? 0);
        $price_max = (int) ($alert->price_max ?? 0);
        if ($price_min || $price_max) {
            $parts[] = sprintf(__('Precio: %1$s a %2$s', 'terramarket'), $price_min ? TM_Helpers::format_price_clp($price_min) : __('sin mínimo', 'terramarket'), $price_max ? TM_Helpers::format_price_clp($price_max) : __('sin máximo', 'terramarket'));
        }
        return $parts ? implode(' · ', $parts) : __('Sin filtros, todos los avisos nuevos', 'terramarket');
    }

    public static function handle_login(): void
    {
        $nonce = sanitize_text_field(wp_unslash($_POST['tm_login_nonce'] ?? ''));
        if (! wp_verify_nonce($nonce, 'tm_login_action')) {
            wp_die(esc_html__('Nonce inválido.', 'terramarket'));
        }
        $redirect_to = esc_url_raw(wp_unslash($_POST['redirect_to'] ?? TM_Helpers::get_account_page_url()));
        $creds = array(
            'user_login'    => sanitize_text_field(wp_unslash($_POST['tm_login_user'] ?? '')),
            'user_password' => (string) ($_POST['tm_login_pass'] ?? ''),
            'remember'      => true,
        );
        $user = wp_signon($creds, is_ssl());
        if (is_wp_error($user)) {
            wp_safe_redirect(TM_Helpers::get_auth_page_url(array('tm_error' => 'login_failed', 'redirect_to' => $redirect_to)));
            exit;
        }
        wp_safe_redirect($redirect_to ?: TM_Helpers::get_account_page_url());
        exit;
    }

    public static function handle_register(): void
    {
        $nonce = sanitize_text_field(wp_unslash($_POST['tm_register_nonce'] ?? ''));
        if (! wp_verify_nonce($nonce, 'tm_register_action')) {
            wp_die(esc_html__('Nonce inválido.', 'terramarket'));
        }
        $name = sanitize_text_field(wp_unslash($_POST['tm_register_name'] ?? ''));
        $email = sanitize_email(wp_unslash($_POST['tm_register_email'] ?? ''));
        $password = (string) ($_POST['tm_register_pass'] ?? '');
        $role = sanitize_key(wp_unslash($_POST['tm_account_type'] ?? 'tm_seller'));
        $redirect_to = esc_url_raw(wp_unslash($_POST['redirect_to'] ?? TM_Helpers::get_account_page_url()));
        if (! in_array($role, array('tm_seller', 'tm_company', 'tm_broker'), true) || ! $name || ! is_email($email) || strlen($password) < 6) {
            wp_safe_redirect(TM_Helpers::get_auth_page_url(array('tm_error' => 'register_invalid', 'redirect_to' => $redirect_to)));
            exit;
        }
        if (email_exists($email)) {
            wp_safe_redirect(TM_Helpers::get_auth_page_url(array('tm_error' => 'email_exists', 'redirect_to' => $redirect_to)));
            exit;
        }
        $username = sanitize_user(current(explode('@', $email)), true);
        if (username_exists($username)) {
            $username .= wp_rand(100, 9999);
        }
        $user_id = wp_insert_user(array('user_login' => $username, 'user_pass' => $password, 'user_email' => $email, 'display_name' => $name, 'role' => $role));
        if (is_wp_error($user_id)) {
            wp_safe_redirect(TM_Helpers::get_auth_page_url(array('tm_error' => 'register_failed', 'redirect_to' => $redirect_to)));
            exit;
        }
        update_user_meta($user_id, 'tm_account_type', $role);
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);
        wp_safe_redirect($redirect_to ?: TM_Helpers::get_account_page_url());
        exit;
    }

    public static function handle_update_profile(): void
    {
        if (! is_user_logged_in()) {
            wp_safe_redirect(TM_Helpers::get_auth_page_url());
            exit;
        }
        $nonce = sanitize_text_field(wp_unslash($_POST['tm_update_profile_nonce'] ?? ''));
        if (! wp_verify_nonce($nonce, 'tm_update_profile')) {
            wp_die(esc_html__('Nonce inválido.', 'terramarket'));
        }
        $user_id = get_current_user_id();
        $name = sanitize_text_field(wp_unslash($_POST['tm_profile_name'] ?? ''));
        $email = sanitize_email(wp_unslash($_POST['tm_profile_email'] ?? ''));
        $phone = sanitize_text_field(wp_unslash($_POST['tm_profile_phone'] ?? ''));
        $result = wp_update_user(array('ID' => $user_id, 'display_name' => $name, 'user_email' => $email));
        if (is_wp_error($result)) {
            wp_safe_redirect(TM_Helpers::get_account_page_url(array('tab' => 'profile', 'tm_error' => 'profile_failed')));
            exit;
        }
        update_user_meta($user_id, 'tm_contact_phone', $phone);
        wp_safe_redirect(TM_Helpers::get_account_page_url(array('tab' => 'profile', 'tm_notice' => 'profile_updated')));
        exit;
    }

    public static function handle_manage_listing(): void
    {
        if (! is_user_logged_in()) {
            wp_safe_redirect(TM_Helpers::get_auth_page_url());
            exit;
        }

        $source = 'POST' === strtoupper($_SERVER['REQUEST_METHOD'] ?? '') ? $_POST : $_GET;
        $listing_id = absint($source['tm_listing_id'] ?? 0);
        $operation = sanitize_key(wp_unslash($source['tm_operation'] ?? ''));

        if (! $listing_id || ! TM_Helpers::is_listing_owner($listing_id, get_current_user_id())) {
            wp_safe_redirect(TM_Helpers::get_account_page_url(array('tm_error' => 'no_permission')));
            exit;
        }

        $nonce_value = sanitize_text_field(wp_unslash($source['tm_manage_listing_nonce'] ?? ($source['_wpnonce'] ?? '')));
        if (! wp_verify_nonce($nonce_value, 'tm_manage_listing_' . $listing_id . '_' . $operation)) {
            wp_die(esc_html__('Nonce inválido.', 'terramarket'));
        }

        $notice = 'listing_updated';
        switch ($operation) {
            case 'pause':
                update_post_meta($listing_id, 'tm_listing_status', 'paused');
                $notice = 'listing_paused';
                break;
            case 'activate':
                update_post_meta($listing_id, 'tm_listing_status', 'active');
                $notice = 'listing_activated';
                break;
            case 'renew':
                wp_update_post(array('ID' => $listing_id, 'post_date' => current_time('mysql'), 'post_date_gmt' => current_time('mysql', 1)));
                update_post_meta($listing_id, 'tm_expiration_date', date('Y-m-d', current_time('timestamp') + (30 * DAY_IN_SECONDS)));
                $notice = 'listing_renewed';
                break;
            case 'duplicate':
                $new_listing_id = wp_insert_post(array(
                    'post_type'    => 'tm_listing',
                    'post_status'  => 'publish',
                    'post_title'   => get_the_title($listing_id) . ' ' . __('(copia)', 'terramarket'),
                    'post_content' => get_post_field('post_content', $listing_id),
                    'post_author'  => get_current_user_id(),
                ));
                if ($new_listing_id && ! is_wp_error($new_listing_id)) {
                    $meta = get_post_meta($listing_id);
                    foreach ($meta as $meta_key => $values) {
                        if (in_array($meta_key, array('_edit_lock', '_edit_last'), true)) {
                            continue;
                        }
                        foreach ((array) $values as $value) {
                            add_post_meta($new_listing_id, $meta_key, maybe_unserialize($value));
                        }
                    }
                    foreach (array('tm_category', 'tm_subcategory', 'tm_region', 'tm_comuna', 'tm_condition') as $taxonomy) {
                        $term_ids = wp_get_object_terms($listing_id, $taxonomy, array('fields' => 'ids'));
                        if (! is_wp_error($term_ids)) {
                            wp_set_object_terms($new_listing_id, $term_ids, $taxonomy, false);
                        }
                    }
                    $gallery_ids = TM_Helpers::get_listing_gallery_ids($listing_id);
                    if (! empty($gallery_ids)) {
                        $new_gallery_ids = array();
                        foreach ($gallery_ids as $index => $attachment_id) {
                            $cloned_attachment_id = self::duplicate_attachment((int) $attachment_id, $new_listing_id);
                            if ($cloned_attachment_id > 0) {
                                self::attach_listing_media($new_listing_id, $cloned_attachment_id, $index, 0 === $index);
                                $new_gallery_ids[] = $cloned_attachment_id;
                            }
                        }
                        if (! empty($new_gallery_ids)) {
                            set_post_thumbnail($new_listing_id, (int) $new_gallery_ids[0]);
                        }
                    }
                    update_post_meta($new_listing_id, 'tm_listing_status', 'active');
                }
                $notice = 'listing_duplicated';
                break;
            case 'sold':
                update_post_meta($listing_id, 'tm_listing_status', 'sold');
                update_post_meta($listing_id, 'tm_sold_at', current_time('Y-m-d'));
                $sale_amount = absint($source['tm_sale_amount'] ?? 0);
                if ($sale_amount > 0) {
                    update_post_meta($listing_id, 'tm_declared_sale_amount', $sale_amount);
                }
                global $wpdb;
                $table = $wpdb->prefix . 'tm_commission_events';
                $commission_rate = (float) TM_Helpers::get_option('tm_settings_marketplace', 'default_commission', '5');
                $commission_amount = $sale_amount > 0 ? ($sale_amount * $commission_rate / 100) : 0;
                $wpdb->insert(
                    $table,
                    array(
                        'listing_id'            => $listing_id,
                        'seller_user_id'        => get_current_user_id(),
                        'sold_at'               => current_time('mysql'),
                        'declared_sale_amount'  => $sale_amount,
                        'commission_rate'       => $commission_rate,
                        'commission_amount'     => $commission_amount,
                        'settlement_status'     => 'pending',
                        'notes'                 => __('Cierre declarado desde Mi Terramarket.', 'terramarket'),
                        'created_at'            => current_time('mysql'),
                        'updated_at'            => current_time('mysql'),
                    ),
                    array('%d', '%d', '%s', '%d', '%f', '%f', '%s', '%s', '%s', '%s')
                );
                $notice = 'listing_sold';
                break;
        }

        wp_safe_redirect(TM_Helpers::get_account_page_url(array('tab' => 'listings', 'tm_notice' => $notice)));
        exit;
    }

    public static function handle_save_alert(): void
    {
        if (! is_user_logged_in()) {
            wp_safe_redirect(TM_Helpers::get_auth_page_url(array('redirect_to' => TM_Helpers::get_account_page_url(array('tab' => 'alerts')))));
            exit;
        }
        $nonce = sanitize_text_field(wp_unslash($_POST['tm_save_alert_nonce'] ?? ''));
        if (! wp_verify_nonce($nonce, 'tm_save_alert')) {
            wp_die(esc_html__('Nonce inválido.', 'terramarket'));
        }
        $data = array(
            'keyword'             => sanitize_text_field(wp_unslash($_POST['tm_alert_keyword'] ?? '')),
            'category_term_id'    => absint($_POST['tm_alert_category_term_id'] ?? 0),
            'subcategory_term_id' => absint($_POST['tm_alert_subcategory_term_id'] ?? 0),
            'region_term_id'      => absint($_POST['tm_alert_region_term_id'] ?? 0),
            'comuna_term_id'      => absint($_POST['tm_alert_comuna_term_id'] ?? 0),
            'price_min'           => absint($_POST['tm_alert_price_min'] ?? 0),
            'price_max'           => absint($_POST['tm_alert_price_max'] ?? 0),
        );
        $has_filter = false;
        foreach ($data as $key => $value) {
            if (! empty($value)) {
                $has_filter = true;
                break;
            }
        }
        if (! $has_filter) {
            wp_safe_redirect(TM_Helpers::get_account_page_url(array('tab' => 'alerts', 'tm_error' => 'alert_invalid')));
            exit;
        }
        TM_Alerts::create_alert(get_current_user_id(), $data);
        wp_safe_redirect(TM_Helpers::get_account_page_url(array('tab' => 'alerts', 'tm_notice' => 'alert_saved')));
        exit;
    }

    public static function handle_delete_alert(): void
    {
        if (! is_user_logged_in()) {
            wp_safe_redirect(TM_Helpers::get_auth_page_url(array('redirect_to' => TM_Helpers::get_account_page_url(array('tab' => 'alerts')))));
            exit;
        }
        $alert_id = absint($_POST['tm_alert_id'] ?? 0);
        $nonce = sanitize_text_field(wp_unslash($_POST['tm_delete_alert_nonce'] ?? ''));
        if (! $alert_id || ! wp_verify_nonce($nonce, 'tm_delete_alert_' . $alert_id)) {
            wp_die(esc_html__('Nonce inválido.', 'terramarket'));
        }
        TM_Alerts::delete_alert($alert_id, get_current_user_id());
        wp_safe_redirect(TM_Helpers::get_account_page_url(array('tab' => 'alerts', 'tm_notice' => 'alert_deleted')));
        exit;
    }

    public static function ajax_filter_listings(): void
    {
        check_ajax_referer('tm_filter_listings', 'nonce');
        $filters = array(
            'keyword'       => sanitize_text_field(wp_unslash($_POST['keyword'] ?? '')),
            'category'      => sanitize_text_field(wp_unslash($_POST['category'] ?? '')),
            'subcategory'   => sanitize_text_field(wp_unslash($_POST['subcategory'] ?? '')),
            'region'        => sanitize_text_field(wp_unslash($_POST['region'] ?? '')),
            'comuna'        => sanitize_text_field(wp_unslash($_POST['comuna'] ?? '')),
            'condition'     => sanitize_text_field(wp_unslash($_POST['condition'] ?? '')),
            'price_min'     => absint($_POST['price_min'] ?? 0),
            'price_max'     => absint($_POST['price_max'] ?? 0),
            'featured_only' => ! empty($_POST['featured_only']) ? 1 : 0,
            'with_photo'    => ! empty($_POST['with_photo']) ? 1 : 0,
            'order'         => sanitize_text_field(wp_unslash($_POST['order'] ?? 'featured_recent')),
            'page'          => max(1, absint($_POST['tm_page'] ?? 1)),
        );
        $query = new WP_Query(self::get_listing_query_args($filters));
        wp_send_json_success(array('html' => self::render_results_markup($query, $filters)));
    }

    public static function handle_save_listing(): void
    {
        if (! is_user_logged_in()) {
            wp_safe_redirect(TM_Helpers::get_auth_page_url());
            exit;
        }
        $nonce = sanitize_text_field(wp_unslash($_POST['tm_save_listing_frontend_nonce'] ?? ''));
        if (! wp_verify_nonce($nonce, 'tm_save_listing_frontend')) {
            wp_die(esc_html__('Nonce inválido.', 'terramarket'));
        }
        $redirect_url = esc_url_raw(wp_unslash($_POST['tm_redirect'] ?? TM_Helpers::get_submit_page_url()));
        $user_id = get_current_user_id();
        $listing_id = absint($_POST['tm_listing_id'] ?? 0);
        $is_edit = $listing_id > 0;
        if ($is_edit && ! TM_Helpers::is_listing_owner($listing_id, $user_id)) {
            wp_safe_redirect(TM_Helpers::build_redirect_url($redirect_url, array('tm_error' => 'no_permission')));
            exit;
        }
        $title = sanitize_text_field(wp_unslash($_POST['tm_title'] ?? ''));
        $description = wp_kses_post(wp_unslash($_POST['tm_description'] ?? ''));
        $price = absint($_POST['tm_price_clp'] ?? 0);
        $contact_name = sanitize_text_field(wp_unslash($_POST['tm_contact_name'] ?? ''));
        $contact_email = sanitize_email(wp_unslash($_POST['tm_contact_email'] ?? ''));
        $contact_phone = sanitize_text_field(wp_unslash($_POST['tm_contact_phone'] ?? ''));
        $category_id = absint($_POST['tm_category_term_id'] ?? 0);
        $subcategory_id = absint($_POST['tm_subcategory_term_id'] ?? 0);
        $region_id = absint($_POST['tm_region_term_id'] ?? 0);
        $comuna_id = absint($_POST['tm_comuna_term_id'] ?? 0);
        $condition_id = absint($_POST['tm_condition_term_id'] ?? 0);
        $status = sanitize_key(wp_unslash($_POST['tm_listing_status'] ?? 'active'));
        $status = array_key_exists($status, TM_Helpers::get_listing_statuses()) ? $status : 'active';
        $delete_media = isset($_POST['tm_delete_media']) ? array_map('absint', (array) wp_unslash($_POST['tm_delete_media'])) : array();
        $max_images = (int) TM_Helpers::get_option('tm_settings_marketplace', 'max_images', 5);

        if (! $title || ! $description || ! $price || ! $contact_name || ! $contact_email || ! $category_id || ! $subcategory_id || ! $region_id || ! $comuna_id || ! $condition_id) {
            wp_safe_redirect(TM_Helpers::build_redirect_url($redirect_url, array('tm_error' => 'missing_fields')));
            exit;
        }
        if (! is_email($contact_email)) {
            wp_safe_redirect(TM_Helpers::build_redirect_url($redirect_url, array('tm_error' => 'invalid_email')));
            exit;
        }
        $existing_count = $is_edit ? TM_Helpers::get_listing_image_count($listing_id) : 0;
        if (! empty($delete_media)) {
            $existing_count = max(0, $existing_count - count($delete_media));
        }
        $new_count = TM_Helpers::get_posted_files_count('tm_images');
        if (($existing_count + $new_count) < 1) {
            wp_safe_redirect(TM_Helpers::build_redirect_url($redirect_url, array('tm_error' => 'image_required')));
            exit;
        }
        if (($existing_count + $new_count) > $max_images) {
            wp_safe_redirect(TM_Helpers::build_redirect_url($redirect_url, array('tm_error' => 'too_many_images')));
            exit;
        }

        $post_data = array('post_type' => 'tm_listing', 'post_title' => $title, 'post_content' => $description, 'post_status' => 'publish', 'post_author' => $user_id);
        if ($is_edit) {
            $post_data['ID'] = $listing_id;
            $saved_id = wp_update_post($post_data, true);
        } else {
            $saved_id = wp_insert_post($post_data, true);
        }
        if (is_wp_error($saved_id) || ! $saved_id) {
            wp_safe_redirect(TM_Helpers::build_redirect_url($redirect_url, array('tm_error' => 'save_failed')));
            exit;
        }
        $listing_id = (int) $saved_id;
        wp_set_object_terms($listing_id, array($category_id), 'tm_category', false);
        wp_set_object_terms($listing_id, array($subcategory_id), 'tm_subcategory', false);
        wp_set_object_terms($listing_id, array($region_id), 'tm_region', false);
        wp_set_object_terms($listing_id, array($comuna_id), 'tm_comuna', false);
        wp_set_object_terms($listing_id, array($condition_id), 'tm_condition', false);
        update_post_meta($listing_id, 'tm_price_clp', $price);
        update_post_meta($listing_id, 'tm_contact_name', $contact_name);
        update_post_meta($listing_id, 'tm_contact_email', $contact_email);
        update_post_meta($listing_id, 'tm_contact_phone', $contact_phone);
        update_post_meta($listing_id, 'tm_listing_status', $status);
        update_post_meta($listing_id, 'tm_category_term_id', $category_id);
        update_post_meta($listing_id, 'tm_subcategory_term_id', $subcategory_id);
        update_post_meta($listing_id, 'tm_region_term_id', $region_id);
        update_post_meta($listing_id, 'tm_comuna_term_id', $comuna_id);
        update_post_meta($listing_id, 'tm_condition_term_id', $condition_id);
        if (! get_post_meta($listing_id, 'tm_views_count', true)) {
            update_post_meta($listing_id, 'tm_views_count', 0);
        }
        if (! empty($delete_media)) {
            self::delete_listing_media($listing_id, $delete_media);
        }
        $uploaded_ids = self::upload_listing_images($listing_id, 'tm_images');
        if (is_wp_error($uploaded_ids)) {
            wp_safe_redirect(TM_Helpers::build_redirect_url($redirect_url, array('tm_error' => 'upload_failed')));
            exit;
        }
        self::ensure_listing_featured_image($listing_id);
        if ($is_edit) {
            wp_safe_redirect(add_query_arg(array('tm_notice' => 'listing_updated', 'tm_edit_listing' => $listing_id), remove_query_arg(array('tm_notice', 'tm_error'), $redirect_url)));
            exit;
        }
        wp_safe_redirect(add_query_arg(array('tm_notice' => 'listing_created'), get_permalink($listing_id)));
        exit;
    }

    public static function handle_send_lead(): void
    {
        $nonce = sanitize_text_field(wp_unslash($_POST['tm_send_lead_nonce'] ?? ''));
        if (! wp_verify_nonce($nonce, 'tm_send_lead')) {
            wp_die(esc_html__('Nonce inválido.', 'terramarket'));
        }
        $listing_id = absint($_POST['tm_listing_id'] ?? 0);
        $redirect_url = $listing_id ? get_permalink($listing_id) : get_post_type_archive_link('tm_listing');
        if (! $listing_id || 'tm_listing' !== get_post_type($listing_id)) {
            wp_safe_redirect(TM_Helpers::build_redirect_url($redirect_url, array('tm_error' => 'listing_invalid')));
            exit;
        }
        $honeypot = sanitize_text_field(wp_unslash($_POST['tm_website'] ?? ''));
        if (! empty($honeypot)) {
            wp_safe_redirect(TM_Helpers::build_redirect_url($redirect_url, array('tm_notice' => 'lead_sent')));
            exit;
        }
        $buyer_name = sanitize_text_field(wp_unslash($_POST['tm_buyer_name'] ?? ''));
        $buyer_email = sanitize_email(wp_unslash($_POST['tm_buyer_email'] ?? ''));
        $buyer_phone = sanitize_text_field(wp_unslash($_POST['tm_buyer_phone'] ?? ''));
        $message = sanitize_textarea_field(wp_unslash($_POST['tm_message'] ?? ''));
        if (! $buyer_name || ! is_email($buyer_email) || ! $message) {
            wp_safe_redirect(TM_Helpers::build_redirect_url($redirect_url, array('tm_error' => 'lead_invalid')));
            exit;
        }
        global $wpdb;
        $seller_user_id = (int) get_post_field('post_author', $listing_id);
        $table = $wpdb->prefix . 'tm_leads';
        $now = current_time('mysql');
        $wpdb->insert($table, array('listing_id' => $listing_id, 'seller_user_id' => $seller_user_id, 'buyer_name' => $buyer_name, 'buyer_email' => $buyer_email, 'buyer_phone' => $buyer_phone, 'message' => $message, 'status' => 'new', 'created_at' => $now, 'updated_at' => $now), array('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s'));
        $seller_email = get_post_meta($listing_id, 'tm_contact_email', true);
        if (! $seller_email || ! is_email($seller_email)) {
            $seller_email = get_the_author_meta('user_email', $seller_user_id);
        }
        $subject = sprintf(__('Nuevo contacto por tu aviso: %s', 'terramarket'), get_the_title($listing_id));
        $body = implode("\n", array(sprintf(__('Aviso: %s', 'terramarket'), get_the_title($listing_id)), sprintf(__('URL: %s', 'terramarket'), get_permalink($listing_id)), '', sprintf(__('Nombre: %s', 'terramarket'), $buyer_name), sprintf(__('Email: %s', 'terramarket'), $buyer_email), sprintf(__('Teléfono: %s', 'terramarket'), $buyer_phone ?: '-'), '', __('Mensaje:', 'terramarket'), $message));
        if ($seller_email) {
            wp_mail($seller_email, $subject, $body);
        }
        $notify_email = TM_Helpers::get_option('tm_settings_general', 'notify_email', '');
        if ($notify_email && is_email($notify_email) && $notify_email !== $seller_email) {
            wp_mail($notify_email, $subject, $body);
        }
        wp_safe_redirect(TM_Helpers::build_redirect_url($redirect_url, array('tm_notice' => 'lead_sent')));
        exit;
    }

    protected static function upload_listing_images(int $listing_id, string $field_name)
    {
        $files = TM_Helpers::normalize_uploaded_files($field_name);
        if (empty($files)) {
            return array();
        }
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $allowed_mimes = TM_Helpers::get_allowed_image_mimes();
        $sort_order = TM_Helpers::get_listing_image_count($listing_id);
        $uploaded_ids = array();
        foreach ($files as $file) {
            if (! empty($file['error'])) {
                continue;
            }
            $uploaded = wp_handle_upload($file, array('test_form' => false, 'mimes' => $allowed_mimes));
            if (isset($uploaded['error'])) {
                return new WP_Error('tm_upload_error', $uploaded['error']);
            }
            $attachment = array('guid' => $uploaded['url'], 'post_mime_type' => $uploaded['type'], 'post_title' => sanitize_file_name(pathinfo($uploaded['file'], PATHINFO_FILENAME)), 'post_content' => '', 'post_status' => 'inherit');
            $attachment_id = wp_insert_attachment($attachment, $uploaded['file'], $listing_id);
            if (is_wp_error($attachment_id) || ! $attachment_id) {
                continue;
            }
            $metadata = wp_generate_attachment_metadata($attachment_id, $uploaded['file']);
            if (! is_wp_error($metadata)) {
                wp_update_attachment_metadata($attachment_id, $metadata);
            }
            self::process_attachment_image((int) $attachment_id);
            self::attach_listing_media($listing_id, (int) $attachment_id, $sort_order, 0 === $sort_order);
            $uploaded_ids[] = (int) $attachment_id;
            $sort_order++;
        }
        return $uploaded_ids;
    }

    protected static function attach_listing_media(int $listing_id, int $attachment_id, int $sort_order, bool $is_featured): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'tm_listing_media';
        $wpdb->insert($table, array('listing_id' => $listing_id, 'attachment_id' => $attachment_id, 'sort_order' => $sort_order, 'is_featured' => $is_featured ? 1 : 0, 'created_at' => current_time('mysql')), array('%d', '%d', '%d', '%d', '%s'));
        if ($is_featured || ! get_post_thumbnail_id($listing_id)) {
            set_post_thumbnail($listing_id, $attachment_id);
            $wpdb->update($table, array('is_featured' => 0), array('listing_id' => $listing_id), array('%d'), array('%d'));
            $wpdb->update($table, array('is_featured' => 1), array('listing_id' => $listing_id, 'attachment_id' => $attachment_id), array('%d'), array('%d', '%d'));
        }
    }

    protected static function delete_listing_media(int $listing_id, array $attachment_ids): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'tm_listing_media';
        foreach (array_values(array_filter(array_map('absint', $attachment_ids))) as $attachment_id) {
            $wpdb->delete($table, array('listing_id' => $listing_id, 'attachment_id' => $attachment_id), array('%d', '%d'));
            if ((int) get_post_thumbnail_id($listing_id) === $attachment_id) {
                delete_post_thumbnail($listing_id);
            }
            wp_delete_attachment($attachment_id, true);
        }
    }

    protected static function duplicate_attachment(int $attachment_id, int $parent_post_id): int
    {
        $file = get_attached_file($attachment_id);
        if (! $file || ! file_exists($file)) {
            return 0;
        }

        $uploads = wp_upload_dir();
        if (! empty($uploads['error'])) {
            return 0;
        }

        $path_info = pathinfo($file);
        $new_filename = wp_unique_filename($uploads['path'], ($path_info['filename'] ?? 'image') . '-copy.' . ($path_info['extension'] ?? 'jpg'));
        $new_path = trailingslashit($uploads['path']) . $new_filename;
        if (! copy($file, $new_path)) {
            return 0;
        }

        $mime = get_post_mime_type($attachment_id) ?: mime_content_type($new_path);
        $attachment = array(
            'guid'           => trailingslashit($uploads['url']) . $new_filename,
            'post_mime_type' => $mime ?: 'image/jpeg',
            'post_title'     => sanitize_file_name($path_info['filename'] ?? $new_filename),
            'post_content'   => '',
            'post_status'    => 'inherit',
        );
        $new_attachment_id = wp_insert_attachment($attachment, $new_path, $parent_post_id);
        if (! $new_attachment_id || is_wp_error($new_attachment_id)) {
            return 0;
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        $metadata = wp_generate_attachment_metadata($new_attachment_id, $new_path);
        if (! is_wp_error($metadata)) {
            wp_update_attachment_metadata($new_attachment_id, $metadata);
        }

        return (int) $new_attachment_id;
    }

    protected static function ensure_listing_featured_image(int $listing_id): void
    {
        global $wpdb;
        $gallery_ids = TM_Helpers::get_listing_gallery_ids($listing_id);
        if (empty($gallery_ids)) {
            delete_post_thumbnail($listing_id);
            return;
        }
        $first_id = (int) $gallery_ids[0];
        if ((int) get_post_thumbnail_id($listing_id) !== $first_id) {
            set_post_thumbnail($listing_id, $first_id);
        }
        $table = $wpdb->prefix . 'tm_listing_media';
        $wpdb->update($table, array('is_featured' => 0), array('listing_id' => $listing_id), array('%d'), array('%d'));
        $wpdb->update($table, array('is_featured' => 1), array('listing_id' => $listing_id, 'attachment_id' => $first_id), array('%d'), array('%d', '%d'));
    }

    protected static function process_attachment_image(int $attachment_id): void
    {
        $file = get_attached_file($attachment_id);
        if (! $file || ! file_exists($file)) {
            return;
        }
        $max_width = (int) TM_Helpers::get_option('tm_settings_marketplace', 'image_max_width', 2000);
        $quality = (int) TM_Helpers::get_option('tm_settings_marketplace', 'image_quality', 82);
        $editor = wp_get_image_editor($file);
        if (! is_wp_error($editor)) {
            $size = $editor->get_size();
            if (! empty($size['width']) && (int) $size['width'] > $max_width) {
                $editor->resize($max_width, null, false);
            }
            if (method_exists($editor, 'set_quality')) {
                $editor->set_quality($quality);
            }
            $editor->save($file);
        }
        $enable_watermark = (int) TM_Helpers::get_option('tm_settings_marketplace', 'enable_watermark', 1);
        $watermark_logo_id = (int) TM_Helpers::get_option('tm_settings_branding', 'watermark_logo_id', 0);
        if (! $enable_watermark || ! $watermark_logo_id) {
            return;
        }
        $watermark_file = get_attached_file($watermark_logo_id);
        if (! $watermark_file || ! file_exists($watermark_file)) {
            return;
        }
        self::apply_watermark($file, $watermark_file);
        $metadata = wp_generate_attachment_metadata($attachment_id, $file);
        if (! is_wp_error($metadata)) {
            wp_update_attachment_metadata($attachment_id, $metadata);
        }
    }

    protected static function apply_watermark(string $base_path, string $watermark_path): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            return;
        }
        $base_info = wp_check_filetype($base_path);
        $wm_info = wp_check_filetype($watermark_path);
        $base = self::create_image_resource($base_path, $base_info['type'] ?? '');
        $logo = self::create_image_resource($watermark_path, $wm_info['type'] ?? '');
        if (! $base || ! $logo) {
            return;
        }
        $base_w = imagesx($base);
        $base_h = imagesy($base);
        $logo_w = imagesx($logo);
        $logo_h = imagesy($logo);
        if (! $base_w || ! $base_h || ! $logo_w || ! $logo_h) {
            imagedestroy($base); imagedestroy($logo); return;
        }
        $target_logo_w = (int) max(120, min($logo_w, $base_w * 0.22));
        $ratio = $logo_h / $logo_w;
        $target_logo_h = (int) round($target_logo_w * $ratio);
        $resized_logo = imagecreatetruecolor($target_logo_w, $target_logo_h);
        imagealphablending($resized_logo, false); imagesavealpha($resized_logo, true);
        $transparent = imagecolorallocatealpha($resized_logo, 0, 0, 0, 127);
        imagefill($resized_logo, 0, 0, $transparent);
        imagecopyresampled($resized_logo, $logo, 0, 0, 0, 0, $target_logo_w, $target_logo_h, $logo_w, $logo_h);
        $margin = 20;
        $position = (string) TM_Helpers::get_option('tm_settings_marketplace', 'watermark_position', 'bottom-right');
        $x = $margin; $y = $margin;
        switch ($position) {
            case 'top-right': $x = $base_w - $target_logo_w - $margin; break;
            case 'bottom-left': $y = $base_h - $target_logo_h - $margin; break;
            case 'center': $x = (int) round(($base_w - $target_logo_w) / 2); $y = (int) round(($base_h - $target_logo_h) / 2); break;
            case 'bottom-right': default: $x = $base_w - $target_logo_w - $margin; $y = $base_h - $target_logo_h - $margin; break;
        }
        self::imagecopymerge_alpha($base, $resized_logo, $x, $y, 0, 0, $target_logo_w, $target_logo_h, (int) TM_Helpers::get_option('tm_settings_marketplace', 'watermark_opacity', 45));
        self::save_image_resource($base, $base_path, $base_info['type'] ?? '', (int) TM_Helpers::get_option('tm_settings_marketplace', 'image_quality', 82));
        imagedestroy($base); imagedestroy($logo); imagedestroy($resized_logo);
    }

    protected static function create_image_resource(string $path, string $mime)
    {
        switch ($mime) {
            case 'image/jpeg': return function_exists('imagecreatefromjpeg') ? imagecreatefromjpeg($path) : false;
            case 'image/png': return function_exists('imagecreatefrompng') ? imagecreatefrompng($path) : false;
            case 'image/webp': return function_exists('imagecreatefromwebp') ? imagecreatefromwebp($path) : false;
            default: return false;
        }
    }

    protected static function save_image_resource($image, string $path, string $mime, int $quality): void
    {
        switch ($mime) {
            case 'image/jpeg': if (function_exists('imagejpeg')) { imagejpeg($image, $path, $quality); } break;
            case 'image/png': if (function_exists('imagepng')) { imagepng($image, $path, max(0, min(9, (int) round((100 - $quality) / 10)))); } break;
            case 'image/webp': if (function_exists('imagewebp')) { imagewebp($image, $path, $quality); } break;
        }
    }

    protected static function imagecopymerge_alpha($dst_im, $src_im, int $dst_x, int $dst_y, int $src_x, int $src_y, int $src_w, int $src_h, int $pct): void
    {
        $pct = max(0, min(100, $pct));
        imagealphablending($dst_im, true);
        imagesavealpha($dst_im, true);
        for ($x = 0; $x < $src_w; $x++) {
            for ($y = 0; $y < $src_h; $y++) {
                $rgba = imagecolorat($src_im, $x, $y);
                $alpha = ($rgba & 0x7F000000) >> 24;
                $alpha = 127 - (127 - $alpha) * ($pct / 100);
                $color = imagecolorsforindex($src_im, $rgba);
                $new_color = imagecolorallocatealpha($dst_im, $color['red'], $color['green'], $color['blue'], (int) $alpha);
                imagesetpixel($dst_im, $dst_x + $x, $dst_y + $y, $new_color);
            }
        }
    }

    public static function notice_label(string $code): string
    {
        $map = array(
            'listing_created'  => __('Aviso publicado correctamente.', 'terramarket'),
            'listing_updated'  => __('Aviso actualizado correctamente.', 'terramarket'),
            'listing_paused'   => __('Aviso pausado correctamente.', 'terramarket'),
            'listing_activated'=> __('Aviso reactivado correctamente.', 'terramarket'),
            'listing_sold'     => __('Aviso marcado como vendido.', 'terramarket'),
            'lead_sent'        => __('Tu mensaje fue enviado al vendedor.', 'terramarket'),
            'missing_fields'   => __('Completa todos los campos obligatorios.', 'terramarket'),
            'invalid_email'    => __('El email de contacto no es válido.', 'terramarket'),
            'image_required'   => __('Debes mantener al menos una imagen.', 'terramarket'),
            'too_many_images'  => __('Superaste el máximo de imágenes permitidas.', 'terramarket'),
            'save_failed'      => __('No se pudo guardar el aviso.', 'terramarket'),
            'upload_failed'    => __('Hubo un problema al subir las imágenes.', 'terramarket'),
            'lead_invalid'     => __('Completa correctamente el formulario de contacto.', 'terramarket'),
            'listing_invalid'  => __('El aviso indicado no es válido.', 'terramarket'),
            'no_permission'    => __('No tienes permiso para realizar esa acción.', 'terramarket'),
            'login_failed'     => __('No se pudo iniciar sesión con esos datos.', 'terramarket'),
            'register_invalid' => __('Completa correctamente el registro.', 'terramarket'),
            'register_failed'  => __('No se pudo crear la cuenta.', 'terramarket'),
            'email_exists'     => __('Ese email ya está registrado.', 'terramarket'),
            'profile_updated'  => __('Perfil actualizado correctamente.', 'terramarket'),
            'profile_failed'   => __('No se pudo actualizar el perfil.', 'terramarket'),
            'logged_out'       => __('Sesión cerrada correctamente.', 'terramarket'),
        );
        return $map[$code] ?? $code;
    }
}
