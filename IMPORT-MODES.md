# 🚗 Import Modes - EuroStocks Onderdelen Categorieën

## 📋 Alle Beschikbare Import Opties

### **Specifieke Onderdelen** (8 opties)

1. **Alleen automotoren** (`engines`)
   - Motoren en motorblokken
   - Detectie: `subCategory=MOTOR_AND_ACCESSORIES` of `productType` bevat `ENGINE_` of `MOTOR`

2. **Alleen versnellingsbakken** (`gearboxes`)
   - Handgeschakelde en automatische versnellingsbakken
   - Detectie: `productType` bevat `GEAR_BOX` of `subCategory=TRANSMISSION_DRIVE_AND_ACCESSORIES`

3. **Alleen turbo's** (`turbos`)
   - Turboladers en turbochargers
   - Detectie: `productType` of titel bevat `TURBO`

4. **Alleen katalysatoren** (`catalysts`)
   - Katalysatoren en uitlaatsystemen
   - Detectie: `productType` of titel bevat `CATALYST` of `KATALYSATOR`

5. **Alleen startmotoren** (`starters`)
   - Startmotoren en onderdelen
   - Detectie: `productType` of titel bevat `STARTER` of `STARTMOTOR`

6. **Alleen dynamo's** (`alternators`)
   - Alternators en dynamo's
   - Detectie: `productType` of titel bevat `ALTERNATOR` of `DYNAMO`

7. **Alleen airco compressors** (`ac_compressors`)
   - Airconditioning compressors
   - Detectie: `productType` of titel bevat `AIRCO` of `AC_COMPRESSOR`

8. **Alleen stuurbekrachtiging pompen** (`power_steering`)
   - Stuurbekrachtiging pompen en systemen
   - Detectie: `productType` of titel bevat `POWER_STEERING` of `STUURBEKRACHTIGING`

---

### **Combinaties** (3 opties)

9. **Motoren + Versnellingsbakken** (`engines_gearboxes`)
   - Alle motoren EN alle versnellingsbakken
   - Populairste combinatie voor auto-onderdelen handelaren

10. **Alle motoronderdelen** (`engine_parts`)
    - Motoren
    - Turbo's
    - Startmotoren
    - Dynamo's
    - Ideaal voor motorspecialisten

11. **Alle transmissie onderdelen** (`transmission_parts`)
    - Versnellingsbakken
    - Transmissie accessoires
    - Ideaal voor transmissie specialisten

---

### **Alles** (1 optie)

12. **✨ Alles importeren** (`all`)
    - **ALLE** EuroStocks producten zonder filtering
    - Ideaal voor algemene auto-onderdelen handelaren
    - Importeert ALLES wat EuroStocks aanbiedt

---

## 🎯 Welke Mode Kiezen?

### Voor Motorspecialisten
```
✅ Alle motoronderdelen
   (motoren, turbo's, starters, dynamo's)
```

### Voor Transmissie Specialisten
```
✅ Alle transmissie onderdelen
   (versnellingsbakken + accessoires)
```

### Voor Algemene Handelaren
```
✅ Alles importeren
   (alle categorieën, geen filtering)
```

### Voor Specifieke Niche
```
✅ Kies één specifiek onderdeel
   (bijv. alleen turbo's of alleen katalysatoren)
```

---

## 🔧 Hoe Werkt de Filtering?

De plugin detecteert product types op 3 manieren:

1. **Via subCategory** (van EuroStocks API)
   - Bijvoorbeeld: `MOTOR_AND_ACCESSORIES`

2. **Via productType** (van EuroStocks API)
   - Bijvoorbeeld: `ENGINE_DIESEL`, `GEAR_BOX_AUTOMATIC`

3. **Via product titel** (backup methode)
   - Bijvoorbeeld: titel bevat "TURBO" of "KATALYSATOR"

**Smart Matching:**
- Case-insensitive matching (`stripos`)
- Meerdere talen ondersteund (Engels + Nederlands)
- Flexibel: werkt met variaties in API data

---

## 📊 Voorbeelden

### Voorbeeld 1: Alleen Turbo's
```
Instellingen → Wat wil je importeren?
▼ Specifieke onderdelen
  ⚪ Alleen automotoren
  ⚪ Alleen versnellingsbakken
  🔘 Alleen turbo's  ← Geselecteerd
```

**Resultaat:** Importeert ALLEEN turboladers

---

### Voorbeeld 2: Motoronderdelen
```
Instellingen → Wat wil je importeren?
▼ Combinaties
  ⚪ Motoren + Versnellingsbakken
  🔘 Alle motoronderdelen  ← Geselecteerd
```

**Resultaat:** Importeert motoren, turbo's, starters, dynamo's

---

### Voorbeeld 3: Alles
```
Instellingen → Wat wil je importeren?
▼ Alles
  🔘 ✨ Alles importeren (alle categorieën)  ← Geselecteerd
```

**Resultaat:** Importeert ALLE producten van EuroStocks

---

## 🔄 Backward Compatibility

Bestaande configuraties blijven werken:

| Oude Waarde | Nieuwe Betekenis | Blijft Werken? |
|-------------|------------------|----------------|
| `engines` | Alleen automotoren | ✅ Ja |
| `gearboxes` | Alleen versnellingsbakken | ✅ Ja |
| `both` | Motoren + Versnellingsbakken | ✅ Ja |

**Geen actie nodig!** Bestaande installaties werken gewoon door.

---

## 🧪 Testen

### Test 1: Check welke producten worden geïmporteerd
1. Stel import mode in
2. Klik "Start import nu"
3. Check de geïmporteerde posts in **Posts → Onderdelen**
4. Verifieer dat alleen de juiste categorieën zijn geïmporteerd

### Test 2: Switch tussen modes
1. Importeer met mode "Alleen turbo's"
2. Wissel naar "Alleen katalysatoren"
3. Start nieuwe import
4. Verifieer dat bestaande turbo's blijven staan (niet verwijderd)

### Test 3: "Alles importeren"
1. Zet mode op "✨ Alles importeren"
2. Start import
3. Check dat er producten zijn van verschillende types
4. Controleer totaal aantal (moet VEEL hoger zijn dan alleen motoren)

---

## 📝 Technische Details

### Locatie Code
- **UI:** `includes/admin.php` regel 222-247
- **Filtering Logic:** `includes/importer.php` regel 270-308
- **Functie:** `matches_import_mode($details, $mode)`

### Detectie Logica (pseudo-code)
```php
if ($mode === 'all') return true; // Importeer alles!

// Detect types
$is_turbo = (stripos($type, 'TURBO') !== false) 
         || (stripos($title, 'TURBO') !== false);

// Check mode
if ($mode === 'turbos') return $is_turbo;
```

### API Fields Gebruikt
- `$details['subCategory']` - Hoofdcategorie
- `$details['productType']` - Product type
- `$details['productInfo'][0]['PRODUCT_TITLE']` - Product naam

---

## 🆘 Troubleshooting

### "Geen producten geïmporteerd"
- Check of je Location ID correct is (gebruik "Test Location ID" button)
- Verifieer dat EuroStocks producten heeft in de gekozen categorie
- Probeer "Alles importeren" om te zien of er überhaupt data binnenkomt

### "Verkeerde producten geïmporteerd"
- Check debug.log voor `matches_import_mode` informatie
- Kijk naar `_ce_raw_details` post meta om te zien wat API stuurt
- Rapporteer edge cases voor betere filtering

### "Te veel producten geïmporteerd"
- Kies een specifiekere mode (bijv. "Alleen turbo's" i.p.v. "Alle motoronderdelen")
- Gebruik SearchText filter voor extra filtering

---

**Versie:** 0.6.0+  
**Feature:** Expanded Import Modes  
**Commit:** 3f76bd4

---

## 🏷️ Taxonomieën

### **Nieuwe Taxonomie Structuur (v0.6.1+)**

De taxonomieën zijn generiek gemaakt voor alle producttypen:

- **Merken** (`ce_make`) - Automerken (BMW, Mercedes, Audi, etc.)
- **Modellen** (`ce_model`) - Automodellen (3 Series, C-Klasse, A4, etc.)
- **Productcodes** (`ce_engine_code`) - Motor codes, turbo codes, transmissie codes, etc.
  - **Let op:** Database naam blijft `ce_engine_code` voor backwards compatibility
  - Label is gewijzigd naar "Productcodes" (was "Motorcodes")
  - Menu naam: "Codes"
- **Type onderdeel** (`ce_part_type`) - Categorie (Motor, Versnellingsbak, Turbo, etc.)

### **URL Structuur (v0.6.1+)**

- CPT: `/onderdelen/` (was: `/automotoren/`)
- Make: `/merk/bmw/`
- Model: `/model/3-series/`
- Code: `/code/n47d20c/` (was: `/motorcode/n47d20c/`)
- Type: `/type/motor/`

**Let op:** Na update naar v0.6.1, ga naar **Instellingen → Permalinks** en klik op **"Wijzigingen opslaan"** om de nieuwe URL structuur te activeren.

