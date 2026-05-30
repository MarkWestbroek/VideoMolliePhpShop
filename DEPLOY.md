# Deployment — VideoMolliePhpShop

Stappen om de site te deployen op Plesk (getest op `video.msss.nl`).

---

## Vereisten

- Toegang tot Plesk
- FTP-client (bijv. FileZilla) of Plesk bestandsbeheer
- Mollie account met API-sleutel

---

## ⚠️ PHP-versie controleren

Deze site vereist **minimaal PHP 8.0**.

1. Plesk → jouw domein → **PHP**
2. Controleer de versie. Als dit 7.x is: klik op PHP en kies versie **8.1** of **8.2**
3. Sla op

> Op het screenshot staat PHP 7.4.33 — dit moet omhoog vóór de site werkt.

---

## Stap 1 — Database aanmaken

1. Plesk → **Databases** → **Database toevoegen**
2. Kies een naam (bijv. `videos`), maak ook een databasegebruiker aan en noteer:
   - Databasenaam
   - Gebruikersnaam
   - Wachtwoord
3. Klik op de nieuwe database → **phpMyAdmin**
4. Ga naar het tabblad **SQL**, plak de inhoud van `sql/schema.sql` en klik **Uitvoeren**

---

## Stap 2 — Video-map aanmaken buiten de web root

Er is geen SSH-terminal nodig: doe dit via **Plesk Bestandsbeheer**.

1. Plesk → jouw domein → **Files** (bestandsbeheer)
2. Navigeer naar de map van jouw domein (bijv. `video.msss.nl/`)
3. Je ziet daar de map `cgi-bin` en straks ook `httpdocs` — klik **niet** in `httpdocs`
4. Klik op **`+`** (links bovenaan) → **Nieuwe map aanmaken**
5. Naam: `private` → bevestigen
6. Klik in de zojuist aangemaakte map `private`
7. Klik weer **`+`** → **Nieuwe map aanmaken**
8. Naam: `videos` → bevestigen

Resultaat: `video.msss.nl/private/videos/` — dit is buiten de web root en dus niet direct toegankelijk via de browser.

**VIDEO_PATH voor in `config.php`:**
Het absolute serverpad is (voor systeemgebruiker `msss`):
```
/var/www/vhosts/msss.nl/video.msss.nl/private/videos
```
> Weet je het niet zeker? Maak alvast de map aan. In stap 3 leggen we uit hoe je het pad kunt verifiëren.

---

## Stap 3 — Configuratie invullen

Bewerk het bestand `httpdocs/includes/config.php` **vóór** het uploaden:

```php
define('DB_HOST',    'localhost');
define('DB_NAME',    'VULDEZELF_dbnaam');       // ← uit stap 1
define('DB_USER',    'VULDEZELF_dbgebruiker');  // ← uit stap 1
define('DB_PASS',    'VULDEZELF_wachtwoord');   // ← uit stap 1

define('MOLLIE_API_KEY', 'test_VULDEZELF');     // ← zie stap 4

define('BASE_URL', 'https://video.msss.nl');    // ← jouw domein, geen trailing slash

// Pad naar de private/videos map die je in stap 2 hebt aangemaakt.
// Voor systeemgebruiker 'msss' is dit doorgaans:
define('VIDEO_PATH', '/var/www/vhosts/msss.nl/video.msss.nl/private/videos');
```

---

## Stap 4 — Mollie API-sleutel ophalen

1. Ga naar [mollie.com/dashboard](https://www.mollie.com/dashboard)
2. Instellingen → **API-sleutels**
3. Kopieer de **Testsleutel** (begint met `test_`) om eerst te testen
4. Na succesvolle tests: vervang door de **Live-sleutel** (begint met `live_`)

---

## Stap 5 — Bestanden uploaden naar de server

Via FTP (FileZilla) of Plesk bestandsbeheer:

- Upload de volledige inhoud van de map `httpdocs/` naar de map `httpdocs/` op de server
- De bestaande HTML-bestanden van de huidige site blijven staan; voeg de nieuwe bestanden toe

Mappenstructuur op de server na uploaden:
```
httpdocs/
├── .htaccess
├── login.php
├── logout.php
├── register.php
├── stream.php
├── admin/
├── assets/
├── includes/
├── members/
└── payment/
```

---

## Stap 6 — Document root instellen

> **Let op:** Plesk stelt de document root standaard in op de domeinnaam zonder `httpdocs`. Dat moet gecorrigeerd worden.

1. Plesk → jouw domein → **Hosting Settings** (tandwiel)
2. Verander **Document root** van `video.msss.nl` naar `video.msss.nl/httpdocs`
3. Opslaan

---

## Stap 7 — Mollie SDK installeren via Git deployment action

De aanbevolen methode is composer automatisch laten draaien na elke git pull:

1. Plesk → jouw domein → **Git**
2. Klik op het potlood naast de repository
3. Vink aan: **"Enable additional deployment actions"**
4. Voer in als deploy action:
   ```bash
   cd /var/www/vhosts/msss.nl/video.msss.nl && composer install --no-dev --optimize-autoloader
   ```
   > Pas het pad aan voor andere domeinen: vervang `msss.nl/video.msss.nl` door het juiste pad.
5. Opslaan → klik **Deploy**
6. Na afloop moet `httpdocs/vendor/mollie/` bestaan (controleer via bestandsbeheer)

> **Alternatief (SSH beschikbaar):** `cd /pad/naar/httpdocs && composer install --no-dev`

---

## Stap 8 — Eerste admin-account instellen

1. Ga naar `https://video.msss.nl/register.php` en maak een account aan
2. Open **phpMyAdmin** (Plesk → Databases → klik op de database → phpMyAdmin)
3. Klik op tabel `users` → **Verkennen**
4. Klik op het potloodicoon naast jouw account → zet `is_admin` op `1` → Opslaan

Of via het SQL-tabblad in phpMyAdmin:
```sql
UPDATE users SET is_admin = 1 WHERE email = 'jouw@emailadres.nl';
```

---

## Stap 9 — Video's toevoegen

1. Upload videobestanden (MP4, H.264) via **Plesk bestandsbeheer** of FTP naar
   `video.msss.nl/private/videos/` (de map uit stap 2)
   - Bijv. `les1.mp4`, `les2.mp4`
2. Log in op `https://video.msss.nl/admin/`
3. Klik **+ Video toevoegen**
4. Vul titel, omschrijving, prijs en exact de bestandsnaam in (bijv. `les1.mp4`)

---

## Stap 9b — Events / besloten toegangscodes (privacy)

Met events bundel je video's per evenement. Alleen gebruikers die de bijbehorende
**toegangscode** hebben ingevoerd, zien en kunnen de video's van dat event kopen.
Video's zonder event blijven openbaar zichtbaar voor alle ingelogde gebruikers.

**Database-migratie (eenmalig):**

1. Open **phpMyAdmin** (Plesk → Databases → klik op de database → phpMyAdmin)
2. Open het **SQL**-tabblad en plak de inhoud van `sql/migration_events.sql`
3. Klik **Starten** / **Go**

Dit voegt de tabellen `events` en `event_access` toe en een kolom `event_id` aan `videos`.

**Gebruik:**

1. Admin → **Events** → **+ Event toevoegen**
   - Vul naam, organisator in. Laat de toegangscode leeg voor een automatisch
     gegenereerde code, of vul een eigen code in.
2. Koppel video's aan het event: Admin → video bewerken → kies het **Event** in de dropdown.
3. Deel de toegangscode met de bezoekers van het event.
4. Bezoekers voeren de code in:
   - bij **registratie** (veld "Event-toegangscode"), of
   - later op **Mijn account** (`/members/account.php`).
5. Na invoeren verschijnen de event-video's in hun overzicht en kunnen ze gekocht worden.

---

## Stap 10 — Testen

Doorloop het volledige betaalproces in **testmodus**:

1. Ga naar `https://video.msss.nl/register.php` → nieuw testaccount aanmaken
2. Log in → ga naar `https://video.msss.nl/members/`
3. Klik **Koop toegang** bij een video
4. Je wordt doorgestuurd naar Mollie testpagina → kies **Betaling geslaagd**
5. Je wordt teruggestuurd naar de site
6. De video moet nu afspeelbaar zijn (inclusief seekbar)

Controleer ook:
- `https://video.msss.nl/stream.php?id=1` zonder inloggen → moet **403** geven
- Admin → **Verkopen** → de testaankoop moet zichtbaar zijn met status `paid`

---

## Stap 11 — Live zetten

1. Vervang in `config.php` de testsleutel (`test_...`) door de live-sleutel (`live_...`)
2. Upload het gewijzigde `config.php`

---

## Problemen oplossen

| Probleem | Oorzaak | Oplossing |
|---|---|---|
| Wit scherm / PHP-fout | DB-gegevens onjuist | Controleer `config.php` |
| Video speelt niet af | Bestand niet gevonden | Controleer bestandsnaam in admin en het pad in `config.php` |
| Seekbar werkt niet | Server stuurt geen 206 | Controleer of `mod_rewrite` en PHP-streams werken |
| Webhook werkt niet | URL niet bereikbaar | Controleer of `BASE_URL` klopt en HTTPS actief is |
| Composer werkt niet | PHP-path afwijkend | Vraag mijndomein.nl support naar het composer-commando |

---

## Beveiliging na livegang (aanbevolen)

- Voeg je eigen IP-adres toe aan `httpdocs/admin/.htaccess`:
  ```apache
  Require ip JOUW.IP.ADRES.HIER
  ```
- Schakel Mollie testmodus uit (live-sleutel gebruiken)
