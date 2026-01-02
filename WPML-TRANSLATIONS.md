# WPML & Vertalingen - Status & Implementatie

## Huidige Situatie (Versie 0.5.0)

### ❌ Wat NIET Werkt

1. **Geen WordPress i18n Functies**
   - Geen `__()`, `_e()`, `esc_html__()` gebruikt in code
   - Alle strings zijn hardcoded in Nederlands
   - Geen text domain gedefinieerd

2. **Geen Translation Files**
   - Geen `.pot` template bestand
   - Geen `languages/` directory
   - Geen `.po` / `.mo` bestanden

3. **Geen WPML Integratie**
   - Custom Post Type niet geregistreerd als vertaalbaar
   - Taxonomieën niet geregistreerd als vertaalbaar
   - Geen WPML configuratie

### ✅ Wat WEL Werkt

1. **API Taal Selectie**
   - Plugin haalt data op in gekozen taal via `language_iso` setting
   - Standaard: `nl` (Nederlands)
   - EuroStocks API ondersteunt: `nl`, `de`, `en`, `fr`, `es`, `it`, etc.
   - Product data komt binnen in de juiste taal

2. **Data Opslag**
   - Producttitels en beschrijvingen worden opgeslagen zoals ontvangen van API
   - Als je `language_iso = en` instelt, krijg je Engelse content

## Probleem met Huidige Aanpak

**Scenario:** Website heeft WPML geïnstalleerd met NL + EN

1. Plugin importeert in Nederlands (default)
2. Product posts worden aangemaakt in NL
3. WPML ziet deze posts als "originele NL versie"
4. Er worden GEEN automatische vertalingen aangemaakt
5. Je moet handmatig elke post vertalen in WPML

## Oplossing 1: WordPress i18n (Plugin Interface Vertalen)

Vertaal de **admin interface** van de plugin zelf.

### Implementatie

#### Stap 1: Text Domain Toevoegen

**cpl-engines-eurostocks-importer.php:**
```php
/**
 * Plugin Name: Creators EuroStocks Importer
 * Description: Import/sync automotoren en/of versnellingsbakken vanuit EuroStocks
 * Version: 0.5.0
 * Author: Creators
 * Text Domain: creators-eurostocks
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Load translations
add_action('plugins_loaded', 'ce_eurostocks_load_textdomain');
function ce_eurostocks_load_textdomain() {
  load_plugin_textdomain('creators-eurostocks', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
```

#### Stap 2: Strings Vervangen

**Voorbeelden in includes/admin.php:**

```php
// VOOR:
'labels' => array(
  'name' => 'Onderdelen',
  'singular_name' => 'Onderdeel',
),

// NA:
'labels' => array(
  'name' => __('Onderdelen', 'creators-eurostocks'),
  'singular_name' => __('Onderdeel', 'creators-eurostocks'),
),
```

```php
// VOOR:
echo '<p><strong>Voorraad:</strong><br>';

// NA:
echo '<p><strong>' . __('Voorraad:', 'creators-eurostocks') . '</strong><br>';
```

```php
// VOOR:
wp_redirect(CE_EuroStocks_Helpers::admin_url_with_msg(array('ce_msg' => rawurlencode('Test OK.'))));

// NA:
wp_redirect(CE_EuroStocks_Helpers::admin_url_with_msg(array('ce_msg' => rawurlencode(__('Test OK.', 'creators-eurostocks')))));
```

#### Stap 3: POT Bestand Genereren

```bash
# Met WP-CLI
wp i18n make-pot . languages/creators-eurostocks.pot --domain=creators-eurostocks

# Of met Poedit
# Open Poedit > Nieuw vanuit broncode > Selecteer plugin directory
```

#### Stap 4: Vertalingen Maken

```bash
# Maak bijvoorbeeld Duitse vertaling
cp languages/creators-eurostocks.pot languages/creators-eurostocks-de_DE.po

# Vertaal in Poedit
# Compileer naar .mo bestand
```

## Oplossing 2: WPML Integratie (Product Content Vertalen)

Maak producten vertaalbaar in WPML.

### Implementatie

#### Stap 1: Custom Post Type Registreren als Vertaalbaar

**includes/importer.php:**
```php
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

  // WPML: Register post type as translatable
  if (function_exists('wpml_register_single_type')) {
    wpml_register_single_type(self::CPT);
  }

  // Taxonomies...
  register_taxonomy('ce_make', self::CPT, array(
    'labels' => array('name' => __('Merken', 'creators-eurostocks'), 'singular_name' => __('Merk', 'creators-eurostocks')),
    'public' => true,
    'hierarchical' => false,
    'rewrite' => array('slug' => 'automotoren'),
    'show_in_rest' => true,
  ));

  // WPML: Register taxonomy as translatable
  if (function_exists('wpml_register_taxonomy')) {
    wpml_register_taxonomy('ce_make');
    wpml_register_taxonomy('ce_model');
    wpml_register_taxonomy('ce_engine_code');
    wpml_register_taxonomy('ce_part_type');
  }
}
```

#### Stap 2: WPML Config Bestand

**wpml-config.xml:** (maak dit in plugin root)
```xml
<wpml-config>
  <custom-types>
    <custom-type translate="1">ce_part</custom-type>
  </custom-types>
  
  <taxonomies>
    <taxonomy translate="1">ce_make</taxonomy>
    <taxonomy translate="1">ce_model</taxonomy>
    <taxonomy translate="1">ce_engine_code</taxonomy>
    <taxonomy translate="1">ce_part_type</taxonomy>
  </taxonomies>
  
  <custom-fields>
    <!-- Translate these fields -->
    <custom-field action="translate">_ce_fuel</custom-field>
    <custom-field action="translate">_ce_condition</custom-field>
    <custom-field action="translate">_ce_delivery</custom-field>
    
    <!-- Copy these fields (don't translate) -->
    <custom-field action="copy">_ce_eurostocks_ad_id</custom-field>
    <custom-field action="copy">_ce_price</custom-field>
    <custom-field action="copy">_ce_stock</custom-field>
    <custom-field action="copy">_ce_km_value</custom-field>
    <custom-field action="copy">_ce_warranty_months</custom-field>
    <custom-field action="copy">_ce_power_kw</custom-field>
    <custom-field action="copy">_ce_power_hp</custom-field>
    <custom-field action="copy">_ce_engine_capacity</custom-field>
    <custom-field action="copy">_ce_year</custom-field>
    <custom-field action="copy">_ce_weight</custom-field>
    <custom-field action="copy">_ce_ean</custom-field>
    <custom-field action="copy">_ce_sku</custom-field>
    <custom-field action="copy">_ce_part_number</custom-field>
    <custom-field action="copy">_ce_oem_number</custom-field>
    <custom-field action="copy">_ce_gallery</custom-field>
    
    <!-- Ignore these fields -->
    <custom-field action="ignore">_ce_raw_details</custom-field>
    <custom-field action="ignore">_ce_last_seen</custom-field>
  </custom-fields>
</wpml-config>
```

## Oplossing 3: Multi-Taal Import (GEAVANCEERD)

Importeer hetzelfde product in meerdere talen via EuroStocks API.

### Concept

1. Plugin stelt in: "Importeer in: NL, EN, DE"
2. Voor elk product:
   - Haal NL versie op → Maak NL post
   - Haal EN versie op → Maak EN post en link aan NL via WPML
   - Haal DE versie op → Maak DE post en link aan NL via WPML

### Implementatie

**includes/admin.php - Settings:**
```php
'default' => array(
  // ...
  'import_languages' => array('nl'), // Multi-select: nl, en, de, fr
  'default_language' => 'nl',
)
```

**includes/importer.php - Multi-Language Import:**
```php
private static function upsert_part_post($details, $opts, $run_id) {
  $adId = isset($details['eurostocksAdId']) ? (string)$details['eurostocksAdId'] : '';
  if ($adId === '') return 0;

  $import_languages = $opts['import_languages'] ?? array('nl');
  $default_language = $opts['default_language'] ?? 'nl';
  
  $original_post_id = 0;
  
  foreach ($import_languages as $lang) {
    // Fetch product details in this language
    $lang_details = self::fetch_product_in_language($adId, $lang, $opts);
    if (is_wp_error($lang_details)) continue;
    
    // Create/update post
    $post_id = self::create_or_update_post($lang_details, $lang, $run_id);
    
    if ($lang === $default_language) {
      $original_post_id = $post_id;
    } else if ($original_post_id && function_exists('wpml_add_translation')) {
      // Link translation to original
      wpml_add_translation($original_post_id, $post_id, $lang);
    }
  }
  
  return $original_post_id;
}

private static function fetch_product_in_language($adId, $lang, $opts) {
  $productBase = rtrim($opts['product_data_api_base'] ?? 'https://products-data-api.eurostocks.com', '/');
  $detailUrl = $productBase . '/api/v1/productdatasupplier/productDetails/' . rawurlencode((string)$opts['location_id']) . '/' . rawurlencode((string)$adId);
  
  // Override language for this request
  $opts_lang = $opts;
  $opts_lang['language_iso'] = $lang;
  
  return CE_EuroStocks_API::get_json($detailUrl, $opts_lang);
}
```

## Oplossing 4: Polylang Ondersteuning

Alternatief voor WPML (gratis).

### Implementatie

Vergelijkbaar met WPML, maar gebruik Polylang functies:

```php
// Check if Polylang is active
if (function_exists('pll_register_string')) {
  // Register CPT
  add_filter('pll_get_post_types', function($post_types) {
    $post_types['ce_part'] = 'ce_part';
    return $post_types;
  });
  
  // Register taxonomies
  add_filter('pll_get_taxonomies', function($taxonomies) {
    $taxonomies['ce_make'] = 'ce_make';
    $taxonomies['ce_model'] = 'ce_model';
    $taxonomies['ce_engine_code'] = 'ce_engine_code';
    $taxonomies['ce_part_type'] = 'ce_part_type';
    return $taxonomies;
  });
}
```

## Aanbeveling

### Voor Jouw Situatie:

**Beste aanpak hangt af van je use case:**

### Optie A: Alleen Nederlandse Site
✅ **Niets doen** - huidige setup werkt prima

### Optie B: Plugin Interface Vertalen (Admin)
✅ **Implementeer Oplossing 1** - i18n functies toevoegen
- Admin interface vertaalbaar voor developers
- Relatief weinig werk (~2-3 uur)
- Product content blijft Nederlands

### Optie C: Content Vertaalbaar Maken (WPML/Polylang)
✅ **Implementeer Oplossing 2** - WPML config
- Posts handmatig vertalen in WPML
- Veel werk voor redacteuren
- Flexibel maar arbeidsintensief

### Optie D: Automatische Multi-Taal Import
✅ **Implementeer Oplossing 3** - Multi-language import
- Volledig automatisch
- Vereist WPML/Polylang
- Meeste ontwikkeltijd (~8-12 uur)
- **BESTE OPTIE** voor serieuze multi-language sites

## Implementatie Prioriteit

1. **Snel & Simpel** → Oplossing 2 (WPML config file maken)
2. **Professioneel** → Oplossing 1 + 2 (i18n + WPML)
3. **Enterprise** → Oplossing 1 + 2 + 3 (volledig geautomatiseerd)

## Wil Je Dat Ik Het Implementeer?

Zeg het maar welke oplossing je wilt en ik implementeer het voor je! 🚀
