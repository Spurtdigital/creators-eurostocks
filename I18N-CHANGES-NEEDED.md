# Complete I18N Changes for Creators EuroStocks Plugin

This document lists ALL Dutch strings that need to be wrapped with i18n functions.

## Text Domain
All functions use: `'creators-eurostocks'`

## Function Usage Guide
- `__('string', 'creators-eurostocks')` - Returns translated string
- `_e('string', 'creators-eurostocks')` - Echoes translated string
- `esc_html__('string', 'creators-eurostocks')` - Returns escaped translated string
- `esc_attr__('string', 'creators-eurostocks')` - Returns escaped attribute

---

## includes/importer.php

### COMPLETED ✅
Lines 39-40: Post type labels
```php
'name' => __('Onderdelen', 'creators-eurostocks'),
'singular_name' => __('Onderdeel', 'creators-eurostocks'),
```

Lines 51-76: Taxonomy labels
```php
'labels' => array('name' => __('Merken', 'creators-eurostocks'), 'singular_name' => __('Merk', 'creators-eurostocks')),
'labels' => array('name' => __('Modellen', 'creators-eurostocks'), 'singular_name' => __('Model', 'creators-eurostocks')),
'labels' => array('name' => __('Motorcodes', 'creators-eurostocks'), 'singular_name' => __('Motorcode', 'creators-eurostocks')),
'labels' => array('name' => __('Type onderdeel', 'creators-eurostocks'), 'singular_name' => __('Type onderdeel', 'creators-eurostocks')),
```

Lines 90, 95: Error messages
```php
$err = __('API instellingen ontbreken (username/password/api key).', 'creators-eurostocks');
$err = __('Location ID ontbreekt.', 'creators-eurostocks');
```

Line 227: Part type labels
```php
$part_type_label = $is_engine ? __('Automotor', 'creators-eurostocks') : __('Versnellingsbak', 'creators-eurostocks');
```

---

## includes/admin.php

### Metabox Titles (Lines 11-15) - COMPLETED ✅
```php
add_meta_box('ce_eurostocks_info', __('EuroStocks Info', 'creators-eurostocks'), ...
add_meta_box('ce_eurostocks_specs', __('Specificaties', 'creators-eurostocks'), ...
add_meta_box('ce_eurostocks_price', __('Prijs & Voorraad', 'creators-eurostocks'), ...
add_meta_box('ce_eurostocks_gallery', __('Afbeeldingen', 'creators-eurostocks'), ...
add_meta_box('ce_eurostocks_debug', __('Debug & Diagnostics', 'creators-eurostocks'), ...
```

### Debug Metabox (Lines 28-44) - TODO
```php
Line 28: '<p><strong>' . esc_html__('Snelle velden', 'creators-eurostocks') . '</strong></p>'
Line 30: 'Kilometerstand', 'creators-eurostocks'
Line 31: 'Garantie', 'creators-eurostocks'
        'maanden', 'creators-eurostocks'
Line 32: 'Brandstof', 'creators-eurostocks'
Line 33: 'Prijs ex BTW (uit tekst)', 'creators-eurostocks'
        'Ja', 'creators-eurostocks' / 'Nee/onbekend', 'creators-eurostocks'
Line 37: 'Afbeeldingen errors', 'creators-eurostocks'
Line 41: 'Toon alle info (raw JSON)', 'creators-eurostocks'
Line 42: 'Handig om te zien welke velden we nog missen of kunnen mappen.', 'creators-eurostocks'
```

### Settings Page (Lines 123-302) - TODO
```php
Line 124: 'Creators EuroStocks Import'
Line 130: 'Sync inschakelen'
Line 134: 'Dagelijkse sync via WP-Cron'
Line 139: 'Authenticatie'
Line 154: 'API instellingen'
Line 167: 'Bijvoorbeeld 915'
Line 170: 'Taal (ISO)'
Line 174: 'Import'
Line 177: 'Afbeeldingen ophalen'
Line 181: 'Download afbeeldingen en zet de eerste als uitgelichte afbeelding'
Line 187: 'Ontbrekende producten'
Line 191: 'Markeer producten die niet meer in EuroStocks staan als niet op voorraad (stock = 0)'
Line 195: 'Ik snap het: handmatige opschoning mag ontbrekende producten definitief verwijderen (incl. bijlagen)'
Line 201: 'Wat wil je importeren?'
Line 204-206: 'Alleen automotoren', 'Alleen versnellingsbakken', 'Automotoren + versnellingsbakken'
Line 212: 'SearchText (optioneel)'
Line 214: 'Laat leeg om alles op te halen (aanrader). Gebruik alleen voor test.'
Line 238: 'Voorkomt max execution time. 15–25 sec werkt meestal goed.'
Line 248: 'Instellingen opslaan'
Line 253: 'Tools'
Line 258: 'Test Data API (languages)'
Line 264: 'Start import nu'
Line 270: 'Verwijder alle data (posts + termen)'
Line 276: 'Verwijder ontbrekende producten'
Line 277: 'Verwijdert posts die in de laatste import niet zijn teruggekomen. Werkt alleen als je de bevestiging hierboven aanvinkt.'
```

### Handler Functions (Lines 305-423) - TODO
```php
Line 306: 'Geen toegang.'
Line 314: 'Test mislukt: '
Line 318: 'Test OK. Ontvangen: '
Line 323: 'Geen toegang.'
Line 329-340: Import batch messages
Line 345-375: Test image messages  
Line 378-396: Delete missing messages
Line 400-412: Show last raw messages
Line 416-422: Purge messages
```

### Info Metabox (Lines 458-478) - TODO
```php
Line 466: 'EuroStocks ID:'
Line 469: 'Laatst gesynchroniseerd:'
Line 472: 'Aangemaakt in EuroStocks:'
Line 475: 'Laatst gewijzigd in EuroStocks:'
```

### Price Metabox (Lines 480-523) - TODO
```php
Line 493: 'Prijs:', 'Excl. BTW', 'Incl. BTW:', 'BTW %:'
Line 505: 'Voorraad:'
Line 508: 'Op voorraad', 'Niet op voorraad', 'Onbekend'
Line 517: 'Conditie:', 'Levering:'
```

### Specs Metabox (Lines 525-635) - TODO
All section titles and field labels need translation

### Gallery Metabox (Lines 637-666) - TODO
```php
Line 649: 'Uitgelicht'
Line 655: 'Totaal: X afbeeldingen'
Line 657: 'Geen afbeeldingen beschikbaar.'
Line 662: 'Afbeeldingen errors'
```

---

## includes/admin-extensions.php

### Handler Functions - TODO
```php
Line 22: 'Geen toegang.'
Line 27: 'Location ID ontbreekt.'
Line 41: 'Location ID is geldig! (Product ID 1 niet gevonden, maar authenticatie werkt)'
Line 44: 'Location ID test mislukt: '
Line 48: 'Location ID test OK! Product data opgehaald.'
Line 56-57: Bulk action labels
Line 85-89: Bulk action notices
Line 103-105: Filter dropdown labels
```

### Dashboard Widget (Lines 146-196) - TODO
```php
Line 149: 'EuroStocks Import Status'
Line 174-194: All dashboard widget labels and messages
```

### CSV Export (Lines 198-288) - TODO
```php
Line 202: 'Geen toegang.'
Line 214: 'Geen producten gevonden om te exporteren.'
Line 230-252: CSV column headers
```

---

## includes/helpers.php

### Template Helpers (Lines 247-304) - TODO
```php
Line 247: 'jaar', 'maanden', 'garantie'
Line 265-303: All specification labels
```

---

## SUMMARY

**Total Strings to Translate:** ~180+

**Breakdown:**
- includes/importer.php: 7 strings ✅ DONE
- includes/admin.php: ~120 strings (5 done, 115 TODO)
- includes/admin-extensions.php: ~35 strings
- includes/helpers.php: ~25 strings

**Status:** importer.php and admin metabox titles complete. Remaining files need systematic translation.
