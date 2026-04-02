<?php

if (! defined('ABSPATH')) {
    exit;
}

class TM_Template
{
    public static function init(): void
    {
        add_action('init', array(__CLASS__, 'add_rewrite_rules'), 9);
        add_filter('query_vars', array(__CLASS__, 'register_query_vars'));
        add_filter('template_include', array(__CLASS__, 'template_include'));
        add_filter('body_class', array(__CLASS__, 'body_class'));
    }

    public static function add_rewrite_rules(): void
    {
        $base = TM_Helpers::get_market_slug();
        add_rewrite_rule('^' . preg_quote($base, '/') . '/publicar/?$', 'index.php?tm_view=submit', 'top');
        add_rewrite_rule('^' . preg_quote($base, '/') . '/mi-cuenta/?$', 'index.php?tm_view=account', 'top');
        add_rewrite_rule('^' . preg_quote($base, '/') . '/acceso/?$', 'index.php?tm_view=auth', 'top');
    }

    public static function register_query_vars(array $vars): array
    {
        $vars[] = 'tm_view';
        return $vars;
    }

    public static function is_standalone_request(): bool
    {
        return (bool) get_query_var('tm_view')
            || is_post_type_archive('tm_listing')
            || is_singular('tm_listing')
            || is_tax(array('tm_category', 'tm_region', 'tm_comuna', 'tm_subcategory', 'tm_condition'));
    }

    public static function template_include(string $template): string
    {
        if (self::is_standalone_request()) {
            $standalone = TM_PLUGIN_DIR . 'templates/standalone.php';
            if (file_exists($standalone)) {
                return $standalone;
            }
        }

        return $template;
    }

    public static function body_class(array $classes): array
    {
        if (self::is_standalone_request()) {
            $classes[] = 'tm-standalone-body';
        }
        return $classes;
    }
}
