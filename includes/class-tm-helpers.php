<?php

if (! defined('ABSPATH')) {
    exit;
}

class TM_Helpers
{
    public static function get_option(string $group, string $key, $default = '')
    {
        $value = get_option($group, array());
        return is_array($value) && array_key_exists($key, $value) ? $value[$key] : $default;
    }

    public static function get_market_slug(): string
    {
        $slug = self::get_option('tm_settings_general', 'market_slug', '');
        if (! $slug) {
            $legacy = self::get_option('tm_settings_general', 'listing_slug', '');
            $slug = ('aviso' !== $legacy && $legacy) ? $legacy : 'terramarket';
        }

        return sanitize_title($slug) ?: 'terramarket';
    }

    public static function get_marketplace_name(): string
    {
        return (string) self::get_option('tm_settings_general', 'marketplace_name', 'Terramarket');
    }

    public static function trailingslashit_home(string $path = ''): string
    {
        return trailingslashit(home_url('/' . ltrim($path, '/')));
    }

    public static function get_market_url(string $path = ''): string
    {
        $base = self::get_market_slug();
        $path = trim($path, '/');
        return self::trailingslashit_home($base . ($path ? '/' . $path : ''));
    }

    public static function get_submit_page_url(array $args = array()): string
    {
        $url = self::get_market_url('publicar');
        return empty($args) ? $url : add_query_arg($args, $url);
    }

    public static function get_auth_page_url(array $args = array()): string
    {
        $url = self::get_market_url('acceso');
        return empty($args) ? $url : add_query_arg($args, $url);
    }

    public static function get_account_page_url(array $args = array()): string
    {
        $url = self::get_market_url('mi-cuenta');
        return empty($args) ? $url : add_query_arg($args, $url);
    }

    public static function get_logo_url(): string
    {
        $logo_id = (int) self::get_option('tm_settings_branding', 'logo_id', 0);
        return $logo_id ? (wp_get_attachment_image_url($logo_id, 'full') ?: '') : '';
    }

    public static function get_terms_for_select(string $taxonomy): array
    {
        $terms = get_terms(array(
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ));

        return is_wp_error($terms) ? array() : $terms;
    }

    public static function get_regions_ordered(): array
    {
        $terms = self::get_terms_for_select('tm_region');
        usort($terms, static function ($a, $b) {
            return (int) get_term_meta($a->term_id, 'tm_region_order', true) <=> (int) get_term_meta($b->term_id, 'tm_region_order', true);
        });
        return $terms;
    }

    public static function selected_term_id(int $post_id, string $taxonomy): int
    {
        $terms = wp_get_object_terms($post_id, $taxonomy, array('fields' => 'ids'));
        if (is_wp_error($terms) || empty($terms)) {
            return 0;
        }

        return (int) $terms[0];
    }

    public static function get_term_name_for_post(int $post_id, string $taxonomy): string
    {
        $term_id = self::selected_term_id($post_id, $taxonomy);
        if (! $term_id) {
            return '';
        }

        $term = get_term($term_id, $taxonomy);
        return ($term && ! is_wp_error($term)) ? $term->name : '';
    }

    public static function is_subcategory_of_category(int $subcategory_id, int $category_id): bool
    {
        if ($subcategory_id < 1 || $category_id < 1) {
            return false;
        }

        $subcategory = get_term($subcategory_id, 'tm_subcategory');
        $category = get_term($category_id, 'tm_category');
        if (! ($subcategory instanceof WP_Term) || ! ($category instanceof WP_Term)) {
            return false;
        }

        $parent_category_id = (int) get_term_meta($subcategory_id, 'tm_parent_category_id', true);
        return $parent_category_id > 0 && $parent_category_id === $category_id;
    }

    public static function is_comuna_of_region(int $comuna_id, int $region_id): bool
    {
        if ($comuna_id < 1 || $region_id < 1) {
            return false;
        }

        $comuna = get_term($comuna_id, 'tm_comuna');
        $region = get_term($region_id, 'tm_region');
        if (! ($comuna instanceof WP_Term) || ! ($region instanceof WP_Term)) {
            return false;
        }

        $linked_region_id = (int) get_term_meta($comuna_id, 'tm_region_id', true);
        return $linked_region_id > 0 && $linked_region_id === $region_id;
    }

    public static function get_listing_statuses(): array
    {
        return array(
            'active' => __('Activo', 'terramarket'),
            'paused' => __('Pausado', 'terramarket'),
            'sold'   => __('Vendido', 'terramarket'),
        );
    }

    public static function get_listing_status_label(string $status): string
    {
        $statuses = self::get_listing_statuses();
        return $statuses[$status] ?? $statuses['active'];
    }

    public static function admin_url_for_taxonomy(string $taxonomy): string
    {
        return admin_url('edit-tags.php?taxonomy=' . $taxonomy . '&post_type=tm_listing');
    }

    public static function sanitize_hex($value, string $default = ''): string
    {
        $sanitized = sanitize_hex_color((string) $value);
        return $sanitized ? $sanitized : $default;
    }

    public static function maybe_bool($value): int
    {
        return empty($value) ? 0 : 1;
    }

    public static function format_price_clp($amount): string
    {
        return '$' . number_format_i18n((int) $amount, 0);
    }

    public static function get_listing_gallery_ids(int $listing_id): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'tm_listing_media';
        $rows  = $wpdb->get_col($wpdb->prepare(
            "SELECT attachment_id FROM {$table} WHERE listing_id = %d ORDER BY is_featured DESC, sort_order ASC, id ASC",
            $listing_id
        ));

        $ids = array_map('absint', is_array($rows) ? $rows : array());
        $ids = array_values(array_filter(array_unique($ids)));

        $thumbnail_id = get_post_thumbnail_id($listing_id);
        if ($thumbnail_id && ! in_array((int) $thumbnail_id, $ids, true)) {
            array_unshift($ids, (int) $thumbnail_id);
        }

        return $ids;
    }

    public static function get_listing_gallery(int $listing_id): array
    {
        $gallery = array();
        foreach (self::get_listing_gallery_ids($listing_id) as $attachment_id) {
            $gallery[] = array(
                'id'    => $attachment_id,
                'full'  => wp_get_attachment_image_url($attachment_id, 'large') ?: '',
                'thumb' => wp_get_attachment_image_url($attachment_id, 'medium') ?: '',
                'html'  => wp_get_attachment_image($attachment_id, 'large', false, array('loading' => 'lazy')),
                'alt'   => get_post_meta($attachment_id, '_wp_attachment_image_alt', true),
            );
        }

        return $gallery;
    }

    public static function get_listing_primary_image_url(int $listing_id, string $size = 'large'): string
    {
        $ids = self::get_listing_gallery_ids($listing_id);
        if (! empty($ids)) {
            $url = wp_get_attachment_image_url((int) $ids[0], $size);
            return $url ?: '';
        }

        return '';
    }

    public static function get_listing_image_count(int $listing_id): int
    {
        return count(self::get_listing_gallery_ids($listing_id));
    }

    public static function is_listing_owner(int $listing_id, int $user_id): bool
    {
        $post = get_post($listing_id);
        return $post instanceof WP_Post && 'tm_listing' === $post->post_type && (int) $post->post_author === $user_id;
    }

    public static function current_url(array $remove_query_args = array()): string
    {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';
        $url = home_url($request_uri);
        return empty($remove_query_args) ? $url : remove_query_arg($remove_query_args, $url);
    }

    public static function build_redirect_url(string $base_url, array $args = array()): string
    {
        $clean = remove_query_arg(array('tm_notice', 'tm_error'), $base_url);
        return add_query_arg($args, $clean);
    }

    public static function get_posted_files_count(string $field_name): int
    {
        if (empty($_FILES[$field_name]) || empty($_FILES[$field_name]['name'])) {
            return 0;
        }

        $names = $_FILES[$field_name]['name'];
        if (! is_array($names)) {
            return empty($names) ? 0 : 1;
        }

        $count = 0;
        foreach ($names as $name) {
            if (! empty($name)) {
                $count++;
            }
        }

        return $count;
    }

    public static function normalize_uploaded_files(string $field_name): array
    {
        if (empty($_FILES[$field_name]) || empty($_FILES[$field_name]['name']) || ! is_array($_FILES[$field_name]['name'])) {
            return array();
        }

        $normalized = array();
        $files      = $_FILES[$field_name];
        $total      = count($files['name']);

        for ($i = 0; $i < $total; $i++) {
            if (empty($files['name'][$i])) {
                continue;
            }

            $normalized[] = array(
                'name'     => sanitize_file_name(wp_unslash($files['name'][$i])),
                'type'     => sanitize_text_field(wp_unslash($files['type'][$i] ?? '')),
                'tmp_name' => $files['tmp_name'][$i] ?? '',
                'error'    => absint($files['error'][$i] ?? 0),
                'size'     => absint($files['size'][$i] ?? 0),
            );
        }

        return $normalized;
    }

    public static function debug_log(string $event, array $context = array()): void
    {
        if (! defined('WP_DEBUG') || ! WP_DEBUG) {
            return;
        }

        $safe_context = array();
        foreach ($context as $key => $value) {
            if (is_scalar($value) || null === $value) {
                $safe_context[(string) $key] = $value;
            }
        }

        $encoded = wp_json_encode($safe_context);
        if (! is_string($encoded)) {
            $encoded = '{}';
        }

        error_log('[Terramarket][' . sanitize_key($event) . '] ' . $encoded); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
    }

    public static function get_submit_form_url(): string
    {
        return admin_url('admin-post.php');
    }

    public static function get_allowed_image_mimes(): array
    {
        return array(
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png'          => 'image/png',
            'webp'         => 'image/webp',
        );
    }

    public static function get_branding_vars(): array
    {
        return array(
            '--tm-primary'   => (string) self::get_option('tm_settings_branding', 'primary_color', '#ffc500'),
            '--tm-secondary' => (string) self::get_option('tm_settings_branding', 'secondary_color', '#2f6b3b'),
            '--tm-button'    => (string) self::get_option('tm_settings_branding', 'button_color', '#2f6b3b'),
        );
    }

    public static function get_user_account_type(int $user_id): string
    {
        $role = get_user_meta($user_id, 'tm_account_type', true);
        if ($role) {
            return (string) $role;
        }

        $user = get_userdata($user_id);
        if (! $user) {
            return 'tm_seller';
        }

        foreach (array('tm_company', 'tm_broker', 'tm_seller') as $role_name) {
            if (in_array($role_name, (array) $user->roles, true)) {
                return $role_name;
            }
        }

        return 'tm_seller';
    }

    public static function get_account_type_label(string $role): string
    {
        $labels = array(
            'tm_seller'  => __('Particular', 'terramarket'),
            'tm_company' => __('Empresa', 'terramarket'),
            'tm_broker'  => __('Corredor / Intermediario', 'terramarket'),
        );

        return $labels[$role] ?? $labels['tm_seller'];
    }
}
