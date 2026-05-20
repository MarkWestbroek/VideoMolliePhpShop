# Deployment — VideoMolliePhpShop

Stappen om de site te deployen op mijndomein.nl (Plesk, PHP 8.3, MySQL).

---

## Vereisten

- Toegang tot Plesk op `server020.mijndomeinhosting.nl`
- FTP-client (bijv. FileZilla) of Plesk bestandsbeheer
- Mollie account met API-sleutel

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

Via Plesk → **SSH-terminal**:

```bash
mkdir -p ~/private/videos
```

Dit maakt de map `/var/www/vhosts/hbfoto.nl/private/videos` aan.
Video-bestanden hier uploaden via FTP of Plesk bestandsbeheer (zie stap 6).

---

## Stap 3 — Configuratie invullen

Bewerk het bestand `httpdocs/includes/config.php` **vóór** het uploaden:

```php
define('DB_HOST',    'localhost');
define('DB_NAME',    'VULDEZELF_dbnaam');       // ← uit stap 1
define('DB_USER',    'VULDEZELF_dbgebruiker');  // ← uit stap 1
define('DB_PASS',    'VULDEZELF_wachtwoord');   // ← uit stap 1

define('MOLLIE_API_KEY', 'test_VULDEZELF');     // ← zie stap 4

define('BASE_URL', 'https://hbfoto.nl');        // ← geen trailing slash

define('VIDEO_PATH', '/var/www/vhosts/hbfoto.nl/private/videos'); // ← uit stap 2
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

## Stap 6 — Mollie SDK installeren

Via Plesk → **SSH-terminal**:

```bash
cd ~/httpdocs
composer install
```

Dit downloadt de Mollie SDK naar `httpdocs/vendor/`.

> Als `composer` niet werkt, probeer: `php /usr/local/bin/composer install`

---

## Stap 7 — Eerste admin-account instellen

1. Ga naar `https://hbfoto.nl/register.php` en maak een account aan
2. Open phpMyAdmin → tabel `users`
3. Zoek het zojuist aangemaakte account en zet `is_admin` op `1`

Of via SSH:
```sql
UPDATE users SET is_admin = 1 WHERE email = 'jouw@emailadres.nl';
```

---

## Stap 8 — Video's toevoegen

1. Upload videobestanden (MP4, H.264) via FTP naar `~/private/videos/`
   - Bijv. `les1.mp4`, `les2.mp4`
2. Log in op `https://hbfoto.nl/admin/`
3. Klik **+ Video toevoegen**
4. Vul titel, omschrijving, prijs en exact de bestandsnaam in (bijv. `les1.mp4`)

---

## Stap 9 — Testen

Doorloop het volledige betaalproces in **testmodus**:

1. Ga naar `https://hbfoto.nl/register.php` → nieuw testaccount aanmaken
2. Log in → ga naar `https://hbfoto.nl/members/`
3. Klik **Koop toegang** bij een video
4. Je wordt doorgestuurd naar Mollie testpagina → kies **Betaling geslaagd**
5. Je wordt teruggestuurd naar de site
6. De video moet nu afspeelbaar zijn (inclusief seekbar)

Controleer ook:
- `https://hbfoto.nl/stream.php?id=1` zonder inloggen → moet **403** geven
- Admin → **Verkopen** → de testaankoop moet zichtbaar zijn met status `paid`

---

## Stap 10 — Live zetten

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
