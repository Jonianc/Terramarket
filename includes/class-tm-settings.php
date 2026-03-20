<?php

if (! defined('ABSPATH')) {
    exit;
}

class TM_Settings
{
    public static function set_default_options(): void
    {
        $existing_general = get_option('tm_settings_general', array());
        $derived_market_slug = isset($existing_general['market_slug']) ? sanitize_title((string) $existing_general['market_slug']) : '';
        if (! $derived_market_slug) {
            $legacy_slug = isset($existing_general['listing_slug']) ? sanitize_title((string) $existing_general['listing_slug']) : '';
            $derived_market_slug = $legacy_slug && 'aviso' !== $legacy_slug ? $legacy_slug : 'terramarket';
        }

        $general = wp_parse_args($existing_general, array(
            'marketplace_name'  => 'Terramarket',
            'market_slug'       => $derived_market_slug,
            'listings_per_page' => 16,
            'from_email'        => get_option('admin_email'),
            'notify_email'      => get_option('admin_email'),
        ));
        $general['market_slug'] = sanitize_title((string) ($general['market_slug'] ?? 'terramarket')) ?: 'terramarket';

        $existing_branding = get_option('tm_settings_branding', array());
        $branding = wp_parse_args($existing_branding, array(
            'logo_id'           => 0,
            'watermark_logo_id' => 0,
            'primary_color'     => '#ffc500',
            'secondary_color'   => '#2f6b3b',
            'button_color'      => '#2f6b3b',
        ));

        $existing_marketplace = get_option('tm_settings_marketplace', array());
        $marketplace = wp_parse_args($existing_marketplace, array(
            'default_commission' => '5',
            'max_images'         => 5,
            'image_max_width'    => 2000,
            'image_quality'      => 82,
            'enable_watermark'   => 1,
            'watermark_position' => 'bottom-right',
            'watermark_opacity'  => 45,
        ));

        update_option('tm_settings_general', $general);
        update_option('tm_settings_branding', $branding);
        update_option('tm_settings_marketplace', $marketplace);
    }

    public static function register_settings(): void
    {
        register_setting('tm_settings_group_general', 'tm_settings_general', array(__CLASS__, 'sanitize_general'));
        register_setting('tm_settings_group_branding', 'tm_settings_branding', array(__CLASS__, 'sanitize_branding'));
        register_setting('tm_settings_group_marketplace', 'tm_settings_marketplace', array(__CLASS__, 'sanitize_marketplace'));

        add_action('update_option_tm_settings_general', array(__CLASS__, 'maybe_flush_rewrite_rules'), 10, 2);
    }

    public static function sanitize_general(array $input): array
    {
        $current = get_option('tm_settings_general', array());

        return array(
            'marketplace_name'  => sanitize_text_field($input['marketplace_name'] ?? ($current['marketplace_name'] ?? 'Terramarket')),
            'market_slug'       => sanitize_title($input['market_slug'] ?? ($current['market_slug'] ?? 'terramarket')) ?: 'terramarket',
            'listings_per_page' => max(1, absint($input['listings_per_page'] ?? ($current['listings_per_page'] ?? 16))),
            'from_email'        => sanitize_email($input['from_email'] ?? ($current['from_email'] ?? get_option('admin_email'))),
            'notify_email'      => sanitize_email($input['notify_email'] ?? ($current['notify_email'] ?? get_option('admin_email'))),
        );
    }

    public static function sanitize_branding(array $input): array
    {
        return array(
            'logo_id'           => absint($input['logo_id'] ?? 0),
            'watermark_logo_id' => absint($input['watermark_logo_id'] ?? 0),
            'primary_color'     => TM_Helpers::sanitize_hex($input['primary_color'] ?? '#ffc500', '#ffc500'),
            'secondary_color'   => TM_Helpers::sanitize_hex($input['secondary_color'] ?? '#2f6b3b', '#2f6b3b'),
            'button_color'      => TM_Helpers::sanitize_hex($input['button_color'] ?? '#2f6b3b', '#2f6b3b'),
        );
    }

    public static function sanitize_marketplace(array $input): array
    {
        return array(
            'default_commission' => sanitize_text_field($input['default_commission'] ?? '5'),
            'max_images'         => max(1, absint($input['max_images'] ?? 5)),
            'image_max_width'    => max(500, absint($input['image_max_width'] ?? 2000)),
            'image_quality'      => max(30, min(100, absint($input['image_quality'] ?? 82))),
            'enable_watermark'   => TM_Helpers::maybe_bool($input['enable_watermark'] ?? 0),
            'watermark_position' => sanitize_text_field($input['watermark_position'] ?? 'bottom-right'),
            'watermark_opacity'  => max(0, min(100, absint($input['watermark_opacity'] ?? 45))),
        );
    }

    public static function maybe_flush_rewrite_rules($old_value, $new_value): void
    {
        $old_slug = is_array($old_value) ? sanitize_title((string) ($old_value['market_slug'] ?? '')) : '';
        $new_slug = is_array($new_value) ? sanitize_title((string) ($new_value['market_slug'] ?? '')) : '';

        if ($old_slug !== $new_slug) {
            TM_Post_Types::register();
            TM_Taxonomies::register();
            TM_Template::add_rewrite_rules();
            flush_rewrite_rules();
        }
    }
}
