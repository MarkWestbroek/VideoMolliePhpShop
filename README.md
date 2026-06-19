# HB Foto & Video — Video Streaming Platform

PHP video streaming platform met Mollie-betalingen, staffelprijzen, event-toegang, e-mailverificatie en IP-tracking tegen login delen.

## Features

- **Video verkoop** via Mollie (eenmalige betaling per video)
- **Staffelkorting** — trapsgewijze prijzen (hoe meer video's, hoe lager de prijs)
- **Events** — besloten video's via toegangscode
- **Gratis video's** — checkbox in admin, prijs/staffel worden genegeerd
- **E-mailverificatie** — nieuwe accounts moeten e-mail bevestigen
- **IP-tracking** — maximaal 3 IP's per gebruiker per 2 weken; 4e IP blokkeert video-toegang
- **Video-weergaven tracking** — bijgehouden wie wanneer welke video bekijkt
- **Streaming-detectie** — admin ziet wie er op dit moment een video streamt
- **24-uurs sessies** — gebruikers en admins blijven 24 uur ingelogd
- **Verdachte registratie-detectie** — admins krijgen notificatie bij bot-achtige accounts
- **Admin dashboard** — video CRUD, gebruikersbeheer, verkoopoverzicht, Mollie betalingsbeheer

## Stack

| Component | Technologie |
|---|---|
| Backend | PHP 8.3 (`declare(strict_types=1)`) |
| Database | MySQL / PDO (prepared statements) |
| Betalingen | Mollie PHP SDK v2 |
| E-mail | PHPMailer v6.9 (SMTP, met fallback naar `mail()`) |
| Frontend | Vanilla HTML/CSS, geen frameworks |

## Project structuur

```
httpdocs/
├── includes/
│   ├── config.php              # Centrale configuratie
│   ├── config.local.php        # Lokale override (wachtwoorden) — NIET in Git
│   ├── auth.php                # Login, sessie, IP-tracking
│   ├── db.php                  # PDO singleton
│   ├── mail.php                # PHPMailer wrapper + fallback
│   ├── notify.php              # Admin notificaties (registraties)
│   └── PHPMailer/              # PHPMailer v6.9 source
├── admin/
│   ├── index.php               # Admin dashboard (video's, verkopen)
│   ├── users.php               # Gebruikersbeheer + IP-reset
│   ├── staffels.php            # Staffelbeheer
│   ├── events.php              # Eventbeheer
│   └── help.php                # Handleiding
├── members/
│   ├── index.php               # Video catalogus
│   ├── watch.php               # Video player
│   └── contact.php             # Contactformulier (geblokkeerde gebruikers)
├── payment/
│   └── checkout.php            # Mollie checkout
├── login.php                   # Login
├── register.php                # Registratie (+ verificatie-e-mail)
├── verify.php                  # E-mailverificatie
├── forgot_password.php         # Wachtwoord vergeten
└── test_mail.php               # E-mail testpagina (admin only)

sql/
├── install_full.sql            # Volledig databaseschema
├── migration_login_ips.sql     # Migratie: IP-tracking
├── migration_email_verification.sql  # Migratie: e-mailverificatie
├── migration_video_views.sql   # Migratie: weergaven-tracking
└── migration_rate_limiting.sql       # Migratie: rate limiting

private/
└── videos/                     # Videobestanden (BUITEN web root)
```

## Deployment

### Setup

1. Upload alle bestanden naar de server
2. Voer het SQL schema uit via phpMyAdmin → `sql/install_full.sql`
3. Voer eventuele migraties uit (bijv. `migration_login_ips.sql`)
4. Kopieer `config.php` en pas aan voor de omgeving
5. Maak `config.local.php` aan met wachtwoorden (wordt door `.gitignore` genegeerd)

### Config.local.php voorbeeld

```php
<?php
define('SMTP_PASSWORD', 'het_echte_wachtwoord');
```

### Environment-specifieke configuratie

Er zijn twee omgevingen (beide in `config.php`, één uitgecommentarieerd):

| Omgeving | Domein | SMTP | Mollie |
|---|---|---|---|
| **Staging** | `video.msss.nl` | `smtp.msss.nl:465` | Test key |
| **Live (Hans)** | `hbfoto.nl` | `smtp.mijndomein.nl:587` | Live key |

## IP-tracking (login delen tegengaan)

De site registreert bij elke login het IP-adres van de gebruiker in de tabel `login_ips`.

**Configuratie:**
```php
define('IP_TRACK_MAX', 3);        // Max unieke IP's per gebruiker
define('IP_TRACK_TTL', 1209600);  // 2 weken (14 × 24 × 3600 seconden)
```

**Flow:**
1. Bij login: IP wordt geregistreerd of `last_seen` wordt bijgewerkt
2. Verlopen IP's (> 2 weken) worden automatisch opgeruimd
3. Bij 4e uniek IP: `$_SESSION['viewing_blocked'] = true`
4. Op `members/index.php`: geblokkeerde gebruiker ziet contactformulier i.p.v. video's
5. Admin kan IP-lijst wissen via Gebruikers → ↻ IP's

## Database

8 tabellen (zie `sql/install_full.sql`):

| Tabel | Doel |
|---|---|
| `users` | Gebruikersaccounts |
| `staffels` | Prijstrappen groepen |
| `staffelprijzen` | Prijzen per trap |
| `events` | Besloten video collecties |
| `videos` | Video metadata |
| `purchases` | Aankopen en betalingen |
| `event_access` | Event-toegang per gebruiker |
| `password_resets` | Wachtwoord reset tokens |
| `login_attempts` | Rate limiting |
| `login_ips` | IP-tracking (tegen delen) |
| `video_views` | Weergaven-tracking |

## E-mail

- **Primair:** PHPMailer via SMTP (met pre-check via `fsockopen`)
- **Fallback:** PHP `mail()` als SMTP niet bereikbaar is
- **Configuratie:** SMTP constants in `config.php`, wachtwoord in `config.local.php`
- **Test:** `/test_mail.php` (alleen voor admins)

## Admin handleiding

Zie `/admin/help.php` voor de volledige handleiding (video's toevoegen, staffels, events, gebruikersbeheer, IP-tracking).
