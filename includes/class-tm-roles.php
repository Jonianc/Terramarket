<?php

if (! defined('ABSPATH')) {
    exit;
}

class TM_Roles
{
    public static function add_roles_and_caps(): void
    {
        $caps = array(
            'read'                    => true,
            'tm_create_listings'      => true,
            'tm_edit_own_listings'    => true,
            'tm_pause_own_listings'   => true,
            'tm_mark_own_listings_sold' => true,
            'tm_view_own_leads'       => true,
            'tm_manage_own_alerts'    => true,
        );

        add_role('tm_seller', __('Vendedor Terramarket', 'terramarket'), $caps);
        add_role('tm_company', __('Empresa Terramarket', 'terramarket'), $caps);
        add_role('tm_broker', __('Corredor Terramarket', 'terramarket'), $caps);

        $admin = get_role('administrator');
        if ($admin) {
            foreach (array(
                'tm_create_listings',
                'tm_edit_own_listings',
                'tm_pause_own_listings',
                'tm_mark_own_listings_sold',
                'tm_view_own_leads',
                'tm_manage_own_alerts',
                'tm_manage_settings',
                'tm_manage_vitrine',
                'tm_manage_categories',
                'tm_manage_regions',
                'tm_manage_leads',
                'tm_manage_commissions',
            ) as $cap) {
                $admin->add_cap($cap);
            }
        }
    }

    public static function remove_roles_and_caps(): void
    {
        remove_role('tm_seller');
        remove_role('tm_company');
        remove_role('tm_broker');
    }
}
