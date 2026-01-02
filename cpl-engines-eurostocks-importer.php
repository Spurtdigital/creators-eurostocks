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

define('CE_EUROSTOCKS_VERSION', '0.5.0');
define('CE_EUROSTOCKS_PLUGIN_FILE', __FILE__);
define('CE_EUROSTOCKS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CE_EUROSTOCKS_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once CE_EUROSTOCKS_PLUGIN_DIR . 'includes/helpers.php';
require_once CE_EUROSTOCKS_PLUGIN_DIR . 'includes/api.php';
require_once CE_EUROSTOCKS_PLUGIN_DIR . 'includes/importer.php';
require_once CE_EUROSTOCKS_PLUGIN_DIR . 'includes/admin.php';

register_activation_hook(__FILE__, array('CE_EuroStocks_Importer', 'activate'));
register_deactivation_hook(__FILE__, array('CE_EuroStocks_Importer', 'deactivate'));

add_action('init', array('CE_EuroStocks_Importer', 'register_cpt_and_taxonomies'));
add_action('admin_menu', array('CE_EuroStocks_Admin', 'menu'));
add_action('admin_init', array('CE_EuroStocks_Admin', 'register_settings'));
add_action('admin_init', array('CE_EuroStocks_Admin', 'hooks'));

add_action('admin_post_ce_eurostocks_run_import', array('CE_EuroStocks_Admin', 'handle_manual_import'));
add_action('admin_post_ce_eurostocks_test_languages', array('CE_EuroStocks_Admin', 'handle_test_languages'));
add_action('admin_post_ce_eurostocks_purge', array('CE_EuroStocks_Admin', 'handle_purge'));
add_action('admin_post_ce_eurostocks_show_last_raw', array('CE_EuroStocks_Admin', 'handle_show_last_raw'));
add_action('admin_post_ce_eurostocks_delete_missing', array('CE_EuroStocks_Admin', 'handle_delete_missing'));
add_action('admin_post_ce_eurostocks_test_image', array('CE_EuroStocks_Admin', 'handle_test_image'));

add_action(CE_EuroStocks_Importer::CRON_HOOK, array('CE_EuroStocks_Importer', 'run_import'));
