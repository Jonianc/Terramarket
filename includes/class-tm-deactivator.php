<?php

if (! defined('ABSPATH')) {
    exit;
}

class TM_Deactivator
{
    public static function deactivate(): void
    {
        TM_Alerts::clear_schedule();
        flush_rewrite_rules();
    }
}
