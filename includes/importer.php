<?php
if (!defined('ABSPATH')) { exit; }

class CE_EuroStocks_Importer {

  const CPT = 'ce_part';
  const OPT_GROUP = 'ce_settings';
  const OPT_KEY = 'ce_eurostocks';
  const META_EXT_ID = '_ce_eurostocks_ad_id';
  const CRON_HOOK = 'ce_eurostocks_cron_sync';

  /**
   * Log message (for cron runs and debugging)
   */
  private static function log($message, $level = 'info') {
    if (wp_doing_cron() || (defined('WP_DEBUG') && WP_DEBUG)) {
      error_log('[CE_EuroStocks ' . strtoupper($level) . '] ' . $message);
    }
  }

  public static function activate() {
    self::register_cpt_and_taxonomies();
    flush_rewrite_rules();
    if (!wp_next_scheduled(self::CRON_HOOK)) {
      wp_schedule_event(time() + 120, 'daily', self::CRON_HOOK);
    }
  }

  public static function deactivate() {
    $ts = wp_next_scheduled(self::CRON_HOOK);
    if ($ts) wp_unschedule_event($ts, self::CRON_HOOK);
    flush_rewrite_rules();
  }

  public static function register_cpt_and_taxonomies() {

    register_post_type(self::CPT, array(
      'labels' => array(
        'name' => __('Onderdelen', 'creators-eurostocks'),
        'singular_name' => __('Onderdeel', 'creators-eurostocks'),
      ),
      'public' => true,
      'has_archive' => true,
      'menu_icon' => 'dashicons-car',
      'supports' => array('title', 'editor', 'thumbnail', 'excerpt'),
      'rewrite' => array('slug' => 'automotoren'),
      'show_in_rest' => true,
    ));

    register_taxonomy('ce_make', self::CPT, array(
      'labels' => array('name' => __('Merken', 'creators-eurostocks'), 'singular_name' => __('Merk', 'creators-eurostocks')),
      'public' => true,
      'hierarchical' => false,
      'rewrite' => array('slug' => 'automotoren'),
      'show_in_rest' => true,
    ));

    register_taxonomy('ce_model', self::CPT, array(
      'labels' => array('name' => __('Modellen', 'creators-eurostocks'), 'singular_name' => __('Model', 'creators-eurostocks')),
      'public' => true,
      'hierarchical' => false,
      'rewrite' => array('slug' => 'model'),
      'show_in_rest' => true,
    ));

    register_taxonomy('ce_engine_code', self::CPT, array(
      'labels' => array('name' => __('Motorcodes', 'creators-eurostocks'), 'singular_name' => __('Motorcode', 'creators-eurostocks')),
      'public' => true,
      'hierarchical' => false,
      'rewrite' => array('slug' => 'motorcode'),
      'show_in_rest' => true,
    ));

    register_taxonomy('ce_part_type', self::CPT, array(
      'labels' => array('name' => __('Type onderdeel', 'creators-eurostocks'), 'singular_name' => __('Type onderdeel', 'creators-eurostocks')),
      'public' => true,
      'hierarchical' => false,
      'rewrite' => array('slug' => 'type'),
      'show_in_rest' => true,
    ));

    // WPML: Register CPT as translatable
    if (function_exists('do_action')) {
      do_action('wpml_register_single_type', self::CPT);
    }

    // WPML: Register taxonomies as translatable
    if (function_exists('do_action')) {
      do_action('wpml_register_taxonomy', 'ce_make');
      do_action('wpml_register_taxonomy', 'ce_model');
      do_action('wpml_register_taxonomy', 'ce_engine_code');
      do_action('wpml_register_taxonomy', 'ce_part_type');
    }

    // Polylang: Register CPT and taxonomies
    if (function_exists('add_filter')) {
      add_filter('pll_get_post_types', function($post_types) {
        $post_types[self::CPT] = self::CPT;
        return $post_types;
      });
      
      add_filter('pll_get_taxonomies', function($taxonomies) {
        $taxonomies['ce_make'] = 'ce_make';
        $taxonomies['ce_model'] = 'ce_model';
        $taxonomies['ce_engine_code'] = 'ce_engine_code';
        $taxonomies['ce_part_type'] = 'ce_part_type';
        return $taxonomies;
      });
    }
  }
