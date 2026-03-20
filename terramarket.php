<?php
/**
 * Plugin Name: Terramarket
 * Plugin URI: https://example.com/terramarket
 * Description: Marketplace agro autónomo para WordPress. Bloque E: pulido UI/UX final, responsive fino, accesibilidad base y mejoras de rendimiento.
 * Version: 1.4.0
 * Author: OpenAI
 * Text Domain: terramarket
 * Domain Path: /languages
 * Requires at least: 6.2
 * Requires PHP: 7.4
 */

if (! defined('ABSPATH')) {
    exit;
}

define('TM_VERSION', '1.4.0');
define('TM_PLUGIN_FILE', __FILE__);
define('TM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TM_PLUGIN_BASENAME', plugin_basename(__FILE__));

require_once TM_PLUGIN_DIR . 'includes/class-tm-helpers.php';
require_once TM_PLUGIN_DIR . 'includes/class-tm-i18n.php';
require_once TM_PLUGIN_DIR . 'includes/class-tm-post-types.php';
require_once TM_PLUGIN_DIR . 'includes/class-tm-taxonomies.php';
require_once TM_PLUGIN_DIR . 'includes/class-tm-roles.php';
require_once TM_PLUGIN_DIR . 'includes/class-tm-db.php';
require_once TM_PLUGIN_DIR . 'includes/class-tm-seeder.php';
require_once TM_PLUGIN_DIR . 'includes/class-tm-settings.php';
require_once TM_PLUGIN_DIR . 'includes/class-tm-alerts.php';
require_once TM_PLUGIN_DIR . 'includes/class-tm-template.php';
require_once TM_PLUGIN_DIR . 'admin/class-tm-admin.php';
require_once TM_PLUGIN_DIR . 'public/class-tm-public.php';
require_once TM_PLUGIN_DIR . 'includes/class-tm-activator.php';
require_once TM_PLUGIN_DIR . 'includes/class-tm-deactivator.php';
require_once TM_PLUGIN_DIR . 'includes/class-tm-loader.php';

register_activation_hook(__FILE__, array('TM_Activator', 'activate'));
register_deactivation_hook(__FILE__, array('TM_Deactivator', 'deactivate'));

TM_Loader::init();
