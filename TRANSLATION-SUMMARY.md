# i18n Translation Summary

This document tracks all internationalization changes made to the Creators EuroStocks Importer plugin.

## Files Modified

1. `includes/importer.php` - 7 strings translated
2. `includes/admin.php` - 120+ strings translated  
3. `includes/admin-extensions.php` - 35+ strings translated
4. `includes/helpers.php` - 25+ strings translated

## Text Domain

All translations use the text domain: `creators-eurostocks`

## Translation Function Usage

- `__('string', 'creators-eurostocks')` - For return values
- `_e('string', 'creators-eurostocks')` - For direct echo
- `esc_html__('string', 'creators-eurostocks')` - For escaped HTML output
- `esc_attr__('string', 'creators-eurostocks')` - For escaped attributes

## Strings NOT Translated (as per guidelines)

- API field names (PRODUCT_TITLE, PRICE, etc.)
- Meta keys (_ce_stock, _ce_price, etc.)
- Taxonomy slugs (ce_make, ce_model, etc.)
- Technical constants (POST, GET, etc.)
- Log messages (kept in English for debugging)
- Variable names and code identifiers

## Strings Translated

### User-facing Labels
- Onderdelen, Onderdeel
- Merken, Merk
- Modellen, Model
- Motorcodes, Motorcode
- Type onderdeel
- Automotor, Versnellingsbak
- Specificaties, Afbeeldingen
- Prijs & Voorraad
- etc.

### Error Messages
- API instellingen ontbreken
- Location ID ontbreekt
- Geen toegang
- Test mislukt
- etc.

### Admin Interface
- Form labels
- Button text
- Help text
- Section headings
- Table headers
- Notice messages
- etc.

## Next Steps

To complete the internationalization:

1. Generate POT file: `wp i18n make-pot . languages/creators-eurostocks.pot`
2. Create language files (PO/MO) for Dutch and other languages
3. Place translation files in `languages/` directory
4. Load text domain in main plugin file
