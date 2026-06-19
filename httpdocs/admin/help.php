<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

$pageTitle = 'Handleiding — HB Foto & Video';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Navigatie tabs (identiek aan admin/index.php) -->
<nav style="display:flex;gap:.75rem;margin-bottom:1.75rem;border-bottom:1px solid var(--border);padding-bottom:.75rem;flex-wrap:wrap;">
    <a href="<?= BASE_URL ?>/admin/"            class="btn btn-sm btn-secondary">Video's</a>
    <a href="<?= BASE_URL ?>/admin/?action=purchases" class="btn btn-sm btn-secondary">Verkopen</a>
    <a href="<?= BASE_URL ?>/admin/?action=add_video" class="btn btn-sm btn-secondary">+ Video toevoegen</a>
    <a href="<?= BASE_URL ?>/admin/users.php"    class="btn btn-sm btn-secondary">&#9654; Gebruikers</a>
    <a href="<?= BASE_URL ?>/admin/staffels.php" class="btn btn-sm btn-secondary">&#9654; Staffels</a>
    <a href="<?= BASE_URL ?>/admin/events.php"   class="btn btn-sm btn-secondary">&#9654; Events</a>
    <a href="<?= BASE_URL ?>/admin/help.php"     class="btn btn-sm btn-primary">&#9432; Handleiding</a>
</nav>

<div style="max-width:820px;">

    <div class="page-header">
        <h1>Handleiding voor beheerders</h1>
    </div>
    <p class="text-muted" style="margin-bottom:2rem;">
        Deze pagina legt stap voor stap uit hoe je de site beheert: video's
        toevoegen, prijzen instellen, events maken en gebruikers beheren.
        Je hebt geen technische kennis nodig.
    </p>

    <!-- ============================================================ -->
    <section style="margin-bottom:2.5rem;">
        <h2>1. Een video toevoegen</h2>
        <p>Een video bestaat uit <strong>twee delen</strong>: het videobestand
           (dat je via FTP uploadt) en de gegevens in de site (titel, prijs).</p>

        <h3 style="font-size:1.05rem;margin-top:1.25rem;">Stap A — Videobestand uploaden via FTP</h3>
        <ol>
            <li>Open je FTP-programma (bijv. FileZilla) en verbind met de server.</li>
            <li>Upload het videobestand (bijv. <code>les1.mp4</code>) naar de map
                <strong>private/videos</strong>. <em>Let op:</em> dit is een aparte
                map <strong>buiten</strong> de website-map — video's mogen daar staan
                zodat ze niet zomaar te downloaden zijn.</li>
            <li>Onthoud de exacte bestandsnaam, inclusief <code>.mp4</code>.
                Gebruik liefst namen zonder spaties of vreemde tekens.</li>
        </ol>

        <h3 style="font-size:1.05rem;margin-top:1.25rem;">Stap B — Video aanmaken in de site</h3>
        <ol>
            <li>Ga naar <strong>Beheer &rarr; Video's &rarr; + Video toevoegen</strong>.</li>
            <li>Vul de <strong>titel</strong> en eventueel een omschrijving in.</li>
            <li>Vul bij <strong>bestandsnaam</strong> exact de naam in die je
                geüpload hebt (bijv. <code>les1.mp4</code>).</li>
            <li>Kies een <strong>prijs</strong> óf een <strong>staffel</strong>
                (zie punt 2 hieronder).</li>
            <li>Wil je de video besloten houden? Kies een <strong>event</strong>
                (zie punt 3). Anders laat je dit op "Openbaar" staan.</li>
            <li>Klik op <strong>Opslaan</strong>.</li>
        </ol>
        <h3 style="font-size:1.05rem;margin-top:1.25rem;">Een video verwijderen</h3>
        <ol>
            <li>Klik in het video-overzicht op de rode <strong>Verwijderen</strong>-knop.</li>
            <li>Er verschijnt een popup. Klik op <strong>Verwijderen</strong> om te bevestigen.</li>
            <li><strong>Is de video al gekocht?</strong> Dan vraagt het systeem om het woord
                <strong>verwijderen</strong> in te typen. Dit is een extra beveiliging:
                verwijder geen video's waar klanten voor betaald hebben, tenzij het
                testaankopen zijn.</li>
        </ol>
        <p class="text-muted" style="font-size:.9rem;">
            Tip: zet een video op <em>inactief</em> (via Bewerken) als je hem
            tijdelijk wilt verbergen zonder hem te verwijderen.
        </p>

        <h3 style="font-size:1.05rem;margin-top:1.25rem;">Testvideo's</h3>
        <p>Je kunt een video markeren als <strong>testvideo</strong> via het vinkje
           in het formulier. Testvideo's:</p>
        <ul>
            <li>Zijn <strong>onzichtbaar</strong> voor gewone gebruikers</li>
            <li>Tellen <strong>niet mee</strong> in het verkoopoverzicht en de totalen</li>
            <li>Zijn wel gewoon zichtbaar en afspeelbaar voor beheerders</li>
        </ul>
        <p class="text-muted" style="font-size:.9rem;">
            Gebruik testvideo's om nieuwe functionaliteit uit te proberen zonder
            dat klanten het zien.
        </p>

        <h3 style="font-size:1.05rem;margin-top:1.25rem;">Video's hosten op Vimeo (alternatief)</h3>
        <p>Naast lokale video's kun je video's ook via <strong>Vimeo</strong> (Pro) hosten.
           Dit is handig voor lange of grote video's, of als je extra beveiliging wilt.</p>
        <h4 style="font-size:.95rem;margin-top:1rem;">Vimeo instellen</h4>
        <ol>
            <li>Upload de video naar je Vimeo-account (Pro vereist voor domeinrestrictie).</li>
            <li>Ga in Vimeo naar de video-instellingen → <strong>Privacy</strong>:</li>
            <ul>
                <li>Zet "Waar kan deze video worden ingesloten?" op <strong>Alleen op specifieke domeinen</strong></li>
                <li>Voeg <strong>hbfoto.nl</strong> toe als toegestaan domein</li>
                <li>Zet "Toon deze video op Vimeo.com" uit</li>
            </ul>
            <li>Kopieer het <strong>video-ID</strong> uit de Vimeo-URL (het getal achter <code>vimeo.com/</code>).</li>
        </ol>
        <h4 style="font-size:.95rem;margin-top:1rem;">In de site koppelen</h4>
        <ol>
            <li>Ga naar <strong>Beheer &rarr; Video's &rarr; + Video toevoegen</strong> (of Bewerken).</li>
            <li>Vul het <strong>Vimeo ID</strong> in.</li>
            <li>Het bestandsnaamveld mag je leeg laten — de video wordt dan via Vimeo gestreamd.</li>
            <li>Klik <strong>Opslaan</strong>.</li>
        </ol>
        <p class="text-muted" style="font-size:.9rem;">
            Lokale video (bestandsnaam ingevuld) en Vimeo (Vimeo ID ingevuld) kunnen
            niet tegelijk. Kies één van de twee.
        </p>
    </section>

    <!-- ============================================================ -->
    <section style="margin-bottom:2.5rem;">
        <h2>2. Prijzen &amp; staffels</h2>
        <p>Er zijn twee manieren om een prijs te bepalen:</p>
        <ul>
            <li><strong>Vaste prijs</strong> — één bedrag voor de video.</li>
            <li><strong>Staffel</strong> — de prijs daalt naarmate een klant méér
                video's uit dezelfde groep koopt. Bijvoorbeeld: 1e video €10,
                2e en 3e €8,75, vanaf de 4e €7,50.</li>
        </ul>
        <p>Een staffel maak je aan onder <strong>Beheer &rarr; Staffels</strong>:</p>
        <ol>
            <li>Maak een staffel aan met een herkenbare naam (bijv. "Cursus voorjaar").</li>
            <li>Voeg <strong>prijstrappen</strong> toe: "van het hoeveelste t/m het
                hoeveelste = welke prijs". Bijv. van 1 t/m 1 = €10, van 2 t/m 3 = €8,75,
                van 4 t/m 999 = €7,50.</li>
            <li>Koppel daarna video's aan deze staffel via het video-formulier.</li>
        </ol>
        <p class="text-muted" style="font-size:.9rem;">
            Kies je een staffel bij een video, dan hoeft het vaste prijsveld niet
            ingevuld te worden (het dient alleen als terugval).
        </p>
    </section>

    <!-- ============================================================ -->
    <section style="margin-bottom:2.5rem;">
        <h2>3. Events (besloten video's)</h2>
        <p>Een <strong>event</strong> gebruik je als bepaalde video's alleen
           zichtbaar mogen zijn voor een specifieke groep mensen — bijvoorbeeld
           de deelnemers van een fotoshoot. Zij krijgen een
           <strong>toegangscode</strong>.</p>
        <ol>
            <li>Ga naar <strong>Beheer &rarr; Events</strong> en maak een nieuw event aan
                (naam, organisator, eventueel omschrijving).</li>
            <li>Laat het codeveld leeg om automatisch een code te laten genereren,
                of vul zelf een code in.</li>
            <li>Koppel video's aan dit event via het video-formulier (veld "Event").</li>
            <li>Geef de toegangscode aan de betrokken klanten. Zij voeren die in op
                hun accountpagina en zien daarna de bijbehorende video's.</li>
        </ol>
        <p class="text-muted" style="font-size:.9rem;">
            Video's met een event zijn voor iedereen anders <strong>onzichtbaar</strong>
            — niet "zichtbaar maar op slot". Zonder code bestaan ze simpelweg niet
            voor die bezoeker.
        </p>
    </section>

    <!-- ============================================================ -->
    <section style="margin-bottom:2.5rem;">
        <h2>4. Gebruikers beheren</h2>
        <p>Onder <strong>Beheer &rarr; Gebruikers</strong> zie je alle accounts.</p>
        <ul>
            <li><strong>Online</strong> — wie de laatste 15 minuten actief was.
                <strong>🔴 stream</strong> betekent dat iemand op dit moment een video aan het kijken is.</li>
            <li><strong>Bekeken video's</strong> — in de details van een gebruiker zie je precies
                welke video's iemand bekeken heeft en op welk tijdstip.</li>
            <li><strong>Events / Aankopen / Betaald</strong> — hoeveel events een
                klant heeft ontgrendeld, hoeveel video's gekocht, en het totaalbedrag.</li>
            <li><strong>Details</strong> — klap een gebruiker open om precies te zien
                welke events en aankopen erbij horen.</li>
            <li><strong>Admin</strong> — geef of ontneem beheerdersrechten. Je eigen
                account kun je niet wijzigen.</li>
            <li><strong>Verwijderen</strong> — klik op Verwijder, daarna verschijnt
                een rood bevestigingsvak. Pas na "Ja, verwijder definitief" wordt de
                gebruiker (inclusief aankopen) verwijderd. Dit kan niet ongedaan gemaakt
                worden.</li>
        </ul>
    </section>

    <!-- ============================================================ -->
    <section style="margin-bottom:2.5rem;">
        <h2>5. Verdachte registraties (bots)</h2>
        <p>Bij elke nieuwe registratie krijgen de beheerders automatisch een
           e-mail. Lijkt een account op een bot (willekeurige naam/e-mail,
           wegwerp-adres), dan staat in het onderwerp
           <strong>&#9888; VERDACHTE registratie</strong>.</p>
        <p>Controleer zo'n account onder Gebruikers en verwijder het indien nodig.
           Echte klanten worden niet gemarkeerd.</p>
    </section>

    <!-- ============================================================ -->
    <section style="margin-bottom:2.5rem;">
        <h2>6. Login delen tegengaan (IP-tracking)</h2>
        <p>De site houdt bij vanaf welke <strong>IP-adressen</strong> een gebruiker
           inlogt. Dit voorkomt dat klanten hun account delen met anderen.</p>

        <h3 style="font-size:1.05rem;margin-top:1.25rem;">Hoe het werkt</h3>
        <ul>
            <li>Maximaal <strong>3 unieke IP-adressen</strong> per gebruiker in
                een periode van <strong>2 weken</strong>.</li>
            <li>Logt iemand in met een <strong>4e IP</strong>? Dan wordt het
                <strong>bekijken van video's geblokkeerd</strong>. De gebruiker
                kan nog wel inloggen en een bericht sturen via het contactformulier.</li>
            <li>IP's ouder dan 2 weken vervallen automatisch, zodat een gebruiker
                daarna weer met een nieuw IP kan inloggen.</li>
        </ul>

        <h3 style="font-size:1.05rem;margin-top:1.25rem;">Wat zie jij als beheerder?</h3>
        <ul>
            <li>In het <strong>gebruikersoverzicht</strong> staat een kolom
                <strong>IP's</strong> met het aantal actieve IP-adressen.</li>
            <li>Bij <strong>3 IP's</strong> wordt het getal <span style="color:#e74c3c;font-weight:600;">rood</span>
                weergegeven — de limiet is bereikt.</li>
            <li>Klap een gebruiker open (<strong>Details</strong>) om te zien
                <em>welke</em> IP's en wanneer ze voor het eerst en laatst zijn gezien.</li>
            <li>In het <strong>video-overzicht</strong> zie je een kolom <strong>Bekeken</strong>
                met het totaal aantal weergaven per video.</li>
        </ul>

        <h3 style="font-size:1.05rem;margin-top:1.25rem;">Blokkade opheffen</h3>
        <ol>
            <li>Als een echte klant geblokkeerd is (bijv. door wisselende wifi of
                4G), klik je in het gebruikersoverzicht op de knop
                <strong>&#x21bb; IP's</strong>.</li>
            <li>Dit verwijdert álle opgeslagen IP's van die gebruiker. De volgende
                keer dat hij inlogt begint de teller opnieuw.</li>
            <li>Je kunt de IP-lijst ook wissen via de <strong>Details</strong>-weergave
                (daar staat ook een Reset-knop).</li>
        </ol>

        <h3 style="font-size:1.05rem;margin-top:1.25rem;">Let op: beheerders</h3>
        <p><strong>Beheerders (admins) zijn uitgezonderd</strong> van de IP-limiet.
           Jij en andere admins kunnen vanaf elk apparaat inloggen zonder geblokkeerd
           te worden. Dit is zo ingesteld zodat je altijd toegang hebt om eventuele
           blokkades van klanten op te heffen.</p>

        <h3 style="font-size:1.05rem;margin-top:1.25rem;">Automatische melding</h3>
        <p>Als een gebruiker geblokkeerd wordt, ontvangen alle beheerders automatisch
           een e-mail met de gegevens van de gebruiker en het nieuwe IP-adres.
           Je hoeft dus niet zelf in de gaten te houden of er blokkades zijn.</p>

        <h3 style="font-size:1.05rem;margin-top:1.25rem;">IP-informatie</h3>
        <p>In de details van een gebruiker zie je bij elk IP-adres ook de
           <strong>internetprovider</strong> (bijv. KPN, Ziggo, Vodafone) en of
           het om een <strong>mobiele verbinding</strong> gaat. Dit helpt om te
           beoordelen of verschillende IP's van dezelfde persoon kunnen zijn
           (bijv. thuis-wifi vs. telefoon onderweg).</p>
    </section>

    <!-- ============================================================ -->
    <section style="margin-bottom:1rem;">
        <h2>7. Veelgestelde vragen</h2>
        <p><strong>Een klant ziet zijn video niet.</strong><br>
           Controleer: is de video <em>actief</em>? Hoort hij bij een
           <em>event</em> waarvan de klant de code nog niet heeft ingevoerd?
           Heeft de klant daadwerkelijk <em>betaald</em> (zie Gebruikers &rarr; Details)?</p>

        <p><strong>De video speelt niet af.</strong><br>
           Controleer of de <em>bestandsnaam</em> in de site exact overeenkomt met
           het geüploade bestand (let op hoofdletters en <code>.mp4</code>).</p>

        <p><strong>Ik word steeds uitgelogd.</strong><br>
           Sessies blijven 24 uur actief. Word je toch uitgelogd, dan is je
           browser mogelijk ingesteld om cookies te wissen bij sluiten.</p>

        <p><strong>Een klant kan niet betalen.</strong><br>
           Dit loopt via Mollie. Controleer in het Mollie-dashboard of er een
           betaling is gestart. Bij twijfel: vraag de eigenaar.</p>
    </section>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
