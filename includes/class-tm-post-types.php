<?php

if (! defined('ABSPATH')) {
    exit;
}

class TM_Post_Types
{
    public static function register(): void
    {
        $base_slug = TM_Helpers::get_market_slug();

        $labels = array(
            'name'               => __('Avisos', 'terramarket'),
            'singular_name'      => __('Aviso', 'terramarket'),
            'menu_name'          => __('Avisos', 'terramarket'),
            'name_admin_bar'     => __('Aviso', 'terramarket'),
            'add_new'            => __('Agregar nuevo', 'terramarket'),
            'add_new_item'       => __('Agregar nuevo aviso', 'terramarket'),
            'edit_item'          => __('Editar aviso', 'terramarket'),
            'new_item'           => __('Nuevo aviso', 'terramarket'),
            'view_item'          => __('Ver aviso', 'terramarket'),
            'all_items'          => __('Todos los avisos', 'terramarket'),
            'search_items'       => __('Buscar avisos', 'terramarket'),
            'not_found'          => __('No se encontraron avisos.', 'terramarket'),
            'not_found_in_trash' => __('No hay avisos en la papelera.', 'terramarket'),
        );

        $listing_caps = TM_Roles::get_listing_caps();

        register_post_type('tm_listing', array(
            'labels'             => $labels,
            'public'             => true,
            'has_archive'        => $base_slug,
            'rewrite'            => array('slug' => $base_slug . '/aviso', 'with_front' => false),
            'supports'           => array('title', 'editor', 'thumbnail', 'author'),
            'menu_icon'          => 'dashicons-store',
            'capability_type'     => array('tm_listing', 'tm_listings'),
            'capabilities'        => $listing_caps,
            'map_meta_cap'        => true,
            'show_in_menu'       => 'terramarket',
            'show_in_rest'       => true,
            'publicly_queryable' => true,
            'query_var'          => true,
        ));
    }
}
