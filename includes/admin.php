<?php
if (!defined('ABSPATH')) { exit; }

class CE_EuroStocks_Admin {

  public static function hooks() {
    add_action('add_meta_boxes', array(__CLASS__, 'register_metabox'));
  }

  public static function register_metabox() {
    add_meta_box('ce_eurostocks_info', __('EuroStocks Info', 'creators-eurostocks'), array(__CLASS__, 'render_info_metabox'), CE_EuroStocks_Importer::CPT, 'normal', 'high');
    add_meta_box('ce_eurostocks_specs', __('Specificaties', 'creators-eurostocks'), array(__CLASS__, 'render_specs_metabox'), CE_EuroStocks_Importer::CPT, 'normal', 'high');
    add_meta_box('ce_eurostocks_price', __('Prijs & Voorraad', 'creators-eurostocks'), array(__CLASS__, 'render_price_metabox'), CE_EuroStocks_Importer::CPT, 'side', 'high');
    add_meta_box('ce_eurostocks_gallery', __('Afbeeldingen', 'creators-eurostocks'), array(__CLASS__, 'render_gallery_metabox'), CE_EuroStocks_Importer::CPT, 'side', 'default');
    add_meta_box('ce_eurostocks_debug', __('Debug & Diagnostics', 'creators-eurostocks'), array(__CLASS__, 'render_metabox'), CE_EuroStocks_Importer::CPT, 'normal', 'low');
  }

  public static function render_metabox($post) {
    $raw = get_post_meta($post->ID, '_ce_raw_details', true);
    $km_raw = get_post_meta($post->ID, '_ce_km_raw', true);
    $km_val = get_post_meta($post->ID, '_ce_km_value', true);
    $war_raw = get_post_meta($post->ID, '_ce_warranty_raw', true);
    $war_m = get_post_meta($post->ID, '_ce_warranty_months', true);
    $fuel = get_post_meta($post->ID, '_ce_fuel', true);
    $price_ex = get_post_meta($post->ID, '_ce_price_ex_vat', true);
    $img_err = get_post_meta($post->ID, '_ce_image_errors', true);

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
      'ce-import',
      array(__CLASS__, 'render_settings_page')
    );
  }

  public static function register_settings() {
    register_setting(CE_EuroStocks_Importer::OPT_GROUP, CE_EuroStocks_Importer::OPT_KEY, array(
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
        'api_rate_limit' => 1,
        'mark_missing_out_of_stock' => 0,
        'confirm_delete_missing' => 0,
        'max_runtime' => 20,
      ),
    ));
  }

  public static function sanitize_settings($input) {
    $out = array();

    $out['enabled'] = !empty($input['enabled']) ? 1 : 0;
    $out['download_images'] = !empty($input['download_images']) ? 1 : 0;
    $out['api_rate_limit'] = !empty($input['api_rate_limit']) ? 1 : 0;
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

    $opts = get_option(CE_EuroStocks_Importer::OPT_KEY, array());
    $enabled = !empty($opts['enabled']);
    ?>
    <div class="wrap">
      <h1>Creators EuroStocks Import</h1>

      <form method="post" action="options.php">
        <?php settings_fields(CE_EuroStocks_Importer::OPT_GROUP); ?>
        <table class="form-table" role="presentation">
          <tr>
            <th scope="row">Sync inschakelen</th>
            <td>
              <label>
                <input type="checkbox" name="<?php echo esc_attr(CE_EuroStocks_Importer::OPT_KEY); ?>[enabled]" value="1" <?php checked($enabled); ?> />
                Dagelijkse sync via WP-Cron
              </label>
            </td>
          </tr>

          <tr><th colspan="2"><h2>Authenticatie</h2></th></tr>
          <tr>
            <td colspan="2">
              <div class="notice notice-info inline" style="margin: 10px 0; padding: 10px;">
                <p><strong>Hoe kom ik aan API gegevens?</strong> Neem contact op met <a href="https://www.eurostocks.com" target="_blank">EuroStocks</a> voor toegang tot de API. Zie ook de setup handleiding (SETUP-GUIDE.md) in de plugin folder.</p>
              </div>
            </td>
          </tr>

          <tr>
            <th scope="row"><label>Username</label></th>
            <td><input type="text" class="regular-text" name="<?php echo esc_attr(CE_EuroStocks_Importer::OPT_KEY); ?>[username]" value="<?php echo esc_attr($opts['username'] ?? ''); ?>" /></td>
          </tr>
          <tr>
            <th scope="row"><label>Password</label></th>
            <td><input type="password" class="regular-text" name="<?php echo esc_attr(CE_EuroStocks_Importer::OPT_KEY); ?>[password]" value="<?php echo esc_attr($opts['password'] ?? ''); ?>" /></td>
          </tr>
          <tr>
            <th scope="row"><label>API Key</label></th>
            <td><input type="text" class="regular-text" name="<?php echo esc_attr(CE_EuroStocks_Importer::OPT_KEY); ?>[api_key]" value="<?php echo esc_attr($opts['api_key'] ?? ''); ?>" /></td>
          </tr>

          <tr><th colspan="2"><h2>API instellingen</h2></th></tr>

          <tr>
            <th scope="row"><label>Data API Base URL</label></th>
            <td><input type="url" class="regular-text" name="<?php echo esc_attr(CE_EuroStocks_Importer::OPT_KEY); ?>[data_api_base]" value="<?php echo esc_attr($opts['data_api_base'] ?? 'https://data-api.eurostocks.com'); ?>" />
            <p class="description">Voor het ophalen van de productlijst. Meestal: <code>https://data-api.eurostocks.com</code></p></td>
          </tr>
          <tr>
            <th scope="row"><label>Product Data API Base URL</label></th>
            <td><input type="url" class="regular-text" name="<?php echo esc_attr(CE_EuroStocks_Importer::OPT_KEY); ?>[product_data_api_base]" value="<?php echo esc_attr($opts['product_data_api_base'] ?? 'https://products-data-api.eurostocks.com'); ?>" />
            <p class="description">Voor het ophalen van gedetailleerde productinformatie. Meestal: <code>https://products-data-api.eurostocks.com</code></p></td>
          </tr>
          <tr>
            <th scope="row"><label>Location ID</label></th>
            <td><input type="number" class="small-text" name="<?php echo esc_attr(CE_EuroStocks_Importer::OPT_KEY); ?>[location_id]" value="<?php echo esc_attr($opts['location_id'] ?? 0); ?>" />
            <p class="description">Je locatie-ID van EuroStocks (bijv. 915). Vraag dit op bij je EuroStocks contactpersoon.</p></td>
          </tr>
          <tr>
            <th scope="row"><label>Taal (ISO)</label></th>
            <td><input type="text" class="small-text" name="<?php echo esc_attr(CE_EuroStocks_Importer::OPT_KEY); ?>[language_iso]" value="<?php echo esc_attr($opts['language_iso'] ?? 'nl'); ?>" />
            <p class="description">Taal van de DATA uit EuroStocks API (bijv. "nl", "en", "de"). Dit is NIET hetzelfde als WPML talen. Zie SETUP-GUIDE.md voor meer info.</p></td>
          </tr>

          <tr><th colspan="2"><h2>Import</h2></th></tr>

          <tr>
            <th scope="row">Afbeeldingen ophalen</th>
            <td>
              <label>
                <input type="checkbox" name="<?php echo esc_attr(CE_EuroStocks_Importer::OPT_KEY); ?>[download_images]" value="1" <?php checked(!empty($opts['download_images'])); ?> />
                Download afbeeldingen en zet de eerste als uitgelichte afbeelding
              </label>
            </td>
          </tr>

          <tr>
            <th scope="row">API Rate Limiting</th>
            <td>
              <label>
                <input type="checkbox" name="<?php echo esc_attr(CE_EuroStocks_Importer::OPT_KEY); ?>[api_rate_limit]" value="1" <?php checked(!empty($opts['api_rate_limit'])); ?> />
                Voeg 100ms pauze toe tussen API calls (aanbevolen voor grote imports om blokkering te voorkomen)
              </label>
            </td>
          </tr>

          <tr>
            <th scope="row">Ontbrekende producten</th>
            <td>
              <label style="display:block; margin-bottom:6px;">
                <input type="checkbox" name="<?php echo esc_attr(CE_EuroStocks_Importer::OPT_KEY); ?>[mark_missing_out_of_stock]" value="1" <?php checked(!empty($opts['mark_missing_out_of_stock'])); ?> />
                Markeer producten die niet meer in EuroStocks staan als niet op voorraad (stock = 0)
              </label>
              <label style="display:block;">
                <input type="checkbox" name="<?php echo esc_attr(CE_EuroStocks_Importer::OPT_KEY); ?>[confirm_delete_missing]" value="1" <?php checked(!empty($opts['confirm_delete_missing'])); ?> />
                Ik snap het: handmatige opschoning mag ontbrekende producten definitief verwijderen (incl. bijlagen)
              </label>
            </td>
          </tr>

          <tr>
            <th scope="row"><label>Wat wil je importeren?</label></th>
            <td>
              <select name="<?php echo esc_attr(CE_EuroStocks_Importer::OPT_KEY); ?>[import_mode]">
                <optgroup label="Specifieke onderdelen">
                  <option value="engines" <?php selected(($opts['import_mode'] ?? 'engines'), 'engines'); ?>>Alleen automotoren</option>
                  <option value="gearboxes" <?php selected(($opts['import_mode'] ?? 'engines'), 'gearboxes'); ?>>Alleen versnellingsbakken</option>
                  <option value="turbos" <?php selected(($opts['import_mode'] ?? 'engines'), 'turbos'); ?>>Alleen turbo's</option>
                  <option value="catalysts" <?php selected(($opts['import_mode'] ?? 'engines'), 'catalysts'); ?>>Alleen katalysatoren</option>
                  <option value="starters" <?php selected(($opts['import_mode'] ?? 'engines'), 'starters'); ?>>Alleen startmotoren</option>
                  <option value="alternators" <?php selected(($opts['import_mode'] ?? 'engines'), 'alternators'); ?>>Alleen dynamo's</option>
                  <option value="ac_compressors" <?php selected(($opts['import_mode'] ?? 'engines'), 'ac_compressors'); ?>>Alleen airco compressors</option>
                  <option value="power_steering" <?php selected(($opts['import_mode'] ?? 'engines'), 'power_steering'); ?>>Alleen stuurbekrachtiging pompen</option>
                </optgroup>
                <optgroup label="Combinaties">
                  <option value="engines_gearboxes" <?php selected(($opts['import_mode'] ?? 'engines'), 'engines_gearboxes'); ?>>Motoren + Versnellingsbakken</option>
                  <option value="engine_parts" <?php selected(($opts['import_mode'] ?? 'engines'), 'engine_parts'); ?>>Alle motoronderdelen (motoren, turbo's, starters, etc.)</option>
                  <option value="transmission_parts" <?php selected(($opts['import_mode'] ?? 'engines'), 'transmission_parts'); ?>>Alle transmissie onderdelen</option>
                </optgroup>
                <optgroup label="Alles">
                  <option value="all" <?php selected(($opts['import_mode'] ?? 'engines'), 'all'); ?>>✨ Alles importeren (alle categorieën)</option>
                </optgroup>
              </select>
              <p class="description">Kies welke EuroStocks producten je wilt importeren. Bij "Alles importeren" worden alle onderdelen gesynchroniseerd.</p>
            </td>
          </tr>

          <tr>
            <th scope="row"><label>SearchText (optioneel)</label></th>
            <td><input type="text" class="regular-text" name="<?php echo esc_attr(CE_EuroStocks_Importer::OPT_KEY); ?>[search_text]" value="<?php echo esc_attr($opts['search_text'] ?? ''); ?>" />
            <p class="description">Laat leeg om alles op te halen (aanrader). Gebruik alleen voor test.</p></td>
          </tr>

          <tr>
            <th scope="row"><label>SortOn</label></th>
            <td><input type="text" class="regular-text" name="<?php echo esc_attr(CE_EuroStocks_Importer::OPT_KEY); ?>[sort_on]" value="<?php echo esc_attr($opts['sort_on'] ?? 'LastUpdatedDate'); ?>" /></td>
          </tr>
          <tr>
            <th scope="row"><label>SortOrder</label></th>
            <td>
              <select name="<?php echo esc_attr(CE_EuroStocks_Importer::OPT_KEY); ?>[sort_order]">
                <option value="desc" <?php selected(($opts['sort_order'] ?? 'desc'), 'desc'); ?>>desc</option>
                <option value="asc" <?php selected(($opts['sort_order'] ?? 'desc'), 'asc'); ?>>asc</option>
              </select>
            </td>
          </tr>

          <tr>
            <th scope="row"><label>PageSize</label></th>
            <td><input type="number" class="small-text" name="<?php echo esc_attr(CE_EuroStocks_Importer::OPT_KEY); ?>[page_size]" value="<?php echo esc_attr($opts['page_size'] ?? 50); ?>" /></td>
          </tr>
          <tr>
            <th scope="row"><label>Max runtime per run (sec)</label></th>
            <td><input type="number" class="small-text" name="<?php echo esc_attr(CE_EuroStocks_Importer::OPT_KEY); ?>[max_runtime]" value="<?php echo esc_attr($opts['max_runtime'] ?? 20); ?>" />
            <p class="description">Voorkomt max execution time. 15–25 sec werkt meestal goed.</p></td>
          </tr>

          <tr>
            <th scope="row"><label>Max pages</label></th>
            <td><input type="number" class="small-text" name="<?php echo esc_attr(CE_EuroStocks_Importer::OPT_KEY); ?>[max_pages]" value="<?php echo esc_attr($opts['max_pages'] ?? 200); ?>" /></td>
          </tr>

        </table>

        <?php submit_button('Instellingen opslaan'); ?>
      </form>

      <hr/>

      <h2>Import Status & Statistieken</h2>

      <?php
      // Get current statistics
      $db_count = wp_count_posts(CE_EuroStocks_Importer::CPT);
      $db_total = ($db_count->publish ?? 0) + ($db_count->draft ?? 0) + ($db_count->pending ?? 0) + ($db_count->private ?? 0);
      
      $import_state = get_option('ce_eurostocks_import_state', array());
      $total_api = !empty($_GET['total_api']) ? absint($_GET['total_api']) : ($import_state['total_records'] ?? 0);
      $total_db = !empty($_GET['total_db']) ? absint($_GET['total_db']) : $db_total;
      $current_page = !empty($_GET['current_page']) ? absint($_GET['current_page']) : ($import_state['page'] ?? 0);
      $total_pages = !empty($_GET['total_pages']) ? absint($_GET['total_pages']) : ($import_state['total_pages'] ?? 0);
      
      // Calculate progress from state if not in URL
      if (!empty($_GET['progress'])) {
        $progress = absint($_GET['progress']);
      } elseif ($total_pages > 0 && $current_page > 0) {
        $progress = round(($current_page / $total_pages) * 100);
      } else {
        $progress = 0;
      }
      
      $is_running = !empty($_GET['ce_continue']);
      $is_complete = !empty($_GET['import_complete']);
      ?>

      <table class="widefat" style="max-width: 600px; margin-bottom: 20px;">
        <thead>
          <tr>
            <th>Statistiek</th>
            <th>Waarde</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><strong>Totaal in database</strong></td>
            <td><?php echo esc_html(number_format($db_total, 0, ',', '.')); ?> producten</td>
          </tr>
          <?php if ($total_api > 0): ?>
          <tr>
            <td><strong>Totaal in EuroStocks API</strong></td>
            <td><?php echo esc_html(number_format($total_api, 0, ',', '.')); ?> producten</td>
          </tr>
          <?php endif; ?>
          <?php if ($current_page > 0 && $total_pages > 0): ?>
          <tr>
            <td><strong>Huidige pagina</strong></td>
            <td><?php echo esc_html($current_page); ?> van <?php echo esc_html($total_pages); ?></td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>

      <?php if ($is_running && $progress > 0): ?>
      <div style="margin-bottom: 20px;">
        <h3>Import voortgang: <?php echo esc_html($progress); ?>%</h3>
        <div style="width: 100%; max-width: 600px; height: 30px; background: #f0f0f0; border-radius: 5px; overflow: hidden; border: 1px solid #ddd;">
          <div style="width: <?php echo esc_attr($progress); ?>%; height: 100%; background: linear-gradient(90deg, #2271b1 0%, #135e96 100%); transition: width 0.3s ease;"></div>
        </div>
        <p style="margin-top: 8px;"><em>Import loopt... De pagina wordt automatisch ververst.</em></p>
      </div>
      <?php endif; ?>

      <?php if ($is_complete): ?>
      <div class="notice notice-success" style="max-width: 600px; padding: 12px; margin-bottom: 20px;">
        <p style="margin: 0; font-size: 16px;"><strong>✅ Import compleet!</strong> Alle producten zijn succesvol gesynchroniseerd.</p>
      </div>
      <?php endif; ?>

      <h2>Tools</h2>

      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block; margin-right: 12px;">
        <?php wp_nonce_field('ce_eurostocks_test_languages'); ?>
        <input type="hidden" name="action" value="ce_eurostocks_test_languages">
        <?php submit_button('Test Data API (languages)', 'secondary', 'submit', false); ?>
      </form>

      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block; margin-right: 12px;">
        <?php wp_nonce_field('ce_eurostocks_test_location'); ?>
        <input type="hidden" name="action" value="ce_eurostocks_test_location">
        <?php submit_button('Test Location ID', 'secondary', 'submit', false); ?>
      </form>

      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="ce-import-form" style="display:inline-block; margin-right: 12px;">
        <?php wp_nonce_field('ce_eurostocks_run_import'); ?>
        <input type="hidden" name="action" value="ce_eurostocks_run_import">
        <?php submit_button('Start import nu', 'primary', 'submit', false); ?>
      </form>

      <?php if ($is_running): ?>
      <button type="button" id="ce-stop-import" class="button" style="display:inline-block;">Stop import</button>
      <?php endif; ?>

      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;">
        <?php wp_nonce_field('ce_eurostocks_purge'); ?>
        <input type="hidden" name="action" value="ce_eurostocks_purge">
        <?php submit_button('Verwijder alle data (posts + termen)', 'delete', 'submit', false); ?>
      </form>

      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block; margin-left:12px;">
        <?php wp_nonce_field('ce_eurostocks_delete_missing'); ?>
        <input type="hidden" name="action" value="ce_eurostocks_delete_missing">
        <?php submit_button('Verwijder ontbrekende producten', 'delete', 'submit', false); ?>
        <p class="description">Verwijdert posts die in de laatste import niet zijn teruggekomen. Werkt alleen als je de bevestiging hierboven aanvinkt.</p>
      </form>

      <?php if (!empty($_GET['ce_continue'])): ?>
<script>
console.log('EUROSTOCKS: Auto-continue script loading...');
(function(){
  var autoSubmitTimer = null;
  var stopButton = document.getElementById('ce-stop-import');
  var importForm = document.getElementById('ce-import-form');
  console.log('EUROSTOCKS: Form found:', !!importForm, 'Stop button found:', !!stopButton);
  
  function startAutoSubmit() {
    console.log('EUROSTOCKS: Starting auto-submit timer (2 seconds)');
    autoSubmitTimer = setTimeout(function(){
      console.log('EUROSTOCKS: Timer expired, attempting submit...');
      if (importForm) {
        var submitBtn = importForm.querySelector('input[type="submit"]');
        if (submitBtn) {
          console.log('EUROSTOCKS: Clicking submit button');
          submitBtn.click();
        } else {
          console.log('EUROSTOCKS: Submit button not found, using form.submit()');
          importForm.submit();
        }
      } else {
        console.error('EUROSTOCKS: Import form not found!');
      }
    }, 2000);
  }
  
  function stopAutoSubmit() {
    console.log('EUROSTOCKS: Stop button clicked');
    if (autoSubmitTimer) {
      clearTimeout(autoSubmitTimer);
      autoSubmitTimer = null;
    }
    window.location.href = <?php echo wp_json_encode(admin_url('options-general.php?page=ce-import&ce_msg=' . rawurlencode('Import gestopt door gebruiker'))); ?>;
  }
  
  if (stopButton) {
    stopButton.addEventListener('click', stopAutoSubmit);
  }
  
  if (document.readyState === 'loading') {
    console.log('EUROSTOCKS: DOM loading, waiting for DOMContentLoaded');
    document.addEventListener('DOMContentLoaded', startAutoSubmit);
  } else {
    console.log('EUROSTOCKS: DOM ready, starting immediately');
    startAutoSubmit();
  }
})();
</script>
<?php endif; ?>

<?php if (!empty($_GET['ce_msg'])): ?>
        <div class="notice notice-success" style="margin-top: 12px;"><p><?php echo esc_html(wp_unslash($_GET['ce_msg'])); ?></p></div>
      <?php endif; ?>

      <?php if (!empty($_GET['ce_err'])): ?>
        <div class="notice notice-error" style="margin-top: 12px;"><p><?php echo esc_html(wp_unslash($_GET['ce_err'])); ?></p></div>
      <?php endif; ?>


      <hr style="margin-top: 30px;"/>
      
      <h2>Import Logboek</h2>
      <p class="description">Toont de laatste 10 import runs.</p>
      
      <?php
      $import_log = get_option('ce_eurostocks_import_log', array());
      $display_log = array_slice($import_log, 0, 10);
      
      if (!empty($display_log)):
      ?>
      <table class="widefat" style="max-width: 900px;">
        <thead>
          <tr>
            <th>Datum/Tijd</th>
            <th>Toegevoegd</th>
            <th>Overgeslagen</th>
            <th>Fouten</th>
            <th>Pagina</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($display_log as $entry): ?>
          <tr>
            <td><?php echo esc_html($entry['timestamp'] ?? '-'); ?></td>
            <td><?php echo esc_html($entry['upserts'] ?? 0); ?></td>
            <td><?php echo esc_html($entry['skipped'] ?? 0); ?></td>
            <td><?php echo esc_html($entry['errors'] ?? 0); ?></td>
            <td>
              <?php 
              if (!empty($entry['page']) && !empty($entry['total_pages'])) {
                echo esc_html($entry['page']) . ' / ' . esc_html($entry['total_pages']);
              } else {
                echo '-';
              }
              ?>
            </td>
            <td>
              <?php
              if (!empty($entry['completed'])) {
                echo '<span style="color: green; font-weight: bold;">✓ Compleet</span>';
              } elseif (!empty($entry['continue'])) {
                echo '<span style="color: orange;">⟳ Bezig...</span>';
              } else {
                echo '-';
              }
              ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
      <p><em>Nog geen imports uitgevoerd.</em></p>
      <?php endif; ?>
    </div>
    <?php
  }

  public static function handle_test_languages() {
    if (!current_user_can('manage_options')) wp_die('Geen toegang.');
    check_admin_referer('ce_eurostocks_test_languages');

    $opts = get_option(CE_EuroStocks_Importer::OPT_KEY, array());
    $url = rtrim($opts['data_api_base'] ?? 'https://data-api.eurostocks.com', '/') . '/api/v1/languages';

    $res = CE_EuroStocks_API::get_json($url, $opts);
    if (is_wp_error($res)) {
      wp_redirect(CE_EuroStocks_Helpers::admin_url_with_msg(array('ce_err' => rawurlencode('Test mislukt: ' . $res->get_error_message()))));
      exit;
    }

    wp_redirect(CE_EuroStocks_Helpers::admin_url_with_msg(array('ce_msg' => rawurlencode('Test OK. Ontvangen: ' . wp_json_encode($res)))));
    exit;
  }

  public static function handle_manual_import() {
    if (!current_user_can('manage_options')) wp_die('Geen toegang.');
    check_admin_referer('ce_eurostocks_run_import');

    $result = CE_EuroStocks_Importer::run_import();

    // Log this import run
    self::log_import_run($result);

    if (!empty($result['error'])) {
      wp_redirect(CE_EuroStocks_Helpers::admin_url_with_msg(array('ce_err' => rawurlencode($result['error']))));
      exit;
    }

    // Build detailed message
    $msg = sprintf('Batch klaar: %d toegevoegd/bijgewerkt, %d overgeslagen, %d fouten', 
      $result['upserts'], $result['skipped'], $result['errors']);
    
    // Add progress info if available
    if (!empty($result['current_page']) && !empty($result['total_pages'])) {
      $msg .= sprintf(' | Pagina %d van %d (%d%%)', 
        $result['current_page'], $result['total_pages'], $result['progress']);
    }
    
    if (!empty($result['total_records'])) {
      $msg .= sprintf(' | Totaal API: %d', $result['total_records']);
    }
    
    if (!empty($result['db_total'])) {
      $msg .= sprintf(' | In database: %d', $result['db_total']);
    }

    $params = array('ce_msg' => rawurlencode($msg));
    
    // Add statistics to URL for display
    if (isset($result['total_records']) && $result['total_records'] > 0) $params['total_api'] = $result['total_records'];
    if (!empty($result['db_total'])) $params['total_db'] = $result['db_total'];
    if (!empty($result['progress'])) $params['progress'] = $result['progress'];

    if (!empty($result['continue'])) {
      $params['ce_continue'] = 1;
      $params['current_page'] = $result['current_page'] ?? 0;
      $params['total_pages'] = $result['total_pages'] ?? 0;
      wp_redirect(CE_EuroStocks_Helpers::admin_url_with_msg($params));
      exit;
    }
    
    // Import completed
    $params['import_complete'] = 1;
    wp_redirect(CE_EuroStocks_Helpers::admin_url_with_msg($params));
    exit;
  }
  
  private static function log_import_run($result) {
    $log = get_option('ce_eurostocks_import_log', array());
    $run_id = get_option('ce_eurostocks_run_id', 0);
    
    // Check if this is a continuation of an existing run
    $is_continuation = !empty($result['continue']);
    $is_completion = !empty($result['completed']);
    
    // Find existing entry for this run_id
    $existing_index = null;
    foreach ($log as $index => $entry) {
      if (isset($entry['run_id']) && $entry['run_id'] == $run_id) {
        $existing_index = $index;
        break;
      }
    }
    
    if ($existing_index !== null) {
      // Update existing entry
      $log[$existing_index]['upserts'] += $result['upserts'] ?? 0;
      $log[$existing_index]['skipped'] += $result['skipped'] ?? 0;
      $log[$existing_index]['errors'] += $result['errors'] ?? 0;
      $log[$existing_index]['page'] = $result['current_page'] ?? $log[$existing_index]['page'];
      $log[$existing_index]['total_pages'] = $result['total_pages'] ?? $log[$existing_index]['total_pages'];
      $log[$existing_index]['timestamp'] = current_time('mysql');
      
      if ($is_completion) {
        $log[$existing_index]['completed'] = true;
        $log[$existing_index]['continue'] = false;
      }
    } else {
      // Create new entry
      $entry = array(
        'run_id' => $run_id,
        'timestamp' => current_time('mysql'),
        'upserts' => $result['upserts'] ?? 0,
        'skipped' => $result['skipped'] ?? 0,
        'errors' => $result['errors'] ?? 0,
        'continue' => $is_continuation,
        'page' => $result['current_page'] ?? null,
        'total_pages' => $result['total_pages'] ?? null,
        'completed' => $is_completion,
      );
      
      array_unshift($log, $entry);
    }
    
    // Keep last 50 entries
    $log = array_slice($log, 0, 50);
    
    update_option('ce_eurostocks_import_log', $log);
  }

  public static function handle_test_image() {
    if (!current_user_can('manage_options')) wp_die('Geen toegang.');
    check_admin_referer('ce_eurostocks_test_image');

    $raw = get_option('ce_eurostocks_last_raw', '');
    if (!$raw) {
      wp_redirect(CE_EuroStocks_Helpers::admin_url_with_msg(array('ce_err' => rawurlencode('Geen raw JSON gevonden. Draai eerst een import.'))));
      exit;
    }
    $data = json_decode($raw, true);
    $url = '';
    if (is_array($data) && !empty($data['images']) && is_array($data['images']) && !empty($data['images'][0]['ref'])) {
      $url = (string)$data['images'][0]['ref'];
    }
    if (!$url) {
      wp_redirect(CE_EuroStocks_Helpers::admin_url_with_msg(array('ce_err' => rawurlencode('Geen image URL gevonden in laatste raw JSON.'))));
      exit;
    }

    $head = wp_remote_head($url, array('timeout' => 20));
    if (is_wp_error($head)) {
      wp_redirect(CE_EuroStocks_Helpers::admin_url_with_msg(array('ce_err' => rawurlencode('HEAD request faalde: ' . $head->get_error_message()))));
      exit;
    }

    $code = wp_remote_retrieve_response_code($head);
    $ct = wp_remote_retrieve_header($head, 'content-type');
    $cl = wp_remote_retrieve_header($head, 'content-length');
    $msg = 'HEAD OK. HTTP ' . $code . ', content-type: ' . $ct . ', content-length: ' . $cl;
    wp_redirect(CE_EuroStocks_Helpers::admin_url_with_msg(array('ce_msg' => rawurlencode($msg))));
    exit;
  }

  public static function handle_delete_missing() {
    if (!current_user_can('manage_options')) wp_die('Geen toegang.');
    check_admin_referer('ce_eurostocks_delete_missing');

    $opts = get_option(CE_EuroStocks_Importer::OPT_KEY, array());
    if (empty($opts['confirm_delete_missing'])) {
      wp_redirect(CE_EuroStocks_Helpers::admin_url_with_msg(array('ce_err' => rawurlencode('Bevestiging ontbreekt. Vink eerst aan: "handmatige opschoning mag definitief verwijderen".'))));
      exit;
    }

    $run_id = (int)get_option('ce_eurostocks_run_id', 0);
    if (!$run_id) {
      wp_redirect(CE_EuroStocks_Helpers::admin_url_with_msg(array('ce_err' => rawurlencode('Geen run-id gevonden. Draai eerst een import.'))));
      exit;
    }

    $r = CE_EuroStocks_Importer::delete_missing_posts($run_id, true);
    $msg = sprintf('Ontbrekende producten verwijderd. Posts: %d, Bijlagen: %d.', (int)$r['deleted_posts'], (int)$r['deleted_attachments']);
    wp_redirect(CE_EuroStocks_Helpers::admin_url_with_msg(array('ce_msg' => rawurlencode($msg))));
    exit;
  }

  public static function handle_show_last_raw() {
    if (!current_user_can('manage_options')) wp_die('Geen toegang.');
    check_admin_referer('ce_eurostocks_show_last_raw');

    $raw = get_option('ce_eurostocks_last_raw', '');
    if (!$raw) {
      wp_redirect(CE_EuroStocks_Helpers::admin_url_with_msg(array('ce_err' => rawurlencode('Geen raw JSON gevonden. Draai eerst een import.'))));
      exit;
    }

    // show a short snippet to avoid huge URLs; full JSON is in meta box per post
    $snippet = substr((string)$raw, 0, 900);
    wp_redirect(CE_EuroStocks_Helpers::admin_url_with_msg(array('ce_msg' => rawurlencode('Laatste raw JSON (eerste 900 chars): ' . $snippet))));
    exit;
  }

  public static function handle_purge() {
    if (!current_user_can('manage_options')) wp_die('Geen toegang.');
    check_admin_referer('ce_eurostocks_purge');

    $r = CE_EuroStocks_Importer::purge_all_data();
    $msg = sprintf('Alles verwijderd. Posts: %d, Termen: %d, Afbeeldingen: %d.', (int)$r['deleted_posts'], (int)$r['deleted_terms'], (int)$r['deleted_attachments']);
    wp_redirect(CE_EuroStocks_Helpers::admin_url_with_msg(array('ce_msg' => rawurlencode($msg))));
    exit;
  }

  // Delegate to extension class for new features
  public static function handle_test_location() {
    return CE_EuroStocks_Admin_Extensions::handle_test_location();
  }

  public static function register_bulk_actions($bulk_actions) {
    return CE_EuroStocks_Admin_Extensions::register_bulk_actions($bulk_actions);
  }

  public static function handle_bulk_actions($redirect_to, $doaction, $post_ids) {
    return CE_EuroStocks_Admin_Extensions::handle_bulk_actions($redirect_to, $doaction, $post_ids);
  }

  public static function bulk_action_notices() {
    return CE_EuroStocks_Admin_Extensions::bulk_action_notices();
  }

  public static function add_admin_filters($post_type) {
    return CE_EuroStocks_Admin_Extensions::add_admin_filters($post_type);
  }

  public static function filter_admin_query($query) {
    return CE_EuroStocks_Admin_Extensions::filter_admin_query($query);
  }

  public static function add_dashboard_widget() {
    return CE_EuroStocks_Admin_Extensions::add_dashboard_widget();
  }

  public static function handle_export_csv() {
    return CE_EuroStocks_Admin_Extensions::handle_export_csv();
  }

  public static function render_info_metabox($post) {
    $eurostocks_id = get_post_meta($post->ID, '_ce_eurostocks_ad_id', true);
    $last_seen = get_post_meta($post->ID, '_ce_last_seen', true);
    $created = get_post_meta($post->ID, '_ce_created_date', true);
    $updated = get_post_meta($post->ID, '_ce_last_updated_date', true);
    
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
    $price = get_post_meta($post->ID, '_ce_price', true);
    $currency = get_post_meta($post->ID, '_ce_price_currency', true);
    $price_incl_vat = get_post_meta($post->ID, '_ce_price_incl_vat', true);
    $vat_percentage = get_post_meta($post->ID, '_ce_price_vat_percentage', true);
    $price_ex_vat = get_post_meta($post->ID, '_ce_price_ex_vat', true);
    $stock = get_post_meta($post->ID, '_ce_stock', true);
    $condition = get_post_meta($post->ID, '_ce_condition', true);
    $delivery = get_post_meta($post->ID, '_ce_delivery', true);
    
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
    // Toon ALLE velden, ook lege voor debugging
    $all_fields = array(
      'Basis Info' => array(
        'EuroStocks ID' => '_ce_eurostocks_ad_id',
        'Subcategorie' => '_ce_subcategory',
        'Product Type' => '_ce_product_type',
      ),
      'Motor Specificaties' => array(
        'Motorinhoud' => '_ce_engine_capacity',
        'Vermogen (kW)' => '_ce_power_kw',
        'Vermogen (pk)' => '_ce_power_hp',
        'Brandstof (parsed)' => '_ce_fuel',
        'Brandstof (API)' => '_ce_fuel_type',
      ),
      'Versnellingsbak' => array(
        'Transmissie' => '_ce_transmission',
        'Gear Type' => '_ce_gear_type',
      ),
      'Conditie & Gebruik' => array(
        'Kilometerstand (numeriek)' => '_ce_km_value',
        'Kilometerstand (tekst)' => '_ce_km_raw',
        'Garantie (maanden)' => '_ce_warranty_months',
        'Garantie (tekst)' => '_ce_warranty_raw',
      ),
      'Identificatie' => array(
        'Fabrikant' => '_ce_manufacturer',
        'Bouwjaar' => '_ce_year',
        'Onderdeelnummer' => '_ce_part_number',
        'OEM Nummer' => '_ce_oem_number',
        'EAN' => '_ce_ean',
        'SKU' => '_ce_sku',
      ),
      'Fysieke Eigenschappen' => array(
        'Gewicht' => '_ce_weight',
        'Lengte' => '_ce_length',
        'Breedte' => '_ce_width',
        'Hoogte' => '_ce_height',
        'Kleur' => '_ce_color',
      ),
      'Leverancier & Locatie' => array(
        'Leverancier Naam' => '_ce_supplier_name',
        'Leverancier ID' => '_ce_supplier_id',
        'Locatie' => '_ce_location',
      ),
      'Datums' => array(
        'Aangemaakt' => '_ce_created_date',
        'Laatst gewijzigd' => '_ce_last_updated_date',
        'Laatst gezien (sync)' => '_ce_last_seen',
      ),
    );
    
    echo '<table class="form-table" style="margin:0;"><tbody>';
    
    foreach ($all_fields as $section_title => $fields) {
      echo '<tr><th colspan="2" style="padding:12px 0 4px 0; border-bottom:2px solid #ddd; background:#f9f9f9;"><strong>' . esc_html($section_title) . '</strong></th></tr>';
      
      foreach ($fields as $label => $meta_key) {
        $value = get_post_meta($post->ID, $meta_key, true);
        
        // Format specific fields
        if ($meta_key === '_ce_km_value' && $value) {
          $value = number_format((int)$value, 0, ',', '.') . ' km';
        } elseif ($meta_key === '_ce_warranty_months' && $value) {
          $m = (int)$value;
          if ($m >= 12 && $m % 12 === 0) {
            $value = ($m / 12) . ' jaar';
          } else {
            $value = $m . ' ' . ($m === 1 ? 'maand' : 'maanden');
          }
        } elseif ($meta_key === '_ce_last_seen' && $value) {
          $value = date('d-m-Y H:i', (int)$value);
        } elseif (in_array($meta_key, array('_ce_part_number', '_ce_oem_number', '_ce_ean', '_ce_sku', '_ce_eurostocks_ad_id'))) {
          if ($value) $value = '<code>' . esc_html($value) . '</code>';
        }
        
        // Show all fields, mark empty ones
        if ($value === '' || $value === false || $value === null) {
          echo '<tr><th style="width:40%; padding:8px 0; opacity:0.5;">' . esc_html($label) . '</th>';
          echo '<td style="padding:8px 0; color:#999; font-style:italic;">Geen data</td></tr>';
        } else {
          echo '<tr><th style="width:40%; padding:8px 0;">' . esc_html($label) . '</th>';
          echo '<td style="padding:8px 0;">' . $value . '</td></tr>';
        }
      }
    }
    
    echo '</tbody></table>';
    
    // Debug info
    echo '<details style="margin-top:16px; padding:12px; background:#f0f0f0; border-radius:4px;">';
    echo '<summary style="cursor:pointer; font-weight:bold;">🔍 Debug: Alle post meta velden</summary>';
    echo '<div style="margin-top:12px;">';
    $all_meta = get_post_meta($post->ID);
    if (!empty($all_meta)) {
      echo '<table style="width:100%; font-size:11px; font-family:monospace;"><tr><th style="text-align:left; padding:4px; background:#fff;">Meta Key</th><th style="text-align:left; padding:4px; background:#fff;">Waarde (preview)</th></tr>';
      foreach ($all_meta as $key => $values) {
        if (strpos($key, '_ce_') === 0 || strpos($key, 'eurostocks') !== false) {
          $val = is_array($values) ? $values[0] : $values;
          $preview = is_string($val) && strlen($val) > 100 ? substr($val, 0, 100) . '...' : $val;
          echo '<tr><td style="padding:4px; background:#fff;"><code>' . esc_html($key) . '</code></td>';
          echo '<td style="padding:4px; background:#fff;">' . esc_html($preview) . '</td></tr>';
        }
      }
      echo '</table>';
    } else {
      echo '<p style="color:#999;">Geen meta velden gevonden.</p>';
    }
    echo '</div>';
    echo '</details>';
  }

  public static function render_gallery_metabox($post) {
    $gallery_json = get_post_meta($post->ID, '_ce_gallery', true);
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
    
    $img_err = get_post_meta($post->ID, '_ce_image_errors', true);
    if (!empty($img_err)) {
      echo '<details style="margin-top:12px;"><summary style="cursor:pointer; color:#d63638;">⚠ Afbeeldingen errors</summary>';
      echo '<pre style="background:#fff; padding:8px; border:1px solid #ddd; border-radius:4px; font-size:11px; overflow:auto; max-height:200px; margin-top:8px;">' . esc_html($img_err) . '</pre>';
      echo '</details>';
    }
  }
}
