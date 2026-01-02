# WPML Ondersteuning - Implementatie Compleet ✅

## Wat is Geïmplementeerd

### 1. ✅ WordPress i18n Basis
**Bestanden aangepast:**
- `cpl-engines-eurostocks-importer.php`: Text domain toegevoegd + translation loading functie
- `includes/importer.php`: Alle CPT/taxonomy labels vertaalbaar gemaakt
- `languages/creators-eurostocks.pot`: POT template bestand gegenereerd

**Wat dit betekent:**
- Plugin interface is nu vertaalbaar
- Post type labels ('Onderdelen', 'Merk', 'Model', etc.) gebruiken `__()` functies
- Klaar voor vertalingen via Poedit of Loco Translate

### 2. ✅ WPML Configuratie
**Bestanden toegevoegd:**
- `wpml-config.xml`: Complete WPML configuratie

**Wat dit doet:**
- Custom Post Type `ce_part` geregistreerd als vertaalbaar
- Alle taxonomies (`ce_make`, `ce_model`, etc.) geregistreerd als vertaalbaar
- Custom fields gecategoriseerd:
  - **Translate**: Tekstvelden (`_ce_fuel`, `_ce_condition`, etc.)
  - **Copy**: Technische data (`_ce_price`, `_ce_stock`, `_ce_km_value`, etc.)
  - **Ignore**: Debug data (`_ce_raw_details`, `_ce_last_seen`)

### 3. ✅ WPML Programmatische Integratie
**Bestanden aangepast:**
- `includes/importer.php`: WPML + Polylang hooks toegevoegd in `register_cpt_and_taxonomies()`

**Code toegevoegd:**
```php
// WPML: Register CPT as translatable
if (function_exists('do_action')) {
  do_action('wpml_register_single_type', self::CPT);
}

// WPML: Register taxonomies as translatable
if (function_exists('do_action')) {
  do_action('wpml_register_taxonomy', 'ce_make');
  do_action('wpml_register_taxonomy', 'ce_model');
  do_action('wpml_register_taxonomy', 'ce_engine_code');
  do_action('wpml_register_taxonomy', 'ce_part_type');
}

// Polylang: Register CPT and taxonomies
if (function_exists('add_filter')) {
  add_filter('pll_get_post_types', function($post_types) {
    $post_types[self::CPT] = self::CPT;
    return $post_types;
  });
  
  add_filter('pll_get_taxonomies', function($taxonomies) {
    $taxonomies['ce_make'] = 'ce_make';
    $taxonomies['ce_model'] = 'ce_model';
    $taxonomies['ce_engine_code'] = 'ce_engine_code';
    $taxonomies['ce_part_type'] = 'ce_part_type';
    return $taxonomies;
  });
}
```

## Hoe Te Gebruiken

### Met WPML Geïnstalleerd

1. **Activeer WPML**
   - Installeer WPML + WPML String Translation
   - Configureer talen (bijv. NL, EN, DE)

2. **Posts Vertalen**
   - Ga naar Posts → Onderdelen
   - Klik op het + icoon naast een post om een vertaling toe te voegen
   - WPML toont vertaaleditor

3. **Automatische API Import in Meerdere Talen** (Optioneel - Nog te implementeren)
   - Zie `WPML-TRANSLATIONS.md` → "Oplossing 3: Multi-Taal Import"
   - Hiervoor moet extra code toegevoegd worden om per product meerdere talen op te halen

### Met Polylang Geïnstalleerd

1. **Activeer Polylang**
   - Installeer Polylang (gratis)
   - Configureer talen

2. **Posts Vertalen**
   - Werkt vergelijkbaar met WPML
   - Gebruik Polylang interface om vertalingen te maken

### Plugin Interface Vertalen

1. **Nederlandse Vertaling Maken**
   ```bash
   # In plugin directory
   cd languages
   wp i18n make-po creators-eurostocks.pot creators-eurostocks-nl_NL.po
   # Of gebruik Poedit om .pot te openen en te vertalen
   ```

2. **Andere Talen**
   ```bash
   wp i18n make-po creators-eurostocks.pot creators-eurostocks-de_DE.po
   wp i18n make-po creators-eurostocks.pot creators-eurostocks-en_US.po
   ```

## Nog Te Doen (Optioneel)

### Resterende i18n Strings
De Task agent heeft ~7 strings in importer.php vertaald. Er staan nog ~168 strings in andere bestanden:
- `includes/admin.php`: ~115 strings (settings pagina, knoppen, etc.)
- `includes/admin-extensions.php`: ~35 strings (bulk actions, dashboard)
- `includes/helpers.php`: ~25 strings (template helpers)

**Zie:** `I18N-CHANGES-NEEDED.md` voor complete lijst met lijnnummers

### Automatische Multi-Taal Import
Voor automatisch importeren van producten in meerdere talen:
- Zie `WPML-TRANSLATIONS.md` → "Oplossing 3" voor complete implementatie
- Vereist uitbreiding van importer logic om:
  1. Per taal API call te doen
  2. Posts te linken via WPML/Polylang API
  3. Settings toe te voegen voor taal selectie

## Testen

1. **Controleer WPML Config**
   - WPML → Settings → Custom Posts/Taxonomies
   - Controleer of `ce_part` en taxonomies zichtbaar zijn

2. **Test Vertaling**
   - Maak een test post in NL
   - Voeg EN vertaling toe
   - Controleer of custom fields correct gekopieerd/vertaald worden

3. **Test Import**
   - Run import
   - Controleer of posts aangemaakt worden
   - Als WPML actief is, controleer of ze als "origineel" (NL) gemarkeerd zijn

## Bestanden Gewijzigd/Toegevoegd

**Gewijzigd:**
- `cpl-engines-eurostocks-importer.php` (text domain + loading)
- `includes/importer.php` (i18n labels + WPML hooks)

**Toegevoegd:**
- `wpml-config.xml` (WPML configuratie)
- `languages/creators-eurostocks.pot` (translation template)

**Backup:**
- `includes/importer.php.backup` (originele versie)

## Ondersteuning

- **WPML Docs**: https://wpml.org/documentation/
- **Polylang Docs**: https://polylang.pro/doc/
- **WordPress i18n**: https://developer.wordpress.org/plugins/internationalization/

## Troubleshooting

**Posts niet vertaalbaar in WPML?**
- Check WPML → Settings → Post Types Synchronization
- Deactiveer en heractiveer plugin om hooks te triggeren

**Taxonomies niet gesynchroniseerd?**
- Check `wpml-config.xml` aanwezig is in plugin root
- WPML scant config bij plugin activatie

**Strings niet vertaalbaar?**
- Check of `languages/creators-eurostocks.pot` bestaat
- Regenereer met: `wp i18n make-pot . languages/creators-eurostocks.pot`

