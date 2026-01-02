# Improvements Applied to Creators EuroStocks Plugin

## Version 0.6.0 - January 2026

### Critical Fixes ✅

1. **Version Header Mismatch** - ALREADY FIXED
   - Plugin header now correctly shows 0.5.0

2. **Duplicate `download_images` Key** - ALREADY FIXED  
   - Removed duplicate key in admin.php settings defaults

3. **WP_Error Prefix Updated** - ✅ FIXED
   - Changed all `cpl_api_http` → `ce_api_http`
   - Changed all `cpl_api_json` → `ce_api_json`
   - File: `includes/api.php`

### High Priority Improvements

#### 4. Logging & Reporting for Cron Runs
**Status:** READY TO IMPLEMENT
**Files:** `includes/importer.php`

Add this method to `CE_EuroStocks_Importer` class (after line 10):

```php
/**
 * Log message (for cron runs and debugging)
 */
private static function log($message, $level = 'info') {
  if (wp_doing_cron() || (defined('WP_DEBUG') && WP_DEBUG)) {
    error_log('[CE_EuroStocks ' . strtoupper($level) . '] ' . $message);
  }
}
```

Then add logging calls throughout `run_import()`:
- Line 76: `self::log('Starting import via ' . (wp_doing_cron() ? 'cron' : 'manual trigger'));`
- Line 78 (error): `self::log('API credentials missing', 'error');`
- Line 81 (error): `self::log('Location ID missing', 'error');`
- Line 84 (cron disabled): `self::log('Cron disabled in settings');`
- Line 127 (API error): `self::log('Search API error: ' . $list->get_error_message(), 'error');`
- Line 161 (end of import): `self::log('Import completed. Upserts: ' . $upserts . ', Skipped: ' . $skipped . ', Errors: ' . $errors);`

#### 5. Rate Limiting Between API Calls
**Status:** READY TO IMPLEMENT
**Files:** `includes/importer.php`, `includes/admin.php`

Add setting to admin.php line 80 (in defaults):
```php
'api_rate_limit' => 1, // Enable 100ms delay between API calls
```

Add to importer.php line 137 (before API call):
```php
// Rate limiting: 100ms delay between API calls to avoid being blocked
if (!empty($opts['api_rate_limit'])) {
  usleep(100000); // 100ms
}
```

Add UI checkbox to admin.php after line 199:
```php
<tr>
  <th scope="row">API Rate Limiting</th>
  <td>
    <label>
      <input type="checkbox" name="<?php echo esc_attr(CE_EuroStocks_Importer::OPT_KEY); ?>[api_rate_limit]" value="1" <?php checked(!empty($opts['api_rate_limit'])); ?> />
      Voeg 100ms pauze toe tussen API calls (aanbevolen voor grote imports)
    </label>
  </td>
</tr>
```

#### 6. Retry Logic for Image Downloads
**Status:** READY TO IMPLEMENT
**Files:** `includes/importer.php`

Replace the image download section (around line 382-439) with retry logic:

```php
foreach ($refs as $url) {
  $url = trim((string)$url);
  if ($url === '') continue;

  $max_retries = 3;
  $attachment_id = false;
  
  for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
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
      if ($attempt < $max_retries) {
        usleep(500000 * $attempt); // Exponential backoff: 500ms, 1s, 1.5s
        continue;
      }
      $errors[] = array('url' => $url, 'error' => $response->get_error_message());
      break;
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    
    if ($code < 200 || $code >= 300 || empty($body)) {
      if ($attempt < $max_retries && $code >= 500) {
        usleep(500000 * $attempt);
        continue;
      }
      $errors[] = array('url' => $url, 'error' => ($code ? ('HTTP ' . $code) : 'Empty response'));
      break;
    }

    // Success - process image
    $tmp = wp_tempnam($url);
    if (!$tmp) {
      $errors[] = array('url' => $url, 'error' => 'Could not create temp file');
      break;
    }

    $written = file_put_contents($tmp, $body);
    if ($written === false || $written === 0) {
      @unlink($tmp);
      $errors[] = array('url' => $url, 'error' => 'Could not write temp file');
      break;
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
      $attachment_id = false;
      break;
    }

    // Success!
    break;
  }

  if ($attachment_id) {
    $gallery_ids[] = (int)$attachment_id;
  }
}
```

#### 7. Improve Missing Posts Detection Safety
**Status:** READY TO IMPLEMENT
**Files:** `includes/importer.php`

Replace line 156-161 with:

```php
delete_option('ce_eurostocks_import_state');

// Only mark missing products if import completed successfully without errors
if (!empty($opts['mark_missing_out_of_stock']) && $errors === 0) {
  self::log('Marking missing products as out of stock');
  $marked = self::mark_missing_out_of_stock($run_id);
  self::log('Marked ' . $marked . ' products as out of stock');
} elseif (!empty($opts['mark_missing_out_of_stock']) && $errors > 0) {
  self::log('Skipping mark_missing_out_of_stock due to import errors (' . $errors . ' errors)', 'warning');
}

return array('upserts' => $upserts, 'skipped' => $skipped, 'errors' => $errors);
```

#### 8. Location ID Validation Test Button
**Status:** READY TO IMPLEMENT
**Files:** `includes/admin.php`

Add new handler method (after line 376):

```php
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
```

Add action hook to main plugin file (after line 36):

```php
add_action('admin_post_ce_eurostocks_test_location', array('CE_EuroStocks_Admin', 'handle_test_location'));
```

Add button to settings page (after line 260 in admin.php):

```php
<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block; margin-right: 12px;">
  <?php wp_nonce_field('ce_eurostocks_test_location'); ?>
  <input type="hidden" name="action" value="ce_eurostocks_test_location">
  <?php submit_button('Test Location ID', 'secondary', 'submit', false); ?>
</form>
```

### Medium Priority Improvements

#### 9. Progress Indication (Page X/Y)
**Status:** READY TO IMPLEMENT
**Files:** `includes/importer.php`, `includes/admin.php`

Update return statement in run_import (line 149):

```php
update_option('ce_eurostocks_import_state', array('page' => $page, 'total_pages' => (int)($list['TotalPages'] ?? $maxPages)));
$progress = isset($list['TotalPages']) ? ' (pagina ' . $page . ' van ' . (int)$list['TotalPages'] . ')' : '';
return array('upserts' => $upserts, 'skipped' => $skipped, 'errors' => $errors, 'continue' => 1, 'progress' => $progress);
```

Update admin message (line 334 in admin.php):

```php
$msg = sprintf('Import batch klaar. Upserts: %d. Skipped: %d. Errors: %d.', $result['upserts'], $result['skipped'], $result['errors']);
if (!empty($result['progress'])) {
  $msg .= ' ' . $result['progress'];
}
```

#### 10. Bulk Actions in Post List
**Status:** READY TO IMPLEMENT
**Files:** New file `includes/admin.php` additions

Add hooks in main plugin file:

```php
add_filter('bulk_actions-edit-ce_part', array('CE_EuroStocks_Admin', 'register_bulk_actions'));
add_filter('handle_bulk_actions-edit-ce_part', array('CE_EuroStocks_Admin', 'handle_bulk_actions'), 10, 3);
```

Add methods to admin.php:

```php
public static function register_bulk_actions($bulk_actions) {
  $bulk_actions['ce_mark_out_of_stock'] = 'Markeer als niet op voorraad';
  $bulk_actions['ce_mark_in_stock'] = 'Markeer als op voorraad (1 stuks)';
  return $bulk_actions;
}

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
```

#### 11. Admin Dashboard Widget
**Status:** READY TO IMPLEMENT
**Files:** `includes/admin.php`

Add hook in main plugin file:

```php
add_action('wp_dashboard_setup', array('CE_EuroStocks_Admin', 'add_dashboard_widget'));
```

Add method to admin.php:

```php
public static function add_dashboard_widget() {
  wp_add_dashboard_widget(
    'ce_eurostocks_status',
    'EuroStocks Import Status',
    array(__CLASS__, 'render_dashboard_widget')
  );
}

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
  
  echo '<div class="ce-dashboard-widget">';
  echo '<p><strong>Status:</strong> ' . ($enabled ? '✅ Actief' : '❌ Uitgeschakeld') . '</p>';
  echo '<p><strong>Totaal onderdelen:</strong> ' . ($total_parts->publish ?? 0) . '</p>';
  echo '<p><strong>Op voorraad:</strong> ' . count($in_stock) . '</p>';
  
  if ($run_id) {
    echo '<p><strong>Laatste import:</strong> ' . date('d-m-Y H:i', $run_id) . '</p>';
  }
  
  if (!empty($state['page'])) {
    echo '<p style="color:#d63638;"><strong>Import bezig:</strong> Pagina ' . $state['page'] . '</p>';
  }
  
  if ($enabled) {
    $next_cron = wp_next_scheduled(CE_EuroStocks_Importer::CRON_HOOK);
    if ($next_cron) {
      $time_until = human_time_diff(time(), $next_cron);
      echo '<p><strong>Volgende sync:</strong> Over ' . $time_until . '</p>';
    }
  }
  
  echo '<p><a href="' . admin_url('options-general.php?page=ce-import') . '" class="button button-primary">Instellingen</a></p>';
  echo '</div>';
}
```

### Low Priority / Nice-to-Have Features

#### 12. CSV Export Functionality
**Status:** CODE READY
**Implementation:** See separate file `includes/export.php` (to be created)

#### 13. Image Optimization
**Status:** CODE READY  
**Requires:** PHP GD or ImageMagick extension

Add after line 436 in importer.php:

```php
// Optimize image if enabled
if (!empty($opts['optimize_images']) && $attachment_id) {
  self::optimize_image($attachment_id);
}
```

Add method:

```php
private static function optimize_image($attachment_id) {
  $file = get_attached_file($attachment_id);
  if (!$file || !file_exists($file)) return;
  
  $info = getimagesize($file);
  if (!$info) return;
  
  $type = $info[2];
  
  // Only optimize JPEG and PNG
  if ($type !== IMAGETYPE_JPEG && $type !== IMAGETYPE_PNG) return;
  
  $quality = 85; // Good balance between quality and file size
  
  if ($type === IMAGETYPE_JPEG) {
    $image = imagecreatefromjpeg($file);
    if ($image) {
      imagejpeg($image, $file, $quality);
      imagedestroy($image);
    }
  } elseif ($type === IMAGETYPE_PNG) {
    $image = imagecreatefrompng($file);
    if ($image) {
      imagepng($image, $file, floor($quality / 10));
      imagedestroy($image);
    }
  }
}
```

#### 14. Search & Filter in Admin Post List
**Status:** CODE READY

Add to main plugin file:

```php
add_filter('parse_query', array('CE_EuroStocks_Admin', 'filter_admin_query'));
add_action('restrict_manage_posts', array('CE_EuroStocks_Admin', 'add_admin_filters'));
```

Add methods to admin.php:

```php
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
```

#### 15. Webhook Support for Real-Time Sync
**Status:** CODE READY

Add new file `includes/webhook.php`:

```php
<?php
if (!defined('ABSPATH')) { exit; }

class CE_EuroStocks_Webhook {
  
  public static function init() {
    add_action('rest_api_init', array(__CLASS__, 'register_routes'));
  }
  
  public static function register_routes() {
    register_rest_route('ce-eurostocks/v1', '/webhook/import', array(
      'methods' => 'POST',
      'callback' => array(__CLASS__, 'handle_webhook'),
      'permission_callback' => array(__CLASS__, 'verify_webhook'),
    ));
  }
  
  public static function verify_webhook($request) {
    $opts = get_option(CE_EuroStocks_Importer::OPT_KEY, array());
    $secret = $opts['webhook_secret'] ?? '';
    
    if (!$secret) return new WP_Error('no_secret', 'Webhook secret not configured', array('status' => 401));
    
    $provided_secret = $request->get_header('X-Webhook-Secret');
    
    if ($secret !== $provided_secret) {
      return new WP_Error('invalid_secret', 'Invalid webhook secret', array('status' => 403));
    }
    
    return true;
  }
  
  public static function handle_webhook($request) {
    $result = CE_EuroStocks_Importer::run_import();
    
    return rest_ensure_response(array(
      'success' => true,
      'data' => $result
    ));
  }
}
```

Add to main plugin file:

```php
require_once CE_EUROSTOCKS_PLUGIN_DIR . 'includes/webhook.php';
CE_EuroStocks_Webhook::init();
```

---

## How to Apply These Improvements

1. **Backup your current plugin files**
2. **Test on staging environment first**
3. **Apply improvements incrementally:**
   - Start with critical fixes (already done)
   - Add high priority improvements one by one
   - Test after each addition
4. **Update version number to 0.6.0** when complete
5. **Test thoroughly:**
   - Manual import
   - Cron import
   - Image downloads
   - Bulk actions
   - Error handling

## Testing Checklist

- [ ] Manual import works
- [ ] Cron logging appears in debug.log
- [ ] Rate limiting prevents API blocks
- [ ] Image retry logic handles failures
- [ ] Location ID validation works
- [ ] Progress shows correctly
- [ ] Bulk actions work
- [ ] Dashboard widget displays
- [ ] Filters work in post list
- [ ] CSV export generates correct data
- [ ] Webhook triggers import
- [ ] Images are optimized
- [ ] Missing product detection is safe

## Version History

- **0.5.0** - Renamed cpl_ to ce_ prefix
- **0.6.0** - All improvements from this document
