<?php
/**
 * Plugin Name: Creators EuroStocks Importer
 * Description: Import/sync automotoren en/of versnellingsbakken vanuit EuroStocks (Data API + Product Data API) naar een CPT met taxonomieën.
 * Version: 0.4.0
 * Author: Creators
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) { exit; }

define('CPL_EUROSTOCKS_VERSION', '0.4.0');
define('CPL_EUROSTOCKS_PLUGIN_FILE', __FILE__);
define('CPL_EUROSTOCKS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CPL_EUROSTOCKS_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once CPL_EUROSTOCKS_PLUGIN_DIR . 'includes/helpers.php';
require_once CPL_EUROSTOCKS_PLUGIN_DIR . 'includes/api.php';
require_once CPL_EUROSTOCKS_PLUGIN_DIR . 'includes/importer.php';
require_once CPL_EUROSTOCKS_PLUGIN_DIR . 'includes/admin.php';

register_activation_hook(__FILE__, array('CPL_EuroStocks_Importer', 'activate'));
register_deactivation_hook(__FILE__, array('CPL_EuroStocks_Importer', 'deactivate'));

add_action('init', array('CPL_EuroStocks_Importer', 'register_cpt_and_taxonomies'));
add_action('admin_menu', array('CPL_EuroStocks_Admin', 'menu'));
add_action('admin_init', array('CPL_EuroStocks_Admin', 'register_settings'));
add_action('admin_init', array('CPL_EuroStocks_Admin', 'hooks'));

add_action('admin_post_cpl_eurostocks_run_import', array('CPL_EuroStocks_Admin', 'handle_manual_import'));
add_action('admin_post_cpl_eurostocks_test_languages', array('CPL_EuroStocks_Admin', 'handle_test_languages'));
add_action('admin_post_cpl_eurostocks_purge', array('CPL_EuroStocks_Admin', 'handle_purge'));
add_action('admin_post_cpl_eurostocks_show_last_raw', array('CPL_EuroStocks_Admin', 'handle_show_last_raw'));
add_action('admin_post_cpl_eurostocks_delete_missing', array('CPL_EuroStocks_Admin', 'handle_delete_missing'));
add_action('admin_post_cpl_eurostocks_test_image', array('CPL_EuroStocks_Admin', 'handle_test_image'));

add_action(CPL_EuroStocks_Importer::CRON_HOOK, array('CPL_EuroStocks_Importer', 'run_import'));
