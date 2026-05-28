Read [](file:///d%3A/Git/Web/Hans/input)

Good context from the screenshots. A few key questions before I finalize the plan: 

Analyzing your answers...

Good, that's enough to design a solid plan. Let me put it together. 

Created memory file [](file:///c%3A/Users/User/AppData/Roaming/Code/User/workspaceStorage/29a6a3bc26e457f21309fd1a30f42eb3/GitHub.copilot-chat/memory-tool/memories/MjQzOThkMWUtNDllNS00ODIwLWE3N2QtOWE2MzE4ZGNhMDgy/plan.md)

---

## Plan: PHP video streaming site met Mollie betaling op mijndomein.nl

**TL;DR:** Eenvoudige custom PHP applicatie met MySQL, Mollie PHP SDK (via Composer), self-hosted video's buiten de web root, en een streaming script met HTTP range request support. Geen framework nodig voor < 10 video's.

---

### Fase 1 — Database (Plesk → Databases)

1. Maak een nieuwe MySQL database + gebruiker aan via Plesk
2. Voer SQL uit voor 3 tabellen:
   - **`users`** — `id, email, password_hash, name, created_at`
   - **`videos`** — `id, title, description, price, filename, thumbnail, active`
   - **`purchases`** — `id, user_id, video_id, mollie_payment_id, status (open/pending/paid/failed), amount, paid_at`

### Fase 2 — Auth systeem

3. `includes/config.php` — DB-credentials, base URL, video-pad
4. `includes/db.php` — PDO singleton
5. `includes/auth.php` — `requireLogin()`, `isLoggedIn()`, `currentUser()`
6. `register.php` — formulier + `password_hash()` + redirect naar login
7. `login.php` — `password_verify()` → session starten
8. `logout.php` — session vernietigen

### Fase 3 — Video opslag & streaming

9. Maak map `private/videos/` **buiten** `httpdocs` aan (via Plesk bestandsbeheer, op `/var/www/vhosts/hbfoto.nl/private/videos/`). Niet web-accessible.
10. `stream.php?id=X` — checkt sessie + aankoop in DB, stuurt video met **HTTP 206 range requests** (zodat de videospeler kan seeked worden). Content-Type: video/mp4.

### Fase 4 — Mollie betaling

11. Installeer Mollie SDK: `composer require mollie/mollie-api-php` (via SSH-terminal in Plesk)
12. `payment/checkout.php` — maakt Mollie payment aan met:
    - `amount`, `description`, `redirectUrl` (return.php), `webhookUrl` (webhook.php)
    - Slaat `purchase` op met status `open` in DB
13. `payment/webhook.php` — ontvangt Mollie callback, verifieert, zet status op `paid` + logt `paid_at`
14. `payment/return.php` — toont succespagina of foutmelding na redirect

### Fase 5 — Members area

15. `members/index.php` — lijst van video's met prijs; per video: "Kijk" (indien al betaald) of "Koop toegang (€X.XX)"
16. `members/watch.php?id=X` — check aankoop in DB → toont `<video>` tag die streamt via `stream.php?id=X`

### Fase 6 — Admin

17. `admin/index.php` — eenvoudig beheerscherm (beschermd via admin-vlag in users tabel):
    - Video's toevoegen/bewerken/deactiveren
    - Video-bestanden uploaden (of verwijzen naar via FTP geplaatst bestand)
    - Overzicht verkopen

### Fase 7 — Afwerking

18. CSS passend bij de bestaande huisstijl van de site
19. `.htaccess` — redirect HTTP→HTTPS, bescherm `/admin` extra
20. Testen met Mollie test-modus (geen echte betalingen)

---

**Relevante bestanden (te maken)**

| Pad | Doel |
|---|---|
| `httpdocs/includes/config.php` | Centrale config |
| `httpdocs/stream.php` | Video streaming + auth check |
| `httpdocs/payment/webhook.php` | Mollie webhook (HTTPS vereist!) |
| `httpdocs/members/watch.php` | Video watch pagina |
| `private/videos/` | Video bestanden (buiten web root) |

---

**Verification**

1. Registreer testaccount → login werkt → redirect naar members
2. Klik "Koop" → Mollie testbetaling doorlopen → webhook ontvangt status `paid`
3. Terug op members pagina: video-knop actief → video speelt af + seeking werkt
4. Probeer `stream.php?id=1` zonder sessie → geeft 403
5. Controleer Mollie dashboard: testbetaling zichtbaar

---

**Beslissingen**

- **Video hosting:** zelf op de server (private/videos/), niet in httpdocs — zo kan PHP toegang controleren. Als opslaglimiet een probleem wordt → Vimeo met private embeds als fallback.
- **Geen framework:** pure PHP volstaat voor < 10 video's en deze feature set
- **Composer:** al beschikbaar in Plesk (zichtbaar in screenshot) — voor Mollie SDK

**Aandachtspunten**

1. **PHP upload limiet:** Grote video's beter via FTP/Plesk file manager uploaden dan via een webformulier. PHP heeft standaard een lage `upload_max_filesize` (aanpasbaar via Plesk PHP-instellingen).
2. **Webhook vereist HTTPS:** Mollie stuurt de webhook alleen naar HTTPS-URL's. Let's Encrypt certificaat is al ingesteld (zichtbaar in screenshot).
3. **Video-formaat:** Zorg dat video's als H.264 MP4 beschikbaar zijn — dat streamt in alle browsers zonder conversie.

---

Wil je dat ik met de implementatie begin, of zijn er aanpassingen aan dit plan?

Alles is aangemaakt. Laat me een overzicht geven van de volledige structuur: 

Ran terminal command: Get-ChildItem -Path "d:\Git\Web\Hans" -Recurse -File | Select-Object -ExpandProperty FullName | Sort-Object

Implementatie compleet. Hier is wat er staat en wat je/je vriend nog moet doen:

---

## Structuur

```
httpdocs/           ← upload dit naar de server
├── .htaccess       ← HTTPS afdwingen, includes afschermen
├── login.php / logout.php / register.php
├── stream.php      ← beveiligde video streaming (Range Requests)
├── members/        ← alleen na login
│   ├── index.php   ← videooverzicht + koopknoppen
│   └── watch.php   ← videospeler
├── payment/
│   ├── checkout.php  ← start Mollie betaling
│   ├── return.php    ← landing na betaling
│   └── webhook.php   ← Mollie status callback
├── admin/          ← video-/verkoopbeheer
│   └── index.php
├── includes/       ← niet web-toegankelijk
│   ├── config.php, db.php, auth.php, csrf.php
│   ├── header.php, footer.php
└── assets/style.css
sql/schema.sql      ← eenmalig uitvoeren in phpMyAdmin
composer.json       ← Mollie SDK dependency
```

---

## Deploymentstappen

**1. Mollie SDK installeren** — via Plesk SSH-terminal in de httpdocs map:
```bash
composer install
```

**2. Database aanmaken** — Plesk → Databases → nieuwe DB + gebruiker → importeer schema.sql via phpMyAdmin

**3. Config invullen** — config.php: DB-gegevens, Mollie API-sleutel (`test_...` eerst), `BASE_URL`

**4. Video-map aanmaken** — Plesk SSH:
```bash
mkdir -p ~/private/videos
```
Video-pad in config: `/var/www/vhosts/hbfoto.nl/private/videos`

**5. Eerste admin instellen** — na registreren, in phpMyAdmin:
```sql
UPDATE users SET is_admin = 1 WHERE email = 'jouw@email.nl';
```

**6. Video's uploaden** — via Plesk bestandsbeheer of FTP naar `private/videos/`, daarna in admin registreren

**7. Testen** — Mollie testmodus → betaling doorlopen → webhook checken → video bekijken → seekbar werkt via HTTP 206
