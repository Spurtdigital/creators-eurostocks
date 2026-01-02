# AGENTS.md - Developer Guide

This guide is for AI coding agents working on the CPL Engines EuroStocks Importer WordPress plugin.

## Project Overview

**Plugin Name:** CPL Engines – EuroStocks Importer  
**Type:** WordPress Plugin  
**Language:** PHP 7.4+  
**WordPress Version:** 5.8+  
**Version:** 0.3.7

WordPress plugin that imports auto parts (engines and gearboxes) from the EuroStocks API into a Custom Post Type with taxonomies. Supports scheduled syncing via WP-Cron and manual imports through the admin interface.

## Project Structure

```
.
├── cpl-engines-eurostocks-importer.php  # Main plugin file (entry point)
├── uninstall.php                        # Cleanup on uninstall
└── includes/
    ├── admin.php                        # Admin UI and settings
    ├── api.php                          # API communication layer
    ├── helpers.php                      # Helper functions (parsing, cleaning)
    └── importer.php                     # Core import logic
```

## Build/Lint/Test Commands

### No Build Required
This is a pure PHP WordPress plugin with no build step, compilation, or transpilation needed.

### Development Workflow
- **Install:** Copy plugin to `wp-content/plugins/creators-eurostocks/`
- **Activate:** Via WordPress admin → Plugins
- **Configure:** Settings → CPL Engines Import
- **Test Import:** Use "Start import nu" button in settings page
- **Logs:** Check WordPress debug.log if `WP_DEBUG` is enabled

### Testing
No automated tests exist yet. Manual testing via:
- Admin interface: Settings → CPL Engines Import
- Test buttons: "Test Data API (languages)", "Start import nu"
- Check imported posts: Posts → Onderdelen
- Debug: Check metabox on individual posts for raw API data

### Code Quality
No linter/formatter configured. Follow WordPress coding standards manually.

## Code Style Guidelines

### PHP Style

**File Security:** Every PHP file MUST start with:
```php
<?php
if (!defined('ABSPATH')) { exit; }
```

**Class Names:** PascalCase with `CPL_EuroStocks_` prefix
```php
class CPL_EuroStocks_Importer { }
class CPL_EuroStocks_API { }
```

**Method Names:** snake_case
```php
public static function run_import() { }
private static function upsert_part_post($details, $opts, $run_id) { }
```

**Variable Names:** snake_case
```php
$post_id = 123;
$raw_make = 'Mercedes-Benz';
$desc_for_parse = 'Merk: BMW...';
```

**Constants:** SCREAMING_SNAKE_CASE
```php
const CPT = 'cpl_part';
const META_EXT_ID = '_cpl_eurostocks_ad_id';
```

**Array Syntax:** Use `array()` not `[]` (WordPress standard)
```php
$opts = array('username' => '', 'password' => '');
$results = isset($list['Results']) && is_array($list['Results']) ? $list['Results'] : array();
```

**String Quotes:** Single quotes preferred, double quotes when needed for interpolation
```php
$key = 'cpl_engines_eurostocks';
$msg = "Import batch klaar. Upserts: {$result['upserts']}.";
```

**Indentation:** 2 spaces (not tabs)

**Line Length:** Keep reasonable (~120 chars), but no strict limit

**Braces:** Same line for control structures
```php
if ($code < 200 || $code >= 300) {
  return new WP_Error('cpl_api_http', 'API error');
}
```

### WordPress Conventions

**Sanitization:** Always sanitize user input
```php
$out['username'] = isset($input['username']) ? sanitize_text_field($input['username']) : '';
$out['location_id'] = isset($input['location_id']) ? absint($input['location_id']) : 0;
```

**Escaping:** Always escape output
```php
echo esc_html($fuel);
echo esc_attr($opts['username']);
echo esc_url(admin_url('admin-post.php'));
```

**Nonces:** Use for form submissions
```php
wp_nonce_field('cpl_eurostocks_run_import');
check_admin_referer('cpl_eurostocks_run_import');
```

**Hooks:** Use WordPress action/filter system
```php
add_action('init', array('CPL_EuroStocks_Importer', 'register_cpt_and_taxonomies'));
add_action('admin_menu', array('CPL_EuroStocks_Admin', 'menu'));
```

**Database:** Use WordPress functions, not direct SQL
```php
update_post_meta($post_id, '_cpl_stock', (int)$details['stock']);
$existing = get_posts(array('post_type' => self::CPT, 'meta_key' => self::META_EXT_ID));
```

### Error Handling

**Return WP_Error for API failures:**
```php
if (is_wp_error($res)) return $res;
if ($code < 200 || $code >= 300) {
  return new WP_Error('cpl_api_http', 'API error (' . $code . '): ' . substr($body, 0, 500));
}
```

**Check array keys before access:**
```php
$adId = isset($details['eurostocksAdId']) ? (string)$details['eurostocksAdId'] : '';
$price = isset($details['priceInfo']['PRICE']) ? (string)$details['priceInfo']['PRICE'] : null;
```

**Type casting for safety:**
```php
$adId = (int)$item['Id'];
$title = (string)($productInfo['PRODUCT_TITLE'] ?? 'Default');
```

**Silent failure for metadata:**
```php
// Don't fail import if one field is missing
if (isset($details['stock'])) update_post_meta($post_id, '_cpl_stock', (int)$details['stock']);
```

### Comments

**Avoid obvious comments.** Comment only for:
- Complex regex patterns
- Business logic decisions
- Workarounds for external API quirks

```php
// Some CDNs return 403 unless you send a browser-like User-Agent / Referer.
$response = wp_remote_get($url, array(
  'headers' => array('User-Agent' => 'Mozilla/5.0 ...'),
));
```

### Naming Conventions

**Post Meta Keys:** Prefix with `_cpl_`
```php
_cpl_eurostocks_ad_id
_cpl_stock
_cpl_km_value
_cpl_warranty_months
```

**Options:** Use plugin-specific prefix
```php
cpl_engines_eurostocks
cpl_eurostocks_run_id
cpl_eurostocks_import_state
```

**Taxonomies:** Use `cpl_` prefix
```php
cpl_make, cpl_model, cpl_engine_code, cpl_part_type
```

**Post Types:** Use `cpl_` prefix
```php
cpl_part
```

## Key Architectural Patterns

### Static Classes
All classes use static methods (no instantiation):
```php
CPL_EuroStocks_Importer::run_import();
CPL_EuroStocks_API::get_json($url, $opts);
```

### Helpers for Parsing
`CPL_EuroStocks_Helpers` contains pure functions for text processing:
- `clean_text()` - Strip HTML, normalize whitespace
- `extract_labeled_value()` - Parse "Label: value" from descriptions
- `split_brands()` - Parse comma/slash-separated brands
- `parse_kilometerstand()` - Extract mileage with units

### Upsert Pattern
Find existing post by external ID, update if exists, insert if new:
```php
$existing = get_posts(array('meta_key' => self::META_EXT_ID, 'meta_value' => $adId));
if (!empty($existing)) {
  $postarr['ID'] = $existing[0];
  wp_update_post($postarr);
} else {
  wp_insert_post($postarr);
}
```

### Batched Imports with State
To avoid PHP timeouts, imports run in batches:
- Track `page` in `cpl_eurostocks_import_state` option
- Check runtime, stop early if needed
- Resume from saved page on next run
- Auto-continue via JavaScript redirect if `cpl_continue=1`

## Common Tasks

### Adding a New Parsed Field
1. Add parsing logic to `includes/helpers.php`
2. Call parser in `includes/importer.php` → `upsert_part_post()`
3. Store result with `update_post_meta($post_id, '_cpl_new_field', $value)`
4. Display in metabox: `includes/admin.php` → `render_metabox()`

### Adding a New Setting
1. Add default in `includes/admin.php` → `register_settings()`
2. Add sanitization in `sanitize_settings()`
3. Add form field in `render_settings_page()`
4. Use in `includes/importer.php` via `$opts['new_setting']`

### Debugging API Responses
- Raw API response stored in post meta: `_cpl_raw_details`
- Last response stored globally: `cpl_eurostocks_last_raw` option
- View in metabox when editing a post (click "Toon alle info (raw JSON)")

## Important Notes

- **No direct database queries** - Always use WordPress functions
- **Security first** - Never trust user input, always sanitize & escape
- **Graceful degradation** - Missing API fields shouldn't break imports
- **Memory/time limits** - Respect `max_runtime` setting, process in batches
- **Image downloads** - Can be disabled, stored refs prevent re-downloading
- **Cron scheduling** - Daily sync can be enabled/disabled via settings
