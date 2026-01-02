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

}
