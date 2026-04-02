<?php

if (! defined('ABSPATH')) {
    exit;
}

class TM_Alerts
{
    public const CRON_HOOK = 'tm_daily_alerts_event';

    public static function init(): void
    {
        add_action(self::CRON_HOOK, array(__CLASS__, 'process_due_alerts'));
        self::ensure_schedule();
    }

    public static function ensure_schedule(): void
    {
        if (! wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK);
        }
    }

    public static function clear_schedule(): void
    {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        while ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
            $timestamp = wp_next_scheduled(self::CRON_HOOK);
        }
    }

    public static function process_pending_alerts(): void
    {
        self::process_due_alerts();
    }

    public static function expire_due_listings(): int
    {
        $expired_ids = get_posts(array(
            'post_type'              => 'tm_listing',
            'post_status'            => 'publish',
            'posts_per_page'         => -1,
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'meta_query'             => array(
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
        ));

        if (empty($expired_ids)) {
            return 0;
        }

        $count = 0;
        foreach ($expired_ids as $listing_id) {
            update_post_meta((int) $listing_id, 'tm_listing_status', 'expired');
            $count++;
        }

        return $count;
    }

    public static function process_due_alerts(): void
    {
        self::expire_due_listings();

        global $wpdb;

        $table = $wpdb->prefix . 'tm_alerts';
        $alerts = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE is_active = %d AND frequency = %s ORDER BY id ASC LIMIT %d",
            1,
            'daily',
            500
        ));
        if (! $alerts) {
            return;
        }

        foreach ($alerts as $alert) {
            self::process_single_alert($alert);
        }
    }

    public static function process_single_alert($alert): bool
    {
        global $wpdb;

        $alert_id = (int) ($alert->id ?? 0);
        $user_id  = (int) ($alert->user_id ?? 0);
        if (! $alert_id || ! $user_id) {
            TM_Helpers::debug_log('alerts_invalid_payload', array('alert_id' => $alert_id, 'user_id' => $user_id));
            return false;
        }

        $user = get_userdata($user_id);
        if (! $user || ! is_email($user->user_email)) {
            TM_Helpers::debug_log('alerts_invalid_user', array('alert_id' => $alert_id, 'user_id' => $user_id));
            return false;
        }

        $after = ! empty($alert->last_sent_at) ? gmdate('Y-m-d H:i:s', strtotime((string) $alert->last_sent_at)) : gmdate('Y-m-d H:i:s', time() - DAY_IN_SECONDS);
        $query_args = self::build_query_args((array) $alert, $after);
        $query = new WP_Query($query_args);

        if (! $query->have_posts()) {
            $wpdb->update(
                $wpdb->prefix . 'tm_alerts',
                array('last_sent_at' => current_time('mysql'), 'updated_at' => current_time('mysql')),
                array('id' => $alert_id),
                array('%s', '%s'),
                array('%d')
            );
            return false;
        }

        $subject = sprintf(__('Nuevos avisos para tu alerta en %s', 'terramarket'), TM_Helpers::get_marketplace_name());
        $message_lines = array();
        $message_lines[] = sprintf(__('Hola %s, encontramos nuevos avisos que coinciden con tu alerta diaria:', 'terramarket'), $user->display_name ?: $user->user_login);
        $message_lines[] = '';

        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();
            $message_lines[] = get_the_title($post_id);
            $message_lines[] = TM_Helpers::format_price_clp((int) get_post_meta($post_id, 'tm_price_clp', true));
            $message_lines[] = get_permalink($post_id);
            $message_lines[] = '';
        }
        wp_reset_postdata();

        $message_lines[] = __('Puedes revisar tu panel para editar o desactivar tus alertas cuando quieras.', 'terramarket');
        $message_lines[] = TM_Helpers::get_account_page_url(array('tab' => 'alerts'));

        $headers = array('Content-Type: text/plain; charset=UTF-8');
        $from_email = TM_Helpers::get_option('tm_settings_general', 'from_email', get_option('admin_email'));
        if (is_email($from_email)) {
            $headers[] = 'From: ' . TM_Helpers::get_marketplace_name() . ' <' . $from_email . '>';
        }

        $sent = wp_mail($user->user_email, $subject, implode("\n", $message_lines), $headers);
        if ($sent) {
            $wpdb->update(
                $wpdb->prefix . 'tm_alerts',
                array('last_sent_at' => current_time('mysql'), 'updated_at' => current_time('mysql')),
                array('id' => $alert_id),
                array('%s', '%s'),
                array('%d')
            );
        } else {
            TM_Helpers::debug_log('alerts_mail_failed', array('alert_id' => $alert_id, 'user_id' => $user_id));
        }

        return (bool) $sent;
    }

    public static function build_query_args(array $alert, string $after = ''): array
    {
        $args = array(
            'post_type'      => 'tm_listing',
            'post_status'    => 'publish',
            'posts_per_page' => 10,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => TM_Helpers::get_public_active_listings_meta_query(),
            'tax_query'      => array(),
        );

        if (! empty($alert['keyword'])) {
            $args['s'] = (string) $alert['keyword'];
        }
        if (! empty($after)) {
            $args['date_query'] = array(
                array(
                    'after'     => $after,
                    'inclusive' => false,
                    'column'    => 'post_date_gmt',
                ),
            );
        }
        if (! empty($alert['price_min'])) {
            $args['meta_query'][] = array('key' => 'tm_price_clp', 'value' => (int) $alert['price_min'], 'type' => 'NUMERIC', 'compare' => '>=');
        }
        if (! empty($alert['price_max'])) {
            $args['meta_query'][] = array('key' => 'tm_price_clp', 'value' => (int) $alert['price_max'], 'type' => 'NUMERIC', 'compare' => '<=');
        }

        $map = array(
            'tm_category'    => 'category_term_id',
            'tm_subcategory' => 'subcategory_term_id',
            'tm_region'      => 'region_term_id',
            'tm_comuna'      => 'comuna_term_id',
        );
        foreach ($map as $taxonomy => $key) {
            $term_id = (int) ($alert[$key] ?? 0);
            if ($term_id > 0) {
                $args['tax_query'][] = array(
                    'taxonomy' => $taxonomy,
                    'field'    => 'term_id',
                    'terms'    => array($term_id),
                );
            }
        }
        if (count($args['tax_query']) > 1) {
            $args['tax_query']['relation'] = 'AND';
        }

        return $args;
    }

    public static function get_user_alerts(int $user_id): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'tm_alerts';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC, id DESC", $user_id));
        return is_array($rows) ? $rows : array();
    }

    public static function create_alert(int $user_id, array $data)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'tm_alerts';
        $inserted = $wpdb->insert(
            $table,
            array(
                'user_id'             => $user_id,
                'keyword'             => sanitize_text_field($data['keyword'] ?? ''),
                'category_term_id'    => absint($data['category_term_id'] ?? 0),
                'subcategory_term_id' => absint($data['subcategory_term_id'] ?? 0),
                'region_term_id'      => absint($data['region_term_id'] ?? 0),
                'comuna_term_id'      => absint($data['comuna_term_id'] ?? 0),
                'price_min'           => absint($data['price_min'] ?? 0),
                'price_max'           => absint($data['price_max'] ?? 0),
                'frequency'           => 'daily',
                'is_active'           => 1,
                'created_at'          => current_time('mysql'),
                'updated_at'          => current_time('mysql'),
            ),
            array('%d', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%d', '%s', '%s')
        );
        return $inserted ? (int) $wpdb->insert_id : false;
    }

    public static function delete_alert(int $alert_id, int $user_id): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'tm_alerts';
        $deleted = $wpdb->delete($table, array('id' => $alert_id, 'user_id' => $user_id), array('%d', '%d'));
        return (bool) $deleted;
    }
}
