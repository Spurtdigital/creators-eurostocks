<?php
if (!defined('ABSPATH')) { exit; }

class CPL_EuroStocks_Helpers {

  public static function clean_text($text) {
    if (!is_string($text)) return '';
    $t = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = wp_strip_all_tags($t, true);
    $t = str_replace(array("\xC2\xA0", "&nbsp;"), ' ', $t);
    $t = preg_replace('/\s+/u', ' ', $t);
    return trim($t);
  }

  public static function extract_labeled_value($text, $label) {
    if (!is_string($text) || $text === '') return '';

    $labels = array('Merk','Model','Kilometerstand','Motorcode','Garantie','Prijs','Brand','Make','EngineCode');
    $others = array();
    foreach ($labels as $l) {
      if (strcasecmp($l, $label) !== 0) $others[] = preg_quote($l, '/');
    }
    $stop = implode('|', $others);

    $pattern = '/'.preg_quote($label,'/').'\s*:\s*(.*?)(?=(\r?\n|(?:'.$stop.')\s*:|$))/is';
    if (preg_match($pattern, $text, $m)) {
      return trim($m[1]);
    }
    return '';
  }

  public static function split_brands($raw_make) {
    $brands = array();
    if (!is_string($raw_make) || trim($raw_make) === '') return $brands;

    // Clean + stop at glued labels
    $raw_make = self::clean_text($raw_make);
    $raw_make = preg_split('/Model:|Kilometerstand:|Motorcode:|Garantie:|Prijs:/i', $raw_make)[0];

    $parts = preg_split('/,|\\/|&/u', $raw_make);
    foreach ($parts as $b) {
      $b = self::clean_text($b);
      if ($b === '') continue;
      if (strtolower($b) === 'nbsp') continue;
      if (strtoupper($b) === 'VAG') continue;

      // Guard against garbage/html remnants
      if (preg_match('/<|style=|font-family|calibri|sans-serif|\bp>\b/i', $b)) continue;
      if (!preg_match('/^[A-Za-z0-9 .\-\'’]{2,60}$/u', $b)) continue;

      $brands[] = $b;
    }
    return array_values(array_unique($brands));
  }

  public static function brand_slug($brand) {
    $b = strtolower(trim((string)$brand));
    $map = array(
      'mercedes-benz' => 'mercedes',
      'mercedes benz' => 'mercedes',
      'land rover' => 'landrover',
      'range rover' => 'rangerover',
      'vw' => 'volkswagen',
    );
    if (isset($map[$b])) return $map[$b];
    return sanitize_title($brand);
  }

  public static function admin_url_with_msg($args = array()) {
    $base = admin_url('options-general.php?page=cpl-engines-import');
    return add_query_arg($args, $base);
  }

  
  public static function parse_kilometerstand($desc_for_parse) {
    $raw = self::extract_labeled_value($desc_for_parse, 'Kilometerstand');
    if ($raw === '') return array('raw' => '', 'value' => null);
    $raw_clean = self::clean_text($raw);
    // common formats: 61.816 KM, 0 KM (Na revisie), Meerdere op voorraad
    if (preg_match('/([0-9][0-9\.,\s]*)\s*KM/i', $raw_clean, $m)) {
      $n = preg_replace('/[^0-9]/', '', $m[1]);
      $val = $n !== '' ? (int)$n : null;
      return array('raw' => $raw_clean, 'value' => $val);
    }
    return array('raw' => $raw_clean, 'value' => null);
  }

  public static function parse_garantie_maanden($desc_for_parse) {
    $raw = self::extract_labeled_value($desc_for_parse, 'Garantie');
    if ($raw === '') return array('raw' => '', 'months' => null);
    $raw_clean = self::clean_text($raw);
    if (preg_match('/(\d{1,2})\s*(maand|maanden)/i', $raw_clean, $m)) {
      return array('raw' => $raw_clean, 'months' => (int)$m[1]);
    }
    if (preg_match('/(\d{1,2})\s*(jaar|jaren)/i', $raw_clean, $m)) {
      return array('raw' => $raw_clean, 'months' => ((int)$m[1]) * 12);
    }
    return array('raw' => $raw_clean, 'months' => null);
  }

  public static function parse_brandstof($desc_for_parse) {
    // Sometimes "Brandstof:" or "Fuel:"
    $raw = self::extract_labeled_value($desc_for_parse, 'Brandstof');
    if ($raw === '') $raw = self::extract_labeled_value($desc_for_parse, 'Fuel');
    if ($raw === '') return '';
    $raw_clean = self::clean_text($raw);
    // normalize
    $map = array(
      'benzine' => 'Benzine',
      'petrol' => 'Benzine',
      'diesel' => 'Diesel',
      'hybrid' => 'Hybride',
      'hybride' => 'Hybride',
      'elektrisch' => 'Elektrisch',
      'electric' => 'Elektrisch',
      'lpg' => 'LPG',
      'cng' => 'CNG',
    );
    $k = strtolower($raw_clean);
    if (isset($map[$k])) return $map[$k];
    return $raw_clean;
  }

  public static function parse_prijs_ex_btw_flag($desc_for_parse) {
    $t = strtolower(self::clean_text($desc_for_parse));
    // "Prijs is EX BTW" appears frequently
    if (strpos($t, 'ex btw') !== false || strpos($t, 'ex. btw') !== false) return 1;
    return 0;
  }

  // Template helper functions

  /**
   * Get a custom field value safely
   * @param int|null $post_id Post ID (null = current post)
   * @param string $field_name Meta key
   * @param mixed $default Default value if not found
   * @return mixed
   */
  public static function get_field($field_name, $post_id = null, $default = '') {
    if (!$post_id) $post_id = get_the_ID();
    if (!$post_id) return $default;
    
    $value = get_post_meta($post_id, $field_name, true);
    return ($value !== '' && $value !== false) ? $value : $default;
  }

  /**
   * Get formatted price
   * @param int|null $post_id
   * @return string Formatted price or empty string
   */
  public static function get_price($post_id = null) {
    $price = self::get_field('_cpl_price', $post_id);
    if (!$price) return '';
    
    $currency = self::get_field('_cpl_price_currency', $post_id, 'EUR');
    $symbol = ($currency === 'EUR') ? '€' : $currency . ' ';
    
    // Format number
    $formatted = number_format((float)$price, 2, ',', '.');
    
    return $symbol . ' ' . $formatted;
  }

  /**
   * Get gallery image IDs
   * @param int|null $post_id
   * @return array Array of attachment IDs
   */
  public static function get_gallery($post_id = null) {
    $gallery_json = self::get_field('_cpl_gallery', $post_id);
    if (!$gallery_json) return array();
    
    $gallery = json_decode($gallery_json, true);
    return is_array($gallery) ? $gallery : array();
  }

  /**
   * Display gallery images
   * @param int|null $post_id
   * @param string $size Image size (thumbnail, medium, large, full)
   * @param array $attrs Additional HTML attributes
   */
  public static function display_gallery($post_id = null, $size = 'medium', $attrs = array()) {
    $gallery = self::get_gallery($post_id);
    if (empty($gallery)) return;
    
    $class = isset($attrs['class']) ? esc_attr($attrs['class']) : 'cpl-gallery';
    
    echo '<div class="' . $class . '">';
    foreach ($gallery as $attachment_id) {
      $img = wp_get_attachment_image($attachment_id, $size, false, $attrs);
      if ($img) {
        $url = wp_get_attachment_url($attachment_id);
        echo '<a href="' . esc_url($url) . '" class="cpl-gallery-item">' . $img . '</a>';
      }
    }
    echo '</div>';
  }

  /**
   * Get stock status
   * @param int|null $post_id
   * @return string 'in_stock', 'out_of_stock', or 'unknown'
   */
  public static function get_stock_status($post_id = null) {
    $stock = self::get_field('_cpl_stock', $post_id);
    if ($stock === '') return 'unknown';
    
    return ((int)$stock > 0) ? 'in_stock' : 'out_of_stock';
  }

  /**
   * Check if product is in stock
   * @param int|null $post_id
   * @return bool
   */
  public static function is_in_stock($post_id = null) {
    return self::get_stock_status($post_id) === 'in_stock';
  }

  /**
   * Get formatted mileage
   * @param int|null $post_id
   * @return string Formatted mileage or empty string
   */
  public static function get_mileage($post_id = null) {
    $km = self::get_field('_cpl_km_value', $post_id);
    if (!$km) return '';
    
    return number_format((int)$km, 0, ',', '.') . ' km';
  }

  /**
   * Get formatted warranty
   * @param int|null $post_id
   * @return string Warranty description or empty string
   */
  public static function get_warranty($post_id = null) {
    $months = self::get_field('_cpl_warranty_months', $post_id);
    if (!$months) return '';
    
    $months = (int)$months;
    if ($months >= 12 && $months % 12 === 0) {
      $years = $months / 12;
      return $years . ' ' . ($years === 1 ? 'jaar' : 'jaar') . ' garantie';
    }
    
    return $months . ' ' . ($months === 1 ? 'maand' : 'maanden') . ' garantie';
  }

  /**
   * Get all part specifications as array
   * @param int|null $post_id
   * @return array Key-value pairs of specifications
   */
  public static function get_specifications($post_id = null) {
    if (!$post_id) $post_id = get_the_ID();
    if (!$post_id) return array();
    
    $specs = array();
    
    // Basic info
    if ($merk = self::get_field('_cpl_manufacturer', $post_id)) $specs['Merk'] = $merk;
    if ($year = self::get_field('_cpl_year', $post_id)) $specs['Bouwjaar'] = $year;
    if ($fuel = self::get_field('_cpl_fuel', $post_id)) $specs['Brandstof'] = $fuel;
    
    // Engine specs
    if ($capacity = self::get_field('_cpl_engine_capacity', $post_id)) $specs['Motorinhoud'] = $capacity;
    if ($power_kw = self::get_field('_cpl_power_kw', $post_id)) {
      $specs['Vermogen'] = $power_kw . ' kW';
      if ($power_hp = self::get_field('_cpl_power_hp', $post_id)) {
        $specs['Vermogen'] .= ' (' . $power_hp . ' pk)';
      }
    }
    
    // Transmission
    if ($trans = self::get_field('_cpl_transmission', $post_id)) $specs['Transmissie'] = $trans;
    if ($gear = self::get_field('_cpl_gear_type', $post_id)) $specs['Versnellingsbak type'] = $gear;
    
    // Mileage & warranty
    if ($km = self::get_mileage($post_id)) $specs['Kilometerstand'] = $km;
    if ($warranty = self::get_warranty($post_id)) $specs['Garantie'] = $warranty;
    
    // Part numbers
    if ($part_nr = self::get_field('_cpl_part_number', $post_id)) $specs['Onderdeelnummer'] = $part_nr;
    if ($oem = self::get_field('_cpl_oem_number', $post_id)) $specs['OEM nummer'] = $oem;
    
    // Dimensions & weight
    if ($weight = self::get_field('_cpl_weight', $post_id)) $specs['Gewicht'] = $weight;
    $dims = array();
    if ($length = self::get_field('_cpl_length', $post_id)) $dims[] = $length;
    if ($width = self::get_field('_cpl_width', $post_id)) $dims[] = $width;
    if ($height = self::get_field('_cpl_height', $post_id)) $dims[] = $height;
    if (!empty($dims)) $specs['Afmetingen (LxBxH)'] = implode(' x ', $dims);
    
    // Stock & delivery
    $stock = self::get_field('_cpl_stock', $post_id);
    if ($stock !== '') $specs['Voorraad'] = ($stock > 0) ? 'Op voorraad' : 'Niet op voorraad';
    if ($delivery = self::get_field('_cpl_delivery', $post_id)) $specs['Levering'] = $delivery;
    if ($condition = self::get_field('_cpl_condition', $post_id)) $specs['Conditie'] = $condition;
    
    return $specs;
  }

}
