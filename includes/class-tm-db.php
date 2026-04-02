<?php

if (! defined('ABSPATH')) {
    exit;
}

class TM_DB
{
    public static function create_tables(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $prefix  = $wpdb->prefix;

        $sql = array();

        $sql[] = "CREATE TABLE {$prefix}tm_leads (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            listing_id BIGINT UNSIGNED NOT NULL,
            seller_user_id BIGINT UNSIGNED NOT NULL,
            buyer_name VARCHAR(190) NOT NULL,
            buyer_email VARCHAR(190) NOT NULL,
            buyer_phone VARCHAR(50) DEFAULT '',
            message LONGTEXT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'new',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY  (id),
            KEY listing_id (listing_id),
            KEY seller_user_id (seller_user_id),
            KEY status (status)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$prefix}tm_alerts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            keyword VARCHAR(190) DEFAULT '',
            category_term_id BIGINT UNSIGNED DEFAULT 0,
            subcategory_term_id BIGINT UNSIGNED DEFAULT 0,
            region_term_id BIGINT UNSIGNED DEFAULT 0,
            comuna_term_id BIGINT UNSIGNED DEFAULT 0,
            price_min BIGINT UNSIGNED DEFAULT 0,
            price_max BIGINT UNSIGNED DEFAULT 0,
            frequency VARCHAR(20) NOT NULL DEFAULT 'daily',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            last_sent_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY is_active (is_active)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$prefix}tm_listing_media (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            listing_id BIGINT UNSIGNED NOT NULL,
            attachment_id BIGINT UNSIGNED NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_featured TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY listing_id (listing_id),
            KEY attachment_id (attachment_id)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$prefix}tm_category_fields (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            category_term_id BIGINT UNSIGNED NOT NULL,
            subcategory_term_id BIGINT UNSIGNED DEFAULT 0,
            field_key VARCHAR(100) NOT NULL,
            field_label VARCHAR(190) NOT NULL,
            field_type VARCHAR(50) NOT NULL DEFAULT 'text',
            field_options_json LONGTEXT NULL,
            is_required TINYINT(1) NOT NULL DEFAULT 0,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY  (id),
            KEY category_term_id (category_term_id),
            KEY subcategory_term_id (subcategory_term_id),
            KEY field_key (field_key)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$prefix}tm_commission_events (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            listing_id BIGINT UNSIGNED NOT NULL,
            seller_user_id BIGINT UNSIGNED NOT NULL,
            sold_at DATETIME NULL,
            declared_sale_amount BIGINT UNSIGNED DEFAULT 0,
            commission_rate DECIMAL(8,2) DEFAULT 0.00,
            commission_amount DECIMAL(12,2) DEFAULT 0.00,
            settlement_status VARCHAR(30) NOT NULL DEFAULT 'pending',
            notes LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY  (id),
            KEY listing_id (listing_id),
            KEY seller_user_id (seller_user_id),
            KEY settlement_status (settlement_status)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$prefix}tm_vitrine_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            listing_id BIGINT UNSIGNED NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            starts_at DATETIME NULL,
            ends_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY  (id),
            KEY listing_id (listing_id),
            KEY is_active (is_active),
            KEY sort_order (sort_order)
        ) {$charset};";

        foreach ($sql as $statement) {
            dbDelta($statement);
        }
    }
}
