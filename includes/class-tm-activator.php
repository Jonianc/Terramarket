<?php

if (! defined('ABSPATH')) {
    exit;
}

class TM_Activator
{
    public static function activate(): void
    {
        TM_Settings::set_default_options();
        TM_Post_Types::register();
        TM_Taxonomies::register();
        TM_Template::add_rewrite_rules();
        TM_DB::create_tables();
        TM_Roles::add_roles_and_caps();
        TM_Seeder::seed_all();
        TM_Alerts::ensure_schedule();
        flush_rewrite_rules();
    }
}
