# EuroStocks Import Plugin - Setup Guide

## 📋 Inhoudsopgave

1. [EuroStocks API Credentials](#eurostocks-api-credentials)
2. [Plugin Configuratie](#plugin-configuratie)
3. [WPML Integratie](#wpml-integratie)
4. [Eerste Import](#eerste-import)
5. [Troubleshooting](#troubleshooting)

---

## 🔐 EuroStocks API Credentials

### Waar krijg je deze gegevens?

Deze credentials krijg je van **EuroStocks** wanneer je een account aanmaakt.

### Benodigde Gegevens

| Veld | Waar te vinden | Voorbeeld |
|------|---------------|-----------|
| **Username** | EuroStocks account | `motorenplaza_bv` |
| **Password** | EuroStocks account | `****************` |
| **API Key** | EuroStocks dashboard → API Settings | `24BC4A70-6E15-46F7-840B-487E8B6A2859` |
| **Location ID** | EuroStocks dashboard → Locations | `915` |

### Hoe kom je aan een EuroStocks account?

1. **Contact EuroStocks**: https://eurostocks.com/contact
2. **Vraag een dealer account aan**
3. **Ontvang credentials per email**
4. **Login op**: https://www.eurostocks.com/
5. **Ga naar API Settings** voor je API Key en Location ID

---

## ⚙️ Plugin Configuratie

### 1. Authenticatie

Ga naar: `WordPress Admin → Settings → Creators EuroStocks Import`

```
┌─────────────────────────────────────────┐
│ Authenticatie                           │
├─────────────────────────────────────────┤
│ Username:    motorenplaza_bv            │
│ Password:    ****************           │
│ API Key:     24BC4A70-6E15-46F7-...     │
└─────────────────────────────────────────┘
```

**Vul hier je EuroStocks credentials in** (zie hierboven).

---

### 2. API Instellingen

```
┌──────────────────────────────────────────────────────────┐
│ API instellingen                                          │
├──────────────────────────────────────────────────────────┤
│ Data API Base URL:                                       │
│ https://data-api.eurostocks.com                          │
│                                                           │
│ Product Data API Base URL:                               │
│ https://products-data-api.eurostocks.com                 │
│                                                           │
│ Location ID:  915                                        │
│ (Dit is jouw warehouse/locatie ID bij EuroStocks)       │
│                                                           │
│ Taal (ISO):  nl                                          │
│ (ISO 639-1 code: nl, en, de, fr, it, es, etc.)          │
└──────────────────────────────────────────────────────────┘
```

#### Data API Base URL
**Standaard:** `https://data-api.eurostocks.com`  
**Gebruikt voor:** 
- Product search/list endpoints
- Ophalen van talen
- Filtering en sorting

**Wijzig alleen als:** EuroStocks je een ander endpoint geeft.

#### Product Data API Base URL
**Standaard:** `https://products-data-api.eurostocks.com`  
**Gebruikt voor:**
- Gedetailleerde product informatie
- Product afbeeldingen
- Extra metadata

**Wijzig alleen als:** EuroStocks je een ander endpoint geeft.

#### Location ID
**Wat is dit?** Jouw unieke warehouse/locatie ID bij EuroStocks.

**Waar vind je dit?**
1. Login op EuroStocks dashboard
2. Ga naar "Locations" of "Vestigingen"
3. Kopieer je Location ID (bijv. `915`)

**Waarom belangrijk?** De API filtert producten op basis van deze Location ID.

#### Taal (ISO)
**Wat is dit?** ISO 639-1 taalcode (2 letters).

**Beschikbare talen:**
- `nl` - Nederlands
- `en` - Engels
- `de` - Duits
- `fr` - Frans
- `it` - Italiaans
- `es` - Spaans

**Wat doet dit?**
- Bepaalt de taal van product titels en beschrijvingen
- Gebruikt voor API communicatie
- **Let op:** Dit is NIET hetzelfde als WPML (zie verderop)

---

### 3. Import Instellingen

```
┌──────────────────────────────────────────────────────────┐
│ Import                                                    │
├──────────────────────────────────────────────────────────┤
│ ☑ Afbeeldingen ophalen                                   │
│   Download afbeeldingen en zet eerste als featured image │
│                                                           │
│ ☑ Markeer ontbrekende producten als niet op voorraad    │
│ ☑ Bevestiging: mag ontbrekende producten verwijderen    │
│                                                           │
│ Wat importeren:  [Automotoren + versnellingsbakken ▼]   │
│                                                           │
│ SearchText: [leeg laten voor alles]                      │
│                                                           │
│ SortOn:     LastUpdatedDate                              │
│ SortOrder:  desc                                          │
│                                                           │
│ PageSize:   50                                           │
│ Max runtime per run: 20 sec                              │
│ Max pages:  200                                          │
└──────────────────────────────────────────────────────────┘
```

**Aanbevolen instellingen voor eerste import:**
- Afbeeldingen ophalen: **AAN** (tenzij je veel producten hebt)
- Wat importeren: **Automotoren + versnellingsbakken**
- PageSize: **50** (balans tussen snelheid en server load)
- Max runtime: **20 sec** (voorkomt timeout)

---

## 🌍 WPML Integratie

### Wat is WPML?

WPML (WordPress Multilingual Plugin) is een premium plugin voor meertalige WordPress sites.

**Website:** https://wpml.org/

### Hoe Werkt WPML met Deze Plugin?

De EuroStocks Import plugin is **WPML-ready**, wat betekent:

#### ✅ Automatisch Ondersteund

1. **Custom Post Type (`ce_part`)**
   - Wordt automatisch geregistreerd bij WPML
   - Producten kunnen vertaald worden
   
2. **Taxonomieën**
   - `ce_make` (Merk)
   - `ce_model` (Model)
   - `ce_engine_code` (Motorcode)
   - `ce_part_type` (Onderdeeltype)
   - Worden automatisch gesynchroniseerd

3. **Custom Fields**
   - Prijs, voorraad, garantie, etc.
   - Worden automatisch gekopieerd naar vertalingen

#### 📋 Setup WPML (Stap voor Stap)

**Stap 1: Installeer WPML**
```bash
WordPress Admin → Plugins → Add New → Upload WPML
```

**Stap 2: Configureer Talen**
```
WPML → Languages → Add languages
```
Bijvoorbeeld:
- 🇳🇱 Nederlands (standaard)
- 🇬🇧 Engels
- 🇩🇪 Duits

**Stap 3: Controleer Post Type Registratie**
```
WPML → Settings → Post Types Translation
```
Zorg dat `ce_part` (Onderdelen) staat op **"Translatable"**.

**Stap 4: Configureer Taxonomieën**
```
WPML → Taxonomy Translation
```
Zorg dat deze op **"Translate"** staan:
- Merk (ce_make)
- Model (ce_model)
- Motorcode (ce_engine_code)
- Onderdeeltype (ce_part_type)

**Stap 5: Vertaal Producten**

Er zijn 2 manieren:

##### Optie A: Handmatige Vertaling
```
Posts → Onderdelen → [Select product] → WPML box → + icon
```
Vertaal titel, beschrijving, en custom fields handmatig.

##### Optie B: Automatische Vertaling (WPML Advanced Translation Editor)
```
WPML → Translation Management → Select content → Send to translation
```
Gebruik WPML's eigen vertaal-editor of koppel aan DeepL/Google Translate.

**Stap 6: String Translations (Plugin Interface)**

De plugin interface (admin teksten) vertalen:
```
WPML → String Translation → Filter: "creators-eurostocks"
```
Vertaal admin labels zoals:
- "Start import nu" → "Start import now"
- "Batch klaar" → "Batch complete"
- etc.

---

### 🔄 EuroStocks Taal vs WPML Talen

**Belangrijk:** Dit zijn 2 VERSCHILLENDE dingen!

| Setting | Doel | Voorbeeld |
|---------|------|-----------|
| **EuroStocks Taal (ISO)** | Taal van data uit API | `nl` = Nederlandse producttitels van EuroStocks |
| **WPML Talen** | Website talen voor bezoekers | `nl`, `en`, `de` = Site beschikbaar in 3 talen |

#### Scenario 1: Eéntalige Site met NL Data
```
EuroStocks Taal: nl
WPML: Niet nodig
```
**Resultaat:** Nederlandse producten op Nederlandse site.

#### Scenario 2: Meertalige Site met NL Data
```
EuroStocks Taal: nl
WPML Talen: nl (default), en, de
```
**Workflow:**
1. Import haalt Nederlandse data op van EuroStocks
2. Producten worden aangemaakt in Nederlands (standaard taal)
3. WPML vertaalt naar Engels en Duits (handmatig of automatisch)

**Resultaat:** 
- `site.nl/onderdelen/bmw-motor-123` (Nederlands)
- `site.nl/en/parts/bmw-engine-123` (Engels, vertaald)
- `site.nl/de/teile/bmw-motor-123` (Duits, vertaald)

#### Scenario 3: Meerdere EuroStocks Imports (Geavanceerd)

Als je producten in **meerdere talen** wilt importeren van EuroStocks:

**Optie A: Aparte Import per Taal** ⚠️ (Niet aanbevolen - duplicaten!)
```
Import 1: EuroStocks Taal = nl → WordPress NL posts
Import 2: EuroStocks Taal = en → WordPress EN posts
```
**Probleem:** Duplicaten! Elke import maakt nieuwe posts.

**Optie B: Eén Import + WPML Vertaling** ✅ (Aanbevolen)
```
Import: EuroStocks Taal = nl
WPML: Vertaal posts naar en, de
```
**Voordeel:** Geen duplicaten, correcte WPML relaties.

---

### 📝 WPML Custom Fields Configuratie

De plugin heeft `wpml-config.xml` die custom fields definieert.

**Locatie:** `/wp-content/plugins/creators-eurostocks/wpml-config.xml`

**Wat doet dit?**
- Vertelt WPML welke custom fields gekopieerd moeten worden
- Markeert prijs/voorraad als "copy" (niet vertalen)
- Markeert beschrijvingen als "translate" (wel vertalen)

**Voorbeeld uit wpml-config.xml:**
```xml
<custom-fields>
  <custom-field action="copy">_ce_price</custom-field>
  <custom-field action="copy">_ce_stock</custom-field>
  <custom-field action="translate">_ce_description</custom-field>
</custom-fields>
```

**Als je extra fields wilt toevoegen**, edit dit bestand.

---

## 🚀 Eerste Import

### 1. Test de API Connectie

```
Settings → Creators EuroStocks Import → Test Data API (languages)
```

**Verwachte output:**
```
Test OK. Ontvangen: {"nl":"Nederlands","en":"English",...}
```

**Als dit faalt:**
- Check username, password, API key
- Check Data API Base URL
- Contact EuroStocks support

---

### 2. Start Import

```
Settings → Creators EuroStocks Import → Start import nu
```

**Wat gebeurt er?**
1. Plugin haalt producten op van EuroStocks API
2. Verwerkt 50 producten per batch (PageSize)
3. Stopt na 20 seconden (Max runtime)
4. Refresht automatisch elke 2 seconden
5. Herhaalt tot alle pagina's zijn verwerkt

**Verwachte output:**
```
Batch klaar: 50 toegevoegd/bijgewerkt, 0 overgeslagen, 0 fouten | 
Pagina 1 van 8 (13%) | 
Totaal API: 383 | 
In database: 50

[Progress bar: 13%]
[Auto-refresh na 2 seconden...]
```

---

### 3. Monitor Import

**Via Admin UI:**
- Progress bar toont percentage
- Statistieken tabel toont API totaal vs Database
- Import logboek toont laatste runs

**Via Browser Console (F12):**
```
EUROSTOCKS: Auto-continue script loading...
EUROSTOCKS: Form found: true
EUROSTOCKS: Starting auto-submit timer (2 seconds)
EUROSTOCKS: Clicking submit button
```

**Via WordPress Debug Log:**
```
tail -f wp-content/debug.log | grep EUROSTOCKS
```

---

### 4. Na Import

**Check Geïmporteerde Producten:**
```
Posts → Onderdelen
```

Je zou moeten zien:
- Producten met titels van EuroStocks
- Featured images (als "Afbeeldingen ophalen" aan stond)
- Taxonomieën: Merk, Model, etc.

**Check Custom Fields:**

Open een product → Scroll naar beneden → Meta boxes:
- Prijs & Voorraad
- Technische Specificaties
- EuroStocks Info
- Raw JSON Data (voor debugging)

---

## 🔧 Troubleshooting

### Probleem: "API Error (401)"

**Oorzaak:** Verkeerde credentials.

**Oplossing:**
1. Check Username, Password, API Key
2. Test via: https://data-api.eurostocks.com/api/v1/languages
   ```bash
   curl -H "UserName: jouw_username" \
        -H "Password: jouw_password" \
        -H "APIKey: jouw_api_key" \
        https://data-api.eurostocks.com/api/v1/languages
   ```
3. Contact EuroStocks als credentials correct zijn

---

### Probleem: "Totaal API: 0"

**Oorzaak:** API stuurt geen producten voor je Location ID.

**Oplossing:**
1. Check Location ID in settings
2. Login op EuroStocks dashboard
3. Controleer of je producten hebt op die locatie
4. Test met `test-api-response.php`:
   ```
   https://jouw-site.nl/wp-content/plugins/creators-eurostocks/test-api-response.php
   ```

---

### Probleem: Auto-refresh werkt niet

**Oorzaak:** JavaScript wordt geblokkeerd.

**Oplossing:**
1. Open browser console (F12)
2. Check voor errors
3. Filter op "EUROSTOCKS"
4. Als je `Form found: false` ziet, refresh de pagina
5. Disable browser extensions (AdBlock, etc.)

---

### Probleem: Producten komen niet in WPML

**Oorzaak:** Post type niet translatable.

**Oplossing:**
```
WPML → Settings → Post Types Translation
→ Check "ce_part" is set to "Translatable"
```

---

### Probleem: Custom fields worden niet gekopieerd naar vertalingen

**Oorzaak:** WPML weet niet welke fields te kopiëren.

**Oplossing:**
1. Check of `wpml-config.xml` bestaat
2. Check of custom fields in XML staan
3. Heractiveer plugin:
   ```
   Plugins → Deactivate → Activate
   ```
4. WPML scant wpml-config.xml opnieuw

---

### Probleem: Images worden niet gedownload

**Oorzaak:** Download kan falen door CDN protectie.

**Debug:**
1. Check WordPress debug.log
2. Zoek naar "Image download failed"
3. Test image URL handmatig in browser

**Oplossing:**
- Images hebben vaak CDN protectie
- Plugin stuurt User-Agent en Referer headers
- Als het blijft falen, schakel "Afbeeldingen ophalen" uit
- Gebruik externe image URL in plaats van downloaden

---

## 📚 Nuttige Links

- **EuroStocks Website:** https://eurostocks.com/
- **EuroStocks API Docs:** (Vraag aan EuroStocks support)
- **WPML Documentatie:** https://wpml.org/documentation/
- **Plugin GitHub:** (Als beschikbaar)
- **WordPress Codex:** https://codex.wordpress.org/

---

## 💡 Tips & Tricks

### Tip 1: Scheduled Imports

Schakel dagelijkse sync in:
```
Settings → Sync inschakelen ☑
```
Plugin gebruikt WP-Cron om elke dag te synchroniseren.

### Tip 2: Import Logging

Check `ce_eurostocks_import_log` optie voor geschiedenis:
```php
// In functions.php of custom plugin
$log = get_option('ce_eurostocks_import_log');
print_r($log);
```

### Tip 3: Bulk Update Prijzen

Als EuroStocks prijzen update, run opnieuw import:
- Bestaande producten worden geüpdatet (op basis van EuroStocks ID)
- Nieuwe producten worden toegevoegd
- Ontbrekende producten worden gemarkeerd als niet op voorraad

### Tip 4: WPML String Scannen

Laat WPML alle plugin strings scannen:
```
WPML → Theme and plugins localization → Scan
```

---

## ❓ Veelgestelde Vragen

**Q: Kan ik producten handmatig toevoegen naast de import?**  
A: Ja, maar zorg dat ze geen `_ce_eurostocks_ad_id` meta field hebben, anders kunnen ze overschreven worden.

**Q: Worden afbeeldingen opnieuw gedownload bij elke import?**  
A: Nee, plugin slaat `_ce_downloaded_images` op om duplicaten te voorkomen.

**Q: Kan ik de import stoppen?**  
A: Ja, klik op "Stop import" knop tijdens import.

**Q: Wat gebeurt er met producten die niet meer in EuroStocks staan?**  
A: Als "Markeer ontbrekende producten" aan staat, wordt stock op 0 gezet. Je kunt ze handmatig verwijderen via bulk action.

**Q: Ondersteunt de plugin Polylang in plaats van WPML?**  
A: Ja! De plugin is compatible met zowel WPML als Polylang via `wpml-config.xml`.

**Q: Kan ik eigen custom fields toevoegen?**  
A: Ja, edit `includes/importer.php` → `upsert_part_post()` functie.

---

**Versie:** 0.5.0  
**Laatst bijgewerkt:** 2 januari 2026  
**Auteur:** CPL Engines / Motorenplaza B.V.
