<?php

if (! defined('ABSPATH')) {
    exit;
}

class TM_Loader
{
    public static function init(): void
    {
        add_action('plugins_loaded', array('TM_I18n', 'load_textdomain'));
        add_action('init', array('TM_Post_Types', 'register'), 5);
        add_action('init', array('TM_Taxonomies', 'register'), 6);
        add_action('init', array('TM_Template', 'init'), 20);
        add_action('init', array('TM_Public', 'init'), 20);
        add_action('init', array('TM_Alerts', 'init'), 20);

        if (is_admin()) {
            add_action('init', array('TM_Admin', 'init'), 20);
            add_action('admin_init', array('TM_Settings', 'register_settings'));
        }
    }
}
