# Changelog - Creators EuroStocks Importer

## Version 0.6.0 - January 2026

### ✨ Nieuwe Features

#### 🎯 High Priority Features

1. **Logging voor Cron Runs** ✅
   - Automatische logging naar WordPress debug.log bij cron runs
   - Error tracking voor API fouten
   - Import statistieken logging (upserts, skipped, errors)
   - Locatie: `includes/importer.php` - `log()` methode

2. **Test Location ID Button** ✅
   - Nieuwe "Test Location ID" knop in admin interface
   - Valideert of Location ID + API credentials correct zijn
   - Duidelijke foutmeldingen bij problemen
   - Locatie: `includes/admin-extensions.php`, `includes/admin.php`

3. **Progress Indicator (Pagina X/Y)** ✅
   - Toont "Pagina 5 van 39" tijdens import
   - Real-time voortgang in statistieken tabel
   - Percentage berekening voor batch imports
   - Locatie: `includes/importer.php` - regel 227-231

4. **Dashboard Widget** ✅
   - "EuroStocks Import Status" widget op WordPress dashboard
   - Toont: Status (actief/uit), totaal onderdelen, voorraad, laatste import
   - Countdown tot volgende geplande sync
   - Link naar instellingenpagina
   - Locatie: `includes/admin-extensions.php` - `render_dashboard_widget()`

#### 🔧 Medium Priority Features

5. **Retry Logic voor Afbeeldingen** ✅
   - 3 pogingen per afbeelding bij download fouten
   - Exponential backoff (500ms, 1s, 1.5s)
   - Verbeterde error handling voor 5xx fouten
   - Locatie: `includes/importer.php` - regel 488-544

6. **API Rate Limiting** ✅
   - Optionele 100ms pauze tussen API calls
   - Voorkomt blokkering door EuroStocks API
   - Instelbaar via checkbox in admin
   - Locatie: `includes/importer.php` regel 204-206, `includes/admin.php`

7. **Bulk Acties** ✅
   - "Markeer als niet op voorraad" bulk actie
   - "Markeer als op voorraad (1 stuks)" bulk actie
   - Werkt op geselecteerde producten in post lijst
   - Locatie: `includes/admin-extensions.php` - `handle_bulk_actions()`

8. **Filter op Voorraad Status** ✅
   - Dropdown filter in admin post lijst
   - Opties: Alle voorraad / Op voorraad / Niet op voorraad
   - Snelle filtering van producten
   - Locatie: `includes/admin-extensions.php` - `add_admin_filters()`

9. **Verbeterde Missing Posts Detection** ✅
   - Markeert alleen producten als out-of-stock als import ZONDER fouten compleet is
   - Voorkomt data verlies bij API problemen
   - Logging van aantal gemarkeerde producten
   - Locatie: `includes/importer.php` - regel 241-250

### 🔐 Veiligheid & Stabiliteit

- **Error Logging**: Alle API fouten worden gelogd voor troubleshooting
- **Safe Missing Detection**: Producten worden alleen gemarkeerd na succesvolle import
- **Retry Logic**: Vermindert image download fouten met 3 pogingen
- **Rate Limiting**: Voorkomt API throttling bij grote imports

### 📊 UX Verbeteringen

- **Test Buttons**: Valideer configuratie voordat je importeert
- **Progress Tracking**: Altijd weten hoever je bent met import
- **Dashboard Widget**: Status in één oogopslag
- **Bulk Actions**: Snelle voorraad aanpassingen
- **Filters**: Vind producten snel

### 🛠️ Technische Details

**Gewijzigde Bestanden:**
- `cpl-engines-eurostocks-importer.php` - Version bump naar 0.6.0
- `includes/importer.php` - Logging, retry logic, safe missing detection
- `includes/admin.php` - Test Location ID button, API rate limiting checkbox
- `includes/admin-extensions.php` - Dashboard widget, bulk actions, filters
- `README.md` - Version update

**Nieuwe Dependencies:**
- Geen! Alle features gebruiken WordPress core functionaliteit

**Database Changes:**
- Geen nieuwe tabellen of meta keys
- Gebruikt bestaande options en post meta

### 🧪 Testing Checklist

- [x] PHP Syntax validatie (alle bestanden)
- [ ] Manual import test
- [ ] Cron logging test (check debug.log)
- [ ] Test Location ID button
- [ ] Dashboard widget display
- [ ] Bulk actions (mark in/out of stock)
- [ ] Filter op voorraad werkt
- [ ] Image retry bij 500 error
- [ ] API rate limiting (100ms delay)
- [ ] Missing detection safety (alleen bij error=0)

### 📝 Gebruikers Documentatie

Zie `SETUP-GUIDE.md` voor:
- API credentials verkrijgen
- Eerste import uitvoeren
- WPML configuratie
- Troubleshooting

## Version 0.5.0 - December 2025

### Basis Functionaliteit
- EuroStocks API integratie
- Custom Post Type voor onderdelen
- Taxonomieën: Merken, Modellen, Motorcodes
- Automatische image downloads
- WPML/Polylang support
- WP-Cron dagelijkse sync
- Batch import met auto-continue
