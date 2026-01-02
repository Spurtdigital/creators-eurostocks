<?php
if (!defined('ABSPATH')) { exit; }

class CPL_EuroStocks_Importer {

  const CPT = 'cpl_part';
  const OPT_GROUP = 'cpl_engines_settings';
  const OPT_KEY = 'cpl_engines_eurostocks';
  const META_EXT_ID = '_cpl_eurostocks_ad_id';
  const CRON_HOOK = 'cpl_eurostocks_cron_sync';

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
        'name' => 'Onderdelen',
        'singular_name' => 'Onderdeel',
      ),
      'public' => true,
      'has_archive' => true,
      'menu_icon' => 'dashicons-car',
      'supports' => array('title', 'editor', 'thumbnail', 'excerpt'),
      'rewrite' => array('slug' => 'automotoren'),
      'show_in_rest' => true,
    ));

    register_taxonomy('cpl_make', self::CPT, array(
      'labels' => array('name' => 'Merken', 'singular_name' => 'Merk'),
      'public' => true,
      'hierarchical' => false,
      'rewrite' => array('slug' => 'automotoren'),
      'show_in_rest' => true,
    ));

    register_taxonomy('cpl_model', self::CPT, array(
      'labels' => array('name' => 'Modellen', 'singular_name' => 'Model'),
      'public' => true,
      'hierarchical' => false,
      'rewrite' => array('slug' => 'model'),
      'show_in_rest' => true,
    ));

    register_taxonomy('cpl_engine_code', self::CPT, array(
      'labels' => array('name' => 'Motorcodes', 'singular_name' => 'Motorcode'),
      'public' => true,
      'hierarchical' => false,
      'rewrite' => array('slug' => 'motorcode'),
      'show_in_rest' => true,
    ));

    register_taxonomy('cpl_part_type', self::CPT, array(
      'labels' => array('name' => 'Type onderdeel', 'singular_name' => 'Type onderdeel'),
      'public' => true,
      'hierarchical' => false,
      'rewrite' => array('slug' => 'type'),
      'show_in_rest' => true,
    ));
  }

  public static function run_import() {
    $opts = get_option(self::OPT_KEY, array());

    if (empty($opts['username']) || empty($opts['password']) || empty($opts['api_key'])) {
      return array('upserts' => 0, 'skipped' => 0, 'errors' => 1, 'error' => 'API instellingen ontbreken (username/password/api key).');
    }
    if (empty($opts['location_id'])) {
      return array('upserts' => 0, 'skipped' => 0, 'errors' => 1, 'error' => 'Location ID ontbreekt.');
    }
    if (wp_doing_cron() && empty($opts['enabled'])) {
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

    $run_id = (int)get_option('cpl_eurostocks_run_id', 0);
    if (!$run_id) { $run_id = time(); update_option('cpl_eurostocks_run_id', $run_id); }

    $state = get_option('cpl_eurostocks_import_state', array('page' => 1));
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

      $list = CPL_EuroStocks_API::post_json($searchUrl, $opts, $payload);
      if (is_wp_error($list)) {
        return array('upserts' => $upserts, 'skipped' => $skipped, 'errors' => ++$errors, 'error' => $list->get_error_message());
      }

      $results = isset($list['Results']) && is_array($list['Results']) ? $list['Results'] : array();
      if (empty($results)) break;

      foreach ($results as $item) {
        $adId = isset($item['Id']) ? (int)$item['Id'] : 0;
        if (!$adId) { $skipped++; continue; }

        $detailUrl = $productBase . '/api/v1/productdatasupplier/productDetails/' . rawurlencode((string)$opts['location_id']) . '/' . rawurlencode((string)$adId);
        $details = CPL_EuroStocks_API::get_json($detailUrl, $opts);
        if (is_wp_error($details)) { $errors++; continue; }

        if (!self::matches_import_mode($details, $mode)) { $skipped++; continue; }

        $post_id = self::upsert_part_post($details, $opts, $run_id);
        if ($post_id) $upserts++;

        // Time budget check
        if ((microtime(true) - $start) > $max_runtime) {
          update_option('cpl_eurostocks_import_state', array('page' => $page));
          return array('upserts' => $upserts, 'skipped' => $skipped, 'errors' => $errors, 'continue' => 1);
        }
      }

      if (!empty($list['TotalPages']) && $page >= (int)$list['TotalPages']) break;
    }

    delete_option('cpl_eurostocks_import_state');

    if (!empty($opts['mark_missing_out_of_stock'])) {
      self::mark_missing_out_of_stock($run_id);
    }
    return array('upserts' => $upserts, 'skipped' => $skipped, 'errors' => $errors);
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

    $desc_for_parse = CPL_EuroStocks_Helpers::clean_text($desc);

    $raw_make = CPL_EuroStocks_Helpers::extract_labeled_value($desc_for_parse, 'Merk');
    $model = CPL_EuroStocks_Helpers::extract_labeled_value($desc_for_parse, 'Model');
    $engine_code = CPL_EuroStocks_Helpers::extract_labeled_value($desc_for_parse, 'Motorcode');

    $km = CPL_EuroStocks_Helpers::parse_kilometerstand($desc_for_parse);
    $gar = CPL_EuroStocks_Helpers::parse_garantie_maanden($desc_for_parse);
    $fuel = CPL_EuroStocks_Helpers::parse_brandstof($desc_for_parse);
    $price_ex_vat = CPL_EuroStocks_Helpers::parse_prijs_ex_btw_flag($desc_for_parse);

    $brands = CPL_EuroStocks_Helpers::split_brands($raw_make);

    $sub = strtoupper((string)($details['subCategory'] ?? ''));
    $ptype = strtoupper((string)($details['productType'] ?? ''));
    $is_engine = ($sub === 'MOTOR_AND_ACCESSORIES') || (stripos($ptype, 'ENGINE_') !== false);
    $part_type_label = $is_engine ? 'Automotor' : 'Versnellingsbak';

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
    update_post_meta($post_id, '_cpl_last_seen', (int)$run_id);
    // Debug: store full raw details
    update_post_meta($post_id, '_cpl_raw_details', wp_json_encode($details));
    update_option('cpl_eurostocks_last_raw', wp_json_encode($details));

    if (isset($details['stock'])) update_post_meta($post_id, '_cpl_stock', (int)$details['stock']);

    $price = null;
    if (isset($details['priceInfo']) && is_array($details['priceInfo']) && isset($details['priceInfo']['PRICE']) && $details['priceInfo']['PRICE'] !== '') {
      $price = (string)$details['priceInfo']['PRICE'];
    }
    if ($price !== null) update_post_meta($post_id, '_cpl_price', $price);

    if (isset($details['condition'])) update_post_meta($post_id, '_cpl_condition', (string)$details['condition']);
    if (isset($details['delivery'])) update_post_meta($post_id, '_cpl_delivery', (string)$details['delivery']);

    // Parsed from description
    if (!empty($km['raw'])) update_post_meta($post_id, '_cpl_km_raw', (string)$km['raw']);
    if (!is_null($km['value'])) update_post_meta($post_id, '_cpl_km_value', (int)$km['value']);
    if (!empty($gar['raw'])) update_post_meta($post_id, '_cpl_warranty_raw', (string)$gar['raw']);
    if (!is_null($gar['months'])) update_post_meta($post_id, '_cpl_warranty_months', (int)$gar['months']);
    if (!empty($fuel)) update_post_meta($post_id, '_cpl_fuel', (string)$fuel);
    update_post_meta($post_id, '_cpl_price_ex_vat', (int)$price_ex_vat);
    if (isset($details['images']) && is_array($details['images'])) update_post_meta($post_id, '_cpl_images', wp_json_encode($details['images']));

    // Media: download images into WP media library (optional)
    if (!empty($opts['download_images']) && !empty($details['images']) && is_array($details['images'])) {
      self::sync_images($post_id, $details['images']);
    }

    foreach ($brands as $brand) {
      self::set_term($post_id, 'cpl_make', $brand, CPL_EuroStocks_Helpers::brand_slug($brand));
    }
    if ($model) self::set_term($post_id, 'cpl_model', $model);
    if ($engine_code) self::set_term($post_id, 'cpl_engine_code', $engine_code);
    self::set_term($post_id, 'cpl_part_type', $part_type_label);

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
    $old_refs_json = get_post_meta($post_id, '_cpl_image_refs', true);
    $old_refs = array();
    if (is_string($old_refs_json) && $old_refs_json !== '') {
      $tmp = json_decode($old_refs_json, true);
      if (is_array($tmp)) $old_refs = $tmp;
    }

    $errors = array();

    if ($old_refs === $refs) {
      // still ensure featured image exists
      if (!has_post_thumbnail($post_id)) {
        $gallery = get_post_meta($post_id, '_cpl_gallery', true);
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
      update_post_meta($post_id, '_cpl_gallery', wp_json_encode($gallery_ids));
    }

    update_post_meta($post_id, '_cpl_image_refs', wp_json_encode($refs));

    if (!empty($errors)) {
      update_post_meta($post_id, '_cpl_image_errors', wp_json_encode($errors));
      update_option('cpl_eurostocks_last_image_error', wp_json_encode($errors));
    } else {
      delete_post_meta($post_id, '_cpl_image_errors');
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
    foreach ($ids as $id) {
      $ok = wp_delete_post((int)$id, true);
      if ($ok) $deleted_posts++;
    }

    $taxes = array('cpl_make','cpl_model','cpl_engine_code','cpl_part_type');
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

    return array('deleted_posts' => $deleted_posts, 'deleted_terms' => $deleted_terms);
  }
}
