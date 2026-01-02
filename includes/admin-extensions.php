<?php
/**
 * Additional admin features for Creators EuroStocks Plugin
 * Version: 0.6.0
 * 
 * This file contains improvements added in version 0.6.0:
 * - Location ID validation
 * - Bulk actions for stock management
 * - Admin post list filters
 * - Dashboard widget
 * - CSV export
 */

if (!defined('ABSPATH')) { exit; }

class CE_EuroStocks_Admin_Extensions {

  /**
   * Test Location ID validity
   */
  public static function handle_test_location() {
    if (!current_user_can('manage_options')) wp_die('Geen toegang.');
    check_admin_referer('ce_eurostocks_test_location');

    $opts = get_option(CE_EuroStocks_Importer::OPT_KEY, array());
    if (empty($opts['location_id'])) {
      wp_redirect(CE_EuroStocks_Helpers::admin_url_with_msg(array('ce_err' => rawurlencode('Location ID ontbreekt.'))));
      exit;
    }

    $productBase = rtrim($opts['product_data_api_base'] ?? 'https://products-data-api.eurostocks.com', '/');
    
    // Try to fetch any product (use a dummy ID to test authentication + location)
    $testUrl = $productBase . '/api/v1/productdatasupplier/productDetails/' . rawurlencode((string)$opts['location_id']) . '/1';
    
    $res = CE_EuroStocks_API::get_json($testUrl, $opts);
    if (is_wp_error($res)) {
      $err = $res->get_error_message();
      // If it's 404, location ID is valid but product doesn't exist (which is OK)
      if (strpos($err, '404') !== false) {
        wp_redirect(CE_EuroStocks_Helpers::admin_url_with_msg(array('ce_msg' => rawurlencode('Location ID is geldig! (Product ID 1 niet gevonden, maar authenticatie werkt)'))));
        exit;
      }
      wp_redirect(CE_EuroStocks_Helpers::admin_url_with_msg(array('ce_err' => rawurlencode('Location ID test mislukt: ' . $err))));
      exit;
    }

    wp_redirect(CE_EuroStocks_Helpers::admin_url_with_msg(array('ce_msg' => rawurlencode('Location ID test OK! Product data opgehaald.'))));
    exit;
  }

  /**
   * Register bulk actions in post list
   */
  public static function register_bulk_actions($bulk_actions) {
    $bulk_actions['ce_mark_out_of_stock'] = 'Markeer als niet op voorraad';
    $bulk_actions['ce_mark_in_stock'] = 'Markeer als op voorraad (1 stuks)';
    return $bulk_actions;
  }

  /**
   * Handle bulk actions
   */
  public static function handle_bulk_actions($redirect_to, $doaction, $post_ids) {
    if ($doaction === 'ce_mark_out_of_stock') {
      foreach ($post_ids as $post_id) {
        update_post_meta($post_id, '_ce_stock', 0);
      }
      $redirect_to = add_query_arg('bulk_marked_out_of_stock', count($post_ids), $redirect_to);
    } elseif ($doaction === 'ce_mark_in_stock') {
      foreach ($post_ids as $post_id) {
        update_post_meta($post_id, '_ce_stock', 1);
      }
      $redirect_to = add_query_arg('bulk_marked_in_stock', count($post_ids), $redirect_to);
    }
    return $redirect_to;
  }

  /**
   * Show bulk action notices
   */
  public static function bulk_action_notices() {
    if (!empty($_REQUEST['bulk_marked_out_of_stock'])) {
      $count = intval($_REQUEST['bulk_marked_out_of_stock']);
      printf('<div class="notice notice-success is-dismissible"><p>%d producten gemarkeerd als niet op voorraad.</p></div>', $count);
    }
    if (!empty($_REQUEST['bulk_marked_in_stock'])) {
      $count = intval($_REQUEST['bulk_marked_in_stock']);
      printf('<div class="notice notice-success is-dismissible"><p>%d producten gemarkeerd als op voorraad.</p></div>', $count);
    }
  }

  /**
   * Add filter dropdowns to post list
   */
  public static function add_admin_filters($post_type) {
    if ($post_type !== CE_EuroStocks_Importer::CPT) return;
    
    // Stock filter
    $stock_filter = isset($_GET['stock_status']) ? $_GET['stock_status'] : '';
    ?>
    <select name="stock_status">
      <option value="">Alle voorraad</option>
      <option value="in_stock" <?php selected($stock_filter, 'in_stock'); ?>>Op voorraad</option>
      <option value="out_of_stock" <?php selected($stock_filter, 'out_of_stock'); ?>>Niet op voorraad</option>
    </select>
    <?php
  }

  /**
   * Filter admin query based on filters
   */
  public static function filter_admin_query($query) {
    global $pagenow, $typenow;
    
    if ($pagenow !== 'edit.php' || $typenow !== CE_EuroStocks_Importer::CPT) return;
    
    if (!empty($_GET['stock_status'])) {
      $meta_query = array();
      
      if ($_GET['stock_status'] === 'in_stock') {
        $meta_query[] = array(
          'key' => '_ce_stock',
          'value' => 0,
          'compare' => '>',
          'type' => 'NUMERIC'
        );
      } elseif ($_GET['stock_status'] === 'out_of_stock') {
        $meta_query[] = array(
          'key' => '_ce_stock',
          'value' => 0,
          'compare' => '<=',
          'type' => 'NUMERIC'
        );
      }
      
      if (!empty($meta_query)) {
        $query->set('meta_query', $meta_query);
      }
    }
  }

  /**
   * Add dashboard widget
   */
  public static function add_dashboard_widget() {
    wp_add_dashboard_widget(
      'ce_eurostocks_status',
      'EuroStocks Import Status',
      array(__CLASS__, 'render_dashboard_widget')
    );
  }

  /**
   * Render dashboard widget
   */
  public static function render_dashboard_widget() {
    $opts = get_option(CE_EuroStocks_Importer::OPT_KEY, array());
    $enabled = !empty($opts['enabled']);
    $run_id = get_option('ce_eurostocks_run_id', 0);
    $state = get_option('ce_eurostocks_import_state', array());
    
    $total_parts = wp_count_posts(CE_EuroStocks_Importer::CPT);
    $in_stock = get_posts(array(
      'post_type' => CE_EuroStocks_Importer::CPT,
      'meta_query' => array(
        array('key' => '_ce_stock', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC')
      ),
      'fields' => 'ids',
      'numberposts' => -1
    ));
    
    echo '<div class="ce-dashboard-widget" style="line-height:2;">';
    echo '<p><strong>Status:</strong> ' . ($enabled ? '<span style="color:green;">✅ Actief</span>' : '<span style="color:red;">❌ Uitgeschakeld</span>') . '</p>';
    echo '<p><strong>Totaal onderdelen:</strong> ' . ($total_parts->publish ?? 0) . '</p>';
    echo '<p><strong>Op voorraad:</strong> ' . count($in_stock) . '</p>';
    
    if ($run_id) {
      echo '<p><strong>Laatste import:</strong> ' . date('d-m-Y H:i', $run_id) . '</p>';
    }
    
    if (!empty($state['page'])) {
      echo '<p style="color:#d63638;"><strong>⚠ Import bezig:</strong> Pagina ' . $state['page'] . '</p>';
    }
    
    if ($enabled) {
      $next_cron = wp_next_scheduled(CE_EuroStocks_Importer::CRON_HOOK);
      if ($next_cron) {
        $time_until = human_time_diff(time(), $next_cron);
        echo '<p><strong>Volgende sync:</strong> Over ' . $time_until . '</p>';
      }
    }
    
    echo '<p style="margin-top:12px;"><a href="' . admin_url('options-general.php?page=ce-import') . '" class="button button-primary">Instellingen →</a></p>';
    echo '</div>';
  }

  /**
   * Export products to CSV
   */
  public static function handle_export_csv() {
    if (!current_user_can('manage_options')) wp_die('Geen toegang.');
    check_admin_referer('ce_eurostocks_export_csv');

    $posts = get_posts(array(
      'post_type' => CE_EuroStocks_Importer::CPT,
      'post_status' => 'publish',
      'numberposts' => -1,
      'orderby' => 'date',
      'order' => 'DESC'
    ));

    if (empty($posts)) {
      wp_redirect(CE_EuroStocks_Helpers::admin_url_with_msg(array('ce_err' => rawurlencode('Geen producten gevonden om te exporteren.'))));
      exit;
    }

    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=eurostocks-export-' . date('Y-m-d-His') . '.csv');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Open output stream
    $output = fopen('php://output', 'w');

    // BOM for Excel UTF-8 support
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // CSV Headers
    $headers = array(
      'ID',
      'Titel',
      'Prijs (EUR)',
      'Voorraad',
      'Merk',
      'Model',
      'Motorcode',
      'Kilometerstand',
      'Brandstof',
      'Vermogen (kW)',
      'Vermogen (pk)',
      'Garantie (maanden)',
      'EuroStocks ID',
      'SKU',
      'EAN',
      'OEM Nummer',
      'Conditie',
      'Bouwjaar',
      'Gewicht',
      'URL'
    );
    fputcsv($output, $headers, ';');

    // Data rows
    foreach ($posts as $post) {
      $merken = wp_get_post_terms($post->ID, 'ce_make', array('fields' => 'names'));
      $modellen = wp_get_post_terms($post->ID, 'ce_model', array('fields' => 'names'));
      $motorcodes = wp_get_post_terms($post->ID, 'ce_engine_code', array('fields' => 'names'));

      $row = array(
        $post->ID,
        $post->post_title,
        get_post_meta($post->ID, '_ce_price', true),
        get_post_meta($post->ID, '_ce_stock', true),
        !empty($merken) ? implode(', ', $merken) : '',
        !empty($modellen) ? implode(', ', $modellen) : '',
        !empty($motorcodes) ? implode(', ', $motorcodes) : '',
        get_post_meta($post->ID, '_ce_km_value', true),
        get_post_meta($post->ID, '_ce_fuel', true),
        get_post_meta($post->ID, '_ce_power_kw', true),
        get_post_meta($post->ID, '_ce_power_hp', true),
        get_post_meta($post->ID, '_ce_warranty_months', true),
        get_post_meta($post->ID, '_ce_eurostocks_ad_id', true),
        get_post_meta($post->ID, '_ce_sku', true),
        get_post_meta($post->ID, '_ce_ean', true),
        get_post_meta($post->ID, '_ce_oem_number', true),
        get_post_meta($post->ID, '_ce_condition', true),
        get_post_meta($post->ID, '_ce_year', true),
        get_post_meta($post->ID, '_ce_weight', true),
        get_permalink($post->ID)
      );
      fputcsv($output, $row, ';');
    }

    fclose($output);
    exit;
  }

}
