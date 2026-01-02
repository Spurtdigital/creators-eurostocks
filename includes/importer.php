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

  public static function run_import() {
    $is_cron = wp_doing_cron();
    if ($is_cron) self::log('Starting scheduled import via WP-Cron');
    
    $opts = get_option(self::OPT_KEY, array());

    if (empty($opts['username']) || empty($opts['password']) || empty($opts['api_key'])) {
      $err = __('API instellingen ontbreken (username/password/api key).', 'creators-eurostocks');
      self::log($err, 'error');
      return array('upserts' => 0, 'skipped' => 0, 'errors' => 1, 'error' => $err);
    }
    if (empty($opts['location_id'])) {
      $err = __('Location ID ontbreekt.', 'creators-eurostocks');
      self::log($err, 'error');
      return array('upserts' => 0, 'skipped' => 0, 'errors' => 1, 'error' => $err);
    }
    if ($is_cron && empty($opts['enabled'])) {
      self::log('Cron is disabled in settings, skipping import');
      return array('upserts' => 0, 'skipped' => 0, 'errors' => 0);
    }

    $dataBase = rtrim($opts['data_api_base'] ?? 'https://data-api.eurostocks.com', '/');
    $productBase = rtrim($opts['product_data_api_base'] ?? 'https://products-data-api.eurostocks.com', '/');

    $pageSize = (int)($opts['page_size'] ?? 50);
    $maxPages = (int)($opts['max_pages'] ?? 200);
    $language = $opts['language_iso'] ?? 'nl';
    $mode = $opts['import_mode'] ?? 'engines';
    $sortOn = $opts['sort_on'] ?? 'LastUpdatedDate';
    $sortOrder = $opts['sort_order'] ?? 'desc';
    $searchText = $opts['search_text'] ?? '';

    $upserts = 0; $skipped = 0; $errors = 0;

    // Prevent timeouts: process in batches and resume
    $start = microtime(true);
    $max_runtime = (int)($opts['max_runtime'] ?? 20); // seconds
    if ($max_runtime < 5) $max_runtime = 20;
    if (function_exists('set_time_limit')) { @set_time_limit(0); }

    $run_id = (int)get_option('ce_eurostocks_run_id', 0);
    if (!$run_id) { $run_id = time(); update_option('ce_eurostocks_run_id', $run_id); }

    $state = get_option('ce_eurostocks_import_state', array('page' => 1));

    // Restore API totals from previous batch if available
    $total_records = isset($state['total_records']) ? (int)$state['total_records'] : 0;
    $total_pages = isset($state['total_pages']) ? (int)$state['total_pages'] : 0;
    $page_start = isset($state['page']) ? max(1, (int)$state['page']) : 1;
    

    $searchUrl = $dataBase . '/api/v1/Search/list';

    for ($page = $page_start; $page <= $maxPages; $page++) {
      $payload = array(
        'LanguageIsoCode' => $language,
        'PageSize' => $pageSize,
        'PageNumber' => $page,
        'ResultType' => 'Products',
        'SearchText' => $searchText,
        'SortOn' => $sortOn,
        'SortOrder' => $sortOrder,
      );

    $list = CE_EuroStocks_API::post_json($searchUrl, $opts, $payload);
    if (is_wp_error($list)) {
      return array('upserts' => $upserts, 'skipped' => $skipped, 'errors' => ++$errors, 'error' => $list->get_error_message());
    }

    // Track API statistics
    // Debug: Log what we receive from API
    if (defined('WP_DEBUG') && WP_DEBUG) {
      error_log('EUROSTOCKS API Response Keys: ' . implode(', ', array_keys($list)));
      error_log('EUROSTOCKS API Response - TotalResults: ' . ($list['TotalResults'] ?? 'NOT SET') . ', TotalPages: ' . ($list['TotalPages'] ?? 'NOT SET'));
    }
    // Update totals from API response (only if they exist)
    if (isset($list['TotalResults'])) {
      $total_records = (int)$list['TotalResults'];
    }
    if (isset($list['TotalPages'])) {
      $total_pages = (int)$list['TotalPages'];
    }
    
    $results = isset($list['Results']) && is_array($list['Results']) ? $list['Results'] : array();
    if (empty($results)) break;

    foreach ($results as $item) {
      $adId = isset($item['Id']) ? (int)$item['Id'] : 0;
      if (!$adId) { $skipped++; continue; }

      $detailUrl = $productBase . '/api/v1/productdatasupplier/productDetails/' . rawurlencode((string)$opts['location_id']) . '/' . rawurlencode((string)$adId);
      
      // Rate limiting: 100ms delay between API calls to avoid being blocked
      if (!empty($opts['api_rate_limit'])) {
        usleep(100000); // 100ms = 100,000 microseconds
      }
      
      $details = CE_EuroStocks_API::get_json($detailUrl, $opts);
        if (is_wp_error($details)) { $errors++; continue; }

        if (!self::matches_import_mode($details, $mode)) { $skipped++; continue; }

        $post_id = self::upsert_part_post($details, $opts, $run_id);
        if ($post_id) $upserts++;

      // Time budget check
      if ((microtime(true) - $start) > $max_runtime) {
        update_option('ce_eurostocks_import_state', array('page' => $page, 'total_pages' => $total_pages, 'total_records' => $total_records));
        // Get current DB count
        $db_count = wp_count_posts(self::CPT);
        $db_total = ($db_count->publish ?? 0) + ($db_count->draft ?? 0) + ($db_count->pending ?? 0) + ($db_count->private ?? 0);
        return array(
          'upserts' => $upserts, 
          'skipped' => $skipped, 
          'errors' => $errors, 
          'continue' => 1,
          'current_page' => $page,
          'total_pages' => $total_pages,
          'total_records' => $total_records,
          'db_total' => $db_total,
          'progress' => $total_pages > 0 ? round(($page / $total_pages) * 100) : 0
        );
      }
    }

    if (!empty($list['TotalPages']) && $page >= (int)$list['TotalPages']) break;
  }

  delete_option('ce_eurostocks_import_state');

    if (!empty($opts['mark_missing_out_of_stock'])) {
      self::mark_missing_out_of_stock($run_id);
    }
    
    // Get final statistics
    $db_count = wp_count_posts(self::CPT);
    $db_total = ($db_count->publish ?? 0) + ($db_count->draft ?? 0) + ($db_count->pending ?? 0) + ($db_count->private ?? 0);
    
    // $total_records and $total_pages are already set from the loop
    
    return array(
      'upserts' => $upserts, 
      'skipped' => $skipped, 
      'errors' => $errors,
      'total_records' => $total_records,
      'total_pages' => $total_pages,
      'db_total' => $db_total,
      'completed' => 1
    );
  }

  private static function matches_import_mode($details, $mode) {
    $sub = strtoupper((string)($details['subCategory'] ?? ''));
    $type = strtoupper((string)($details['productType'] ?? ''));

    $is_engine = ($sub === 'MOTOR_AND_ACCESSORIES') || (stripos($type, 'ENGINE_') !== false);
    $is_gearbox = (stripos($type, 'GEAR_BOX') !== false) || ($sub === 'TRANSMISSION_DRIVE_AND_ACCESSORIES');

    if ($mode === 'engines') return $is_engine;
    if ($mode === 'gearboxes') return $is_gearbox;
    return ($is_engine || $is_gearbox);
  }

  private static function upsert_part_post($details, $opts, $run_id) {
    $adId = isset($details['eurostocksAdId']) ? (string)$details['eurostocksAdId'] : '';
    if ($adId === '') return 0;

    $productInfo = array();
    if (!empty($details['productInfo']) && is_array($details['productInfo'])) {
      $productInfo = is_array($details['productInfo'][0] ?? null) ? $details['productInfo'][0] : array();
    }

    $title = (string)($productInfo['PRODUCT_TITLE'] ?? ('EuroStocks product ' . $adId));
    $desc  = (string)($productInfo['PRODUCT_SPECIFIC_DESCRIPTION'] ?? '');

    $desc_for_parse = CE_EuroStocks_Helpers::clean_text($desc);

    $raw_make = CE_EuroStocks_Helpers::extract_labeled_value($desc_for_parse, 'Merk');
    $model = CE_EuroStocks_Helpers::extract_labeled_value($desc_for_parse, 'Model');
    $engine_code = CE_EuroStocks_Helpers::extract_labeled_value($desc_for_parse, 'Motorcode');

    $km = CE_EuroStocks_Helpers::parse_kilometerstand($desc_for_parse);
    $gar = CE_EuroStocks_Helpers::parse_garantie_maanden($desc_for_parse);
    $fuel = CE_EuroStocks_Helpers::parse_brandstof($desc_for_parse);
    $price_ex_vat = CE_EuroStocks_Helpers::parse_prijs_ex_btw_flag($desc_for_parse);

    $brands = CE_EuroStocks_Helpers::split_brands($raw_make);

    $sub = strtoupper((string)($details['subCategory'] ?? ''));
    $ptype = strtoupper((string)($details['productType'] ?? ''));
    $is_engine = ($sub === 'MOTOR_AND_ACCESSORIES') || (stripos($ptype, 'ENGINE_') !== false);
    $part_type_label = $is_engine ? __('Automotor', 'creators-eurostocks') : __('Versnellingsbak', 'creators-eurostocks');

    $existing = get_posts(array(
      'post_type' => self::CPT,
      'post_status' => 'any',
      'meta_key' => self::META_EXT_ID,
      'meta_value' => $adId,
      'fields' => 'ids',
      'numberposts' => 1,
    ));

    $postarr = array(
      'post_type' => self::CPT,
      'post_status' => 'publish',
      'post_title' => wp_strip_all_tags($title),
      'post_content' => $desc,
    );

    if (!empty($existing)) {
      $postarr['ID'] = $existing[0];
      $post_id = wp_update_post($postarr, true);
    } else {
      $post_id = wp_insert_post($postarr, true);
    }

    if (is_wp_error($post_id)) return 0;

    update_post_meta($post_id, self::META_EXT_ID, $adId);
    update_post_meta($post_id, '_ce_last_seen', (int)$run_id);
    // Debug: store full raw details
    update_post_meta($post_id, '_ce_raw_details', wp_json_encode($details));
    update_option('ce_eurostocks_last_raw', wp_json_encode($details));

    // Core EuroStocks fields
    if (isset($details['stock'])) update_post_meta($post_id, '_ce_stock', (int)$details['stock']);
    if (isset($details['condition'])) update_post_meta($post_id, '_ce_condition', (string)$details['condition']);
    if (isset($details['delivery'])) update_post_meta($post_id, '_ce_delivery', (string)$details['delivery']);
    if (isset($details['subCategory'])) update_post_meta($post_id, '_ce_subcategory', (string)$details['subCategory']);
    if (isset($details['productType'])) update_post_meta($post_id, '_ce_product_type', (string)$details['productType']);

    // Price info (handle different structures)
    $price = null;
    if (isset($details['priceInfo']) && is_array($details['priceInfo'])) {
      if (isset($details['priceInfo']['PRICE']) && $details['priceInfo']['PRICE'] !== '') {
        $price = (string)$details['priceInfo']['PRICE'];
      }
      // Store all price fields
      if (isset($details['priceInfo']['CURRENCY'])) update_post_meta($post_id, '_ce_price_currency', (string)$details['priceInfo']['CURRENCY']);
      if (isset($details['priceInfo']['VAT_PERCENTAGE'])) update_post_meta($post_id, '_ce_price_vat_percentage', (string)$details['priceInfo']['VAT_PERCENTAGE']);
      if (isset($details['priceInfo']['PRICE_INCL_VAT'])) update_post_meta($post_id, '_ce_price_incl_vat', (string)$details['priceInfo']['PRICE_INCL_VAT']);
    }
    if ($price !== null) update_post_meta($post_id, '_ce_price', $price);

    // Additional product info fields
    if (!empty($productInfo)) {
      if (isset($productInfo['EAN'])) update_post_meta($post_id, '_ce_ean', (string)$productInfo['EAN']);
      if (isset($productInfo['SKU'])) update_post_meta($post_id, '_ce_sku', (string)$productInfo['SKU']);
      if (isset($productInfo['WEIGHT'])) update_post_meta($post_id, '_ce_weight', (string)$productInfo['WEIGHT']);
      if (isset($productInfo['HEIGHT'])) update_post_meta($post_id, '_ce_height', (string)$productInfo['HEIGHT']);
      if (isset($productInfo['WIDTH'])) update_post_meta($post_id, '_ce_width', (string)$productInfo['WIDTH']);
      if (isset($productInfo['LENGTH'])) update_post_meta($post_id, '_ce_length', (string)$productInfo['LENGTH']);
      if (isset($productInfo['COLOR'])) update_post_meta($post_id, '_ce_color', (string)$productInfo['COLOR']);
      if (isset($productInfo['YEAR'])) update_post_meta($post_id, '_ce_year', (string)$productInfo['YEAR']);
      if (isset($productInfo['ENGINE_CAPACITY'])) update_post_meta($post_id, '_ce_engine_capacity', (string)$productInfo['ENGINE_CAPACITY']);
      if (isset($productInfo['POWER_KW'])) update_post_meta($post_id, '_ce_power_kw', (string)$productInfo['POWER_KW']);
      if (isset($productInfo['POWER_HP'])) update_post_meta($post_id, '_ce_power_hp', (string)$productInfo['POWER_HP']);
      if (isset($productInfo['FUEL_TYPE'])) update_post_meta($post_id, '_ce_fuel_type', (string)$productInfo['FUEL_TYPE']);
      if (isset($productInfo['TRANSMISSION'])) update_post_meta($post_id, '_ce_transmission', (string)$productInfo['TRANSMISSION']);
      if (isset($productInfo['GEAR_TYPE'])) update_post_meta($post_id, '_ce_gear_type', (string)$productInfo['GEAR_TYPE']);
      if (isset($productInfo['MANUFACTURER'])) update_post_meta($post_id, '_ce_manufacturer', (string)$productInfo['MANUFACTURER']);
      if (isset($productInfo['PART_NUMBER'])) update_post_meta($post_id, '_ce_part_number', (string)$productInfo['PART_NUMBER']);
      if (isset($productInfo['OEM_NUMBER'])) update_post_meta($post_id, '_ce_oem_number', (string)$productInfo['OEM_NUMBER']);
    }

    // Location/Supplier info
    if (isset($details['location'])) update_post_meta($post_id, '_ce_location', (string)$details['location']);
    if (isset($details['supplierName'])) update_post_meta($post_id, '_ce_supplier_name', (string)$details['supplierName']);
    if (isset($details['supplierId'])) update_post_meta($post_id, '_ce_supplier_id', (string)$details['supplierId']);

    // Dates
    if (isset($details['createdDate'])) update_post_meta($post_id, '_ce_created_date', (string)$details['createdDate']);
    if (isset($details['lastUpdatedDate'])) update_post_meta($post_id, '_ce_last_updated_date', (string)$details['lastUpdatedDate']);

    // Parsed from description
    if (!empty($km['raw'])) update_post_meta($post_id, '_ce_km_raw', (string)$km['raw']);
    if (!is_null($km['value'])) update_post_meta($post_id, '_ce_km_value', (int)$km['value']);
    if (!empty($gar['raw'])) update_post_meta($post_id, '_ce_warranty_raw', (string)$gar['raw']);
    if (!is_null($gar['months'])) update_post_meta($post_id, '_ce_warranty_months', (int)$gar['months']);
    if (!empty($fuel)) update_post_meta($post_id, '_ce_fuel', (string)$fuel);
    update_post_meta($post_id, '_ce_price_ex_vat', (int)$price_ex_vat);
    
    // Store image URLs array for reference
    if (isset($details['images']) && is_array($details['images'])) {
      update_post_meta($post_id, '_ce_images', wp_json_encode($details['images']));
    }

    // Media: download images into WP media library (optional)
    if (!empty($opts['download_images']) && !empty($details['images']) && is_array($details['images'])) {
      self::sync_images($post_id, $details['images']);
    }

    foreach ($brands as $brand) {
      self::set_term($post_id, 'ce_make', $brand, CE_EuroStocks_Helpers::brand_slug($brand));
    }
    if ($model) self::set_term($post_id, 'ce_model', $model);
    if ($engine_code) self::set_term($post_id, 'ce_engine_code', $engine_code);
    self::set_term($post_id, 'ce_part_type', $part_type_label);

    return (int)$post_id;
  }

  private static function set_term($post_id, $taxonomy, $term_name, $forced_slug = '') {
    $term_name = trim((string)$term_name);
    if ($term_name === '') return;

    $exists = term_exists($term_name, $taxonomy);

    if (!$exists) {
      $args = array();
      if ($forced_slug) $args['slug'] = $forced_slug;
      $created = wp_insert_term($term_name, $taxonomy, $args);
      if (is_wp_error($created)) return;
      $term_id = (int)$created['term_id'];
    } else {
      $term_id = is_array($exists) ? (int)$exists['term_id'] : (int)$exists;
    }

    wp_set_object_terms($post_id, array($term_id), $taxonomy, true);
  }

    private static function sync_images($post_id, $images) {
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    // Build list of remote refs (ordered by displaySequence if present)
    $refs = array();
    if (is_array($images)) {
      // normalize to array of ['ref'=>..., 'displaySequence'=>...]
      $tmp = array();
      foreach ($images as $img) {
        if (is_array($img) && !empty($img['ref'])) {
          $tmp[] = array('ref' => (string)$img['ref'], 'seq' => (int)($img['displaySequence'] ?? 0));
        } elseif (is_string($img) && $img !== '') {
          $tmp[] = array('ref' => (string)$img, 'seq' => 0);
        }
      }
      usort($tmp, function($a,$b){ return ($a['seq'] <=> $b['seq']); });
      foreach ($tmp as $t) $refs[] = $t['ref'];
    }
    $refs = array_values(array_filter($refs));
    if (empty($refs)) return;

    // Avoid re-downloading same refs
    $old_refs_json = get_post_meta($post_id, '_ce_image_refs', true);
    $old_refs = array();
    if (is_string($old_refs_json) && $old_refs_json !== '') {
      $tmp = json_decode($old_refs_json, true);
      if (is_array($tmp)) $old_refs = $tmp;
    }

    $errors = array();

    if ($old_refs === $refs) {
      // still ensure featured image exists
      if (!has_post_thumbnail($post_id)) {
        $gallery = get_post_meta($post_id, '_ce_gallery', true);
        $gallery_ids = is_string($gallery) ? json_decode($gallery, true) : array();
        if (is_array($gallery_ids) && !empty($gallery_ids[0])) set_post_thumbnail($post_id, (int)$gallery_ids[0]);
      }
      return;
    }

    $gallery_ids = array();

    // Limit to avoid huge imports
    $refs = array_slice($refs, 0, 12);

    foreach ($refs as $url) {
      $url = trim((string)$url);
      if ($url === '') continue;

      // Some CDNs return 403 unless you send a browser-like User-Agent / Referer.
      $response = wp_remote_get($url, array(
        'timeout' => 30,
        'redirection' => 5,
        'headers' => array(
          'User-Agent' => 'Mozilla/5.0 (compatible; WordPress; CPL EuroStocks Importer)',
          'Referer' => home_url('/'),
          'Accept' => 'image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
        ),
      ));

      if (is_wp_error($response)) {
        $errors[] = array('url' => $url, 'error' => $response->get_error_message());
        continue;
      }

      $code = (int) wp_remote_retrieve_response_code($response);
      $body = wp_remote_retrieve_body($response);
      if ($code < 200 || $code >= 300 || empty($body)) {
        $errors[] = array('url' => $url, 'error' => ($code ? ('HTTP ' . $code) : 'Empty response'));
        continue;
      }

      // Write to temp file
      $tmp = wp_tempnam($url);
      if (!$tmp) {
        $errors[] = array('url' => $url, 'error' => 'Could not create temp file');
        continue;
      }

      $written = file_put_contents($tmp, $body);
      if ($written === false || $written === 0) {
        @unlink($tmp);
        $errors[] = array('url' => $url, 'error' => 'Could not write temp file');
        continue;
      }

      $name = basename(parse_url($url, PHP_URL_PATH));
      if (!$name) $name = 'image-' . time() . '.jpg';

      $file_array = array(
        'name' => sanitize_file_name($name),
        'tmp_name' => $tmp,
      );

      $attachment_id = media_handle_sideload($file_array, $post_id);
      if (is_wp_error($attachment_id)) {
        @unlink($tmp);
        $errors[] = array('url' => $url, 'error' => $attachment_id->get_error_message());
        continue;
      }

      $gallery_ids[] = (int)$attachment_id;
    }

    if (!empty($gallery_ids)) {
      set_post_thumbnail($post_id, (int)$gallery_ids[0]);
      update_post_meta($post_id, '_ce_gallery', wp_json_encode($gallery_ids));
    }

    update_post_meta($post_id, '_ce_image_refs', wp_json_encode($refs));

    if (!empty($errors)) {
      update_post_meta($post_id, '_ce_image_errors', wp_json_encode($errors));
      update_option('ce_eurostocks_last_image_error', wp_json_encode($errors));
    } else {
      delete_post_meta($post_id, '_ce_image_errors');
    }
  }

  public static function purge_all_data() {
    $ids = get_posts(array(
      'post_type' => self::CPT,
      'post_status' => 'any',
      'fields' => 'ids',
      'numberposts' => -1,
    ));

    $deleted_posts = 0;
    $deleted_attachments = 0;
    
    foreach ($ids as $id) {
      // Delete all attachments (images) attached to this post
      $attachments = get_attached_media('', $id);
      foreach ($attachments as $attachment) {
        if (wp_delete_attachment($attachment->ID, true)) {
          $deleted_attachments++;
        }
      }
      
      // Delete the post itself (force delete = true)
      $ok = wp_delete_post((int)$id, true);
      if ($ok) $deleted_posts++;
    }

    $taxes = array('ce_make','ce_model','ce_engine_code','ce_part_type');
    $deleted_terms = 0;

    foreach ($taxes as $tax) {
      $terms = get_terms(array(
        'taxonomy' => $tax,
        'hide_empty' => false,
        'fields' => 'ids',
      ));
      if (is_wp_error($terms)) continue;
      foreach ($terms as $term_id) {
        $r = wp_delete_term((int)$term_id, $tax);
        if (!is_wp_error($r) && $r) $deleted_terms++;
      }
    }

    return array('deleted_posts' => $deleted_posts, 'deleted_terms' => $deleted_terms, 'deleted_attachments' => $deleted_attachments);
  }

  /**
   * Mark products that weren't seen in the last import as out of stock
   * @param int $run_id The run ID from the last import
   */
  public static function mark_missing_out_of_stock($run_id) {
    if (!$run_id) return;

    // Find all posts that were NOT updated in this run
    $missing = get_posts(array(
      'post_type' => self::CPT,
      'post_status' => 'any',
      'fields' => 'ids',
      'numberposts' => -1,
      'meta_query' => array(
        'relation' => 'OR',
        array(
          'key' => '_ce_last_seen',
          'value' => $run_id,
          'compare' => '!=',
          'type' => 'NUMERIC',
        ),
        array(
          'key' => '_ce_last_seen',
          'compare' => 'NOT EXISTS',
        ),
      ),
    ));

    $marked = 0;
    foreach ($missing as $post_id) {
      update_post_meta($post_id, '_ce_stock', 0);
      $marked++;
    }

    return $marked;
  }

  /**
   * Delete products that weren't seen in the last import
   * @param int $run_id The run ID from the last import
   * @param bool $delete_attachments Whether to also delete attached images
   * @return array Statistics about deletion
   */
  public static function delete_missing_posts($run_id, $delete_attachments = false) {
    if (!$run_id) return array('deleted_posts' => 0, 'deleted_attachments' => 0);

    // Find all posts that were NOT updated in this run
    $missing = get_posts(array(
      'post_type' => self::CPT,
      'post_status' => 'any',
      'fields' => 'ids',
      'numberposts' => -1,
      'meta_query' => array(
        'relation' => 'OR',
        array(
          'key' => '_ce_last_seen',
          'value' => $run_id,
          'compare' => '!=',
          'type' => 'NUMERIC',
        ),
        array(
          'key' => '_ce_last_seen',
          'compare' => 'NOT EXISTS',
        ),
      ),
    ));

    $deleted_posts = 0;
    $deleted_attachments = 0;

    foreach ($missing as $post_id) {
      if ($delete_attachments) {
        // Delete all attachments (images) attached to this post
        $attachments = get_attached_media('', $post_id);
        foreach ($attachments as $attachment) {
          if (wp_delete_attachment($attachment->ID, true)) {
            $deleted_attachments++;
          }
        }
      }
      
      // Delete the post itself (force delete = true)
      if (wp_delete_post($post_id, true)) {
        $deleted_posts++;
      }
    }

    return array('deleted_posts' => $deleted_posts, 'deleted_attachments' => $deleted_attachments);
  }
}
