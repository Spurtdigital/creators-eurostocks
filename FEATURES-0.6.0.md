# 🚀 Version 0.6.0 - Nieuwe Features Overzicht

## ✅ Alle Features Geïmplementeerd!

### 📊 Dashboard Widget
**Locatie:** WordPress Dashboard → "EuroStocks Import Status" widget

**Wat zie je:**
- ✅/❌ Status (Actief of Uitgeschakeld)
- Totaal aantal onderdelen in database
- Aantal producten op voorraad
- Timestamp laatste import
- ⚠️ Waarschuwing als import bezig is (met pagina nummer)
- Countdown tot volgende geplande sync
- Directe link naar instellingen pagina

**Gebruiksgemak:** In één oogopslag zie je de status van je EuroStocks integratie!

---

### 🔍 Test Location ID Button
**Locatie:** Instellingen → Creators EuroStocks Import → onder "Test Data API"

**Wat doet het:**
- Test of je Location ID geldig is
- Valideert API credentials (username, password, API key)
- Geeft duidelijke feedback:
  - ✅ "Location ID is geldig!" bij succes
  - ❌ "Location ID test mislukt: [foutmelding]" bij problemen
  - ✅ "Product ID 1 niet gevonden, maar authenticatie werkt" bij 404 (dit is OK!)

**Gebruiksgemak:** Geen gedoe meer met "waarom werkt mijn import niet?" - test eerst!

---

### 📈 Progress Indicator
**Locatie:** Tijdens import in de statistieken tabel

**Wat zie je:**
- "Pagina 5 / 39" in de Pagina kolom
- Percentage voortgang berekening
- Real-time updates terwijl import draait

**Gebruiksgemak:** Je weet altijd hoelang import nog duurt!

---

### 📝 Cron Logging
**Locatie:** WordPress debug.log (als WP_DEBUG = true)

**Wat wordt gelogd:**
```
[CE_EuroStocks INFO] Starting scheduled import via WP-Cron
[CE_EuroStocks ERROR] API credentials missing (username/password/api key)
[CE_EuroStocks INFO] Import completed. Upserts: 45, Skipped: 3, Errors: 0, Total in DB: 383
[CE_EuroStocks INFO] Marking missing products as out of stock
[CE_EuroStocks INFO] Marked 12 products as out of stock
```

**Gebruiksgemak:** Troubleshoot cron problemen makkelijk via logs!

---

### 🔄 Retry Logic voor Afbeeldingen
**Wat doet het:**
- Probeert elke afbeelding **3 keer** te downloaden
- Exponential backoff tussen pogingen:
  - 1e poging: direct
  - 2e poging: na 500ms
  - 3e poging: na 1 seconde
- Alleen retry bij 5xx server errors (niet bij 404)

**Gebruiksgemak:** Minder "afbeelding kon niet worden gedownload" fouten!

---

### ⏱️ API Rate Limiting
**Locatie:** Instellingen → Creators EuroStocks Import → checkbox "API Rate Limiting"

**Wat doet het:**
- Voegt 100ms pauze toe tussen elk API call
- Voorkomt dat je geblokkeerd wordt door EuroStocks API
- Aangeraden voor imports met 100+ producten

**Instelling:** 
```
☑ Voeg 100ms pauze toe tussen API calls (aanbevolen voor grote imports om blokkering te voorkomen)
```

**Gebruiksgemak:** Import is 10% langzamer maar 100% betrouwbaarder!

---

### 📦 Bulk Acties
**Locatie:** Posts → Onderdelen → selecteer producten → Bulkacties dropdown

**Opties:**
1. **"Markeer als niet op voorraad"** - Zet stock op 0 voor geselecteerde producten
2. **"Markeer als op voorraad (1 stuks)"** - Zet stock op 1 voor geselecteerde producten

**Gebruiksgemak:** Snel voorraad aanpassen voor meerdere producten tegelijk!

---

### 🔍 Filter op Voorraad Status
**Locatie:** Posts → Onderdelen → dropdown boven de tabel

**Opties:**
- Alle voorraad (standaard)
- Op voorraad (stock > 0)
- Niet op voorraad (stock ≤ 0)

**Gebruiksgemak:** Vind snel alle producten zonder voorraad!

---

### 🛡️ Veilige Missing Posts Detection
**Wat is verbeterd:**
- Producten worden ALLEEN als "out of stock" gemarkeerd als:
  - Import succesvol compleet is (alle pagina's verwerkt)
  - GEEN fouten tijdens import (errors = 0)
- Bij fouten: waarschuwing in log, maar producten blijven ongewijzigd

**Voorkomt:**
- Data verlies bij API problemen
- Onterecht producten markeren als niet op voorraad
- Paniek bij tijdelijke API downtime

---

## 🎯 Hoe te Gebruiken

### Eerste Keer Setup
1. **Activeer plugin** (indien nog niet gedaan)
2. Ga naar **WordPress Dashboard** → zie nieuwe widget!
3. Ga naar **Instellingen → Creators EuroStocks Import**
4. Vul API credentials in
5. Klik op **"Test Location ID"** button
6. Als test succesvol: klik **"Start import nu"**
7. Bekijk progress indicator tijdens import

### Dagelijks Gebruik
- **Dashboard Widget** → status checken
- **Posts → Onderdelen** → filter/bulk acties gebruiken
- **debug.log** → cron problemen troubleshooten

### Aanbevolen Instellingen
```
☑ Sync inschakelen - Dagelijkse sync via WP-Cron
☑ Download afbeeldingen en zet de eerste als uitgelichte afbeelding
☑ API Rate Limiting (aanbevolen voor grote imports)
☑ Markeer producten die niet meer in EuroStocks staan als niet op voorraad
```

---

## 📊 Feature Vergelijking

| Feature | v0.5.0 | v0.6.0 |
|---------|--------|--------|
| Import functionaliteit | ✅ | ✅ |
| Cron sync | ✅ | ✅ |
| Dashboard widget | ❌ | ✅ |
| Test Location ID | ❌ | ✅ |
| Progress indicator | ❌ | ✅ |
| Cron logging | ❌ | ✅ |
| Image retry logic | ❌ | ✅ |
| API rate limiting | ❌ | ✅ |
| Bulk acties | ❌ | ✅ |
| Voorraad filter | ❌ | ✅ |
| Safe missing detection | ❌ | ✅ |

---

## 🧪 Testing Checklist

Voordat je in productie gaat:

- [ ] Dashboard widget verschijnt op WordPress dashboard
- [ ] "Test Location ID" button geeft correct resultaat
- [ ] Import toont "Pagina X / Y" in statistieken
- [ ] debug.log toont cron logging (zet WP_DEBUG aan)
- [ ] Afbeeldingen worden succesvol gedownload
- [ ] Bulk actie "markeer als niet op voorraad" werkt
- [ ] Filter "Op voorraad" toont alleen producten met stock > 0
- [ ] Bij import met fouten worden producten NIET als missing gemarkeerd

---

## 🐛 Troubleshooting

### Dashboard widget verschijnt niet
- Refresh je dashboard pagina (Ctrl+F5)
- Check of plugin geactiveerd is

### Test Location ID faalt
- Check username, password, API key
- Check Location ID (moet numeriek zijn, bijv. 915)
- Test API verbinding eerst met "Test Data API"

### Cron logging verschijnt niet in debug.log
- Zet `WP_DEBUG` aan in wp-config.php
- Zet `WP_DEBUG_LOG` aan
- Trigger cron handmatig met WP Crontrol plugin

### Afbeeldingen worden niet gedownload
- Check "Download afbeeldingen" checkbox aangevinkt
- Bekijk `_ce_image_errors` post meta voor error details
- Check write permissions voor wp-content/uploads

---

## 📞 Support

Voor vragen of problemen:
1. Check `SETUP-GUIDE.md` voor setup instructies
2. Check `IMPROVEMENTS.md` voor technische details
3. Check WordPress debug.log voor foutmeldingen
4. Check `_ce_image_errors` post meta voor image problemen

---

**Versie:** 0.6.0  
**Datum:** January 2026  
**Plugin:** Creators EuroStocks Importer  
**WordPress:** 5.8+  
**PHP:** 7.4+
