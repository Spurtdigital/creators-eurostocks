<?php
if (!defined('ABSPATH')) { exit; }

class CPL_EuroStocks_Admin {

  public static function hooks() {
    add_action('add_meta_boxes', array(__CLASS__, 'register_metabox'));
  }

  public static function register_metabox() {
    add_meta_box('cpl_eurostocks_info', 'EuroStocks Info', array(__CLASS__, 'render_info_metabox'), CPL_EuroStocks_Importer::CPT, 'normal', 'high');
    add_meta_box('cpl_eurostocks_specs', 'Specificaties', array(__CLASS__, 'render_specs_metabox'), CPL_EuroStocks_Importer::CPT, 'normal', 'high');
    add_meta_box('cpl_eurostocks_price', 'Prijs & Voorraad', array(__CLASS__, 'render_price_metabox'), CPL_EuroStocks_Importer::CPT, 'side', 'high');
    add_meta_box('cpl_eurostocks_gallery', 'Afbeeldingen', array(__CLASS__, 'render_gallery_metabox'), CPL_EuroStocks_Importer::CPT, 'side', 'default');
    add_meta_box('cpl_eurostocks_debug', 'Debug & Diagnostics', array(__CLASS__, 'render_metabox'), CPL_EuroStocks_Importer::CPT, 'normal', 'low');
  }

  public static function render_metabox($post) {
    $raw = get_post_meta($post->ID, '_cpl_raw_details', true);
    $km_raw = get_post_meta($post->ID, '_cpl_km_raw', true);
    $km_val = get_post_meta($post->ID, '_cpl_km_value', true);
    $war_raw = get_post_meta($post->ID, '_cpl_warranty_raw', true);
    $war_m = get_post_meta($post->ID, '_cpl_warranty_months', true);
    $fuel = get_post_meta($post->ID, '_cpl_fuel', true);
    $price_ex = get_post_meta($post->ID, '_cpl_price_ex_vat', true);
    $img_err = get_post_meta($post->ID, '_cpl_image_errors', true);

    echo '<p><strong>Snelle velden</strong></p>';
    echo '<ul style="margin:0 0 12px 16px; list-style:disc;">';
    if ($km_raw !== '') echo '<li>Kilometerstand: ' . esc_html($km_raw) . ($km_val !== '' ? ' (' . esc_html($km_val) . ')' : '') . '</li>';
    if ($war_raw !== '') echo '<li>Garantie: ' . esc_html($war_raw) . ($war_m !== '' ? ' (' . esc_html($war_m) . ' maanden)' : '') . '</li>';
    if ($fuel !== '') echo '<li>Brandstof: ' . esc_html($fuel) . '</li>';
    echo '<li>Prijs ex BTW (uit tekst): ' . ($price_ex ? 'Ja' : 'Nee/onbekend') . '</li>';
    echo '</ul>';

    if (!empty($img_err)) {
      echo '<p><strong>Afbeeldingen errors</strong></p>';
      echo '<textarea readonly style="width:100%; min-height:120px; font-family:monospace;">' . esc_textarea($img_err) . '</textarea>';
    }

    echo '<details><summary style="cursor:pointer;"><strong>Toon alle info (raw JSON)</strong></summary>';
    echo '<p style="margin-top:8px;">Handig om te zien welke velden we nog missen of kunnen mappen.</p>';
    echo '<textarea readonly style="width:100%; min-height:280px; font-family:monospace;">' . esc_textarea($raw) . '</textarea>';
    echo '</details>';
  }


  public static function menu() {
    add_options_page(
      'Creators EuroStocks Import',
      'Creators EuroStocks Import',
      'manage_options',
      'cpl-engines-import',
      array(__CLASS__, 'render_settings_page')
    );
  }

  public static function register_settings() {
    register_setting(CPL_EuroStocks_Importer::OPT_GROUP, CPL_EuroStocks_Importer::OPT_KEY, array(
      'type' => 'array',
      'sanitize_callback' => array(__CLASS__, 'sanitize_settings'),
      'default' => array(
        'enabled' => 0,
        'username' => '',
        'password' => '',
        'api_key' => '',
        'data_api_base' => 'https://data-api.eurostocks.com',
        'product_data_api_base' => 'https://products-data-api.eurostocks.com',
        'location_id' => 0,
        'language_iso' => 'nl',
        'import_mode' => 'engines',
        'page_size' => 50,
        'max_pages' => 200,
        'sort_on' => 'LastUpdatedDate',
        'sort_order' => 'desc',
        'search_text' => '',
        'download_images' => 1,
        'mark_missing_out_of_stock' => 0,
        'confirm_delete_missing' => 0,
        'download_images' => 1,
        'max_runtime' => 20,
      ),
    ));
  }

  public static function sanitize_settings($input) {
    $out = array();

    $out['enabled'] = !empty($input['enabled']) ? 1 : 0;
    $out['download_images'] = !empty($input['download_images']) ? 1 : 0;
    $out['username'] = isset($input['username']) ? sanitize_text_field($input['username']) : '';
    $out['password'] = isset($input['password']) ? sanitize_text_field($input['password']) : '';
    $out['api_key'] = isset($input['api_key']) ? sanitize_text_field($input['api_key']) : '';

    $out['data_api_base'] = isset($input['data_api_base']) ? esc_url_raw($input['data_api_base']) : 'https://data-api.eurostocks.com';
    $out['product_data_api_base'] = isset($input['product_data_api_base']) ? esc_url_raw($input['product_data_api_base']) : 'https://products-data-api.eurostocks.com';

    $out['location_id'] = isset($input['location_id']) ? absint($input['location_id']) : 0;

    $lang = isset($input['language_iso']) ? strtolower(sanitize_text_field($input['language_iso'])) : 'nl';
    $out['language_iso'] = preg_match('/^[a-z]{2}$/', $lang) ? $lang : 'nl';

    $mode = isset($input['import_mode']) ? sanitize_text_field($input['import_mode']) : 'engines';
    $out['import_mode'] = in_array($mode, array('engines','gearboxes','both'), true) ? $mode : 'engines';

    $out['page_size'] = isset($input['page_size']) ? max(1, min(200, absint($input['page_size']))) : 50;
    $out['max_pages'] = isset($input['max_pages']) ? max(1, min(1000, absint($input['max_pages']))) : 200;

    $out['sort_on'] = isset($input['sort_on']) ? sanitize_text_field($input['sort_on']) : 'LastUpdatedDate';
    $out['sort_order'] = isset($input['sort_order']) ? strtolower(sanitize_text_field($input['sort_order'])) : 'desc';
    if (!in_array($out['sort_order'], array('asc','desc'), true)) $out['sort_order'] = 'desc';

    $out['search_text'] = isset($input['search_text']) ? sanitize_text_field($input['search_text']) : '';

    return $out;
  }

  public static function render_settings_page() {
    if (!current_user_can('manage_options')) return;

    $opts = get_option(CPL_EuroStocks_Importer::OPT_KEY, array());
    $enabled = !empty($opts['enabled']);
    ?>
    <div class="wrap">
      <h1>CPL Engines – EuroStocks Import</h1>

      <form method="post" action="options.php">
        <?php settings_fields(CPL_EuroStocks_Importer::OPT_GROUP); ?>
        <table class="form-table" role="presentation">
          <tr>
            <th scope="row">Sync inschakelen</th>
            <td>
              <label>
                <input type="checkbox" name="<?php echo esc_attr(CPL_EuroStocks_Importer::OPT_KEY); ?>[enabled]" value="1" <?php checked($enabled); ?> />
                Dagelijkse sync via WP-Cron
              </label>
            </td>
          </tr>

          <tr><th colspan="2"><h2>Authenticatie</h2></th></tr>

          <tr>
            <th scope="row"><label>Username</label></th>
            <td><input type="text" class="regular-text" name="<?php echo esc_attr(CPL_EuroStocks_Importer::OPT_KEY); ?>[username]" value="<?php echo esc_attr($opts['username'] ?? ''); ?>" /></td>
          </tr>
          <tr>
            <th scope="row"><label>Password</label></th>
            <td><input type="password" class="regular-text" name="<?php echo esc_attr(CPL_EuroStocks_Importer::OPT_KEY); ?>[password]" value="<?php echo esc_attr($opts['password'] ?? ''); ?>" /></td>
          </tr>
          <tr>
            <th scope="row"><label>API Key</label></th>
            <td><input type="text" class="regular-text" name="<?php echo esc_attr(CPL_EuroStocks_Importer::OPT_KEY); ?>[api_key]" value="<?php echo esc_attr($opts['api_key'] ?? ''); ?>" /></td>
          </tr>

          <tr><th colspan="2"><h2>API instellingen</h2></th></tr>

          <tr>
            <th scope="row"><label>Data API Base URL</label></th>
            <td><input type="url" class="regular-text" name="<?php echo esc_attr(CPL_EuroStocks_Importer::OPT_KEY); ?>[data_api_base]" value="<?php echo esc_attr($opts['data_api_base'] ?? 'https://data-api.eurostocks.com'); ?>" /></td>
          </tr>
          <tr>
            <th scope="row"><label>Product Data API Base URL</label></th>
            <td><input type="url" class="regular-text" name="<?php echo esc_attr(CPL_EuroStocks_Importer::OPT_KEY); ?>[product_data_api_base]" value="<?php echo esc_attr($opts['product_data_api_base'] ?? 'https://products-data-api.eurostocks.com'); ?>" /></td>
          </tr>
          <tr>
            <th scope="row"><label>Location ID</label></th>
            <td><input type="number" class="small-text" name="<?php echo esc_attr(CPL_EuroStocks_Importer::OPT_KEY); ?>[location_id]" value="<?php echo esc_attr($opts['location_id'] ?? 0); ?>" />
            <p class="description">Bijvoorbeeld 915</p></td>
          </tr>
          <tr>
            <th scope="row"><label>Taal (ISO)</label></th>
            <td><input type="text" class="small-text" name="<?php echo esc_attr(CPL_EuroStocks_Importer::OPT_KEY); ?>[language_iso]" value="<?php echo esc_attr($opts['language_iso'] ?? 'nl'); ?>" /></td>
          </tr>

          <tr><th colspan="2"><h2>Import</h2></th></tr>

          <tr>
            <th scope="row">Afbeeldingen ophalen</th>
            <td>
              <label>
                <input type="checkbox" name="<?php echo esc_attr(CPL_EuroStocks_Importer::OPT_KEY); ?>[download_images]" value="1" <?php checked(!empty($opts['download_images'])); ?> />
                Download afbeeldingen en zet de eerste als uitgelichte afbeelding
              </label>
            </td>
          </tr>

          <tr>
            <th scope="row">Ontbrekende producten</th>
            <td>
              <label style="display:block; margin-bottom:6px;">
                <input type="checkbox" name="<?php echo esc_attr(CPL_EuroStocks_Importer::OPT_KEY); ?>[mark_missing_out_of_stock]" value="1" <?php checked(!empty($opts['mark_missing_out_of_stock'])); ?> />
                Markeer producten die niet meer in EuroStocks staan als niet op voorraad (stock = 0)
              </label>
              <label style="display:block;">
                <input type="checkbox" name="<?php echo esc_attr(CPL_EuroStocks_Importer::OPT_KEY); ?>[confirm_delete_missing]" value="1" <?php checked(!empty($opts['confirm_delete_missing'])); ?> />
                Ik snap het: handmatige opschoning mag ontbrekende producten definitief verwijderen (incl. bijlagen)
              </label>
            </td>
          </tr>

          <tr>
            <th scope="row"><label>Wat wil je importeren?</label></th>
            <td>
              <select name="<?php echo esc_attr(CPL_EuroStocks_Importer::OPT_KEY); ?>[import_mode]">
                <option value="engines" <?php selected(($opts['import_mode'] ?? 'engines'), 'engines'); ?>>Alleen automotoren</option>
                <option value="gearboxes" <?php selected(($opts['import_mode'] ?? 'engines'), 'gearboxes'); ?>>Alleen versnellingsbakken</option>
                <option value="both" <?php selected(($opts['import_mode'] ?? 'engines'), 'both'); ?>>Automotoren + versnellingsbakken</option>
              </select>
            </td>
          </tr>

          <tr>
            <th scope="row"><label>SearchText (optioneel)</label></th>
            <td><input type="text" class="regular-text" name="<?php echo esc_attr(CPL_EuroStocks_Importer::OPT_KEY); ?>[search_text]" value="<?php echo esc_attr($opts['search_text'] ?? ''); ?>" />
            <p class="description">Laat leeg om alles op te halen (aanrader). Gebruik alleen voor test.</p></td>
          </tr>

          <tr>
            <th scope="row"><label>SortOn</label></th>
            <td><input type="text" class="regular-text" name="<?php echo esc_attr(CPL_EuroStocks_Importer::OPT_KEY); ?>[sort_on]" value="<?php echo esc_attr($opts['sort_on'] ?? 'LastUpdatedDate'); ?>" /></td>
          </tr>
          <tr>
            <th scope="row"><label>SortOrder</label></th>
            <td>
              <select name="<?php echo esc_attr(CPL_EuroStocks_Importer::OPT_KEY); ?>[sort_order]">
                <option value="desc" <?php selected(($opts['sort_order'] ?? 'desc'), 'desc'); ?>>desc</option>
                <option value="asc" <?php selected(($opts['sort_order'] ?? 'desc'), 'asc'); ?>>asc</option>
              </select>
            </td>
          </tr>

          <tr>
            <th scope="row"><label>PageSize</label></th>
            <td><input type="number" class="small-text" name="<?php echo esc_attr(CPL_EuroStocks_Importer::OPT_KEY); ?>[page_size]" value="<?php echo esc_attr($opts['page_size'] ?? 50); ?>" /></td>
          </tr>
          <tr>
            <th scope="row"><label>Max runtime per run (sec)</label></th>
            <td><input type="number" class="small-text" name="<?php echo esc_attr(CPL_EuroStocks_Importer::OPT_KEY); ?>[max_runtime]" value="<?php echo esc_attr($opts['max_runtime'] ?? 20); ?>" />
            <p class="description">Voorkomt max execution time. 15–25 sec werkt meestal goed.</p></td>
          </tr>

          <tr>
            <th scope="row"><label>Max pages</label></th>
            <td><input type="number" class="small-text" name="<?php echo esc_attr(CPL_EuroStocks_Importer::OPT_KEY); ?>[max_pages]" value="<?php echo esc_attr($opts['max_pages'] ?? 200); ?>" /></td>
          </tr>

        </table>

        <?php submit_button('Instellingen opslaan'); ?>
      </form>

      <hr/>

      <h2>Tools</h2>

      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block; margin-right: 12px;">
        <?php wp_nonce_field('cpl_eurostocks_test_languages'); ?>
        <input type="hidden" name="action" value="cpl_eurostocks_test_languages">
        <?php submit_button('Test Data API (languages)', 'secondary', 'submit', false); ?>
      </form>

      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block; margin-right: 12px;">
        <?php wp_nonce_field('cpl_eurostocks_run_import'); ?>
        <input type="hidden" name="action" value="cpl_eurostocks_run_import">
        <?php submit_button('Start import nu', 'primary', 'submit', false); ?>
      </form>

      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;">
        <?php wp_nonce_field('cpl_eurostocks_purge'); ?>
        <input type="hidden" name="action" value="cpl_eurostocks_purge">
        <?php submit_button('Verwijder alle data (posts + termen)', 'delete', 'submit', false); ?>
      </form>

      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block; margin-left:12px;">
        <?php wp_nonce_field('cpl_eurostocks_delete_missing'); ?>
        <input type="hidden" name="action" value="cpl_eurostocks_delete_missing">
        <?php submit_button('Verwijder ontbrekende producten', 'delete', 'submit', false); ?>
        <p class="description">Verwijdert posts die in de laatste import niet zijn teruggekomen. Werkt alleen als je de bevestiging hierboven aanvinkt.</p>
      </form>

      <?php if (!empty($_GET['cpl_continue'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function(){
  setTimeout(function(){
    var forms = document.querySelectorAll('form[action*="admin-post.php"]');
    for (var i=0;i<forms.length;i++){
      if (forms[i].querySelector('input[name="action"][value="cpl_eurostocks_run_import"]')) { forms[i].submit(); return; }
    }
  }, 800);
});
</script>
<?php endif; ?>

<?php if (!empty($_GET['cpl_msg'])): ?>
        <div class="notice notice-success" style="margin-top: 12px;"><p><?php echo esc_html(wp_unslash($_GET['cpl_msg'])); ?></p></div>
      <?php endif; ?>

      <?php if (!empty($_GET['cpl_err'])): ?>
        <div class="notice notice-error" style="margin-top: 12px;"><p><?php echo esc_html(wp_unslash($_GET['cpl_err'])); ?></p></div>
      <?php endif; ?>

    </div>
    <?php
  }

  public static function handle_test_languages() {
    if (!current_user_can('manage_options')) wp_die('Geen toegang.');
    check_admin_referer('cpl_eurostocks_test_languages');

    $opts = get_option(CPL_EuroStocks_Importer::OPT_KEY, array());
    $url = rtrim($opts['data_api_base'] ?? 'https://data-api.eurostocks.com', '/') . '/api/v1/languages';

    $res = CPL_EuroStocks_API::get_json($url, $opts);
    if (is_wp_error($res)) {
      wp_redirect(CPL_EuroStocks_Helpers::admin_url_with_msg(array('cpl_err' => rawurlencode('Test mislukt: ' . $res->get_error_message()))));
      exit;
    }

    wp_redirect(CPL_EuroStocks_Helpers::admin_url_with_msg(array('cpl_msg' => rawurlencode('Test OK. Ontvangen: ' . wp_json_encode($res)))));
    exit;
  }

  public static function handle_manual_import() {
    if (!current_user_can('manage_options')) wp_die('Geen toegang.');
    check_admin_referer('cpl_eurostocks_run_import');

    $result = CPL_EuroStocks_Importer::run_import();

    if (!empty($result['error'])) {
      wp_redirect(CPL_EuroStocks_Helpers::admin_url_with_msg(array('cpl_err' => rawurlencode($result['error']))));
      exit;
    }

    $msg = sprintf('Import batch klaar. Upserts: %d. Skipped: %d. Errors: %d.', $result['upserts'], $result['skipped'], $result['errors']);

    if (!empty($result['continue'])) {
      $msg .= ' Doorgaan met volgende batch…';
      wp_redirect(CPL_EuroStocks_Helpers::admin_url_with_msg(array('cpl_msg' => rawurlencode($msg), 'cpl_continue' => 1)));
      exit;
    }
    wp_redirect(CPL_EuroStocks_Helpers::admin_url_with_msg(array('cpl_msg' => rawurlencode($msg))));
    exit;
  }

  public static function handle_test_image() {
    if (!current_user_can('manage_options')) wp_die('Geen toegang.');
    check_admin_referer('cpl_eurostocks_test_image');

    $raw = get_option('cpl_eurostocks_last_raw', '');
    if (!$raw) {
      wp_redirect(CPL_EuroStocks_Helpers::admin_url_with_msg(array('cpl_err' => rawurlencode('Geen raw JSON gevonden. Draai eerst een import.'))));
      exit;
    }
    $data = json_decode($raw, true);
    $url = '';
    if (is_array($data) && !empty($data['images']) && is_array($data['images']) && !empty($data['images'][0]['ref'])) {
      $url = (string)$data['images'][0]['ref'];
    }
    if (!$url) {
      wp_redirect(CPL_EuroStocks_Helpers::admin_url_with_msg(array('cpl_err' => rawurlencode('Geen image URL gevonden in laatste raw JSON.'))));
      exit;
    }

    $head = wp_remote_head($url, array('timeout' => 20));
    if (is_wp_error($head)) {
      wp_redirect(CPL_EuroStocks_Helpers::admin_url_with_msg(array('cpl_err' => rawurlencode('HEAD request faalde: ' . $head->get_error_message()))));
      exit;
    }

    $code = wp_remote_retrieve_response_code($head);
    $ct = wp_remote_retrieve_header($head, 'content-type');
    $cl = wp_remote_retrieve_header($head, 'content-length');
    $msg = 'HEAD OK. HTTP ' . $code . ', content-type: ' . $ct . ', content-length: ' . $cl;
    wp_redirect(CPL_EuroStocks_Helpers::admin_url_with_msg(array('cpl_msg' => rawurlencode($msg))));
    exit;
  }

  public static function handle_delete_missing() {
    if (!current_user_can('manage_options')) wp_die('Geen toegang.');
    check_admin_referer('cpl_eurostocks_delete_missing');

    $opts = get_option(CPL_EuroStocks_Importer::OPT_KEY, array());
    if (empty($opts['confirm_delete_missing'])) {
      wp_redirect(CPL_EuroStocks_Helpers::admin_url_with_msg(array('cpl_err' => rawurlencode('Bevestiging ontbreekt. Vink eerst aan: “handmatige opschoning mag definitief verwijderen”.'))));
      exit;
    }

    $run_id = (int)get_option('cpl_eurostocks_run_id', 0);
    if (!$run_id) {
      wp_redirect(CPL_EuroStocks_Helpers::admin_url_with_msg(array('cpl_err' => rawurlencode('Geen run-id gevonden. Draai eerst een import.'))));
      exit;
    }

    $r = CPL_EuroStocks_Importer::delete_missing_posts($run_id, true);
    $msg = sprintf('Ontbrekende producten verwijderd. Posts: %d, Bijlagen: %d.', (int)$r['deleted_posts'], (int)$r['deleted_attachments']);
    wp_redirect(CPL_EuroStocks_Helpers::admin_url_with_msg(array('cpl_msg' => rawurlencode($msg))));
    exit;
  }

  public static function handle_show_last_raw() {
    if (!current_user_can('manage_options')) wp_die('Geen toegang.');
    check_admin_referer('cpl_eurostocks_show_last_raw');

    $raw = get_option('cpl_eurostocks_last_raw', '');
    if (!$raw) {
      wp_redirect(CPL_EuroStocks_Helpers::admin_url_with_msg(array('cpl_err' => rawurlencode('Geen raw JSON gevonden. Draai eerst een import.'))));
      exit;
    }

    // show a short snippet to avoid huge URLs; full JSON is in meta box per post
    $snippet = substr((string)$raw, 0, 900);
    wp_redirect(CPL_EuroStocks_Helpers::admin_url_with_msg(array('cpl_msg' => rawurlencode('Laatste raw JSON (eerste 900 chars): ' . $snippet))));
    exit;
  }

  public static function handle_purge() {
    if (!current_user_can('manage_options')) wp_die('Geen toegang.');
    check_admin_referer('cpl_eurostocks_purge');

    $r = CPL_EuroStocks_Importer::purge_all_data();
    $msg = sprintf('Alles verwijderd. Posts: %d, Termen: %d.', (int)$r['deleted_posts'], (int)$r['deleted_terms']);
    wp_redirect(CPL_EuroStocks_Helpers::admin_url_with_msg(array('cpl_msg' => rawurlencode($msg))));
    exit;
  }

  public static function render_info_metabox($post) {
    $eurostocks_id = get_post_meta($post->ID, '_cpl_eurostocks_ad_id', true);
    $last_seen = get_post_meta($post->ID, '_cpl_last_seen', true);
    $created = get_post_meta($post->ID, '_cpl_created_date', true);
    $updated = get_post_meta($post->ID, '_cpl_last_updated_date', true);
    
    echo '<div style="padding:8px 0;">';
    if ($eurostocks_id) {
      echo '<p style="margin:8px 0;"><strong>EuroStocks ID:</strong> <code>' . esc_html($eurostocks_id) . '</code></p>';
    }
    if ($last_seen) {
      echo '<p style="margin:8px 0;"><strong>Laatst gesynchroniseerd:</strong> ' . esc_html(date('d-m-Y H:i', (int)$last_seen)) . '</p>';
    }
    if ($created) {
      echo '<p style="margin:8px 0;"><strong>Aangemaakt in EuroStocks:</strong> ' . esc_html($created) . '</p>';
    }
    if ($updated) {
      echo '<p style="margin:8px 0;"><strong>Laatst gewijzigd in EuroStocks:</strong> ' . esc_html($updated) . '</p>';
    }
    echo '</div>';
  }

  public static function render_price_metabox($post) {
    $price = get_post_meta($post->ID, '_cpl_price', true);
    $currency = get_post_meta($post->ID, '_cpl_price_currency', true);
    $price_incl_vat = get_post_meta($post->ID, '_cpl_price_incl_vat', true);
    $vat_percentage = get_post_meta($post->ID, '_cpl_price_vat_percentage', true);
    $price_ex_vat = get_post_meta($post->ID, '_cpl_price_ex_vat', true);
    $stock = get_post_meta($post->ID, '_cpl_stock', true);
    $condition = get_post_meta($post->ID, '_cpl_condition', true);
    $delivery = get_post_meta($post->ID, '_cpl_delivery', true);
    
    echo '<div style="padding:8px 0;">';
    if ($price) {
      $symbol = ($currency === 'EUR' || !$currency) ? '€' : $currency;
      echo '<p style="margin:8px 0;"><strong>Prijs:</strong><br>';
      echo '<span style="font-size:24px; color:#2c5f2d; font-weight:bold;">' . esc_html($symbol) . ' ' . esc_html(number_format((float)$price, 2, ',', '.')) . '</span>';
      if ($price_ex_vat) echo '<br><small>Excl. BTW</small>';
      echo '</p>';
      if ($price_incl_vat) {
        echo '<p style="margin:8px 0;"><strong>Incl. BTW:</strong> ' . esc_html($symbol) . ' ' . esc_html(number_format((float)$price_incl_vat, 2, ',', '.')) . '</p>';
      }
      if ($vat_percentage) {
        echo '<p style="margin:8px 0;"><strong>BTW %:</strong> ' . esc_html($vat_percentage) . '%</p>';
      }
    }
    echo '<hr style="margin:12px 0;">';
    echo '<p style="margin:8px 0;"><strong>Voorraad:</strong><br>';
    if ($stock !== '') {
      if ((int)$stock > 0) {
        echo '<span style="display:inline-block; background:#d4edda; color:#155724; padding:6px 12px; border-radius:4px; font-weight:bold;">✓ Op voorraad (' . esc_html($stock) . ')</span>';
      } else {
        echo '<span style="display:inline-block; background:#f8d7da; color:#721c24; padding:6px 12px; border-radius:4px; font-weight:bold;">✗ Niet op voorraad</span>';
      }
    } else {
      echo '<span style="color:#999;">Onbekend</span>';
    }
    echo '</p>';
    if ($condition) {
      echo '<p style="margin:8px 0;"><strong>Conditie:</strong> ' . esc_html($condition) . '</p>';
    }
    if ($delivery) {
      echo '<p style="margin:8px 0;"><strong>Levering:</strong> ' . esc_html($delivery) . '</p>';
    }
    echo '</div>';
  }

  public static function render_specs_metabox($post) {
    echo '<table class="form-table" style="margin:0;"><tbody>';
    $specs = array(
      array('Motor', array(
        array('Motorinhoud', '_cpl_engine_capacity'),
        array('Vermogen', '_cpl_power_kw', '_cpl_power_hp'),
        array('Brandstof', '_cpl_fuel', '_cpl_fuel_type'),
      )),
      array('Versnellingsbak', array(
        array('Transmissie', '_cpl_transmission'),
        array('Versnellingsbak type', '_cpl_gear_type'),
      )),
      array('Conditie', array(
        array('Kilometerstand', '_cpl_km_value', '_cpl_km_raw'),
        array('Garantie', '_cpl_warranty_months', '_cpl_warranty_raw'),
      )),
      array('Identificatie', array(
        array('Fabrikant', '_cpl_manufacturer'),
        array('Bouwjaar', '_cpl_year'),
        array('Onderdeelnummer', '_cpl_part_number', 'code'),
        array('OEM nummer', '_cpl_oem_number', 'code'),
        array('EAN', '_cpl_ean', 'code'),
        array('SKU', '_cpl_sku', 'code'),
      )),
      array('Fysiek', array(
        array('Gewicht', '_cpl_weight'),
        array('Afmetingen (LxBxH)', '_cpl_length', '_cpl_width', '_cpl_height', 'dimensions'),
        array('Kleur', '_cpl_color'),
      )),
      array('Leverancier', array(
        array('Leverancier', '_cpl_supplier_name'),
        array('Locatie', '_cpl_location'),
      )),
    );
    
    foreach ($specs as $section) {
      $section_printed = false;
      foreach ($section[1] as $field) {
        $label = $field[0];
        $value = null;
        $format_type = isset($field[count($field) - 1]) && is_string($field[count($field) - 1]) && !strpos($field[count($field) - 1], '_cpl_') ? $field[count($field) - 1] : null;
        
        if ($format_type === 'code' || $format_type === 'dimensions') {
          $values = array();
          for ($i = 1; $i < count($field); $i++) {
            if (strpos($field[$i], '_cpl_') === 0) {
              $v = get_post_meta($post->ID, $field[$i], true);
              if ($v) $values[] = $v;
            }
          }
          if (!empty($values)) {
            if ($format_type === 'dimensions') {
              $value = implode(' x ', array_map('esc_html', $values));
            } else {
              $value = '<code>' . esc_html($values[0]) . '</code>';
            }
          }
        } elseif (count($field) === 4 && $field[1] === '_cpl_km_value') {
          $km_value = get_post_meta($post->ID, '_cpl_km_value', true);
          $km_raw = get_post_meta($post->ID, '_cpl_km_raw', true);
          if ($km_value) {
            $value = esc_html(number_format((int)$km_value, 0, ',', '.')) . ' km';
          } elseif ($km_raw) {
            $value = esc_html($km_raw);
          }
        } elseif (count($field) === 4 && $field[1] === '_cpl_warranty_months') {
          $months = get_post_meta($post->ID, '_cpl_warranty_months', true);
          $raw = get_post_meta($post->ID, '_cpl_warranty_raw', true);
          if ($months) {
            $m = (int)$months;
            if ($m >= 12 && $m % 12 === 0) {
              $value = ($m / 12) . ' jaar';
            } else {
              $value = $m . ' ' . ($m === 1 ? 'maand' : 'maanden');
            }
          } elseif ($raw) {
            $value = esc_html($raw);
          }
        } elseif (count($field) === 4 && $field[1] === '_cpl_power_kw') {
          $kw = get_post_meta($post->ID, '_cpl_power_kw', true);
          $hp = get_post_meta($post->ID, '_cpl_power_hp', true);
          if ($kw || $hp) {
            $parts = array();
            if ($kw) $parts[] = esc_html($kw) . ' kW';
            if ($hp) $parts[] = esc_html($hp) . ' pk';
            $value = implode(' / ', $parts);
          }
        } elseif (count($field) === 4 && $field[1] === '_cpl_fuel') {
          $fuel = get_post_meta($post->ID, '_cpl_fuel', true);
          $fuel_type = get_post_meta($post->ID, '_cpl_fuel_type', true);
          $value = $fuel ? esc_html($fuel) : ($fuel_type ? esc_html($fuel_type) : null);
        } else {
          for ($i = 1; $i < count($field); $i++) {
            if (strpos($field[$i], '_cpl_') === 0) {
              $v = get_post_meta($post->ID, $field[$i], true);
              if ($v) {
                $value = esc_html($v);
                break;
              }
            }
          }
        }
        
        if ($value) {
          if (!$section_printed) {
            echo '<tr><th colspan="2" style="padding:12px 0 4px 0; border-bottom:2px solid #ddd;"><strong>' . esc_html($section[0]) . '</strong></th></tr>';
            $section_printed = true;
          }
          echo '<tr><th style="width:40%; padding:8px 0;">' . esc_html($label) . '</th><td style="padding:8px 0;">' . $value . '</td></tr>';
        }
      }
    }
    echo '</tbody></table>';
  }

  public static function render_gallery_metabox($post) {
    $gallery_json = get_post_meta($post->ID, '_cpl_gallery', true);
    $gallery = $gallery_json ? json_decode($gallery_json, true) : array();
    
    if (!empty($gallery) && is_array($gallery)) {
      echo '<div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">';
      foreach ($gallery as $index => $attachment_id) {
        $img = wp_get_attachment_image($attachment_id, 'thumbnail', false, array('style' => 'width:100%; height:auto; border-radius:4px;'));
        if ($img) {
          echo '<div style="position:relative;">';
          echo '<a href="' . esc_url(wp_get_attachment_url($attachment_id)) . '" target="_blank">' . $img . '</a>';
          if ($index === 0) {
            echo '<span style="position:absolute; top:4px; right:4px; background:rgba(0,0,0,0.7); color:white; padding:2px 6px; border-radius:3px; font-size:10px;">Uitgelicht</span>';
          }
          echo '</div>';
        }
      }
      echo '</div>';
      echo '<p style="margin-top:8px; font-size:12px; color:#666;">Totaal: ' . count($gallery) . ' afbeeldingen</p>';
    } else {
      echo '<p style="color:#999;">Geen afbeeldingen beschikbaar.</p>';
    }
    
    $img_err = get_post_meta($post->ID, '_cpl_image_errors', true);
    if (!empty($img_err)) {
      echo '<details style="margin-top:12px;"><summary style="cursor:pointer; color:#d63638;">⚠ Afbeeldingen errors</summary>';
      echo '<pre style="background:#fff; padding:8px; border:1px solid #ddd; border-radius:4px; font-size:11px; overflow:auto; max-height:200px; margin-top:8px;">' . esc_html($img_err) . '</pre>';
      echo '</details>';
    }
  }
}
