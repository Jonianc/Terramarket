<?php

if (! defined('ABSPATH')) {
    exit;
}

class TM_Roles
{
    public static function init(): void
    {
        self::sync_roles_and_caps();
    }

    public static function add_roles_and_caps(): void
    {
        self::sync_roles_and_caps();
    }

    public static function sync_roles_and_caps(): void
    {
        $base_caps = self::get_marketplace_role_caps();

        foreach (array(
            'tm_seller'  => __('Vendedor Terramarket', 'terramarket'),
            'tm_company' => __('Empresa Terramarket', 'terramarket'),
            'tm_broker'  => __('Corredor Terramarket', 'terramarket'),
        ) as $role_key => $label) {
            $role = get_role($role_key);
            if (! $role) {
                add_role($role_key, $label, $base_caps);
                $role = get_role($role_key);
            }

            if (! $role) {
                continue;
            }

            foreach ($base_caps as $cap => $grant) {
                if ($grant) {
                    $role->add_cap($cap);
                } else {
                    $role->remove_cap($cap);
                }
            }
        }

        $admin = get_role('administrator');
        if ($admin) {
            foreach (self::get_admin_caps() as $cap) {
                $admin->add_cap($cap);
            }
        }
    }

    public static function get_marketplace_role_caps(): array
    {
        return array(
            'read'                      => true,
            'tm_create_listings'        => true,
            'tm_edit_own_listings'      => true,
            'tm_pause_own_listings'     => true,
            'tm_mark_own_listings_sold' => true,
            'tm_view_own_leads'         => true,
            'tm_manage_own_alerts'      => true,
        );
    }

    public static function get_admin_caps(): array
    {
        return array_values(array_unique(array_merge(
            array(
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
            ),
            array_values(self::get_listing_caps())
        )));
    }

    public static function get_listing_caps(): array
    {
        return array(
            'edit_post'              => 'tm_edit_listing',
            'read_post'              => 'tm_read_listing',
            'delete_post'            => 'tm_delete_listing',
            'edit_posts'             => 'tm_edit_listings',
            'edit_others_posts'      => 'tm_edit_others_listings',
            'publish_posts'          => 'tm_publish_listings',
            'read_private_posts'     => 'tm_read_private_listings',
            'delete_posts'           => 'tm_delete_listings',
            'delete_private_posts'   => 'tm_delete_private_listings',
            'delete_published_posts' => 'tm_delete_published_listings',
            'delete_others_posts'    => 'tm_delete_others_listings',
            'edit_private_posts'     => 'tm_edit_private_listings',
            'edit_published_posts'   => 'tm_edit_published_listings',
            'create_posts'           => 'tm_create_listings',
        );
    }

    public static function remove_roles_and_caps(): void
    {
        remove_role('tm_seller');
        remove_role('tm_company');
        remove_role('tm_broker');
    }
}
