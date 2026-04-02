<?php

if (! defined('ABSPATH')) {
    exit;
}

class TM_I18n
{
    public static function load_textdomain(): void
    {
        load_plugin_textdomain('terramarket', false, dirname(TM_PLUGIN_BASENAME) . '/languages/');
    }
}
