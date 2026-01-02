<?php
/**
 * Plugin Name: Creators EuroStocks Importer
 * Description: Import/sync automotoren en/of versnellingsbakken vanuit EuroStocks (Data API + Product Data API) naar een CPT met taxonomieën.
 * Version: 0.6.0
 * Author: Creators
 * Text Domain: creators-eurostocks
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) { exit; }

define('CE_EUROSTOCKS_VERSION', '0.6.0');
define('CE_EUROSTOCKS_PLUGIN_FILE', __FILE__);
define('CE_EUROSTOCKS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CE_EUROSTOCKS_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load text domain for translations
add_action('plugins_loaded', 'ce_eurostocks_load_textdomain');
function ce_eurostocks_load_textdomain() {
  load_plugin_textdomain('creators-eurostocks', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

require_once CE_EUROSTOCKS_PLUGIN_DIR . 'includes/helpers.php';
require_once CE_EUROSTOCKS_PLUGIN_DIR . 'includes/api.php';
require_once CE_EUROSTOCKS_PLUGIN_DIR . 'includes/importer.php';
require_once CE_EUROSTOCKS_PLUGIN_DIR . 'includes/admin.php';
require_once CE_EUROSTOCKS_PLUGIN_DIR . 'includes/admin-extensions.php';

register_activation_hook(__FILE__, array('CE_EuroStocks_Importer', 'activate'));
register_deactivation_hook(__FILE__, array('CE_EuroStocks_Importer', 'deactivate'));

add_action('init', array('CE_EuroStocks_Importer', 'register_cpt_and_taxonomies'));
add_action('admin_menu', array('CE_EuroStocks_Admin', 'menu'));
add_action('admin_init', array('CE_EuroStocks_Admin', 'register_settings'));
add_action('admin_init', array('CE_EuroStocks_Admin', 'hooks'));

add_action('admin_post_ce_eurostocks_run_import', array('CE_EuroStocks_Admin', 'handle_manual_import'));
add_action('admin_post_ce_eurostocks_test_languages', array('CE_EuroStocks_Admin', 'handle_test_languages'));
add_action('admin_post_ce_eurostocks_test_location', array('CE_EuroStocks_Admin', 'handle_test_location'));
add_action('admin_post_ce_eurostocks_purge', array('CE_EuroStocks_Admin', 'handle_purge'));
add_action('admin_post_ce_eurostocks_show_last_raw', array('CE_EuroStocks_Admin', 'handle_show_last_raw'));
add_action('admin_post_ce_eurostocks_delete_missing', array('CE_EuroStocks_Admin', 'handle_delete_missing'));
add_action('admin_post_ce_eurostocks_test_image', array('CE_EuroStocks_Admin', 'handle_test_image'));
add_action('admin_post_ce_eurostocks_export_csv', array('CE_EuroStocks_Admin', 'handle_export_csv'));

add_action(CE_EuroStocks_Importer::CRON_HOOK, array('CE_EuroStocks_Importer', 'run_import'));

// Bulk actions for post list
add_filter('bulk_actions-edit-ce_part', array('CE_EuroStocks_Admin', 'register_bulk_actions'));
add_filter('handle_bulk_actions-edit-ce_part', array('CE_EuroStocks_Admin', 'handle_bulk_actions'), 10, 3);
add_action('admin_notices', array('CE_EuroStocks_Admin', 'bulk_action_notices'));

// Admin list filters
add_filter('parse_query', array('CE_EuroStocks_Admin', 'filter_admin_query'));
add_action('restrict_manage_posts', array('CE_EuroStocks_Admin', 'add_admin_filters'));

// Dashboard widget
add_action('wp_dashboard_setup', array('CE_EuroStocks_Admin', 'add_dashboard_widget'));
