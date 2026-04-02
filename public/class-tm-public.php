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
                'maxImages'    => __('Has alcanzado el máximo de imágenes permitidas.', 'terramarket'),
                'loading'      => __('Cargando avisos...', 'terramarket'),
                'previewClose' => __('Cerrar vista previa', 'terramarket'),
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
        $query->set('meta_query', TM_Helpers::get_public_active_listings_meta_query());
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

        $status = TM_Helpers::get_effective_listing_status($post_id);
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

    protected static function get_category_navigation_items(): array
    {
        $categories = TM_Helpers::get_terms_for_select('tm_category');
        $subcategories = get_terms(array(
            'taxonomy'   => 'tm_subcategory',
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ));

        if (is_wp_error($subcategories)) {
            $subcategories = array();
        }

        $items = array();
        foreach ($categories as $category) {
            $items[(int) $category->term_id] = array(
                'term'          => $category,
                'count'         => (int) $category->count,
                'url'           => get_term_link($category),
                'description'   => self::get_category_description($category->slug),
                'icon'          => self::get_category_icon_markup($category),
                'subcategories' => array(),
            );
        }

        foreach ($subcategories as $subcategory) {
            if (! $subcategory instanceof WP_Term) {
                continue;
            }

            $parent_id = (int) get_term_meta($subcategory->term_id, 'tm_parent_category_id', true);
            if (! isset($items[$parent_id])) {
                continue;
            }

            $items[$parent_id]['subcategories'][] = $subcategory;
        }

        return array_values($items);
    }

    protected static function get_category_description(string $slug): string
    {
        $descriptions = array(
            'maquinarias-y-vehiculos'      => __('Tractores, cosechadoras, camionetas, implementos y equipos de trabajo pesado.', 'terramarket'),
            'animales-ganaderia'           => __('Bovinos, ovinos, equinos, aves y avisos vinculados al manejo ganadero.', 'terramarket'),
            'propiedades'                  => __('Parcelas, campos, bodegas y activos inmobiliarios ligados al mundo rural.', 'terramarket'),
            'apicultura'                   => __('Colmenas, miel, equipos y accesorios para producción y manejo apícola.', 'terramarket'),
            'produccion-agricola'          => __('Frutas, hortalizas, cereales, semillas y oportunidades productivas.', 'terramarket'),
            'deporte-rodeo'                => __('Monturas, accesorios, caballos y equipamiento ligado al rodeo y deporte.', 'terramarket'),
            'insumos-agricolas'            => __('Fertilizantes, agroquímicos, sustratos, repuestos y herramientas.', 'terramarket'),
            'mano-de-obra-profesionales'   => __('Operadores, técnicos, asesorías y servicios especializados para el agro.', 'terramarket'),
            'vivero-flores-ornamentales'   => __('Plantas, árboles, flores y soluciones ornamentales para viveros y paisajismo.', 'terramarket'),
        );

        return $descriptions[$slug] ?? __('Explora avisos activos dentro de este rubro.', 'terramarket');
    }

    protected static function get_category_icon_markup($category): string
    {
        $slug = $category instanceof WP_Term ? $category->slug : sanitize_title((string) $category);
        $term_id = $category instanceof WP_Term ? (int) $category->term_id : 0;
        $icon_id = $term_id > 0 ? (int) get_term_meta($term_id, 'tm_category_icon_id', true) : 0;

        if ($icon_id > 0) {
            $image = wp_get_attachment_image(
                $icon_id,
                'medium',
                false,
                array(
                    'class' => 'tm-category-card__icon-image',
                    'loading' => 'lazy',
                    'alt' => '',
                )
            );

            if ($image) {
                return $image;
            }
        }

        $icons = array(
            'maquinarias-y-vehiculos' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 13h13l3 3v3h-2a2 2 0 1 1-4 0H9a2 2 0 1 1-4 0H3z"/><path d="M5 13V9h8l2 4"/><path d="M7 19h0"/><path d="M15 19h0"/></svg>',
            'animales-ganaderia' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 11V8l2-2 2 2"/><path d="M19 11V8l-2-2-2 2"/><path d="M6 18v-4a6 6 0 0 1 12 0v4"/><path d="M8 18h8"/><path d="M12 12v2"/></svg>',
            'propiedades' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 11 12 5l8 6"/><path d="M6 10.5V19h12v-8.5"/><path d="M10 19v-5h4v5"/></svg>',
            'apicultura' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 9a4 4 0 1 1 8 0"/><path d="M7 12h10"/><path d="M6 15h12"/><path d="M7 18h10"/></svg>',
            'produccion-agricola' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20V6"/><path d="M8 10c0-2 2-4 4-5 2 1 4 3 4 5"/><path d="M7 14c1.5-1.5 3.5-2 5-2"/><path d="M17 14c-1.5-1.5-3.5-2-5-2"/></svg>',
            'deporte-rodeo' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 18c0-4 3-7 7-7h3"/><path d="M7 18h9"/><path d="M16 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/><path d="M10 9 7 6"/></svg>',
            'insumos-agricolas' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 4h8l2 4v10a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V8z"/><path d="M8 10h8"/><path d="M12 13v4"/></svg>',
            'mano-de-obra-profesionales' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"/><path d="M5 20a7 7 0 0 1 14 0"/><path d="M18 8h3"/></svg>',
            'vivero-flores-ornamentales' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20V9"/><path d="M12 9c0-2.5 2-4.5 4.5-4.5 0 2.5-2 4.5-4.5 4.5Z"/><path d="M12 9c0-2.5-2-4.5-4.5-4.5 0 2.5 2 4.5 4.5 4.5Z"/><path d="M8 20h8"/></svg>',
        );

        return $icons[$slug] ?? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="M12 5v14"/></svg>';
    }

    protected static function get_archive_hero_style(): string
    {
        $settings = get_option('tm_settings_marketplace', array());
        $desktop_id = (int) ($settings['hero_background_desktop_id'] ?? 0);
        $mobile_id = (int) ($settings['hero_background_mobile_id'] ?? 0);
        $overlay_color = TM_Helpers::sanitize_hex($settings['hero_overlay_color'] ?? '#ffffff', '#ffffff');
        $overlay_opacity = max(0, min(100, (int) ($settings['hero_overlay_opacity'] ?? 74)));
        $overlay_rgba = TM_Helpers::hex_to_rgba($overlay_color, $overlay_opacity / 100);
        $desktop_url = $desktop_id ? wp_get_attachment_image_url($desktop_id, 'full') : '';
        $mobile_url = $mobile_id ? wp_get_attachment_image_url($mobile_id, 'full') : '';
        $styles = array(
            '--tm-hero-overlay-color: ' . $overlay_rgba,
        );

        if ($desktop_url) {
            $styles[] = '--tm-hero-desktop-image: url("' . esc_url_raw($desktop_url) . '")';
        }

        if ($mobile_url) {
            $styles[] = '--tm-hero-mobile-image: url("' . esc_url_raw($mobile_url) . '")';
        }

        return implode('; ', $styles);
    }

    protected static function is_sticky_category_nav_enabled(): bool
    {
        return (bool) TM_Helpers::get_option('tm_settings_marketplace', 'enable_sticky_category_nav', 0);
    }

    protected static function is_category_hero_enabled(): bool
    {
        return (bool) TM_Helpers::get_option('tm_settings_marketplace', 'enable_category_hero', 1);
    }

    protected static function render_header_category_nav(array $category_items, string $archive_url, string $submit_url): string
    {
        ob_start();
        ?>
        <nav class="tm-site-nav" aria-label="<?php esc_attr_e('Categorías y accesos rápidos', 'terramarket'); ?>">
            <div class="tm-site-nav__row">
                <div class="tm-site-nav__group">
                    <span class="tm-site-nav__label"><?php esc_html_e('Categorías', 'terramarket'); ?></span>
                    <div class="tm-site-nav__list">
                        <?php foreach (array_slice($category_items, 0, 8) as $item) : ?>
                            <?php $term = $item['term']; ?>
                            <div class="tm-site-nav__item">
                                <a class="tm-site-nav__link" href="<?php echo esc_url((string) $item['url']); ?>">
                                    <span class="tm-site-nav__link-icon"><?php echo $item['icon']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                    <span class="tm-site-nav__link-text"><?php echo esc_html($term->name); ?></span>
                                </a>
                                <?php if (! empty($item['subcategories'])) : ?>
                                    <div class="tm-site-nav__panel">
                                        <div class="tm-site-nav__panel-head">
                                            <div>
                                                <span class="tm-site-nav__panel-kicker"><?php esc_html_e('Rubro', 'terramarket'); ?></span>
                                                <strong><?php echo esc_html($term->name); ?></strong>
                                                <p><?php echo esc_html($item['description']); ?></p>
                                            </div>
                                            <a class="tm-text-link" href="<?php echo esc_url((string) $item['url']); ?>"><?php esc_html_e('Ver todo', 'terramarket'); ?></a>
                                        </div>
                                        <div class="tm-site-nav__panel-list">
                                            <?php foreach (array_slice($item['subcategories'], 0, 6) as $subcategory) : ?>
                                                <a href="<?php echo esc_url(get_term_link($subcategory)); ?>"><?php echo esc_html($subcategory->name); ?></a>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="tm-site-nav__quick">
                    <a class="tm-site-nav__quick-link" href="<?php echo esc_url(add_query_arg(array('featured_only' => 1), $archive_url)); ?>"><?php esc_html_e('Destacados', 'terramarket'); ?></a>
                    <a class="tm-site-nav__quick-link" href="<?php echo esc_url(add_query_arg(array('order' => 'recent'), $archive_url)); ?>"><?php esc_html_e('Nuevos', 'terramarket'); ?></a>
                    <a class="tm-site-nav__quick-link" href="<?php echo esc_url(add_query_arg(array('order' => 'price_asc'), $archive_url)); ?>"><?php esc_html_e('Precio menor', 'terramarket'); ?></a>
                    <a class="tm-site-nav__quick-link" href="<?php echo esc_url($archive_url . '#tm-home-categories'); ?>"><?php esc_html_e('Todos los rubros', 'terramarket'); ?></a>
                    <a class="tm-site-nav__quick-link tm-site-nav__quick-link--accent" href="<?php echo esc_url($submit_url); ?>"><?php esc_html_e('Publicar', 'terramarket'); ?></a>
                </div>
            </div>
        </nav>
        <?php
        return (string) ob_get_clean();
    }

    protected static function render_category_showcase(array $category_items): string
    {
        ob_start();
        ?>
        <section id="tm-home-categories" class="tm-category-strip tm-category-strip--reference" aria-labelledby="tm-home-categories-title">
            <h2 id="tm-home-categories-title" class="tm-visually-hidden"><?php esc_html_e('Explora por categoría', 'terramarket'); ?></h2>
            <div class="tm-category-grid tm-category-grid--reference">
                <?php foreach ($category_items as $item) : ?>
                    <?php $term = $item['term']; ?>
                    <a class="tm-category-card tm-category-card--reference" href="<?php echo esc_url((string) $item['url']); ?>">
                        <span class="tm-category-card__icon"><?php echo $item['icon']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                        <strong><?php echo esc_html($term->name); ?></strong>
                        <span class="tm-category-card__count tm-category-card__count--reference"><?php echo esc_html(sprintf(_n('%d aviso', '%d avisos', (int) $item['count'], 'terramarket'), (int) $item['count'])); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
        return (string) ob_get_clean();
    }


    protected static function get_need_icon_markup(string $slug): string
    {
        $icons = array(
            'para-vina' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 5h6"/><path d="M10 5v3c0 1.7.8 3.3 2 4.3 1.2-1 2-2.6 2-4.3V5"/><path d="M8 19h8"/><path d="M12 12v7"/></svg>',
            'para-frutales' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21c4 0 7-2.7 7-6.2 0-3.2-2.5-5.8-5.7-5.8-1.6 0-3.1.7-4.1 1.8-.9-.8-2.1-1.3-3.4-1.3-2.8 0-4.8 2.1-4.8 4.9C1 18.6 5.3 21 12 21Z"/><path d="M12 7c.1-1.5.9-2.8 2.2-3.7"/></svg>',
            'para-ganaderia' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 11V8l2-2 2 2"/><path d="M19 11V8l-2-2-2 2"/><path d="M6 18v-4a6 6 0 0 1 12 0v4"/><path d="M8 18h8"/><path d="M12 12v2"/></svg>',
            'para-maiz' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20V7"/><path d="M9 10c0-2 1.3-3.7 3-5 1.7 1.3 3 3 3 5"/><path d="M8 13c1.3-1 2.7-1.5 4-1.5"/><path d="M16 13c-1.3-1-2.7-1.5-4-1.5"/><path d="M10 20h4"/></svg>',
            'para-riego' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4s5 5.5 5 9a5 5 0 1 1-10 0c0-3.5 5-9 5-9Z"/><path d="M9 18c.8.7 1.9 1 3 1"/></svg>',
            'para-apicultura' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 12h10"/><path d="M8 9a4 4 0 1 1 8 0"/><path d="M6 15h12"/><path d="M9 18h6"/></svg>',
        );

        return $icons[$slug] ?? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="M12 5v14"/></svg>';
    }

    protected static function get_need_navigation_items(array $category_items): array
    {
        $archive_url = get_post_type_archive_link('tm_listing');
        $category_index = array();
        $subcategory_index = array();

        foreach ($category_items as $item) {
            if (! isset($item['term']) || ! $item['term'] instanceof WP_Term) {
                continue;
            }

            $category_index[$item['term']->slug] = $item;

            if (empty($item['subcategories']) || ! is_array($item['subcategories'])) {
                continue;
            }

            foreach ($item['subcategories'] as $subcategory) {
                if ($subcategory instanceof WP_Term) {
                    $subcategory_index[$subcategory->slug] = $subcategory;
                }
            }
        }

        $definitions = array(
            array(
                'slug' => 'para-vina',
                'title' => __('Para viña', 'terramarket'),
                'description' => __('Entra directo a equipos, insumos y oportunidades ligadas a vitivinicultura.', 'terramarket'),
                'category_slug' => 'vitivinicultura',
                'chips' => array(__('Uvas', 'terramarket'), __('Barricas', 'terramarket'), __('Equipos', 'terramarket')),
            ),
            array(
                'slug' => 'para-frutales',
                'title' => __('Para frutales', 'terramarket'),
                'description' => __('Baja al pasillo de frutas y soluciones productivas orientadas a huertos.', 'terramarket'),
                'category_slug' => 'produccion-agricola',
                'subcategory_slug' => 'frutas',
                'chips' => array(__('Frutas', 'terramarket'), __('Cosecha', 'terramarket'), __('Huerto', 'terramarket')),
            ),
            array(
                'slug' => 'para-ganaderia',
                'title' => __('Para ganadería', 'terramarket'),
                'description' => __('Animales, infraestructura y avisos pensados para manejo ganadero.', 'terramarket'),
                'category_slug' => 'animales-ganaderia',
                'chips' => array(__('Bovinos', 'terramarket'), __('Ovinos', 'terramarket'), __('Equinos', 'terramarket')),
            ),
            array(
                'slug' => 'para-maiz',
                'title' => __('Para maíz', 'terramarket'),
                'description' => __('Accede a cereales, equipos y avisos ligados a campañas de maíz y granos.', 'terramarket'),
                'category_slug' => 'produccion-agricola',
                'subcategory_slug' => 'cereales',
                'keyword' => 'maiz',
                'chips' => array(__('Cereales', 'terramarket'), __('Maíz', 'terramarket'), __('Granos', 'terramarket')),
            ),
            array(
                'slug' => 'para-riego',
                'title' => __('Para riego', 'terramarket'),
                'description' => __('Explora riego por goteo, aspersión, bombas y automatización.', 'terramarket'),
                'category_slug' => 'riego-y-tecnologia',
                'chips' => array(__('Goteo', 'terramarket'), __('Aspersión', 'terramarket'), __('Bombas', 'terramarket')),
            ),
            array(
                'slug' => 'para-apicultura',
                'title' => __('Para apicultura', 'terramarket'),
                'description' => __('Colmenas, miel, accesorios y equipamiento para producción apícola.', 'terramarket'),
                'category_slug' => 'apicultura',
                'chips' => array(__('Colmenas', 'terramarket'), __('Miel', 'terramarket'), __('Accesorios', 'terramarket')),
            ),
        );

        $items = array();

        foreach ($definitions as $definition) {
            $category_slug = $definition['category_slug'];
            if (! isset($category_index[$category_slug])) {
                continue;
            }

            $context_item = $category_index[$category_slug];
            $query_args = array();
            $count = (int) ($context_item['count'] ?? 0);

            if (! empty($definition['category_slug'])) {
                $query_args['category'] = (string) $definition['category_slug'];
            }

            if (! empty($definition['subcategory_slug']) && isset($subcategory_index[$definition['subcategory_slug']])) {
                $query_args['subcategory'] = (string) $definition['subcategory_slug'];
                $count = (int) $subcategory_index[$definition['subcategory_slug']]->count;
            }

            if (! empty($definition['keyword'])) {
                $query_args['keyword'] = (string) $definition['keyword'];
            }

            $items[] = array(
                'slug' => $definition['slug'],
                'title' => $definition['title'],
                'description' => $definition['description'],
                'chips' => $definition['chips'],
                'icon' => self::get_need_icon_markup($definition['slug']),
                'url' => add_query_arg($query_args, $archive_url),
                'count' => $count,
                'category_slug' => $category_slug,
                'category_name' => $context_item['term']->name,
            );
        }

        return $items;
    }

    protected static function filter_need_items_for_context(array $need_items, ?WP_Term $current_term, ?array $parent_category_item = null): array
    {
        if (! ($current_term instanceof WP_Term)) {
            return $need_items;
        }

        $context_slug = '';
        if ('tm_category' === $current_term->taxonomy) {
            $context_slug = $current_term->slug;
        } elseif ('tm_subcategory' === $current_term->taxonomy && $parent_category_item && isset($parent_category_item['term']) && $parent_category_item['term'] instanceof WP_Term) {
            $context_slug = $parent_category_item['term']->slug;
        }

        if (! $context_slug) {
            return $need_items;
        }

        $filtered = array_values(array_filter($need_items, static function (array $item) use ($context_slug): bool {
            return isset($item['category_slug']) && $context_slug === $item['category_slug'];
        }));

        return ! empty($filtered) ? $filtered : $need_items;
    }

    protected static function render_need_navigation_section(array $need_items, bool $compact = false): string
    {
        if (empty($need_items)) {
            return '';
        }

        $section_class = $compact ? 'tm-need-strip tm-need-strip--context' : 'tm-need-strip';
        $grid_class = $compact ? 'tm-need-grid tm-need-grid--context' : 'tm-need-grid';
        $kicker = $compact ? __('Explora por necesidad', 'terramarket') : __('Explora por necesidad', 'terramarket');
        $title = $compact ? __('Usos frecuentes dentro de este rubro', 'terramarket') : __('Recorridos rápidos según tu necesidad', 'terramarket');
        $copy = $compact
            ? __('Atajos comerciales para bajar más rápido a resultados relacionados con este contexto.', 'terramarket')
            : __('Suma una segunda capa de navegación por intención, sin perder la entrada principal por rubro.', 'terramarket');

        ob_start();
        ?>
        <section id="tm-home-needs" class="<?php echo esc_attr($section_class); ?>">
            <div class="tm-section-head">
                <div>
                    <span class="tm-section-kicker"><?php echo esc_html($kicker); ?></span>
                    <h2><?php echo esc_html($title); ?></h2>
                    <p><?php echo esc_html($copy); ?></p>
                </div>
            </div>
            <div class="<?php echo esc_attr($grid_class); ?>">
                <?php foreach ($need_items as $item) : ?>
                    <a class="tm-need-card" href="<?php echo esc_url((string) $item['url']); ?>">
                        <span class="tm-need-card__icon"><?php echo $item['icon']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                        <div class="tm-need-card__body">
                            <div class="tm-need-card__topline">
                                <span class="tm-need-card__eyebrow"><?php echo esc_html($item['category_name']); ?></span>
                                <?php if (! empty($item['count'])) : ?>
                                    <span class="tm-need-card__count"><?php echo esc_html(sprintf(_n('%d aviso', '%d avisos', (int) $item['count'], 'terramarket'), (int) $item['count'])); ?></span>
                                <?php endif; ?>
                            </div>
                            <strong><?php echo esc_html((string) $item['title']); ?></strong>
                            <p><?php echo esc_html((string) $item['description']); ?></p>
                            <?php if (! empty($item['chips'])) : ?>
                                <div class="tm-need-card__chips">
                                    <?php foreach (array_slice((array) $item['chips'], 0, 3) as $chip) : ?>
                                        <span><?php echo esc_html((string) $chip); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <span class="tm-need-card__cta"><?php esc_html_e('Ver recorrido', 'terramarket'); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
        return (string) ob_get_clean();
    }


    protected static function get_category_item_by_term(WP_Term $term, array $category_items): ?array
    {
        foreach ($category_items as $item) {
            if (! isset($item['term']) || ! $item['term'] instanceof WP_Term) {
                continue;
            }

            if ((int) $item['term']->term_id === (int) $term->term_id) {
                return $item;
            }
        }

        return null;
    }

    protected static function get_parent_category_item_for_term(WP_Term $term, array $category_items): ?array
    {
        if ('tm_subcategory' !== $term->taxonomy) {
            return null;
        }

        $parent_id = (int) get_term_meta($term->term_id, 'tm_parent_category_id', true);
        if ($parent_id < 1) {
            return null;
        }

        foreach ($category_items as $item) {
            if (! isset($item['term']) || ! $item['term'] instanceof WP_Term) {
                continue;
            }

            if ((int) $item['term']->term_id === $parent_id) {
                return $item;
            }
        }

        return null;
    }

    protected static function get_context_reset_url(): string
    {
        if (is_tax()) {
            $term = get_queried_object();
            if ($term instanceof WP_Term) {
                $link = get_term_link($term);
                if (! is_wp_error($link)) {
                    return $link;
                }
            }
        }

        return get_post_type_archive_link('tm_listing');
    }

    protected static function render_archive_breadcrumbs(?WP_Term $current_term, ?array $selected_category_item, ?array $parent_category_item): string
    {
        if (! ($current_term instanceof WP_Term)) {
            return '';
        }

        $items = array(
            array(
                'label' => __('Inicio', 'terramarket'),
                'url'   => get_post_type_archive_link('tm_listing'),
            ),
        );

        if ($parent_category_item && isset($parent_category_item['term']) && $parent_category_item['term'] instanceof WP_Term) {
            $parent_link = get_term_link($parent_category_item['term']);
            $items[] = array(
                'label' => $parent_category_item['term']->name,
                'url'   => is_wp_error($parent_link) ? '' : (string) $parent_link,
            );
        }

        if ('tm_category' === $current_term->taxonomy) {
            $items[] = array(
                'label' => $current_term->name,
                'url'   => '',
            );
        } elseif ('tm_subcategory' === $current_term->taxonomy) {
            $items[] = array(
                'label' => $current_term->name,
                'url'   => '',
            );
        } else {
            $items[] = array(
                'label' => $current_term->name,
                'url'   => '',
            );
        }

        ob_start();
        ?>
        <nav class="tm-breadcrumbs" aria-label="<?php esc_attr_e('Breadcrumb', 'terramarket'); ?>">
            <?php foreach ($items as $index => $item) : ?>
                <span class="tm-breadcrumbs__item">
                    <?php if (! empty($item['url']) && $index < count($items) - 1) : ?>
                        <a href="<?php echo esc_url((string) $item['url']); ?>"><?php echo esc_html((string) $item['label']); ?></a>
                    <?php else : ?>
                        <span aria-current="page"><?php echo esc_html((string) $item['label']); ?></span>
                    <?php endif; ?>
                </span>
            <?php endforeach; ?>
        </nav>
        <?php
        return (string) ob_get_clean();
    }

    protected static function render_category_landing_panel(WP_Term $current_term, WP_Query $query, ?array $selected_category_item, ?array $parent_category_item): string
    {
        $context_item = $selected_category_item ?: $parent_category_item;
        if (! $context_item || empty($context_item['term']) || ! ($context_item['term'] instanceof WP_Term)) {
            return '';
        }

        $context_term = $context_item['term'];
        $current_link = get_term_link($current_term);
        $context_link = get_term_link($context_term);
        $is_subcategory = 'tm_subcategory' === $current_term->taxonomy;
        $description = trim((string) wp_strip_all_tags($current_term->description));

        if (! $description) {
            $description = $is_subcategory
                ? sprintf(__('Subpasillo dentro de %s para bajar a resultados más específicos sin perder el contexto del rubro.', 'terramarket'), $context_term->name)
                : (string) ($context_item['description'] ?? __('Landing de rubro con acceso rápido a sus pasillos internos y avisos principales.', 'terramarket'));
        }

        $subcategories = $context_item['subcategories'] ?? array();
        $context_url = is_wp_error($context_link) ? self::get_context_reset_url() : (string) $context_link;
        $current_url = is_wp_error($current_link) ? $context_url : (string) $current_link;
        $base_browse_url = remove_query_arg(array('tm_page', 'order', 'featured_only', 'price_min', 'price_max', 'with_photo', 'keyword', 'region', 'comuna', 'condition'), $current_url);

        ob_start();
        ?>
        <section class="tm-category-landing" id="tm-category-landing">
            <?php echo self::render_archive_breadcrumbs($current_term, $selected_category_item, $parent_category_item); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <div class="tm-category-landing__shell">
                <div class="tm-category-landing__intro">
                    <span class="tm-section-kicker"><?php echo esc_html($is_subcategory ? __('Subcategoría', 'terramarket') : __('Landing de rubro', 'terramarket')); ?></span>
                    <div class="tm-category-landing__title-row">
                        <span class="tm-category-landing__icon"><?php echo $context_item['icon']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                        <div>
                            <h2><?php echo esc_html($current_term->name); ?></h2>
                            <p><?php echo esc_html($description); ?></p>
                        </div>
                    </div>
                </div>
                <div class="tm-category-landing__actions">
                    <a class="tm-link-button" href="<?php echo esc_url($base_browse_url); ?>"><?php esc_html_e('Ver todo', 'terramarket'); ?></a>
                    <a class="tm-link-button tm-link-button--light" href="<?php echo esc_url(add_query_arg(array('featured_only' => 1), $base_browse_url)); ?>"><?php esc_html_e('Destacados', 'terramarket'); ?></a>
                    <a class="tm-link-button tm-link-button--light" href="<?php echo esc_url(add_query_arg(array('order' => 'recent'), $base_browse_url)); ?>"><?php esc_html_e('Nuevos', 'terramarket'); ?></a>
                    <?php if ($is_subcategory) : ?>
                        <a class="tm-link-button tm-link-button--ghost" href="<?php echo esc_url($context_url); ?>"><?php echo esc_html(sprintf(__('Volver a %s', 'terramarket'), $context_term->name)); ?></a>
                    <?php else : ?>
                        <a class="tm-link-button tm-link-button--ghost" href="<?php echo esc_url(get_post_type_archive_link('tm_listing') . '#tm-home-categories'); ?>"><?php esc_html_e('Ver todos los rubros', 'terramarket'); ?></a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (! empty($subcategories)) : ?>
                <div class="tm-category-passages">
                    <div class="tm-section-head tm-section-head--tight">
                        <div>
                            <span class="tm-section-kicker"><?php esc_html_e('Pasillos internos', 'terramarket'); ?></span>
                            <h3><?php echo esc_html(sprintf(__('Subcategorías dentro de %s', 'terramarket'), $context_term->name)); ?></h3>
                            <p><?php esc_html_e('Entra directo al tramo correcto antes de bajar al grid de avisos.', 'terramarket'); ?></p>
                        </div>
                    </div>
                    <div class="tm-subcategory-board">
                        <?php foreach ($subcategories as $subcategory) : ?>
                            <?php $is_active_subcategory = 'tm_subcategory' === $current_term->taxonomy && (int) $subcategory->term_id === (int) $current_term->term_id; ?>
                            <a class="tm-subcategory-card<?php echo $is_active_subcategory ? ' is-active' : ''; ?>" href="<?php echo esc_url(get_term_link($subcategory)); ?>">
                                <span class="tm-subcategory-card__eyebrow"><?php echo esc_html($context_term->name); ?></span>
                                <strong><?php echo esc_html($subcategory->name); ?></strong>
                                <span class="tm-subcategory-card__meta"><?php echo esc_html(sprintf(_n('%d aviso', '%d avisos', (int) $subcategory->count, 'terramarket'), (int) $subcategory->count)); ?></span>
                                <span class="tm-subcategory-card__cta"><?php esc_html_e('Entrar al pasillo', 'terramarket'); ?> <span aria-hidden="true">→</span></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    protected static function render_market_toolbar(array $filters, ?WP_Term $current_term = null): string
    {
        $base_url = TM_Helpers::current_url(array('tm_page'));
        $reset_url = self::get_context_reset_url();
        $active_count = self::count_active_filters($filters);
        $term_label = $current_term instanceof WP_Term ? $current_term->name : __('Todo el mall', 'terramarket');
        $featured_url = add_query_arg(array('featured_only' => 1, 'tm_page' => 1), remove_query_arg(array('order', 'tm_page'), $base_url));
        $recent_url = add_query_arg(array('order' => 'recent', 'tm_page' => 1), remove_query_arg(array('featured_only', 'tm_page'), $base_url));
        $price_asc_url = add_query_arg(array('order' => 'price_asc', 'tm_page' => 1), remove_query_arg(array('featured_only', 'tm_page'), $base_url));
        $price_desc_url = add_query_arg(array('order' => 'price_desc', 'tm_page' => 1), remove_query_arg(array('featured_only', 'tm_page'), $base_url));

        ob_start();
        ?>
        <div class="tm-market-toolbar">
            <div class="tm-market-toolbar__context">
                <span class="tm-market-toolbar__chip tm-market-toolbar__chip--context"><?php echo esc_html($term_label); ?></span>
                <span class="tm-market-toolbar__chip"><?php echo esc_html(sprintf(__('Filtros activos: %d', 'terramarket'), $active_count)); ?></span>
            </div>
            <div class="tm-market-toolbar__actions">
                <span class="tm-market-toolbar__label"><?php esc_html_e('Orden rápido', 'terramarket'); ?></span>
                <a class="tm-market-toolbar__link" href="<?php echo esc_url($featured_url); ?>"><?php esc_html_e('Destacados', 'terramarket'); ?></a>
                <a class="tm-market-toolbar__link" href="<?php echo esc_url($recent_url); ?>"><?php esc_html_e('Nuevos', 'terramarket'); ?></a>
                <a class="tm-market-toolbar__link" href="<?php echo esc_url($price_asc_url); ?>"><?php esc_html_e('Precio menor', 'terramarket'); ?></a>
                <a class="tm-market-toolbar__link" href="<?php echo esc_url($price_desc_url); ?>"><?php esc_html_e('Precio mayor', 'terramarket'); ?></a>
                <?php if (self::has_active_filters($filters)) : ?>
                    <a class="tm-market-toolbar__link tm-market-toolbar__link--ghost" href="<?php echo esc_url($reset_url); ?>"><?php esc_html_e('Limpiar', 'terramarket'); ?></a>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    public static function render_standalone_page(): string
    {
        ob_start();
        $marketplace_name = TM_Helpers::get_marketplace_name();
        $archive_url = get_post_type_archive_link('tm_listing');
        $submit_url  = TM_Helpers::get_submit_page_url();
        $account_url = TM_Helpers::get_account_page_url();
        $logo_url    = TM_Helpers::get_logo_url();
        ?>
        <a class="tm-skip-link" href="#tm-main-content"><?php esc_html_e('Saltar al contenido', 'terramarket'); ?></a>
        <div class="tm-app">
            <header class="tm-site-header tm-site-header--simple">
                <div class="tm-site-header__inner tm-site-header__inner--simple">
                    <div class="tm-site-header__left">
                        <a class="tm-brand" href="<?php echo esc_url($archive_url); ?>">
                            <?php if ($logo_url) : ?>
                                <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($marketplace_name); ?>">
                            <?php else : ?>
                                <span class="tm-brand__mark">TM</span>
                            <?php endif; ?>
                            <span class="tm-brand__content">
                                <span class="tm-brand__text"><?php echo esc_html($marketplace_name); ?></span>
                            </span>
                        </a>
                    </div>
                    <form class="tm-header-search tm-header-search--simple" method="get" action="<?php echo esc_url($archive_url); ?>">
                        <label class="tm-visually-hidden" for="tm-header-keyword"><?php esc_html_e('Buscar aviso', 'terramarket'); ?></label>
                        <input id="tm-header-keyword" type="text" name="keyword" value="<?php echo esc_attr(sanitize_text_field(wp_unslash($_GET['keyword'] ?? ''))); ?>" placeholder="<?php esc_attr_e('Buscar productos o servicios…', 'terramarket'); ?>">
                    </form>
                    <div class="tm-header-actions tm-header-actions--simple">
                        <a class="tm-link-button" href="<?php echo esc_url($submit_url); ?>"><?php esc_html_e('Publicar aviso', 'terramarket'); ?></a>
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
        return self::count_active_filters($filters) > 0;
    }

    protected static function count_active_filters(array $filters): int
    {
        $count = 0;

        foreach (array('keyword', 'category', 'subcategory', 'region', 'comuna', 'condition', 'price_min', 'price_max') as $key) {
            if (! empty($filters[$key])) {
                $count++;
            }
        }

        if (! empty($filters['featured_only'])) {
            $count++;
        }

        if (! empty($filters['with_photo'])) {
            $count++;
        }

        if (! empty($filters['order']) && 'featured_recent' !== $filters['order']) {
            $count++;
        }

        return $count;
    }

    protected static function get_filter_term_name(string $taxonomy, string $slug): string
    {
        $term = get_term_by('slug', $slug, $taxonomy);

        return $term instanceof WP_Term ? $term->name : $slug;
    }

    protected static function get_filter_order_label(string $order): string
    {
        $labels = array(
            'featured_recent' => __('Destacados primero', 'terramarket'),
            'recent'          => __('MÃ¡s recientes', 'terramarket'),
            'oldest'          => __('MÃ¡s antiguos', 'terramarket'),
            'price_asc'       => __('Precio menor a mayor', 'terramarket'),
            'price_desc'      => __('Precio mayor a menor', 'terramarket'),
        );

        return $labels[$order] ?? $order;
    }

    protected static function render_active_filter_chips(array $filters): string
    {
        $chips = array();

        if (! empty($filters['keyword'])) {
            $chips[] = array(
                'field' => 'keyword',
                'label' => sprintf(__('BÃºsqueda: %s', 'terramarket'), (string) $filters['keyword']),
            );
        }

        foreach (array(
            'category'    => array('taxonomy' => 'tm_category', 'label' => __('CategorÃ­a: %s', 'terramarket')),
            'subcategory' => array('taxonomy' => 'tm_subcategory', 'label' => __('SubcategorÃ­a: %s', 'terramarket')),
            'region'      => array('taxonomy' => 'tm_region', 'label' => __('RegiÃ³n: %s', 'terramarket')),
            'comuna'      => array('taxonomy' => 'tm_comuna', 'label' => __('Comuna: %s', 'terramarket')),
            'condition'   => array('taxonomy' => 'tm_condition', 'label' => __('CondiciÃ³n: %s', 'terramarket')),
        ) as $key => $config) {
            if (empty($filters[$key])) {
                continue;
            }

            $chips[] = array(
                'field' => $key,
                'label' => sprintf($config['label'], self::get_filter_term_name($config['taxonomy'], (string) $filters[$key])),
            );
        }

        if (! empty($filters['price_min'])) {
            $chips[] = array(
                'field' => 'price_min',
                'label' => sprintf(__('Desde: %s', 'terramarket'), TM_Helpers::format_price_clp((int) $filters['price_min'])),
            );
        }

        if (! empty($filters['price_max'])) {
            $chips[] = array(
                'field' => 'price_max',
                'label' => sprintf(__('Hasta: %s', 'terramarket'), TM_Helpers::format_price_clp((int) $filters['price_max'])),
            );
        }

        if (! empty($filters['with_photo'])) {
            $chips[] = array(
                'field' => 'with_photo',
                'label' => __('Solo con foto', 'terramarket'),
            );
        }

        if (! empty($filters['featured_only'])) {
            $chips[] = array(
                'field' => 'featured_only',
                'label' => __('Solo destacados', 'terramarket'),
            );
        }

        if (! empty($filters['order']) && 'featured_recent' !== $filters['order']) {
            $chips[] = array(
                'field'       => 'order',
                'label'       => sprintf(__('Orden: %s', 'terramarket'), self::get_filter_order_label((string) $filters['order'])),
                'reset_value' => 'featured_recent',
            );
        }

        if (empty($chips)) {
            return '';
        }

        ob_start();
        ?>
        <div class="tm-active-filters" aria-label="<?php esc_attr_e('Filtros activos', 'terramarket'); ?>">
            <span class="tm-active-filters__label"><?php esc_html_e('Filtros activos', 'terramarket'); ?></span>
            <div class="tm-active-filters__list">
                <?php foreach ($chips as $chip) : ?>
                    <button type="button" class="tm-filter-chip" data-remove-filter="<?php echo esc_attr($chip['field']); ?>"<?php echo isset($chip['reset_value']) ? ' data-reset-value="' . esc_attr((string) $chip['reset_value']) . '"' : ''; ?>>
                        <span><?php echo esc_html($chip['label']); ?></span>
                        <span class="tm-filter-chip__remove" aria-hidden="true">x</span>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    protected static function get_filter_order_label_clean(string $order): string
    {
        $labels = array(
            'featured_recent' => __('Destacados primero', 'terramarket'),
            'recent'          => __('Mas recientes', 'terramarket'),
            'oldest'          => __('Mas antiguos', 'terramarket'),
            'price_asc'       => __('Precio menor a mayor', 'terramarket'),
            'price_desc'      => __('Precio mayor a menor', 'terramarket'),
        );

        return $labels[$order] ?? $order;
    }

    protected static function render_active_filter_chips_clean(array $filters): string
    {
        $chips = array();

        if (! empty($filters['keyword'])) {
            $chips[] = array(
                'field' => 'keyword',
                'label' => sprintf(__('Busqueda: %s', 'terramarket'), (string) $filters['keyword']),
            );
        }

        foreach (array(
            'category'    => array('taxonomy' => 'tm_category', 'label' => __('Categoria: %s', 'terramarket')),
            'subcategory' => array('taxonomy' => 'tm_subcategory', 'label' => __('Subcategoria: %s', 'terramarket')),
            'region'      => array('taxonomy' => 'tm_region', 'label' => __('Region: %s', 'terramarket')),
            'comuna'      => array('taxonomy' => 'tm_comuna', 'label' => __('Comuna: %s', 'terramarket')),
            'condition'   => array('taxonomy' => 'tm_condition', 'label' => __('Condicion: %s', 'terramarket')),
        ) as $key => $config) {
            if (empty($filters[$key])) {
                continue;
            }

            $chips[] = array(
                'field' => $key,
                'label' => sprintf($config['label'], self::get_filter_term_name($config['taxonomy'], (string) $filters[$key])),
            );
        }

        if (! empty($filters['price_min'])) {
            $chips[] = array(
                'field' => 'price_min',
                'label' => sprintf(__('Desde: %s', 'terramarket'), TM_Helpers::format_price_clp((int) $filters['price_min'])),
            );
        }

        if (! empty($filters['price_max'])) {
            $chips[] = array(
                'field' => 'price_max',
                'label' => sprintf(__('Hasta: %s', 'terramarket'), TM_Helpers::format_price_clp((int) $filters['price_max'])),
            );
        }

        if (! empty($filters['with_photo'])) {
            $chips[] = array(
                'field' => 'with_photo',
                'label' => __('Solo con foto', 'terramarket'),
            );
        }

        if (! empty($filters['featured_only'])) {
            $chips[] = array(
                'field' => 'featured_only',
                'label' => __('Solo destacados', 'terramarket'),
            );
        }

        if (! empty($filters['order']) && 'featured_recent' !== $filters['order']) {
            $chips[] = array(
                'field'       => 'order',
                'label'       => sprintf(__('Orden: %s', 'terramarket'), self::get_filter_order_label_clean((string) $filters['order'])),
                'reset_value' => 'featured_recent',
            );
        }

        if (empty($chips)) {
            return '';
        }

        ob_start();
        ?>
        <div class="tm-active-filters" aria-label="<?php esc_attr_e('Filtros activos', 'terramarket'); ?>">
            <span class="tm-active-filters__label"><?php esc_html_e('Filtros activos', 'terramarket'); ?></span>
            <div class="tm-active-filters__list">
                <?php foreach ($chips as $chip) : ?>
                    <button type="button" class="tm-filter-chip" data-remove-filter="<?php echo esc_attr($chip['field']); ?>"<?php echo isset($chip['reset_value']) ? ' data-reset-value="' . esc_attr((string) $chip['reset_value']) . '"' : ''; ?>>
                        <span><?php echo esc_html($chip['label']); ?></span>
                        <span class="tm-filter-chip__remove" aria-hidden="true">x</span>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    protected static function get_listing_query_args(array $filters): array
    {
        $args = array(
            'post_type'      => 'tm_listing',
            'post_status'    => 'publish',
            'posts_per_page' => (int) TM_Helpers::get_option('tm_settings_general', 'listings_per_page', 16),
            'paged'          => max(1, (int) ($filters['page'] ?? 1)),
            's'              => (string) ($filters['keyword'] ?? ''),
            'meta_query'     => TM_Helpers::get_public_active_listings_meta_query(),
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
        $category_items = self::get_category_navigation_items();
        $current_term = is_tax() ? get_queried_object() : null;
        $selected_category_item = null;
        $parent_category_item = null;

        if ($current_term instanceof WP_Term) {
            if ('tm_category' === $current_term->taxonomy) {
                $selected_category_item = self::get_category_item_by_term($current_term, $category_items);
            } elseif ('tm_subcategory' === $current_term->taxonomy) {
                $parent_category_item = self::get_parent_category_item_for_term($current_term, $category_items);
            }
        }

        $hero_title = $current_term instanceof WP_Term ? $current_term->name : __('Encuentra lo que buscas fácilmente', 'terramarket');
        $hero_copy = $current_term instanceof WP_Term
            ? wp_strip_all_tags($current_term->description ?: __('Explora avisos filtrados por este rubro del marketplace.', 'terramarket'))
            : __('Explora nuestras categorías y llega más rápido al aviso correcto con una navegación simple y clara.', 'terramarket');
        $hero_secondary_href = is_post_type_archive('tm_listing') ? '#tm-market-section' : ($current_term instanceof WP_Term && ('tm_category' === $current_term->taxonomy || 'tm_subcategory' === $current_term->taxonomy) ? '#tm-category-landing' : '#tm-market-section');
        $hero_secondary_label = is_post_type_archive('tm_listing') ? __('Ver avisos', 'terramarket') : ($current_term instanceof WP_Term && ('tm_category' === $current_term->taxonomy || 'tm_subcategory' === $current_term->taxonomy) ? __('Ver pasillos', 'terramarket') : __('Ver avisos', 'terramarket'));

        $show_archive_hero = is_post_type_archive('tm_listing') || ! ($current_term instanceof WP_Term) || ! in_array($current_term->taxonomy, array('tm_category', 'tm_subcategory'), true) || self::is_category_hero_enabled();

        ob_start();
        ?>
        <?php if (is_post_type_archive('tm_listing')) : ?>
            <?php echo self::render_category_showcase($category_items); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <?php endif; ?>

        <?php if ($show_archive_hero) : ?>
        <section class="tm-home-hero tm-home-hero--simple-reference" style="<?php echo esc_attr(self::get_archive_hero_style()); ?>">
            <div class="tm-home-hero__copy">
                <span class="tm-home-kicker"><?php esc_html_e('Marketplace simple', 'terramarket'); ?></span>
                <h1><?php echo esc_html($hero_title); ?></h1>
                <p><?php echo esc_html($hero_copy); ?></p>
                <div class="tm-home-hero__actions">
                    <a class="tm-link-button tm-link-button--dark" href="<?php echo esc_url($hero_secondary_href); ?>"><?php echo esc_html($hero_secondary_label); ?></a>
                    <a class="tm-link-button tm-link-button--light" href="<?php echo esc_url(TM_Helpers::get_submit_page_url()); ?>"><?php esc_html_e('Publicar aviso', 'terramarket'); ?></a>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($current_term instanceof WP_Term && ('tm_category' === $current_term->taxonomy || 'tm_subcategory' === $current_term->taxonomy)) : ?>
            <?php echo self::render_category_landing_panel($current_term, $query, $selected_category_item, $parent_category_item); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <?php endif; ?>

        <section id="tm-market-section" class="tm-market-section tm-market-section--simple">
            <div class="tm-section-head tm-section-head--simple">
                <div>
                    <span class="tm-section-kicker"><?php esc_html_e('Avisos disponibles', 'terramarket'); ?></span>
                    <h2><?php echo esc_html($current_term instanceof WP_Term ? sprintf(__('Avisos en %s', 'terramarket'), $current_term->name) : __('Explora avisos disponibles', 'terramarket')); ?></h2>
                    <p><?php echo esc_html(sprintf(_n('%d aviso encontrado', '%d avisos encontrados', (int) $query->found_posts, 'terramarket'), (int) $query->found_posts)); ?></p>
                </div>
            </div>
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
        $active_count = self::count_active_filters($filters);
        $reset_url = self::get_context_reset_url();
        ob_start();
        ?>
        <form id="tm-archive-filters" class="tm-filter-bar tm-filter-bar--simple-reference" method="get" action="<?php echo esc_url(TM_Helpers::current_url(array('tm_page'))); ?>" data-auto-submit="1">
            <input type="hidden" name="tm_page" value="1">
            <div class="tm-filter-bar__quick">
                <div class="tm-filter-quick-group tm-filter-quick-group--price">
                    <span class="tm-filter-quick-group__label"><?php esc_html_e('Precio', 'terramarket'); ?></span>
                    <div class="tm-filter-quick-group__fields">
                        <input type="number" name="price_min" min="0" step="1" value="<?php echo esc_attr((string) $filters['price_min']); ?>" placeholder="<?php esc_attr_e('Mínimo', 'terramarket'); ?>">
                        <input type="number" name="price_max" min="0" step="1" value="<?php echo esc_attr((string) $filters['price_max']); ?>" placeholder="<?php esc_attr_e('Máximo', 'terramarket'); ?>">
                    </div>
                </div>
                <div class="tm-filter-quick-group">
                    <span class="tm-filter-quick-group__label"><?php esc_html_e('Ubicación', 'terramarket'); ?></span>
                    <select name="region" id="tm_filter_region"><option value=""><?php esc_html_e('Todas las regiones', 'terramarket'); ?></option><?php foreach ($regions as $region) : ?><option value="<?php echo esc_attr($region->slug); ?>" <?php selected($filters['region'], $region->slug); ?>><?php echo esc_html($region->name); ?></option><?php endforeach; ?></select>
                </div>
                <div class="tm-filter-quick-group">
                    <span class="tm-filter-quick-group__label"><?php esc_html_e('Condición', 'terramarket'); ?></span>
                    <select name="condition"><option value=""><?php esc_html_e('Todas', 'terramarket'); ?></option><?php foreach ($conditions as $condition) : ?><option value="<?php echo esc_attr($condition->slug); ?>" <?php selected($filters['condition'], $condition->slug); ?>><?php echo esc_html($condition->name); ?></option><?php endforeach; ?></select>
                </div>
                <button type="button" class="tm-filter-toggle tm-filter-toggle--simple" data-tm-filter-toggle aria-controls="tm-filter-bar-body" aria-expanded="false">
                    <span><?php esc_html_e('Más filtros', 'terramarket'); ?></span>
                    <span class="tm-filter-toggle__count" data-tm-filter-count <?php echo $active_count > 0 ? '' : 'hidden'; ?>><?php echo esc_html((string) $active_count); ?></span>
                </button>
            </div>
            <div id="tm-filter-bar-body" class="tm-filter-bar__body" data-active-count="<?php echo esc_attr((string) $active_count); ?>">
                <div class="tm-filter-bar__grid tm-filter-bar__grid--secondary">
                    <div class="tm-filter-group tm-filter-group--search">
                        <span class="tm-filter-group__label"><?php esc_html_e('Búsqueda', 'terramarket'); ?></span>
                        <div class="tm-filter-group__fields tm-filter-group__fields--single">
                            <input type="text" name="keyword" value="<?php echo esc_attr($filters['keyword']); ?>" placeholder="<?php esc_attr_e('Palabra clave', 'terramarket'); ?>">
                        </div>
                    </div>
                    <div class="tm-filter-group">
                        <span class="tm-filter-group__label"><?php esc_html_e('Clasificación', 'terramarket'); ?></span>
                        <div class="tm-filter-group__fields">
                            <select name="category" id="tm_filter_category"><option value=""><?php esc_html_e('Categoría', 'terramarket'); ?></option><?php foreach ($categories as $category) : ?><option value="<?php echo esc_attr($category->slug); ?>" <?php selected($filters['category'], $category->slug); ?>><?php echo esc_html($category->name); ?></option><?php endforeach; ?></select>
                            <select name="subcategory" id="tm_filter_subcategory"><option value=""><?php esc_html_e('Subcategoría', 'terramarket'); ?></option><?php foreach ($subcategories as $term) : $parent_id = (int) get_term_meta($term->term_id, 'tm_parent_category_id', true); ?><option value="<?php echo esc_attr($term->slug); ?>" data-parent-category="<?php echo esc_attr((string) $parent_id); ?>" <?php selected($filters['subcategory'], $term->slug); ?>><?php echo esc_html($term->name); ?></option><?php endforeach; ?></select>
                        </div>
                    </div>
                    <div class="tm-filter-group">
                        <span class="tm-filter-group__label"><?php esc_html_e('Detalle de ubicación', 'terramarket'); ?></span>
                        <div class="tm-filter-group__fields">
                            <select name="comuna" id="tm_filter_comuna"><option value=""><?php esc_html_e('Comuna', 'terramarket'); ?></option><?php foreach ($comunas as $term) : $region_id = (int) get_term_meta($term->term_id, 'tm_region_id', true); ?><option value="<?php echo esc_attr($term->slug); ?>" data-region-id="<?php echo esc_attr((string) $region_id); ?>" <?php selected($filters['comuna'], $term->slug); ?>><?php echo esc_html($term->name); ?></option><?php endforeach; ?></select>
                        </div>
                    </div>
                    <div class="tm-filter-group tm-filter-group--options">
                        <span class="tm-filter-group__label"><?php esc_html_e('Opciones y orden', 'terramarket'); ?></span>
                        <div class="tm-filter-group__fields tm-filter-group__fields--options">
                            <label class="tm-check"><input type="checkbox" name="with_photo" value="1" <?php checked($filters['with_photo']); ?>> <span><?php esc_html_e('Solo con foto', 'terramarket'); ?></span></label>
                            <label class="tm-check"><input type="checkbox" name="featured_only" value="1" <?php checked($filters['featured_only']); ?>> <span><?php esc_html_e('Solo destacados', 'terramarket'); ?></span></label>
                            <select name="order"><option value="featured_recent" <?php selected($filters['order'], 'featured_recent'); ?>><?php esc_html_e('Destacados primero', 'terramarket'); ?></option><option value="recent" <?php selected($filters['order'], 'recent'); ?>><?php esc_html_e('Más recientes', 'terramarket'); ?></option><option value="oldest" <?php selected($filters['order'], 'oldest'); ?>><?php esc_html_e('Más antiguos', 'terramarket'); ?></option><option value="price_asc" <?php selected($filters['order'], 'price_asc'); ?>><?php esc_html_e('Precio menor a mayor', 'terramarket'); ?></option><option value="price_desc" <?php selected($filters['order'], 'price_desc'); ?>><?php esc_html_e('Precio mayor a menor', 'terramarket'); ?></option></select>
                        </div>
                    </div>
                </div>
                <div class="tm-filter-bar__actions">
                    <button type="submit" class="tm-link-button"><?php esc_html_e('Aplicar filtros', 'terramarket'); ?></button>
                    <a class="tm-link-button tm-link-button--light" href="<?php echo esc_url($reset_url); ?>"><?php esc_html_e('Limpiar', 'terramarket'); ?></a>
                </div>
            </div>
        </form>
        <?php
        return (string) ob_get_clean();
    }

    protected static function render_results_markup(WP_Query $query, array $filters): string
    {
        ob_start();
        echo self::render_active_filter_chips_clean($filters); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        if ($query->have_posts()) : ?>
            <div class="tm-results-toolbar tm-results-toolbar--simple" aria-live="polite">
                <p class="tm-results-count"><?php echo esc_html(sprintf(_n('%d resultado', '%d resultados', (int) $query->found_posts, 'terramarket'), (int) $query->found_posts)); ?></p>
            </div>
            <div class="tm-listing-grid tm-listing-grid--simple">
                <?php while ($query->have_posts()) :
                    $query->the_post();
                    $post_id = get_the_ID();
                    $price = (int) get_post_meta($post_id, 'tm_price_clp', true);
                    $region = TM_Helpers::get_term_name_for_post($post_id, 'tm_region');
                    $comuna = TM_Helpers::get_term_name_for_post($post_id, 'tm_comuna');
                    $category = TM_Helpers::get_term_name_for_post($post_id, 'tm_category');
                    $condition = TM_Helpers::get_term_name_for_post($post_id, 'tm_condition');
                    $image_url = TM_Helpers::get_listing_primary_image_url($post_id, 'medium_large');
                    $is_featured = (int) get_post_meta($post_id, 'tm_featured', true);
                    $image_count = TM_Helpers::get_listing_image_count($post_id);
                    $location_label = trim($comuna . ', ' . $region, ', ');
                ?>
                    <article class="tm-listing-card tm-listing-card--simple-reference">
                        <a class="tm-listing-card__media" href="<?php the_permalink(); ?>">
                            <?php if ($image_url) : ?>
                                <img src="<?php echo esc_url($image_url); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy" decoding="async">
                            <?php else : ?>
                                <span class="tm-placeholder"><?php esc_html_e('Sin imagen', 'terramarket'); ?></span>
                            <?php endif; ?>
                            <span class="tm-listing-card__badges">
                                <?php if ($is_featured) : ?><span class="tm-chip tm-chip--success"><?php esc_html_e('Destacado', 'terramarket'); ?></span><?php endif; ?>
                            </span>
                            <?php if ($image_count > 0) : ?>
                                <span class="tm-listing-card__media-meta"><?php echo esc_html(sprintf(_n('%d foto', '%d fotos', $image_count, 'terramarket'), $image_count)); ?></span>
                            <?php endif; ?>
                        </a>
                        <div class="tm-listing-card__body">
                            <p class="tm-listing-price tm-listing-price--featured"><?php echo esc_html(TM_Helpers::format_price_clp($price)); ?></p>
                            <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                            <div class="tm-listing-meta-stack">
                                <p class="tm-listing-meta tm-listing-meta--primary"><?php echo esc_html($location_label ?: __('Ubicación por confirmar', 'terramarket')); ?></p>
                                <div class="tm-listing-meta-row">
                                    <?php if ($condition) : ?><span class="tm-meta-pill"><?php echo esc_html($condition); ?></span><?php endif; ?>
                                    <?php if ($category) : ?><span class="tm-meta-pill"><?php echo esc_html($category); ?></span><?php endif; ?>
                                </div>
                            </div>
                            <div class="tm-card-footer tm-card-footer--simple-reference"><a class="tm-link-button tm-link-button--compact" href="<?php the_permalink(); ?>"><?php esc_html_e('Ver detalle', 'terramarket'); ?></a></div>
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
            return get_posts(array(
                'post_type'              => 'tm_listing',
                'post_status'            => 'publish',
                'post__in'               => $ids,
                'orderby'                => 'post__in',
                'posts_per_page'         => $limit,
                'fields'                 => 'ids',
                'no_found_rows'          => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
                'meta_query'             => TM_Helpers::get_public_active_listings_meta_query(),
            ));
        }

        return get_posts(array(
            'post_type'              => 'tm_listing',
            'post_status'            => 'publish',
            'posts_per_page'         => $limit,
            'meta_key'               => 'tm_featured',
            'orderby'                => array('meta_value_num' => 'DESC', 'date' => 'DESC'),
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'meta_query'             => TM_Helpers::get_public_active_listings_meta_query(),
        ));
    }

    protected static function render_single_view(int $post_id): string
    {
        if (! $post_id || 'tm_listing' !== get_post_type($post_id)) {
            return '<div class="tm-empty-state"><h3>' . esc_html__('Aviso no encontrado.', 'terramarket') . '</h3></div>';
        }

        $effective_status = TM_Helpers::get_effective_listing_status($post_id);
        if ('active' !== $effective_status && ! current_user_can('edit_post', $post_id) && ! TM_Helpers::is_listing_owner($post_id, get_current_user_id())) {
            return '<div class="tm-empty-state"><h3>' . esc_html__('Aviso no disponible.', 'terramarket') . '</h3><p>' . esc_html__('Este aviso ya no está visible públicamente.', 'terramarket') . '</p></div>';
        }
        $price = (int) get_post_meta($post_id, 'tm_price_clp', true);
        $status = TM_Helpers::get_effective_listing_status($post_id);
        $contact_name = get_post_meta($post_id, 'tm_contact_name', true);
        $contact_email = get_post_meta($post_id, 'tm_contact_email', true);
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
        $phone_link = preg_replace('/\D+/', '', (string) $contact_phone);

        ob_start();
        ?>
        <section class="tm-page-hero tm-page-hero--single">
            <div>
                <div class="tm-page-hero__chips">
                    <span class="tm-chip"><?php echo esc_html($category ?: __('Aviso', 'terramarket')); ?></span>
                    <span class="tm-chip tm-chip--success"><?php echo esc_html(TM_Helpers::get_listing_status_label($status)); ?></span>
                </div>
                <h1><?php echo esc_html(get_the_title($post_id)); ?></h1>
                <p><?php echo esc_html(trim($subcategory . ' · ' . $comuna . ', ' . $region, ' ·,')); ?></p>
            </div>
            <div class="tm-page-hero__actions">
                <a class="tm-link-button" href="#tm-contact-panel"><?php esc_html_e('Contactar vendedor', 'terramarket'); ?></a>
                <a class="tm-link-button tm-link-button--light" href="<?php echo esc_url($share_link); ?>"><?php esc_html_e('Compartir por email', 'terramarket'); ?></a>
            </div>
        </section>
        <?php if ($notice) : ?><div class="tm-notice tm-notice-success"><?php echo esc_html(self::notice_label($notice)); ?></div><?php endif; ?>
        <?php if ($error) : ?><div class="tm-notice tm-notice-error"><?php echo esc_html(self::notice_label($error)); ?></div><?php endif; ?>
        <div class="tm-single-layout">
            <section class="tm-single-main">
                <div class="tm-gallery"><?php if (! empty($gallery)) : ?><div class="tm-gallery-main"><?php if (count($gallery) > 1) : ?><span class="tm-gallery-counter" data-tm-gallery-counter>1 / <?php echo esc_html((string) count($gallery)); ?></span><?php endif; ?><img id="tm-main-image" src="<?php echo esc_url($gallery[0]['full']); ?>" alt="<?php echo esc_attr($gallery[0]['alt']); ?>" decoding="async"></div><?php if (count($gallery) > 1) : ?><div class="tm-gallery-thumbs"><?php foreach ($gallery as $index => $image) : ?><button type="button" class="tm-gallery-thumb <?php echo 0 === $index ? 'is-active' : ''; ?>" data-full-image="<?php echo esc_url($image['full']); ?>"><img src="<?php echo esc_url($image['thumb']); ?>" alt="<?php echo esc_attr($image['alt']); ?>" loading="lazy" decoding="async"></button><?php endforeach; ?></div><?php endif; ?><?php else : ?><div class="tm-gallery-empty"><?php esc_html_e('Este aviso no tiene imágenes.', 'terramarket'); ?></div><?php endif; ?></div>
                <article class="tm-panel tm-single-summary">
                    <div class="tm-single-price-row">
                        <div>
                            <span class="tm-chip"><?php echo esc_html(TM_Helpers::get_listing_status_label($status)); ?></span>
                            <p class="tm-single-price"><?php echo esc_html(TM_Helpers::format_price_clp($price)); ?></p>
                            <p class="tm-helper-text"><?php echo esc_html(trim($comuna . ', ' . $region, ', ')); ?></p>
                        </div>
                        <div class="tm-single-stats">
                            <div class="tm-stat-card"><strong><?php echo esc_html((string) $views); ?></strong><span><?php esc_html_e('Visitas', 'terramarket'); ?></span></div>
                            <div class="tm-stat-card"><strong><?php echo esc_html((string) count($gallery)); ?></strong><span><?php esc_html_e('Fotos', 'terramarket'); ?></span></div>
                        </div>
                    </div>
                    <div class="tm-single-meta-grid"><div><strong><?php esc_html_e('Condición', 'terramarket'); ?></strong><span><?php echo esc_html($condition ?: '-'); ?></span></div><div><strong><?php esc_html_e('Categoría', 'terramarket'); ?></strong><span><?php echo esc_html($category ?: '-'); ?></span></div><div><strong><?php esc_html_e('Subcategoría', 'terramarket'); ?></strong><span><?php echo esc_html($subcategory ?: '-'); ?></span></div><div><strong><?php esc_html_e('Ubicación', 'terramarket'); ?></strong><span><?php echo esc_html(trim($comuna . ', ' . $region, ', ')); ?></span></div></div>
                    <div class="tm-single-description">
                        <h2><?php esc_html_e('Descripción', 'terramarket'); ?></h2>
                        <div class="tm-content"><?php echo wpautop(wp_kses_post(get_post_field('post_content', $post_id))); ?></div>
                    </div>
                </article>
            </section>
            <aside class="tm-single-sidebar">
                <section class="tm-panel tm-seller-panel">
                    <h2><?php esc_html_e('Contacto del vendedor', 'terramarket'); ?></h2>
                    <ul class="tm-seller-list"><li><strong><?php esc_html_e('Nombre', 'terramarket'); ?>:</strong> <?php echo esc_html($contact_name ?: '-'); ?></li><?php if ($contact_phone) : ?><li><strong><?php esc_html_e('Teléfono', 'terramarket'); ?>:</strong> <?php echo esc_html($contact_phone); ?></li><?php endif; ?><?php if ($contact_email) : ?><li><strong><?php esc_html_e('Email', 'terramarket'); ?>:</strong> <?php echo esc_html($contact_email); ?></li><?php endif; ?></ul>
                    <?php if ($phone_link || $contact_email) : ?>
                        <div class="tm-seller-actions">
                            <?php if ($phone_link) : ?><a class="tm-link-button" href="<?php echo esc_url('tel:' . $phone_link); ?>"><?php esc_html_e('Llamar', 'terramarket'); ?></a><?php endif; ?>
                            <?php if ($contact_email) : ?><a class="tm-link-button tm-link-button--light" href="<?php echo esc_url('mailto:' . sanitize_email($contact_email)); ?>"><?php esc_html_e('Enviar email', 'terramarket'); ?></a><?php endif; ?>
                        </div>
                    <?php endif; ?>
                </section>
                <section id="tm-contact-panel" class="tm-panel tm-contact-panel"><h2><?php esc_html_e('Contactar vendedor', 'terramarket'); ?></h2><form method="post" action="<?php echo esc_url(TM_Helpers::get_submit_form_url()); ?>" class="tm-contact-form"><?php wp_nonce_field('tm_send_lead', 'tm_send_lead_nonce'); ?><input type="hidden" name="action" value="tm_send_lead"><input type="hidden" name="tm_listing_id" value="<?php echo esc_attr((string) $post_id); ?>"><input type="text" name="tm_website" value="" class="tm-honeypot" tabindex="-1" autocomplete="off"><div class="tm-field"><label for="tm_buyer_name"><?php esc_html_e('Nombre', 'terramarket'); ?></label><input type="text" id="tm_buyer_name" name="tm_buyer_name" required></div><div class="tm-field"><label for="tm_buyer_email"><?php esc_html_e('Email', 'terramarket'); ?></label><input type="email" id="tm_buyer_email" name="tm_buyer_email" required></div><div class="tm-field"><label for="tm_buyer_phone"><?php esc_html_e('Teléfono', 'terramarket'); ?></label><input type="text" id="tm_buyer_phone" name="tm_buyer_phone"></div><div class="tm-field"><label for="tm_message"><?php esc_html_e('Mensaje', 'terramarket'); ?></label><textarea id="tm_message" name="tm_message" rows="5" required><?php echo esc_textarea(sprintf(__('Hola, me interesa el aviso "%s".', 'terramarket'), get_the_title($post_id))); ?></textarea></div><button type="submit" class="tm-link-button tm-link-button--block"><?php esc_html_e('Enviar mensaje', 'terramarket'); ?></button></form></section>
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
            'tm_listing_status' => $is_edit ? ('expired' === TM_Helpers::get_effective_listing_status($listing_id) ? 'active' : TM_Helpers::get_listing_raw_status($listing_id)) : 'active',
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
        $existing_media_order = implode(',', array_map(static function ($image) { return (string) ((int) ($image['id'] ?? 0)); }, $gallery));
        $initial_featured_existing_id = ! empty($gallery[0]['id']) ? (int) $gallery[0]['id'] : 0;
        $max_images = (int) TM_Helpers::get_option('tm_settings_marketplace', 'max_images', 5);
        $current_status_label = TM_Helpers::get_listing_status_label((string) $meta['tm_listing_status']);
        $expiration_date = $is_edit ? TM_Helpers::get_listing_expiration_date($listing_id) : '';
        $selected_category_term = $selected_category ? get_term($selected_category, 'tm_category') : null;
        $selected_subcategory_term = $selected_subcategory ? get_term($selected_subcategory, 'tm_subcategory') : null;
        $selected_region_term = $selected_region ? get_term($selected_region, 'tm_region') : null;
        $selected_comuna_term = $selected_comuna ? get_term($selected_comuna, 'tm_comuna') : null;
        $selected_condition_term = $selected_condition ? get_term($selected_condition, 'tm_condition') : null;
        $summary_category = ($selected_category_term instanceof \WP_Term) ? $selected_category_term->name : __('Pendiente', 'terramarket');
        $summary_subcategory = ($selected_subcategory_term instanceof \WP_Term) ? $selected_subcategory_term->name : __('Pendiente', 'terramarket');
        $summary_region = ($selected_region_term instanceof \WP_Term) ? $selected_region_term->name : '';
        $summary_comuna = ($selected_comuna_term instanceof \WP_Term) ? $selected_comuna_term->name : '';
        $summary_location = trim($summary_comuna . ', ' . $summary_region, ', ');
        $summary_condition = ($selected_condition_term instanceof \WP_Term) ? $selected_condition_term->name : __('Pendiente', 'terramarket');
        $step_labels = array(
            1 => __('Básicos', 'terramarket'),
            2 => __('Clasificación', 'terramarket'),
            3 => __('Imágenes', 'terramarket'),
            4 => __('Contacto', 'terramarket'),
        );

        ob_start();
        ?>
        <section class="tm-page-hero tm-page-hero--submit">
            <div>
                <span class="tm-chip"><?php echo esc_html($is_edit ? __('Editar aviso', 'terramarket') : __('Nuevo aviso', 'terramarket')); ?></span>
                <h1><?php echo esc_html($is_edit ? __('Edita tu publicación', 'terramarket') : __('Publica tu aviso en Terramarket', 'terramarket')); ?></h1>
                <p><?php esc_html_e('Ahora el flujo se organiza paso a paso para completar mejor los datos del aviso y revisar el resumen antes de publicarlo.', 'terramarket'); ?></p>
            </div>
        </section>
        <?php if ($notice) : ?><div class="tm-notice tm-notice-success"><?php echo esc_html(self::notice_label($notice)); ?></div><?php endif; ?>
        <?php if ($error) : ?><div class="tm-notice tm-notice-error"><?php echo esc_html(self::notice_label($error)); ?></div><?php endif; ?>
        <div class="tm-submit-shell">
            <nav class="tm-submit-stepper" aria-label="<?php esc_attr_e('Pasos para crear aviso', 'terramarket'); ?>">
                <?php foreach ($step_labels as $step_index => $step_label) : ?>
                    <button type="button" class="tm-submit-stepper__item <?php echo 1 === $step_index ? 'is-active' : ''; ?>" data-tm-go-step="<?php echo esc_attr((string) $step_index); ?>" data-tm-step-label="<?php echo esc_attr($step_label); ?>" aria-current="<?php echo 1 === $step_index ? 'step' : 'false'; ?>">
                        <span class="tm-submit-stepper__index"><?php echo esc_html((string) $step_index); ?></span>
                        <span class="tm-submit-stepper__text"><?php echo esc_html($step_label); ?></span>
                    </button>
                <?php endforeach; ?>
            </nav>

            <form class="tm-submit-form tm-submit-form--wizard" method="post" action="<?php echo esc_url(TM_Helpers::get_submit_form_url()); ?>" enctype="multipart/form-data" data-tm-submit-wizard data-existing-images="<?php echo esc_attr((string) count($gallery)); ?>">
                <?php wp_nonce_field('tm_save_listing_frontend', 'tm_save_listing_frontend_nonce'); ?>
                <input type="hidden" name="action" value="tm_save_listing">
                <input type="hidden" name="tm_listing_id" value="<?php echo esc_attr((string) $listing_id); ?>">
                <input type="hidden" name="tm_redirect" value="<?php echo esc_url(TM_Helpers::current_url(array('tm_notice', 'tm_error'))); ?>">
                <input type="hidden" name="tm_current_step" value="1" data-tm-current-step>
                <input type="hidden" name="tm_existing_media_order" value="<?php echo esc_attr($existing_media_order); ?>" data-tm-existing-media-order>
                <input type="hidden" name="tm_featured_existing_id" value="<?php echo esc_attr((string) $initial_featured_existing_id); ?>" data-tm-featured-existing-id>
                <input type="hidden" name="tm_featured_upload_index" value="-1" data-tm-featured-upload-index>

                <div class="tm-form-grid tm-form-grid--wizard">
                    <div class="tm-form-main">
                        <section class="tm-panel tm-submit-step is-active" data-tm-step="1">
                            <div class="tm-submit-step__header">
                                <span class="tm-submit-step__eyebrow"><?php esc_html_e('Paso 1', 'terramarket'); ?></span>
                                <h2><?php esc_html_e('Información principal', 'terramarket'); ?></h2>
                                <p><?php esc_html_e('Define el título, precio y base comercial del aviso.', 'terramarket'); ?></p>
                            </div>
                            <div class="tm-field">
                                <label for="tm_title"><?php esc_html_e('Título', 'terramarket'); ?></label>
                                <input type="text" id="tm_title" name="tm_title" maxlength="140" value="<?php echo esc_attr($meta['title']); ?>" required data-tm-summary-target="title">
                                <p class="tm-helper-text"><?php esc_html_e('Usa un título claro y concreto para que el aviso se entienda rápido.', 'terramarket'); ?></p>
                            </div>
                            <div class="tm-field">
                                <label for="tm_description"><?php esc_html_e('Descripción', 'terramarket'); ?></label>
                                <textarea id="tm_description" name="tm_description" rows="8" required data-tm-summary-target="description"><?php echo esc_textarea($meta['description']); ?></textarea>
                            </div>
                            <div class="tm-field-row tm-field-row-2">
                                <div class="tm-field">
                                    <label for="tm_price_clp"><?php esc_html_e('Precio CLP', 'terramarket'); ?></label>
                                    <input type="number" id="tm_price_clp" name="tm_price_clp" min="1" step="1" value="<?php echo esc_attr((string) $meta['tm_price_clp']); ?>" required data-tm-summary-target="price">
                                </div>
                                <div class="tm-field">
                                    <label for="tm_condition_term_id"><?php esc_html_e('Condición', 'terramarket'); ?></label>
                                    <select id="tm_condition_term_id" name="tm_condition_term_id" required data-tm-summary-target="condition">
                                        <option value=""><?php esc_html_e('Seleccionar', 'terramarket'); ?></option>
                                        <?php foreach ($conditions as $term) : ?>
                                            <option value="<?php echo esc_attr((string) $term->term_id); ?>" <?php selected($selected_condition, $term->term_id); ?>><?php echo esc_html($term->name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="tm-submit-step__footer">
                                <span class="tm-helper-text"><?php esc_html_e('Completa estos datos primero para construir el aviso con una base clara.', 'terramarket'); ?></span>
                                <button type="button" class="tm-link-button" data-tm-next-step><?php esc_html_e('Continuar', 'terramarket'); ?></button>
                            </div>
                        </section>

                        <section class="tm-panel tm-submit-step" data-tm-step="2" hidden>
                            <div class="tm-submit-step__header">
                                <span class="tm-submit-step__eyebrow"><?php esc_html_e('Paso 2', 'terramarket'); ?></span>
                                <h2><?php esc_html_e('Clasificación y ubicación', 'terramarket'); ?></h2>
                                <p><?php esc_html_e('Organiza bien el aviso para filtros, navegación y alertas del marketplace.', 'terramarket'); ?></p>
                            </div>
                            <div class="tm-field-row tm-field-row-2">
                                <div class="tm-field">
                                    <label for="tm_category_term_id"><?php esc_html_e('Categoría', 'terramarket'); ?></label>
                                    <select id="tm_category_term_id" name="tm_category_term_id" required data-tm-summary-target="category">
                                        <option value=""><?php esc_html_e('Seleccionar', 'terramarket'); ?></option>
                                        <?php foreach ($categories as $term) : ?>
                                            <option value="<?php echo esc_attr((string) $term->term_id); ?>" <?php selected($selected_category, $term->term_id); ?>><?php echo esc_html($term->name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="tm-field">
                                    <label for="tm_subcategory_term_id"><?php esc_html_e('Subcategoría', 'terramarket'); ?></label>
                                    <select id="tm_subcategory_term_id" name="tm_subcategory_term_id" required data-tm-summary-target="subcategory">
                                        <option value=""><?php esc_html_e('Seleccionar', 'terramarket'); ?></option>
                                        <?php foreach ($subcategories as $term) : $parent_id = (int) get_term_meta($term->term_id, 'tm_parent_category_id', true); ?>
                                            <option value="<?php echo esc_attr((string) $term->term_id); ?>" data-parent-category="<?php echo esc_attr((string) $parent_id); ?>" <?php selected($selected_subcategory, $term->term_id); ?>><?php echo esc_html($term->name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="tm-submit-inline-note">
                                <strong><?php esc_html_e('Detalles específicos', 'terramarket'); ?></strong>
                                <span><?php esc_html_e('Cuando este flujo tenga campos dinámicos por categoría, aparecerán aquí automáticamente.', 'terramarket'); ?></span>
                            </div>
                            <div class="tm-field-row tm-field-row-2">
                                <div class="tm-field">
                                    <label for="tm_region_term_id"><?php esc_html_e('Región', 'terramarket'); ?></label>
                                    <select id="tm_region_term_id" name="tm_region_term_id" required data-tm-summary-target="region">
                                        <option value=""><?php esc_html_e('Seleccionar', 'terramarket'); ?></option>
                                        <?php foreach ($regions as $term) : ?>
                                            <option value="<?php echo esc_attr((string) $term->term_id); ?>" <?php selected($selected_region, $term->term_id); ?>><?php echo esc_html($term->name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="tm-field">
                                    <label for="tm_comuna_term_id"><?php esc_html_e('Comuna', 'terramarket'); ?></label>
                                    <select id="tm_comuna_term_id" name="tm_comuna_term_id" required data-tm-summary-target="comuna">
                                        <option value=""><?php esc_html_e('Seleccionar', 'terramarket'); ?></option>
                                        <?php foreach ($comunas as $term) : $region_id = (int) get_term_meta($term->term_id, 'tm_region_id', true); ?>
                                            <option value="<?php echo esc_attr((string) $term->term_id); ?>" data-region-id="<?php echo esc_attr((string) $region_id); ?>" <?php selected($selected_comuna, $term->term_id); ?>><?php echo esc_html($term->name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="tm-submit-step__footer tm-submit-step__footer--split">
                                <button type="button" class="tm-link-button tm-link-button--light" data-tm-prev-step><?php esc_html_e('Volver', 'terramarket'); ?></button>
                                <button type="button" class="tm-link-button" data-tm-next-step><?php esc_html_e('Continuar', 'terramarket'); ?></button>
                            </div>
                        </section>

                        <section class="tm-panel tm-submit-step" data-tm-step="3" hidden>
                            <div class="tm-submit-step__header">
                                <span class="tm-submit-step__eyebrow"><?php esc_html_e('Paso 3', 'terramarket'); ?></span>
                                <h2><?php esc_html_e('Imágenes del aviso', 'terramarket'); ?></h2>
                                <p><?php echo esc_html(sprintf(__('Sube hasta %d imágenes. Ahora puedes definir principal, reordenar y revisar antes de publicar.', 'terramarket'), $max_images)); ?></p>
                            </div>
                            <div class="tm-submit-upload-box">
                                <div class="tm-field">
                                    <div class="tm-submit-inline-note">
                                        <strong><?php esc_html_e('Carga visual de imágenes', 'terramarket'); ?></strong>
                                        <span><?php esc_html_e('Usa arrastrar y soltar o el selector. Luego marca cuál debe quedar como principal.', 'terramarket'); ?></span>
                                    </div>
                                    <div class="tm-upload-module" data-tm-upload-module>
                                        <label class="tm-upload-zone" for="tm_images" data-tm-upload-zone tabindex="0">
                                            <span class="tm-upload-zone__icon" aria-hidden="true">+</span>
                                            <strong><?php esc_html_e('Arrastra imágenes aquí o selecciónalas', 'terramarket'); ?></strong>
                                            <span><?php esc_html_e('Formatos permitidos: JPG, PNG y WEBP. Puedes reordenarlas visualmente antes de guardar.', 'terramarket'); ?></span>
                                            <span class="tm-upload-zone__actions">
                                                <span class="tm-link-button tm-link-button--light tm-link-button--small"><?php esc_html_e('Seleccionar imágenes', 'terramarket'); ?></span>
                                                <small><?php echo esc_html(sprintf(__('Máximo %d imágenes', 'terramarket'), $max_images)); ?></small>
                                            </span>
                                        </label>
                                        <input type="file" id="tm_images" name="tm_images[]" multiple accept="image/*" class="tm-upload-input" data-tm-upload-input>
                                        <div class="tm-upload-toolbar">
                                            <div class="tm-upload-toolbar__status">
                                                <strong data-tm-upload-count><?php echo esc_html(sprintf(_n('%d imagen', '%d imágenes', count($gallery), 'terramarket'), count($gallery))); ?></strong>
                                                <span><?php esc_html_e('Activas para el aviso', 'terramarket'); ?></span>
                                            </div>
                                            <div class="tm-upload-toolbar__status">
                                                <strong data-tm-upload-primary-label><?php echo esc_html($initial_featured_existing_id ? __('Principal actual', 'terramarket') : __('Sin principal definida', 'terramarket')); ?></strong>
                                                <span><?php esc_html_e('Imagen destacada', 'terramarket'); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div id="tm-upload-preview" class="tm-upload-preview" data-tm-upload-preview></div>
                                </div>
                            </div>
                            <?php if (! empty($gallery)) : ?>
                                <div class="tm-existing-gallery-wrap">
                                    <div class="tm-submit-inline-note tm-submit-inline-note--soft">
                                        <strong><?php esc_html_e('Imágenes actuales', 'terramarket'); ?></strong>
                                        <span><?php esc_html_e('Puedes marcar principal, mover izquierda/derecha y decidir cuáles eliminar antes de guardar.', 'terramarket'); ?></span>
                                    </div>
                                    <div class="tm-existing-gallery" data-tm-existing-gallery>
                                        <?php foreach ($gallery as $index => $image) : ?>
                                            <article class="tm-existing-media <?php echo 0 === $index ? 'is-featured' : ''; ?>" data-tm-existing-card data-attachment-id="<?php echo esc_attr((string) $image['id']); ?>">
                                                <div class="tm-existing-media__frame">
                                                    <img src="<?php echo esc_url($image['thumb']); ?>" alt="<?php echo esc_attr($image['alt']); ?>" loading="lazy" decoding="async">
                                                    <span class="tm-media-badge tm-media-badge--featured" data-tm-featured-badge><?php esc_html_e('Principal', 'terramarket'); ?></span>
                                                    <span class="tm-media-badge tm-media-badge--delete" data-tm-delete-badge><?php esc_html_e('Se eliminará', 'terramarket'); ?></span>
                                                </div>
                                                <div class="tm-existing-media__meta">
                                                    <strong><?php echo esc_html(sprintf(__('Imagen %d', 'terramarket'), $index + 1)); ?></strong>
                                                    <span><?php esc_html_e('Actual', 'terramarket'); ?></span>
                                                </div>
                                                <div class="tm-existing-media__actions">
                                                    <button type="button" class="tm-media-pill <?php echo 0 === $index ? 'is-active' : ''; ?>" data-tm-set-existing-featured><?php esc_html_e('Marcar principal', 'terramarket'); ?></button>
                                                    <button type="button" class="tm-media-icon" data-tm-move-existing="-1" aria-label="<?php esc_attr_e('Mover a la izquierda', 'terramarket'); ?>">←</button>
                                                    <button type="button" class="tm-media-icon" data-tm-move-existing="1" aria-label="<?php esc_attr_e('Mover a la derecha', 'terramarket'); ?>">→</button>
                                                </div>
                                                <label class="tm-media-check">
                                                    <input type="checkbox" name="tm_delete_media[]" value="<?php echo esc_attr((string) $image['id']); ?>" data-tm-delete-existing>
                                                    <span><?php esc_html_e('Eliminar al guardar', 'terramarket'); ?></span>
                                                </label>
                                            </article>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div class="tm-submit-step__footer tm-submit-step__footer--split">
                                <button type="button" class="tm-link-button tm-link-button--light" data-tm-prev-step><?php esc_html_e('Volver', 'terramarket'); ?></button>
                                <button type="button" class="tm-link-button" data-tm-next-step><?php esc_html_e('Continuar', 'terramarket'); ?></button>
                            </div>
                        </section>

                        <section class="tm-panel tm-submit-step" data-tm-step="4" hidden>
                            <div class="tm-submit-step__header">
                                <span class="tm-submit-step__eyebrow"><?php esc_html_e('Paso 4', 'terramarket'); ?></span>
                                <h2><?php esc_html_e('Contacto y publicación', 'terramarket'); ?></h2>
                                <p><?php esc_html_e('Revisa quién recibe los contactos y qué estado inicial tendrá el aviso.', 'terramarket'); ?></p>
                            </div>
                            <div class="tm-field-row tm-field-row-2">
                                <div class="tm-field">
                                    <label for="tm_contact_name"><?php esc_html_e('Nombre contacto', 'terramarket'); ?></label>
                                    <input type="text" id="tm_contact_name" name="tm_contact_name" value="<?php echo esc_attr($meta['tm_contact_name']); ?>" required data-tm-summary-target="contact_name">
                                </div>
                                <div class="tm-field">
                                    <label for="tm_contact_email"><?php esc_html_e('Email contacto', 'terramarket'); ?></label>
                                    <input type="email" id="tm_contact_email" name="tm_contact_email" value="<?php echo esc_attr($meta['tm_contact_email']); ?>" required data-tm-summary-target="contact_email">
                                </div>
                            </div>
                            <div class="tm-field-row tm-field-row-2">
                                <div class="tm-field">
                                    <label for="tm_contact_phone"><?php esc_html_e('Teléfono', 'terramarket'); ?></label>
                                    <input type="text" id="tm_contact_phone" name="tm_contact_phone" value="<?php echo esc_attr($meta['tm_contact_phone']); ?>" data-tm-summary-target="contact_phone">
                                </div>
                                <div class="tm-field">
                                    <label for="tm_listing_status"><?php esc_html_e('Estado', 'terramarket'); ?></label>
                                    <select id="tm_listing_status" name="tm_listing_status" data-tm-summary-target="status">
                                        <?php foreach (TM_Helpers::get_user_selectable_listing_statuses() as $key => $label) : ?>
                                            <option value="<?php echo esc_attr($key); ?>" <?php selected($meta['tm_listing_status'], $key); ?>><?php echo esc_html($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="tm-submit-step__footer tm-submit-step__footer--split">
                                <button type="button" class="tm-link-button tm-link-button--light" data-tm-prev-step><?php esc_html_e('Volver', 'terramarket'); ?></button>
                                <button type="submit" class="tm-link-button"><?php echo esc_html($is_edit ? __('Actualizar aviso', 'terramarket') : __('Publicar aviso', 'terramarket')); ?></button>
                            </div>
                        </section>
                    </div>

                    <aside class="tm-form-sidebar">
                        <section class="tm-panel tm-submit-sidebar">
                            <div class="tm-submit-sidebar__header">
                                <span class="tm-submit-step__eyebrow"><?php esc_html_e('Resumen', 'terramarket'); ?></span>
                                <h2><?php esc_html_e('Vista rápida del aviso', 'terramarket'); ?></h2>
                                <p><?php esc_html_e('Se actualiza en vivo a medida que completas cada paso.', 'terramarket'); ?></p>
                            </div>
                            <div class="tm-submit-progress">
                                <div class="tm-submit-progress__top">
                                    <strong data-tm-current-step-label><?php echo esc_html($step_labels[1]); ?></strong>
                                    <span data-tm-current-step-count><?php esc_html_e('Paso 1 de 4', 'terramarket'); ?></span>
                                </div>
                                <div class="tm-submit-progress__bar"><span data-tm-progress-bar style="width:25%"></span></div>
                            </div>
                            <dl class="tm-submit-summary-list">
                                <div>
                                    <dt><?php esc_html_e('Título', 'terramarket'); ?></dt>
                                    <dd data-tm-summary="title"><?php echo esc_html($meta['title'] ?: __('Sin título todavía', 'terramarket')); ?></dd>
                                </div>
                                <div>
                                    <dt><?php esc_html_e('Precio', 'terramarket'); ?></dt>
                                    <dd data-tm-summary="price"><?php echo esc_html($meta['tm_price_clp'] ? TM_Helpers::format_price_clp((int) $meta['tm_price_clp']) : __('Pendiente', 'terramarket')); ?></dd>
                                </div>
                                <div>
                                    <dt><?php esc_html_e('Categoría', 'terramarket'); ?></dt>
                                    <dd data-tm-summary="category"><?php echo esc_html($summary_category); ?></dd>
                                </div>
                                <div>
                                    <dt><?php esc_html_e('Subcategoría', 'terramarket'); ?></dt>
                                    <dd data-tm-summary="subcategory"><?php echo esc_html($summary_subcategory); ?></dd>
                                </div>
                                <div>
                                    <dt><?php esc_html_e('Ubicación', 'terramarket'); ?></dt>
                                    <dd data-tm-summary="location"><?php echo esc_html($summary_location ?: __('Pendiente', 'terramarket')); ?></dd>
                                </div>
                                <div>
                                    <dt><?php esc_html_e('Condición', 'terramarket'); ?></dt>
                                    <dd data-tm-summary="condition"><?php echo esc_html($summary_condition); ?></dd>
                                </div>
                                <div>
                                    <dt><?php esc_html_e('Estado', 'terramarket'); ?></dt>
                                    <dd data-tm-summary="status"><?php echo esc_html($current_status_label); ?></dd>
                                </div>
                            </dl>
                            <div class="tm-submit-sidebar__meta">
                                <div class="tm-submit-meta-card">
                                    <strong data-tm-summary="images_count"><?php echo esc_html(sprintf(_n('%d imagen', '%d imágenes', count($gallery), 'terramarket'), count($gallery))); ?></strong>
                                    <span><?php esc_html_e('Cargadas actualmente', 'terramarket'); ?></span>
                                </div>
                                <div class="tm-submit-meta-card">
                                    <strong><?php echo esc_html($expiration_date ? mysql2date('d/m/Y', $expiration_date) : __('Se asigna al publicar', 'terramarket')); ?></strong>
                                    <span><?php esc_html_e('Vigencia estimada', 'terramarket'); ?></span>
                                </div>
                            </div>
                            <div class="tm-submit-sidebar__actions">
                                <button type="button" class="tm-link-button tm-link-button--light" data-tm-prev-step-sidebar disabled><?php esc_html_e('Paso anterior', 'terramarket'); ?></button>
                                <button type="button" class="tm-link-button" data-tm-next-step-sidebar><?php esc_html_e('Siguiente paso', 'terramarket'); ?></button>
                                <button type="button" class="tm-link-button tm-link-button--light tm-link-button--block" data-tm-open-preview><?php esc_html_e('Vista previa', 'terramarket'); ?></button>
                                <button type="submit" class="tm-link-button tm-link-button--block tm-link-button--strong"><?php echo esc_html($is_edit ? __('Actualizar aviso', 'terramarket') : __('Publicar aviso', 'terramarket')); ?></button>
                            </div>
                        </section>
                    </aside>
                </div>
            </form>
        </div>
        <div class="tm-preview-modal" data-tm-preview-modal hidden>
            <div class="tm-preview-modal__backdrop" data-tm-close-preview></div>
            <div class="tm-preview-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="tm-preview-modal-title">
                <div class="tm-preview-modal__header">
                    <div>
                        <span class="tm-submit-step__eyebrow"><?php esc_html_e('Vista previa', 'terramarket'); ?></span>
                        <h2 id="tm-preview-modal-title"><?php esc_html_e('Así se vería tu aviso', 'terramarket'); ?></h2>
                    </div>
                    <button type="button" class="tm-preview-modal__close" data-tm-close-preview aria-label="<?php esc_attr_e('Cerrar vista previa', 'terramarket'); ?>">×</button>
                </div>
                <div class="tm-preview-modal__body">
                    <div class="tm-preview-modal__media">
                        <img src="" alt="" data-tm-preview-modal-image hidden>
                        <div class="tm-preview-modal__media-empty" data-tm-preview-modal-empty><?php esc_html_e('Todavía no has seleccionado una imagen principal.', 'terramarket'); ?></div>
                    </div>
                    <div class="tm-preview-modal__content">
                        <span class="tm-status tm-status--active" data-tm-preview-modal-status><?php echo esc_html($current_status_label); ?></span>
                        <h3 data-tm-preview-modal-title-text><?php echo esc_html($meta['title'] ?: __('Sin título todavía', 'terramarket')); ?></h3>
                        <p class="tm-preview-modal__price" data-tm-preview-modal-price><?php echo esc_html($meta['tm_price_clp'] ? TM_Helpers::format_price_clp((int) $meta['tm_price_clp']) : __('Pendiente', 'terramarket')); ?></p>
                        <div class="tm-preview-modal__meta">
                            <span data-tm-preview-modal-category><?php echo esc_html($summary_category); ?></span>
                            <span data-tm-preview-modal-location><?php echo esc_html($summary_location ?: __('Pendiente', 'terramarket')); ?></span>
                            <span data-tm-preview-modal-condition><?php echo esc_html($summary_condition); ?></span>
                        </div>
                        <p class="tm-preview-modal__description" data-tm-preview-modal-description><?php echo esc_html($meta['description'] ?: __('Agrega una descripción para revisar aquí cómo se leerá el aviso.', 'terramarket')); ?></p>
                        <div class="tm-preview-modal__contact">
                            <strong><?php esc_html_e('Contacto', 'terramarket'); ?></strong>
                            <span data-tm-preview-modal-contact><?php echo esc_html($meta['tm_contact_name'] ?: __('Pendiente', 'terramarket')); ?></span>
                        </div>
                    </div>
                </div>
                <div class="tm-preview-modal__footer">
                    <button type="button" class="tm-link-button tm-link-button--light" data-tm-close-preview><?php esc_html_e('Seguir editando', 'terramarket'); ?></button>
                </div>
            </div>
        </div>
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
            'total'   => 0,
            'active'  => 0,
            'paused'  => 0,
            'sold'    => 0,
            'expired' => 0,
            'views'   => 0,
        );
        $listing_status_filters = array(
            'all'     => __('Todos', 'terramarket'),
            'active'  => __('Activos', 'terramarket'),
            'paused'  => __('Pausados', 'terramarket'),
            'expired' => __('Expirados', 'terramarket'),
            'sold'    => __('Vendidos', 'terramarket'),
        );
        $current_listing_filter = sanitize_key(wp_unslash($_GET['listing_status'] ?? 'all'));
        if (! isset($listing_status_filters[$current_listing_filter])) {
            $current_listing_filter = 'all';
        }

        $user_listings = get_posts(array(
            'post_type'      => 'tm_listing',
            'author'         => $user_id,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ));
        $listing_status_map = array();
        foreach ($user_listings as $listing) {
            $listing_counts['total']++;
            $status = TM_Helpers::get_effective_listing_status($listing->ID);
            $listing_status_map[$listing->ID] = $status;
            if (isset($listing_counts[$status])) {
                $listing_counts[$status]++;
            }
            $listing_counts['views'] += (int) get_post_meta($listing->ID, 'tm_views_count', true);
        }

        $filtered_user_listings = array_values(array_filter(
            $user_listings,
            static function ($listing) use ($current_listing_filter, $listing_status_map) {
                if ('all' === $current_listing_filter) {
                    return true;
                }

                return $current_listing_filter === ($listing_status_map[$listing->ID] ?? 'active');
            }
        ));

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
            <section class="tm-panel tm-account-listings-toolbar">
                <div class="tm-account-listings-toolbar__copy">
                    <span class="tm-account-listings-toolbar__eyebrow"><?php esc_html_e('Gestión rápida', 'terramarket'); ?></span>
                    <h2><?php esc_html_e('Mis avisos', 'terramarket'); ?></h2>
                    <p><?php echo esc_html(sprintf(__('Mostrando %1$s de %2$s avisos.', 'terramarket'), number_format_i18n(count($filtered_user_listings)), number_format_i18n($listing_counts['total']))); ?></p>
                </div>
                <div class="tm-account-status-filters" role="tablist" aria-label="<?php esc_attr_e('Filtrar avisos por estado', 'terramarket'); ?>">
                    <?php foreach ($listing_status_filters as $filter_key => $filter_label) :
                        $filter_args = array('tab' => 'listings');
                        if ('all' !== $filter_key) {
                            $filter_args['listing_status'] = $filter_key;
                        }
                        $filter_count = 'all' === $filter_key ? $listing_counts['total'] : (int) ($listing_counts[$filter_key] ?? 0);
                    ?>
                        <a class="tm-account-status-pill <?php echo $filter_key === $current_listing_filter ? 'is-active' : ''; ?>" href="<?php echo esc_url(TM_Helpers::get_account_page_url($filter_args)); ?>" role="tab" aria-selected="<?php echo $filter_key === $current_listing_filter ? 'true' : 'false'; ?>">
                            <span><?php echo esc_html($filter_label); ?></span>
                            <strong><?php echo esc_html(number_format_i18n($filter_count)); ?></strong>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
            <div class="tm-account-listings-grid">
                <?php if (! empty($filtered_user_listings)) : foreach ($filtered_user_listings as $listing) :
                    $listing_id = (int) $listing->ID;
                    $status = $listing_status_map[$listing_id] ?? TM_Helpers::get_effective_listing_status($listing_id);
                    $price = (int) get_post_meta($listing_id, 'tm_price_clp', true);
                    $views = (int) get_post_meta($listing_id, 'tm_views_count', true);
                    $sold_amount = (int) get_post_meta($listing_id, 'tm_declared_sale_amount', true);
                    $image = TM_Helpers::get_listing_primary_image_url($listing_id, 'medium_large');
                    $category = TM_Helpers::get_term_name_for_post($listing_id, 'tm_category');
                    $subcategory = TM_Helpers::get_term_name_for_post($listing_id, 'tm_subcategory');
                    $condition = TM_Helpers::get_term_name_for_post($listing_id, 'tm_condition');
                    $comuna = TM_Helpers::get_term_name_for_post($listing_id, 'tm_comuna');
                    $region = TM_Helpers::get_term_name_for_post($listing_id, 'tm_region');
                    $location_parts = array_filter(array($comuna, $region));
                    $location_label = ! empty($location_parts) ? implode(', ', $location_parts) : __('Ubicación no indicada', 'terramarket');
                    $expiration_date = TM_Helpers::get_listing_expiration_date($listing_id);
                    $expiration_label = '';
                    if ('' !== $expiration_date) {
                        $expiration_timestamp = strtotime($expiration_date . ' 00:00:00');
                        if ($expiration_timestamp) {
                            $expiration_label = wp_date(get_option('date_format') ?: 'd/m/Y', $expiration_timestamp);
                        }
                    }
                    $published_label = get_the_date(get_option('date_format') ?: 'd/m/Y', $listing);
                    $status_note = '';
                    if ('expired' === $status && $expiration_label) {
                        $status_note = sprintf(__('Vencido el %s', 'terramarket'), $expiration_label);
                    } elseif ('active' === $status && $expiration_label) {
                        $status_note = sprintf(__('Vence el %s', 'terramarket'), $expiration_label);
                    } elseif ('paused' === $status && $expiration_label) {
                        $status_note = sprintf(__('Vigencia guardada hasta %s', 'terramarket'), $expiration_label);
                    } elseif ('sold' === $status) {
                        $sold_at = (string) get_post_meta($listing_id, 'tm_sold_at', true);
                        if ('' !== $sold_at) {
                            $sold_timestamp = strtotime($sold_at . ' 00:00:00');
                            $status_note = $sold_timestamp ? sprintf(__('Vendido el %s', 'terramarket'), wp_date(get_option('date_format') ?: 'd/m/Y', $sold_timestamp)) : __('Aviso marcado como vendido', 'terramarket');
                        } else {
                            $status_note = __('Aviso marcado como vendido', 'terramarket');
                        }
                    }
                ?>
                    <article class="tm-account-listing tm-account-listing--<?php echo esc_attr($status); ?>">
                        <div class="tm-account-listing__media-wrap">
                            <?php if ($image) : ?>
                                <div class="tm-account-listing__media"><img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr($listing->post_title); ?>"></div>
                            <?php else : ?>
                                <div class="tm-account-listing__media tm-account-listing__media--empty"><span><?php esc_html_e('Sin imagen', 'terramarket'); ?></span></div>
                            <?php endif; ?>
                            <span class="tm-account-status-badge tm-account-status-badge--<?php echo esc_attr($status); ?>"><?php echo esc_html(TM_Helpers::get_listing_status_label($status)); ?></span>
                        </div>
                        <div class="tm-account-listing__body">
                            <div class="tm-account-listing__topline">
                                <?php if ($category) : ?><span class="tm-account-meta-pill"><?php echo esc_html($category); ?></span><?php endif; ?>
                                <?php if ($subcategory) : ?><span class="tm-account-meta-pill"><?php echo esc_html($subcategory); ?></span><?php endif; ?>
                                <?php if ($condition) : ?><span class="tm-account-meta-pill tm-account-meta-pill--muted"><?php echo esc_html($condition); ?></span><?php endif; ?>
                            </div>
                            <div class="tm-account-listing__headline">
                                <div>
                                    <h3><?php echo esc_html($listing->post_title); ?></h3>
                                    <p class="tm-account-listing__location"><?php echo esc_html($location_label); ?></p>
                                </div>
                                <p class="tm-account-listing__price"><?php echo esc_html(TM_Helpers::format_price_clp($price)); ?></p>
                            </div>
                            <div class="tm-account-listing__stats">
                                <span><?php echo esc_html(sprintf(__('%s visitas', 'terramarket'), number_format_i18n($views))); ?></span>
                                <span><?php echo esc_html(sprintf(__('Publicado %s', 'terramarket'), $published_label)); ?></span>
                                <?php if ($status_note) : ?><span><?php echo esc_html($status_note); ?></span><?php endif; ?>
                                <?php if ('sold' === $status && $sold_amount > 0) : ?><span><?php echo esc_html(sprintf(__('Venta declarada %s', 'terramarket'), TM_Helpers::format_price_clp($sold_amount))); ?></span><?php endif; ?>
                            </div>
                            <div class="tm-account-listing__actions">
                                <div class="tm-account-listing__actions-main">
                                    <?php if ('expired' === $status) : ?>
                                        <?php echo self::manage_action_link($listing_id, 'renew', __('Renovar', 'terramarket'), 'tm-link-button'); ?>
                                        <a class="tm-link-button tm-link-button--light" href="<?php echo esc_url(TM_Helpers::get_submit_page_url(array('tm_edit_listing' => $listing_id))); ?>"><?php esc_html_e('Editar', 'terramarket'); ?></a>
                                        <a class="tm-link-button tm-link-button--light" href="<?php echo esc_url(get_permalink($listing_id)); ?>"><?php esc_html_e('Ver', 'terramarket'); ?></a>
                                    <?php elseif ('paused' === $status) : ?>
                                        <?php echo self::manage_action_link($listing_id, 'activate', __('Reactivar', 'terramarket'), 'tm-link-button'); ?>
                                        <a class="tm-link-button tm-link-button--light" href="<?php echo esc_url(TM_Helpers::get_submit_page_url(array('tm_edit_listing' => $listing_id))); ?>"><?php esc_html_e('Editar', 'terramarket'); ?></a>
                                        <a class="tm-link-button tm-link-button--light" href="<?php echo esc_url(get_permalink($listing_id)); ?>"><?php esc_html_e('Ver', 'terramarket'); ?></a>
                                    <?php elseif ('sold' === $status) : ?>
                                        <?php echo self::manage_action_link($listing_id, 'duplicate', __('Duplicar', 'terramarket'), 'tm-link-button'); ?>
                                        <a class="tm-link-button tm-link-button--light" href="<?php echo esc_url(get_permalink($listing_id)); ?>"><?php esc_html_e('Ver', 'terramarket'); ?></a>
                                        <a class="tm-link-button tm-link-button--light" href="<?php echo esc_url(TM_Helpers::get_submit_page_url(array('tm_edit_listing' => $listing_id))); ?>"><?php esc_html_e('Editar', 'terramarket'); ?></a>
                                    <?php else : ?>
                                        <a class="tm-link-button" href="<?php echo esc_url(TM_Helpers::get_submit_page_url(array('tm_edit_listing' => $listing_id))); ?>"><?php esc_html_e('Editar', 'terramarket'); ?></a>
                                        <a class="tm-link-button tm-link-button--light" href="<?php echo esc_url(get_permalink($listing_id)); ?>"><?php esc_html_e('Ver', 'terramarket'); ?></a>
                                        <?php echo self::manage_action_link($listing_id, 'pause', __('Pausar', 'terramarket'), 'tm-link-button tm-link-button--light'); ?>
                                    <?php endif; ?>
                                </div>
                                <?php if ('sold' !== $status) : ?>
                                    <div class="tm-account-listing__actions-secondary">
                                        <?php echo self::manage_action_link($listing_id, 'duplicate', __('Duplicar', 'terramarket'), 'tm-link-button tm-link-button--ghost'); ?>
                                        <?php if ('active' === $status) : ?>
                                            <?php echo self::manage_action_link($listing_id, 'renew', __('Subir de nuevo', 'terramarket'), 'tm-link-button tm-link-button--ghost'); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="tm-account-listing__danger">
                                        <div>
                                            <strong><?php esc_html_e('Cerrar aviso', 'terramarket'); ?></strong>
                                            <p class="tm-helper-text"><?php esc_html_e('Separa esta acción del flujo principal para evitar cierres accidentales.', 'terramarket'); ?></p>
                                        </div>
                                        <form method="post" action="<?php echo esc_url(TM_Helpers::get_submit_form_url()); ?>" class="tm-account-listing__sold-form">
                                            <?php wp_nonce_field('tm_manage_listing_' . $listing_id . '_sold', 'tm_manage_listing_nonce'); ?>
                                            <input type="hidden" name="action" value="tm_manage_listing">
                                            <input type="hidden" name="tm_listing_id" value="<?php echo esc_attr((string) $listing_id); ?>">
                                            <input type="hidden" name="tm_operation" value="sold">
                                            <input type="number" name="tm_sale_amount" min="0" step="1" placeholder="<?php esc_attr_e('Monto final vendido (opcional)', 'terramarket'); ?>">
                                            <button type="submit" class="tm-link-button tm-link-button--danger-light"><?php esc_html_e('Marcar vendido', 'terramarket'); ?></button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; else : ?>
                    <div class="tm-empty-state tm-empty-state--listings">
                        <div>
                            <h3><?php esc_html_e('No hay avisos en este estado.', 'terramarket'); ?></h3>
                            <p><?php esc_html_e('Ajusta el filtro o crea un nuevo aviso para empezar a poblar tu vitrina.', 'terramarket'); ?></p>
                        </div>
                        <a class="tm-link-button" href="<?php echo esc_url(TM_Helpers::get_submit_page_url()); ?>"><?php esc_html_e('Crear aviso', 'terramarket'); ?></a>
                    </div>
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

    protected static function manage_action_link(int $listing_id, string $operation, string $label, string $classes = 'tm-link-button tm-link-button--light'): string
    {
        $url = wp_nonce_url(add_query_arg(array('action' => 'tm_manage_listing', 'tm_listing_id' => $listing_id, 'tm_operation' => $operation), TM_Helpers::get_submit_form_url()), 'tm_manage_listing_' . $listing_id . '_' . $operation);
        return '<a class="' . esc_attr($classes) . '" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
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
        $required_cap = self::required_capability_for_listing_operation($operation);
        if (! $required_cap || ! current_user_can($required_cap)) {
            TM_Helpers::debug_log('manage_listing_denied', array('listing_id' => $listing_id, 'operation' => $operation, 'required_cap' => $required_cap, 'user_id' => get_current_user_id()));
            wp_safe_redirect(TM_Helpers::get_account_page_url(array('tm_error' => 'no_permission')));
            exit;
        }

        $notice = 'listing_updated';
        switch ($operation) {
            case 'pause':
                update_post_meta($listing_id, 'tm_listing_status', 'paused');
                $notice = 'listing_paused';
                break;
            case 'activate':
                update_post_meta($listing_id, 'tm_listing_status', 'active');
                if (TM_Helpers::is_listing_expired($listing_id) || '' === TM_Helpers::get_listing_expiration_date($listing_id)) {
                    TM_Helpers::reset_listing_expiration_date($listing_id);
                }
                $notice = 'listing_activated';
                break;
            case 'renew':
                wp_update_post(array('ID' => $listing_id, 'post_date' => current_time('mysql'), 'post_date_gmt' => current_time('mysql', 1)));
                update_post_meta($listing_id, 'tm_listing_status', 'active');
                TM_Helpers::reset_listing_expiration_date($listing_id);
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
                    TM_Helpers::reset_listing_expiration_date($new_listing_id);
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
        if (! current_user_can('tm_manage_own_alerts')) {
            wp_safe_redirect(TM_Helpers::get_account_page_url(array('tab' => 'alerts', 'tm_error' => 'no_permission')));
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
        $category_term_id = (int) $data['category_term_id'];
        $subcategory_term_id = (int) $data['subcategory_term_id'];
        $region_term_id = (int) $data['region_term_id'];
        $comuna_term_id = (int) $data['comuna_term_id'];
        if ($subcategory_term_id > 0 && $category_term_id > 0 && ! TM_Helpers::is_subcategory_of_category($subcategory_term_id, $category_term_id)) {
            TM_Helpers::debug_log('alert_invalid_terms', array('category_term_id' => $category_term_id, 'subcategory_term_id' => $subcategory_term_id, 'user_id' => get_current_user_id()));
            wp_safe_redirect(TM_Helpers::get_account_page_url(array('tab' => 'alerts', 'tm_error' => 'invalid_terms')));
            exit;
        }
        if ($comuna_term_id > 0 && $region_term_id > 0 && ! TM_Helpers::is_comuna_of_region($comuna_term_id, $region_term_id)) {
            TM_Helpers::debug_log('alert_invalid_terms', array('region_term_id' => $region_term_id, 'comuna_term_id' => $comuna_term_id, 'user_id' => get_current_user_id()));
            wp_safe_redirect(TM_Helpers::get_account_page_url(array('tab' => 'alerts', 'tm_error' => 'invalid_terms')));
            exit;
        }
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
        if (! current_user_can('tm_manage_own_alerts')) {
            wp_safe_redirect(TM_Helpers::get_account_page_url(array('tab' => 'alerts', 'tm_error' => 'no_permission')));
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
        $required_cap = $is_edit ? 'tm_edit_own_listings' : 'tm_create_listings';
        if (! current_user_can($required_cap)) {
            wp_safe_redirect(TM_Helpers::build_redirect_url($redirect_url, array('tm_error' => 'no_permission')));
            exit;
        }
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
        $status = array_key_exists($status, TM_Helpers::get_user_selectable_listing_statuses()) ? $status : 'active';
        $delete_media = isset($_POST['tm_delete_media']) ? array_map('absint', (array) wp_unslash($_POST['tm_delete_media'])) : array();
        $existing_media_order = isset($_POST['tm_existing_media_order']) ? sanitize_text_field(wp_unslash($_POST['tm_existing_media_order'])) : '';
        $featured_existing_id = absint($_POST['tm_featured_existing_id'] ?? 0);
        $featured_upload_index = isset($_POST['tm_featured_upload_index']) ? (int) $_POST['tm_featured_upload_index'] : -1;
        $max_images = (int) TM_Helpers::get_option('tm_settings_marketplace', 'max_images', 5);

        if (! $title || ! $description || ! $price || ! $contact_name || ! $contact_email || ! $category_id || ! $subcategory_id || ! $region_id || ! $comuna_id || ! $condition_id) {
            wp_safe_redirect(TM_Helpers::build_redirect_url($redirect_url, array('tm_error' => 'missing_fields')));
            exit;
        }
        if (! is_email($contact_email)) {
            wp_safe_redirect(TM_Helpers::build_redirect_url($redirect_url, array('tm_error' => 'invalid_email')));
            exit;
        }
        if (! TM_Helpers::is_subcategory_of_category($subcategory_id, $category_id) || ! TM_Helpers::is_comuna_of_region($comuna_id, $region_id)) {
            TM_Helpers::debug_log('listing_invalid_terms', array('listing_id' => $listing_id, 'category_id' => $category_id, 'subcategory_id' => $subcategory_id, 'region_id' => $region_id, 'comuna_id' => $comuna_id, 'user_id' => $user_id));
            wp_safe_redirect(TM_Helpers::build_redirect_url($redirect_url, array('tm_error' => 'invalid_terms')));
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

        $post_data = array(
            'post_type' => 'tm_listing',
            'post_title' => $title,
            'post_content' => $description,
            'post_status' => 'publish',
            'post_author' => $user_id,
        );
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
        if ('active' === $status) {
            $existing_expiration = TM_Helpers::get_listing_expiration_date($listing_id);
            if ('' === $existing_expiration || TM_Helpers::should_treat_status_as_expired('active', $existing_expiration)) {
                TM_Helpers::reset_listing_expiration_date($listing_id);
            }
        }
        if (! get_post_meta($listing_id, 'tm_views_count', true)) {
            update_post_meta($listing_id, 'tm_views_count', 0);
        }

        if (! empty($delete_media)) {
            self::delete_listing_media($listing_id, $delete_media);
        }
        self::reorder_listing_media($listing_id, array_map('absint', array_filter(array_map('trim', explode(',', $existing_media_order)))));

        $uploaded_ids = self::upload_listing_images($listing_id, 'tm_images');
        if (is_wp_error($uploaded_ids)) {
            wp_safe_redirect(TM_Helpers::build_redirect_url($redirect_url, array('tm_error' => 'upload_failed')));
            exit;
        }

        if ($featured_existing_id > 0 && ! in_array($featured_existing_id, $delete_media, true)) {
            self::set_listing_featured_media($listing_id, $featured_existing_id);
        } elseif ($featured_upload_index >= 0 && isset($uploaded_ids[$featured_upload_index])) {
            self::set_listing_featured_media($listing_id, (int) $uploaded_ids[$featured_upload_index]);
        } else {
            self::ensure_listing_featured_image($listing_id);
        }

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
                TM_Helpers::debug_log('listing_upload_failed', array('listing_id' => $listing_id, 'error' => sanitize_text_field((string) $uploaded['error'])));
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

    protected static function reorder_listing_media(int $listing_id, array $ordered_ids): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'tm_listing_media';
        $existing_ids = TM_Helpers::get_listing_gallery_ids($listing_id);
        if (empty($existing_ids)) {
            return;
        }
        $ordered_ids = array_values(array_filter(array_map('absint', $ordered_ids)));
        $normalized = array();
        foreach ($ordered_ids as $attachment_id) {
            if (in_array($attachment_id, $existing_ids, true) && ! in_array($attachment_id, $normalized, true)) {
                $normalized[] = $attachment_id;
            }
        }
        foreach ($existing_ids as $attachment_id) {
            if (! in_array($attachment_id, $normalized, true)) {
                $normalized[] = $attachment_id;
            }
        }
        foreach ($normalized as $index => $attachment_id) {
            $wpdb->update($table, array('sort_order' => $index), array('listing_id' => $listing_id, 'attachment_id' => $attachment_id), array('%d'), array('%d', '%d'));
        }
    }

    protected static function set_listing_featured_media(int $listing_id, int $attachment_id): void
    {
        global $wpdb;
        if ($attachment_id <= 0) {
            self::ensure_listing_featured_image($listing_id);
            return;
        }
        $gallery_ids = TM_Helpers::get_listing_gallery_ids($listing_id);
        if (! in_array($attachment_id, $gallery_ids, true)) {
            self::ensure_listing_featured_image($listing_id);
            return;
        }
        set_post_thumbnail($listing_id, $attachment_id);
        $table = $wpdb->prefix . 'tm_listing_media';
        $wpdb->update($table, array('is_featured' => 0), array('listing_id' => $listing_id), array('%d'), array('%d'));
        $wpdb->update($table, array('is_featured' => 1), array('listing_id' => $listing_id, 'attachment_id' => $attachment_id), array('%d'), array('%d', '%d'));
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
            'invalid_terms'    => __('La combinación de categoría/subcategoría o región/comuna no es válida.', 'terramarket'),
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
            'listing_renewed'  => __('Aviso renovado correctamente.', 'terramarket'),
            'listing_duplicated' => __('Aviso duplicado correctamente.', 'terramarket'),
            'logged_out'       => __('Sesión cerrada correctamente.', 'terramarket'),
        );
        return $map[$code] ?? $code;
    }

    protected static function required_capability_for_listing_operation(string $operation): string
    {
        switch ($operation) {
            case 'pause':
                return 'tm_pause_own_listings';
            case 'sold':
                return 'tm_mark_own_listings_sold';
            case 'activate':
            case 'renew':
            case 'duplicate':
                return 'tm_edit_own_listings';
            default:
                return '';
        }
    }
}
