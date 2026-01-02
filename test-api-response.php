<?php
/**
 * Debug script om te zien wat de EuroStocks API precies teruggeeft
 * Bezoek: https://cplengines.ddev.site/wp-content/plugins/creators-eurostocks/test-api-response.php
 */

require_once('../../../wp-load.php');

if (!current_user_can('manage_options')) {
    die('Geen toegang');
}

require_once('includes/api.php');

$opts = get_option('ce_eurostocks', array());
$dataBase = rtrim($opts['data_api_base'] ?? 'https://data-api.eurostocks.com', '/');
$searchUrl = $dataBase . '/api/v1/Search/list';

$payload = array(
  'LanguageIsoCode' => $opts['language_iso'] ?? 'nl',
  'PageSize' => 10,
  'PageNumber' => 1,
  'ResultType' => 'Products',
  'SearchText' => '',
  'SortOn' => 'LastUpdatedDate',
  'SortOrder' => 'desc',
);

echo "<html><head><title>EuroStocks API Debug</title>";
echo "<style>body{font-family:monospace;padding:20px;} h2{color:#135e96;} pre{background:#f5f5f5;padding:15px;overflow:auto;} .key{color:green;font-weight:bold;}</style>";
echo "</head><body>";

echo "<h1>🔍 EuroStocks API Response Debug</h1>";

$list = CE_EuroStocks_API::post_json($searchUrl, $opts, $payload);

if (is_wp_error($list)) {
    echo "<h2 style='color:red;'>❌ API Error</h2>";
    echo "<pre>" . esc_html($list->get_error_message()) . "</pre>";
} else {
    echo "<h2>✅ API Response Ontvangen</h2>";
    
    echo "<h3>Alle Keys in Response:</h3>";
    echo "<ul>";
    foreach (array_keys($list) as $key) {
        echo "<li class='key'>" . esc_html($key) . " <span style='color:#666;font-weight:normal;'>(" . gettype($list[$key]) . ")</span></li>";
    }
    echo "</ul>";
    
    echo "<h3>Belangrijke Velden:</h3>";
    echo "<table border='1' cellpadding='8' style='border-collapse:collapse;'>";
    echo "<tr><th>Veld</th><th>Waarde</th><th>Type</th></tr>";
    
    $important_fields = array('TotalRecords', 'TotalPages', 'totalRecords', 'totalPages', 'Total', 'Count', 'PageCount', 'RecordCount');
    
    foreach ($important_fields as $field) {
        $exists = isset($list[$field]) ? '✅' : '❌';
        $value = isset($list[$field]) ? $list[$field] : 'N/A';
        $type = isset($list[$field]) ? gettype($list[$field]) : '-';
        echo "<tr><td>{$exists} {$field}</td><td><strong>" . esc_html($value) . "</strong></td><td>{$type}</td></tr>";
    }
    echo "</table>";
    
    if (isset($list['Results'])) {
        echo "<h3>Results Array:</h3>";
        echo "<p>Aantal items: <strong>" . count($list['Results']) . "</strong></p>";
    }
    
    echo "<h3>Volledige Raw Response:</h3>";
    echo "<pre>" . esc_html(print_r($list, true)) . "</pre>";
}

echo "</body></html>";
