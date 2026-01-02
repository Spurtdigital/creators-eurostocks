<?php
if (!defined('ABSPATH')) { exit; }

class CE_EuroStocks_API {

  public static function headers($opts, $is_json) {
    $h = array(
      'UserName' => (string)($opts['username'] ?? ''),
      'Password' => (string)($opts['password'] ?? ''),
      'APIKey'   => (string)($opts['api_key'] ?? ''),
      'Accept'   => 'application/json',
    );
    if ($is_json) $h['Content-Type'] = 'application/json';
    return $h;
  }

  public static function get_json($url, $opts) {
    $res = wp_remote_get($url, array(
      'timeout' => 30,
      'headers' => self::headers($opts, false),
    ));
    if (is_wp_error($res)) return $res;

    $code = wp_remote_retrieve_response_code($res);
    $body = wp_remote_retrieve_body($res);

    if ($code < 200 || $code >= 300) {
      return new WP_Error('cpl_api_http', 'API error (' . $code . '): ' . substr((string)$body, 0, 500));
    }

    $json = json_decode($body, true);
    if (!is_array($json)) return new WP_Error('cpl_api_json', 'JSON parse error: ' . substr((string)$body, 0, 300));
    return $json;
  }

  public static function post_json($url, $opts, $payload) {
    $res = wp_remote_post($url, array(
      'timeout' => 30,
      'headers' => self::headers($opts, true),
      'body'    => wp_json_encode($payload),
    ));
    if (is_wp_error($res)) return $res;

    $code = wp_remote_retrieve_response_code($res);
    $body = wp_remote_retrieve_body($res);

    if ($code < 200 || $code >= 300) {
      return new WP_Error('cpl_api_http', 'API error (' . $code . '): ' . substr((string)$body, 0, 800));
    }

    $json = json_decode($body, true);
    if (!is_array($json)) return new WP_Error('cpl_api_json', 'JSON parse error: ' . substr((string)$body, 0, 300));
    return $json;
  }
}
