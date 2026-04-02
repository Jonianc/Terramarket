<?php

if (! defined('ABSPATH')) {
    exit;
}

class TM_Taxonomies
{
    public static function register(): void
    {
        self::register_category();
        self::register_subcategory();
        self::register_region();
        self::register_comuna();
        self::register_condition();
    }

    protected static function register_category(): void
    {
        register_taxonomy('tm_category', array('tm_listing'), array(
            'labels' => array(
                'name'          => __('Categorías', 'terramarket'),
                'singular_name' => __('Categoría', 'terramarket'),
            ),
            'hierarchical'      => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'capabilities'      => array(
                'manage_terms' => 'tm_manage_categories',
                'edit_terms'   => 'tm_manage_categories',
                'delete_terms' => 'tm_manage_categories',
                'assign_terms' => 'tm_edit_listings',
            ),
            'rewrite'           => array('slug' => TM_Helpers::get_market_slug() . '/categoria', 'with_front' => false),
        ));
    }

    protected static function register_subcategory(): void
    {
        register_taxonomy('tm_subcategory', array('tm_listing'), array(
            'labels' => array(
                'name'          => __('Subcategorías', 'terramarket'),
                'singular_name' => __('Subcategoría', 'terramarket'),
            ),
            'hierarchical'      => false,
            'show_admin_column' => false,
            'show_in_rest'      => true,
            'rewrite'           => array('slug' => TM_Helpers::get_market_slug() . '/subcategoria', 'with_front' => false),
        ));
    }

    protected static function register_region(): void
    {
        register_taxonomy('tm_region', array('tm_listing'), array(
            'labels' => array(
                'name'          => __('Regiones', 'terramarket'),
                'singular_name' => __('Región', 'terramarket'),
            ),
            'hierarchical'      => false,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'rewrite'           => array('slug' => TM_Helpers::get_market_slug() . '/region', 'with_front' => false),
        ));
    }

    protected static function register_comuna(): void
    {
        register_taxonomy('tm_comuna', array('tm_listing'), array(
            'labels' => array(
                'name'          => __('Comunas', 'terramarket'),
                'singular_name' => __('Comuna', 'terramarket'),
            ),
            'hierarchical'      => false,
            'show_admin_column' => false,
            'show_in_rest'      => true,
            'rewrite'           => array('slug' => TM_Helpers::get_market_slug() . '/comuna', 'with_front' => false),
        ));
    }

    protected static function register_condition(): void
    {
        register_taxonomy('tm_condition', array('tm_listing'), array(
            'labels' => array(
                'name'          => __('Condición', 'terramarket'),
                'singular_name' => __('Condición', 'terramarket'),
            ),
            'hierarchical'      => false,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'rewrite'           => array('slug' => TM_Helpers::get_market_slug() . '/condicion', 'with_front' => false),
        ));
    }
}
