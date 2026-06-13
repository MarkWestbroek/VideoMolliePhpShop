# Chat: Mollie installatie zonder git

- **Datum**: 2026-06-04 21:00
- **Sessie-ID**: `fed8155b-0ac6-4d0a-8979-90959a4fb8be`
- **Berichten**: 18

---

## 🧑 User

als ik op hbfoto mollie wil installeren en ik heb geen git maar wel ssh terminal, hoe doe ik dat?

Ik ben niet zeker van het pad naar httpdocs, maar hoe doe je "dir" in bash?

---

## 🤖 Assistant

## Mollie installeren via SSH (zonder Git)

### Pad vinden naar httpdocs

In bash gebruik je `ls` (list) in plaats van `dir`. Zo vind je waar je httpdocs zit:

```bash
# Waar ben ik nu?
pwd

# Wat staat er in deze map?
ls -la

# Zoek httpdocs vanaf de home directory
find / -type d -name "httpdocs" 2>/dev/null
```

Typisch Plesk-pad: `/var/www/vhosts/HOSTINGACCOUNT.nl/DOMEIN.nl/httpdocs`

Bijvoorbeeld voor `hbfoto.nl` is dat waarschijnlijk:
```
/var/www/vhosts/JOUW-HOSTINGACCOUNT/hbfoto.nl/httpdocs
```

### Mollie installeren via Composer

1. **SSH inloggen** op de server

2. ## Mollie installeren via SSH (zonder Git)

### Pad vinden naar httpdocs

In bash gebruik je `ls` (list) in plaats van `dir`. Zo vind je waar je httpdocs zit:

```bash
# Waar ben ik nu?
pwd

# Wat staat er in deze map?
ls -la

# Zoek httpdocs vanaf de home directory
find / -type d -name "httpdocs" 2>/dev/null
```

Typisch Plesk-pad: `/var/www/vhosts/HOSTINGACCOUNT.nl/DOMEIN.nl/httpdocs`

Bijvoorbeeld voor `hbfoto.nl` is dat waarschijnlijk:
```
/var/www/vhosts/JOUW-HOSTINGACCOUNT/hbfoto.nl/httpdocs
```

### Mollie installeren via Composer

1. **SSH inloggen** op de server

2. **Navigeer naar de map bóven httpdocs** (dus in de domeinroot, waar `composer.json` staat):
   ```bash
   cd /var/www/vhosts/JOUW-HOSTINGACCOUNT/hbfoto.nl
   ```

3. **Controleer of  er is**:
   ```bash
   ls -la composer.json
   ```

4. **Zorg dat  aanwezig is** (staat al in jouw projectroot). Als je de bestanden nog niet hebt geüpload, upload die dan eerst via FTP/Plesk bestandsbeheer.

5. **Voer Composer uit**:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```

   Als `composer` niet gevonden wordt, probeer dan:
   ```bash
   /opt/plesk/php/8.2/bin/php /usr/lib64/plesk-9.0/composer.phar install --no-dev --optimize-autoloader
   ```

6. **Controleer of het gelukt is**:
   ```bash
   ls -la httpdocs/vendor/mollie/
   ```
   Als deze map bestaat, is Mollie correct geïnstalleerd.

### Samengevat

| Windows (`cmd`) | Bash/Linux |
|---|---|
| `dir` | `ls -la` |
| `cd` | `cd` (zelfde) |
| `type` | `cat` |
| `echo %cd%` | `pwd` |

Zorg dat je  in de domeinroot staat (samen met `httpdocs`, `private` etc.) — niet ín  — en draai `composer install` vanuit die root. De vendor-map komt dan automatisch in `httpdocs/vendor/` terecht.

---

## 🧑 User

hoe plak ik in bash?

---

## 🤖 Assistant

## Plakken in bash

In de **SSH-terminal** (Linux/bash) plak je anders dan in Windows:

| Manier | Hoe |
|---|---|
| **Rechter muisknop** | Klik met rechts → plakt direct |
| **`Ctrl + Shift + V`** | Werkt in de meeste terminals (Putty, Windows Terminal, VS Code terminal) |
| **`Shift + Insert`** | Ook een veelgebruikte combinatie |

**Belangrijk:** Gebruik niet `Ctrl + V` — dat wordt in bash geïnterpreteerd als een besturingsteken (`^V`), niet als plakken.

Dus: **rechter muisknop** of **`Ctrl + Shift + V`** is wat je zoekt.

---

## 🧑 User

en copy?

---

## 🤖 Assistant

## Kopiëren in bash

| Manier | Hoe |
|---|---|
| **Selecteren met muis** | Selecteer tekst → wordt **automatisch** gekopieerd (geen `Ctrl+C` nodig!) |
| **`Ctrl + Shift + C`** | Werkt in de meeste moderne terminals |
| **`Ctrl + Insert`** | Ook een optie |

**Let op:** In Linux-terminal werkt `Ctrl + C` **niet** voor kopiëren — dat is namelijk het commando om een lopend proces te *stoppen/afbreken*.

### Dus het stappenplan voor SSH:
1. **KopiÃ«ren vanuit Windows:** `Ctrl + C` (gewoon zoals je gewend bent)
2. **Plakken in SSH-terminal:** Rechter muisknop of `Ctrl + Shift + V`
3. **KopiÃ«ren vanuit SSH-terminal:** Selecteer tekst met muis (automatisch gekopieerd) of `Ctrl + Shift + C`
4. **Plakken terug in Windows:** `Ctrl + V`

Kortom: in de SSH-terminal vervang je `Ctrl` door `Ctrl + Shift` voor kopiëren/plakken, of gebruik gewoon de muis (rechtsklik = plakken, selecteren = kopiëren).

---

## 🧑 User

DEPLOY.md  bin  composer.json  dev  error_docs  etc  httpdocs  lib  lib64  logs  private  sbin  tmp  usr  var
bash-4.4$ ls -la composer.json
-rw-r--r-- 1 f_00086643 psacln 268 Jun  4 19:44 composer.json
bash-4.4$ composer install --no-dev --optimize-autoloader
bash: composer: command not found
bash-4.4$ /opt/plesk/php/8.2/bin/php /usr/lib64/plesk-9.0/composer.phar install --no-dev --optimize-autoloader
bash: /opt/plesk/php/8.2/bin/php: No such file or directory


wat nu?

---

## 🤖 Assistant

Dat liep in de lokale PowerShell — ik kan niet direct in jouw SSH-sessie commando's draaien. Probeer deze stappen zelf in de SSH-terminal:

### Stap 1: Vind PHP
```bash
which php
```

Of als `which` niet werkt:
```bash
ls /opt/plesk/php/
```

Of ruimer zoeken:
```bash
find / -name "php" -type f 2>/dev/null | head -20
```

### Stap 2: Vind composer
```bash
find / -name "composer.phar" -type f 2>/dev/null | head -10
```

### Stap 3: Als composer.phar wel ergens staat
Gebruik dan dat pad, bijvoorbeeld:
```bash
/opt/plesk/php/8.1/bin/php /pad/naar/composer.phar install --no-dev --optimize-autoloader
```

### Stap 4: Anders — download composer zelf
```bash
# Eerst PHP versie vinden
ls /opt/plesk/php/

# Download composer met de gevonden PHP (vervang 8.x door jouw versie)
/opt/plesk/php/8.1/bin/php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
/opt/plesk/php/8.1/bin/php composer-setup.php
/opt/plesk/php/8.1/bin/php composer.phar install --no-dev --optimize-autoloader
```

Probeer eerst Stap 1 en 2, en laat me weten wat eruit komt zodat ik je verder kan helpen.

---

## 🧑 User

1 en 2 geeft niets

dan 3

bash-4.4$ # Download composer met de gevonden PHP (vervang 8.x door jouw versie)
bash-4.4$ /opt/plesk/php/8.1/bin/php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
bash: /opt/plesk/php/8.1/bin/php: No such file or directory
bash-4.4$ /opt/plesk/php/8.1/bin/php composer-setup.php
bash: /opt/plesk/php/8.1/bin/php: No such file or directory
bash-4.4$ /opt/plesk/php/8.1/bin/php composer.phar install --no-dev --optimize-autoloader
bash: /opt/plesk/php/8.1/bin/php: No such file or directory

---

## 🤖 Assistant

Probeer eerst uit te vinden welke PHP-versies er wél zijn:

```bash
ls /opt/plesk/php/
```

En ook:
```bash
which php
```

En:
```bash
find /usr -name "php" -type f 2>/dev/null | head -20
```

Laat me weten wat die commando's geven, dan kunnen we het juiste pad vinden.

---

## 🧑 User

ik kan niet hoger dan de root (boven httpdocs)

bash-4.4$ ls /opt/plesk/php/
ls: cannot access '/opt/plesk/php/': No such file or directory
bash-4.4$ cd ..
bash-4.4$ ls
DEPLOY.md  bin  composer.json  dev  error_docs  etc  httpdocs  lib  lib64  logs  private  sbin  tmp  usr  var
bash-4.4$ cd ..
bash-4.4$ ls
DEPLOY.md  bin  composer.json  dev  error_docs  etc  httpdocs  lib  lib64  logs  private  sbin  tmp  usr  var
bash-4.4$ cd ..
bash-4.4$ ls
DEPLOY.md  bin  composer.json  dev  error_docs  etc  httpdocs  lib  lib64  logs  private  sbin  tmp  usr  var

---

## 🤖 Assistant

Je zit in een **chroot-omgeving** (een beperkte shell). Je kunt niet bij `/opt/plesk/php/`. Laten we binnen de jail zoeken:

```bash
# Staat PHP in /usr/bin?
ls /usr/bin/php*

# Of in de paden die je wél hebt?
find /usr -name "php" -type f 2>/dev/null
find /bin -name "php*" -type f 2>/dev/null

# Welke PHP-versies?
/usr/bin/php -v 2>/dev/null
```

Probeer ook:
```bash
# Controleer of php als commando bestaat
php -v
```

Laat me weten wat deze commando's geven.

---

## 🧑 User

bash-4.4$ ls /usr/bin/php*
ls: cannot access '/usr/bin/php*': No such file or directory
bash-4.4$ find /usr -name "php" -type f 2>/dev/null
bash-4.4$ find /bin -name "php*" -type f 2>/dev/null
bash-4.4$ /usr/bin/php -v 2>/dev/null
bash-4.4$ php -v
bash: php: command not found

---

## 🤖 Assistant

PHP is niet geïnstalleerd in deze chroot-omgeving. Een paar opties:

### Optie 1 — Check of er een `plesk` commando is
```bash
plesk version
```

### Optie 2 — Run composer lokaal en upload de vendor-map

Dit is de **simpelste oplossing**: draai composer op je **eigen computer** (in deze VS Code-workspace) en upload de gegenereerde `vendor/` map via FTP/Plesk bestandsbeheer.

Open een nieuwe terminal in VS Code en voer uit:

```bash
cd D:\Git\Web\Hans
composer install --no-dev --optimize-autoloader
```

Daarna upload je via FTP de volledige map `httpdocs/vendor/` naar de server (`httpdocs/vendor/`).

### Optie 3 — Via Plesk webinterface

1. Log in op Plesk
2. Ga naar **Domein** → **PHP** → kies een PHP-versie (bijv. 8.1) en sla op
3. Ga dan terug en via **Bestandsbeheer** kun je soms een "Run Composer" knop zien

---

Probeer eerst even of `plesk version` werkt in SSH, of ga voor **Optie 2** (lokaal draaien en uploaden) — dat is het snelst.

---

## 🧑 User

ik heb de vendor map op mijn website, waar alles al draait. Kan ik het gewoon daarvandaan halen en bij Hans neerzetten?

---

## 🤖 Assistant

Ja, dat kan! Zolang beide sites dezelfde PHP-versie gebruiken, kun je de `vendor/` map gewoon kopiëren. De vendor-bibliotheken zijn platformonafhankelijk.

### Stappen in SSH:

```bash
# Eerst kijken waar je bent (boven httpdocs)
pwd

# Zoek de vendor map van je andere site
find / -path "*/httpdocs/vendor" -type d 2>/dev/null | head -5
```

Als je het pad weet, bijvoorbeeld `/var/www/vhosts/hbfoto.nl/httpdocs/vendor`, dan:

```bash
# Kopieer de vendor map naar de Hans-site
cp -r /var/www/vhosts/hbfoto.nl/httpdocs/vendor /huidige/pad/httpdocs/vendor
```

Waarbij je `/huidige/pad/` vervangt door het pad waar je nu bent (boven httpdocs van Hans).

Of via Plesk bestandsbeheer kopiëren/plakken als dat makkelijker is.

**Alternatief — lokaal draaien en uploaden** is ook zo gepiept. Wil je dat ik `composer install` voor je draai in deze VS Code-workspace? Dan kun je de `vendor/` map direct uploaden via FTP.

---

## 🧑 User

ja, graag

---

## 🤖 Assistant



*Read content.txt, lines 1 to 30*



*Read composer.json · Read content.txt, lines 1 to 30*

De Mollie SDK is succesvol geïnstalleerd! Laten we het controleren:## ✅ Mollie is geïnstalleerd!

De `vendor` map is gegenereerd met de Mollie API PHP SDK (v2.79.1).

### Nu uploaden naar de server

Je kunt de `vendor/` map op twee manieren uploaden:

**1. Via SFTP/SSH** (als die werkt):
```bash
# Als je vanaf de server verbinding kunt maken met deze computer... 
# Of als je lokaal een FTP-client hebt draaien
```

**2. Via Plesk Bestandsbeheer (aanbevolen):**
1. In VS Code: **rechterklik** op `vendor` → **Zip map** (maak er een zip van)
2. Upload de zip via **Plesk → Bestandsbeheer → httpdocs/** en pak daar uit
   Of: sleep de map via FTP (FileZilla) naar de server

**Let op:** Zorg dat op de server in Plesk de PHP-versie op **8.0 of hoger** staat (Instellingen → PHP), anders werkt Mollie niet.

---
