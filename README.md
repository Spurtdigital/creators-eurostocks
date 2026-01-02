# Creators EuroStocks Importer

WordPress plugin voor het importeren en synchroniseren van automotoren en versnellingsbakken vanuit de EuroStocks API.

## Versie
0.5.0

## Vereisten
- WordPress 5.8+
- PHP 7.4+
- EuroStocks API credentials (Username, Password, API Key)

## Installatie

1. Upload de plugin naar `wp-content/plugins/creators-eurostocks/`
2. Activeer de plugin via WordPress admin → Plugins
3. Ga naar Instellingen → Creators EuroStocks Import
4. Vul je EuroStocks API credentials in
5. Configureer de import instellingen
6. Klik op "Start import nu" om de eerste import te draaien

## Automatische Synchronisatie (Cron)

De plugin gebruikt **WordPress WP-Cron** voor automatische dagelijkse synchronisatie.

### Cron Activeren

1. Ga naar **Instellingen → Creators EuroStocks Import**
2. Vink aan: **"Sync inschakelen - Dagelijkse sync via WP-Cron"**
3. Klik op **"Instellingen opslaan"**
4. De cron wordt automatisch geactiveerd bij plugin activatie

### Hoe werkt de Cron?

- **Frequentie**: Eenmaal per dag
- **Eerste run**: 2 minuten na plugin activatie
- **Cron hook**: `ce_eurostocks_cron_sync`
- **Automatisch**: Ja, zolang WordPress bezoekers krijgt (WP-Cron is bezoeker-gebaseerd)

### Cron Status Controleren

Je kunt de cron status controleren met een plugin zoals:
- **WP Crontrol** (aanbevolen)
- **Advanced Cron Manager**

Met WP Crontrol kun je:
- Zien wanneer de volgende sync gepland staat
- Handmatig de cron triggeren voor testing
- De cron hook `ce_eurostocks_cron_sync` bekijken

### Echte Server Cron (Aanbevolen voor Productie)

Voor betrouwbaarder synchronisatie kun je WordPress WP-Cron uitschakelen en een **echte server cron** instellen:

**1. Schakel WordPress WP-Cron uit**

Voeg toe aan `wp-config.php`:
```php
define('DISABLE_WP_CRON', true);
```

**2. Stel een server cron job in**

Via cPanel, Plesk of SSH, voeg deze cron job toe:

```bash
# Draait elke dag om 3:00 's nachts
0 3 * * * wget -q -O - https://jouwwebsite.nl/wp-cron.php?doing_wp_cron >/dev/null 2>&1
```

Of met `curl`:
```bash
0 3 * * * curl -s https://jouwwebsite.nl/wp-cron.php?doing_wp_cron >/dev/null 2>&1
```

**3. Test de cron**

```bash
# Handmatig triggeren via commandline
wp cron event run ce_eurostocks_cron_sync
```

### Troubleshooting Cron

**Cron draait niet?**

1. Controleer of WP-Cron enabled is (niet `DISABLE_WP_CRON`)
2. Zorg dat je website regelmatig bezoekers krijgt (WP-Cron wordt getriggerd door bezoeken)
3. Controleer WordPress debug.log voor errors
4. Test handmatig via WP Crontrol plugin

**Import stopt halverwege?**

- Verhoog `max_runtime` in de instellingen (bijv. van 20 naar 30 seconden)
- De import herstart automatisch in batches als er een `cpl_continue=1` parameter is

## Configuratie

### API Instellingen
- **Username**: Je EuroStocks gebruikersnaam
- **Password**: Je EuroStocks wachtwoord
- **API Key**: Je EuroStocks API sleutel
- **Location ID**: Je locatie ID (bijvoorbeeld 915)
- **Taal**: ISO taalcode (standaard: nl)

### Import Instellingen
- **Sync inschakelen**: Dagelijkse automatische sync via WP-Cron
- **Afbeeldingen ophalen**: Download afbeeldingen automatisch
- **Wat wil je importeren**: Kies tussen automotoren, versnellingsbakken, of beide
- **Max runtime per run**: Voorkomt timeouts (standaard: 20 seconden)

## Custom Post Type

De plugin maakt een Custom Post Type aan: `ce_part`
- **Slug**: `/automotoren/`
- **Label**: Onderdelen
- **Ondersteunt**: Title, Editor, Featured Image, Excerpt

## Taxonomieën

### ce_make (Merken)
Auto merken zoals Mercedes, BMW, Audi, etc.

### ce_model (Modellen)
Automodellen zoals C-Klasse, 3-Serie, A4, etc.

### ce_engine_code (Motorcodes)
Specifieke motorcodes zoals OM651, N47, etc.

### ce_part_type (Type onderdeel)
- Automotor
- Versnellingsbak

## Backend Velden

In de WordPress backend zie je bij elk onderdeel de volgende metaboxes:

### EuroStocks Info
- EuroStocks ID
- Laatst gesynchroniseerd datum
- Aanmaakdatum in EuroStocks  
- Laatst gewijzigd in EuroStocks
- Velden analyse (diagnostics)
- Raw JSON data

### Prijs & Voorraad (Sidebar)
- Prijs (geformatteerd met valuta)
- Prijs incl. BTW
- BTW percentage
- Voorraadstatus (groen = op voorraad, rood = niet op voorraad)
- Conditie
- Levering

### Specificaties
Complete tabel met alle specificaties:
- Motorinhoud, vermogen (kW/pk), brandstof
- Transmissie en versnellingsbak type
- Kilometerstand en garantie
- Fabrikant, bouwjaar
- Onderdeelnummer, OEM nummer, EAN, SKU
- Afmetingen (LxBxH) en gewicht
- Kleur
- Leverancier en locatie

### Afbeeldingen (Sidebar)
- Thumbnail grid van alle afbeeldingen
- Eerste afbeelding gemarkeerd als "Uitgelicht"
- Direct links naar volledige afbeeldingen
- Eventuele download errors

## Beschikbare Velden

De plugin slaat alle EuroStocks data op als WordPress custom fields met het `_ce_` prefix:

### Basis Informatie
- `_ce_eurostocks_ad_id` - EuroStocks advertentie ID
- `_ce_stock` - Voorraad aantal
- `_ce_condition` - Conditie (nieuw, gebruikt, etc.)
- `_ce_delivery` - Levering informatie
- `_ce_subcategory` - Subcategorie
- `_ce_product_type` - Product type

### Prijs
- `_ce_price` - Prijs
- `_ce_price_currency` - Valuta (EUR, USD, etc.)
- `_ce_price_vat_percentage` - BTW percentage
- `_ce_price_incl_vat` - Prijs inclusief BTW
- `_ce_price_ex_vat` - Flag: Prijs is exclusief BTW (0/1)

### Product Specificaties
- `_ce_ean` - EAN barcode
- `_ce_sku` - SKU nummer
- `_ce_weight` - Gewicht
- `_ce_height` - Hoogte
- `_ce_width` - Breedte
- `_ce_length` - Lengte
- `_ce_color` - Kleur
- `_ce_year` - Bouwjaar

### Motor Specificaties
- `_ce_engine_capacity` - Motorinhoud (cc)
- `_ce_power_kw` - Vermogen in kW
- `_ce_power_hp` - Vermogen in pk
- `_ce_fuel` - Brandstof (geparsed uit beschrijving)
- `_ce_fuel_type` - Brandstof type (direct uit API)

### Versnellingsbak
- `_ce_transmission` - Transmissie type
- `_ce_gear_type` - Versnellingsbak type

### Identificatie
- `_ce_manufacturer` - Fabrikant
- `_ce_part_number` - Onderdeelnummer
- `_ce_oem_number` - OEM nummer

### Kilometerstand & Garantie
- `_ce_km_raw` - Kilometerstand (ruwe tekst)
- `_ce_km_value` - Kilometerstand (numeriek)
- `_ce_warranty_raw` - Garantie (ruwe tekst)
- `_ce_warranty_months` - Garantie in maanden

### Afbeeldingen
- `_ce_images` - JSON array met alle afbeelding URLs
- `_ce_gallery` - JSON array met WordPress attachment IDs
- `_ce_image_refs` - Referentie URLs om dubbel downloaden te voorkomen

### Leverancier & Locatie
- `_ce_location` - Locatie/opslagplaats
- `_ce_supplier_name` - Leverancier naam
- `_ce_supplier_id` - Leverancier ID

### Datums
- `_ce_created_date` - Aanmaakdatum
- `_ce_last_updated_date` - Laatst gewijzigd datum
- `_ce_last_seen` - Laatst gezien in import (run ID)

### Debug
- `_ce_raw_details` - Volledige API response (JSON)

## Template Gebruik

### Helper Functions

De plugin biedt helper functions voor eenvoudig gebruik in templates:

```php
// Basis veld ophalen
$stock = CE_EuroStocks_Helpers::get_field('_ce_stock');

// Prijs geformatteerd
$price = CE_EuroStocks_Helpers::get_price(); // € 2.500,00

// Voorraad status
if (CE_EuroStocks_Helpers::is_in_stock()) {
  echo 'Op voorraad!';
}

// Kilometerstand geformatteerd
$km = CE_EuroStocks_Helpers::get_mileage(); // 125.000 km

// Garantie geformatteerd
$warranty = CE_EuroStocks_Helpers::get_warranty(); // 12 maanden garantie

// Gallery
$gallery_ids = CE_EuroStocks_Helpers::get_gallery();

// Specificaties als array
$specs = CE_EuroStocks_Helpers::get_specifications();
```

### Single Template Voorbeeld

Maak een bestand: `single-cpl_part.php` in je thema:

```php
<?php get_header(); ?>

<div class="cpl-part-single">
  <?php while (have_posts()) : the_post(); ?>
    
    <div class="part-header">
      <h1><?php the_title(); ?></h1>
      
      <?php if (CE_EuroStocks_Helpers::is_in_stock()): ?>
        <span class="badge in-stock">Op voorraad</span>
      <?php else: ?>
        <span class="badge out-of-stock">Niet op voorraad</span>
      <?php endif; ?>
    </div>

    <div class="part-content">
      
      <!-- Afbeeldingen -->
      <div class="part-images">
        <!-- Uitgelichte afbeelding -->
        <?php if (has_post_thumbnail()): ?>
          <div class="featured-image">
            <?php the_post_thumbnail('large'); ?>
          </div>
        <?php endif; ?>
        
        <!-- Gallery -->
        <?php 
        $gallery = CE_EuroStocks_Helpers::get_gallery();
        if (!empty($gallery)): 
        ?>
          <div class="gallery">
            <?php foreach ($gallery as $attachment_id): ?>
              <a href="<?php echo esc_url(wp_get_attachment_url($attachment_id)); ?>" class="gallery-item">
                <?php echo wp_get_attachment_image($attachment_id, 'thumbnail'); ?>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Prijs -->
      <div class="part-price">
        <h2><?php echo CE_EuroStocks_Helpers::get_price(); ?></h2>
        <?php if (CE_EuroStocks_Helpers::get_field('_ce_price_ex_vat')): ?>
          <p class="vat-notice">Excl. BTW</p>
        <?php endif; ?>
      </div>

      <!-- Specificaties -->
      <div class="part-specs">
        <h3>Specificaties</h3>
        <?php 
        $specs = CE_EuroStocks_Helpers::get_specifications();
        if (!empty($specs)): 
        ?>
          <table class="specs-table">
            <?php foreach ($specs as $label => $value): ?>
              <tr>
                <th><?php echo esc_html($label); ?></th>
                <td><?php echo esc_html($value); ?></td>
              </tr>
            <?php endforeach; ?>
          </table>
        <?php endif; ?>
      </div>

      <!-- Beschrijving -->
      <div class="part-description">
        <h3>Beschrijving</h3>
        <?php the_content(); ?>
      </div>

      <!-- Taxonomieën -->
      <div class="part-taxonomies">
        <?php 
        $makes = get_the_terms(get_the_ID(), 'cpl_make');
        if ($makes && !is_wp_error($makes)): 
        ?>
          <div class="part-makes">
            <strong>Merken:</strong>
            <?php foreach ($makes as $make): ?>
              <a href="<?php echo esc_url(get_term_link($make)); ?>" class="badge">
                <?php echo esc_html($make->name); ?>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php 
        $models = get_the_terms(get_the_ID(), 'cpl_model');
        if ($models && !is_wp_error($models)): 
        ?>
          <div class="part-models">
            <strong>Modellen:</strong>
            <?php foreach ($models as $model): ?>
              <a href="<?php echo esc_url(get_term_link($model)); ?>" class="badge">
                <?php echo esc_html($model->name); ?>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

    </div>

  <?php endwhile; ?>
</div>

<?php get_footer(); ?>
```

### Archive Template Voorbeeld

Maak een bestand: `archive-cpl_part.php` in je thema:

```php
<?php get_header(); ?>

<div class="cpl-parts-archive">
  <h1><?php post_type_archive_title(); ?></h1>

  <?php if (have_posts()): ?>
    <div class="parts-grid">
      <?php while (have_posts()) : the_post(); ?>
        
        <article class="part-card">
          <a href="<?php the_permalink(); ?>">
            
            <!-- Afbeelding -->
            <?php if (has_post_thumbnail()): ?>
              <div class="part-thumbnail">
                <?php the_post_thumbnail('medium'); ?>
                
                <?php if (!CE_EuroStocks_Helpers::is_in_stock()): ?>
                  <span class="out-of-stock-overlay">Niet op voorraad</span>
                <?php endif; ?>
              </div>
            <?php endif; ?>

            <!-- Content -->
            <div class="part-content">
              <h2><?php the_title(); ?></h2>
              
              <!-- Prijs -->
              <?php if ($price = CE_EuroStocks_Helpers::get_price()): ?>
                <p class="price"><?php echo $price; ?></p>
              <?php endif; ?>

              <!-- Snelle specs -->
              <ul class="quick-specs">
                <?php if ($km = CE_EuroStocks_Helpers::get_mileage()): ?>
                  <li><strong>Km:</strong> <?php echo esc_html($km); ?></li>
                <?php endif; ?>
                
                <?php if ($fuel = CE_EuroStocks_Helpers::get_field('_ce_fuel')): ?>
                  <li><strong>Brandstof:</strong> <?php echo esc_html($fuel); ?></li>
                <?php endif; ?>
                
                <?php if ($warranty = CE_EuroStocks_Helpers::get_warranty()): ?>
                  <li><strong>Garantie:</strong> <?php echo esc_html($warranty); ?></li>
                <?php endif; ?>
              </ul>

              <!-- Merken -->
              <?php 
              $makes = get_the_terms(get_the_ID(), 'cpl_make');
              if ($makes && !is_wp_error($makes)): 
              ?>
                <div class="part-makes">
                  <?php foreach ($makes as $make): ?>
                    <span class="badge"><?php echo esc_html($make->name); ?></span>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>

          </a>
        </article>

      <?php endwhile; ?>
    </div>

    <!-- Pagination -->
    <div class="pagination">
      <?php 
      the_posts_pagination(array(
        'prev_text' => '← Vorige',
        'next_text' => 'Volgende →',
      )); 
      ?>
    </div>

  <?php else: ?>
    <p>Geen onderdelen gevonden.</p>
  <?php endif; ?>
</div>

<?php get_footer(); ?>
```

### Basis CSS Voorbeeld

```css
/* Parts Grid */
.parts-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 24px;
  margin: 32px 0;
}

.part-card {
  border: 1px solid #ddd;
  border-radius: 8px;
  overflow: hidden;
  transition: box-shadow 0.3s;
}

.part-card:hover {
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.part-card a {
  text-decoration: none;
  color: inherit;
  display: block;
}

.part-thumbnail {
  position: relative;
  aspect-ratio: 4/3;
  overflow: hidden;
  background: #f5f5f5;
}

.part-thumbnail img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.out-of-stock-overlay {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  background: rgba(255,0,0,0.9);
  color: white;
  padding: 8px 16px;
  border-radius: 4px;
  font-weight: bold;
}

.part-content {
  padding: 16px;
}

.part-content h2 {
  font-size: 18px;
  margin: 0 0 12px 0;
}

.price {
  font-size: 24px;
  font-weight: bold;
  color: #2c5f2d;
  margin: 8px 0;
}

.quick-specs {
  list-style: none;
  padding: 0;
  margin: 12px 0;
  font-size: 14px;
}

.quick-specs li {
  margin: 4px 0;
}

.badge {
  display: inline-block;
  background: #f0f0f0;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 12px;
  margin: 2px;
}

.badge.in-stock {
  background: #d4edda;
  color: #155724;
}

.badge.out-of-stock {
  background: #f8d7da;
  color: #721c24;
}

/* Gallery */
.gallery {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
  gap: 8px;
  margin: 16px 0;
}

.gallery-item {
  aspect-ratio: 1;
  overflow: hidden;
  border-radius: 4px;
}

.gallery-item img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.3s;
}

.gallery-item:hover img {
  transform: scale(1.1);
}

/* Specs Table */
.specs-table {
  width: 100%;
  border-collapse: collapse;
  margin: 16px 0;
}

.specs-table th,
.specs-table td {
  padding: 12px;
  text-align: left;
  border-bottom: 1px solid #ddd;
}

.specs-table th {
  font-weight: 600;
  width: 40%;
  background: #f8f9fa;
}
```

## Direct Custom Field Gebruik

Als je liever direct met WordPress functies werkt:

```php
// Prijs ophalen
$price = get_post_meta(get_the_ID(), '_ce_price', true);
echo '€ ' . number_format((float)$price, 2, ',', '.');

// Voorraad checken
$stock = (int)get_post_meta(get_the_ID(), '_ce_stock', true);
if ($stock > 0) {
  echo 'Op voorraad: ' . $stock;
}

// Kilometerstand
$km = get_post_meta(get_the_ID(), '_ce_km_value', true);
if ($km) {
  echo number_format((int)$km, 0, ',', '.') . ' km';
}

// Gallery IDs
$gallery_json = get_post_meta(get_the_ID(), '_ce_gallery', true);
$gallery = json_decode($gallery_json, true);
if (is_array($gallery)) {
  foreach ($gallery as $attachment_id) {
    echo wp_get_attachment_image($attachment_id, 'medium');
  }
}

// Motorcode taxonomie
$codes = get_the_terms(get_the_ID(), 'cpl_engine_code');
if ($codes && !is_wp_error($codes)) {
  foreach ($codes as $code) {
    echo $code->name;
  }
}
```

## WooCommerce Integratie

Als je WooCommerce gebruikt, kun je producten automatisch creëren:

```php
// Voeg dit toe aan functions.php
add_action('save_post_ce_part', 'sync_to_woocommerce', 10, 1);

function sync_to_woocommerce($post_id) {
  // Check if WooCommerce is active
  if (!class_exists('WooCommerce')) return;
  
  // Get price
  $price = get_post_meta($post_id, '_ce_price', true);
  $stock = get_post_meta($post_id, '_ce_stock', true);
  
  // Create or update WooCommerce product
  // ... implementatie hier
}
```

## Troubleshooting

### Import loopt vast
- Verhoog `max_runtime` in de instellingen
- Check WordPress debug.log voor errors
- Zorg dat PHP `max_execution_time` hoog genoeg is

### Afbeeldingen worden niet gedownload
- Controleer of `download_images` is aangevinkt
- Check `_ce_image_errors` meta field voor foutmeldingen
- Verhoog PHP `memory_limit` en `upload_max_filesize`

### Geen producten zichtbaar
- Check of de import is voltooid zonder errors
- Ga naar Posts → Onderdelen om te zien of er posts zijn
- Check of de post status "Publish" is

## Support

Voor vragen of problemen, check de diagnostics:
- Ga naar een onderdeel post
- Bekijk de "📊 Beschikbare velden analyse" in de metabox
- Check de "Velden Diagnostics" op de instellingen pagina

## Changelog

### 0.4.0
- **Universele branding**: Plugin hernoemd naar "Creators EuroStocks"
- **Backend metaboxes**: Alle velden nu zichtbaar in overzichtelijke metaboxes
  - Prijs & Voorraad sidebar met visuele status
  - Uitgebreide specificaties tabel
  - Afbeeldingen gallery preview
- Volledige veld-mapping van alle EuroStocks API velden
- Helper functions voor eenvoudig template gebruik
- Verbeterde gallery en featured image handling
- Uitgebreide documentatie en voorbeelden

### 0.3.7
- Initiële versie met basis import functionaliteit

## Licentie

Proprietary - Creators
