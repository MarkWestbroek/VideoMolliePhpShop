<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

requireAdmin();

$action  = $_GET['action'] ?? 'dashboard';
$message = '';
$error   = '';

// ============================================================
// POST-acties
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    // --- Video toevoegen ------------------------------------
    if ($action === 'add_video') {
        $title       = trim($_POST['title']       ?? '');
        $description = trim($_POST['description'] ?? '');
        $price       = $_POST['price']    ?? '';
        $filename    = trim($_POST['filename']    ?? '');
        $isGratis    = isset($_POST['gratis']);
        $staffelId   = (!$isGratis && ($_POST['staffel_id'] ?? '') !== '') ? (int) $_POST['staffel_id'] : null;
        $eventId     = ($_POST['event_id'] ?? '') !== '' ? (int) $_POST['event_id'] : null;

        if ($title === '' || $filename === '') {
            $error = 'Vul alle verplichte velden in.';
        } elseif (!$isGratis && $staffelId === null && ($price === '' || !is_numeric($price) || (float) $price < 0.01)) {
            $error = 'Voer een geldige prijs in (minimaal € 0,01), kies een staffel, of vink "Gratis" aan.';
        } else {
            $safeFilename  = basename($filename);
            $fallbackPrice = $isGratis ? 0.0 : (($price !== '' && is_numeric($price)) ? (float) $price : 0.01);
            $stmt = db()->prepare(
                'INSERT INTO videos (title, description, price, staffel_id, event_id, filename) VALUES (?,?,?,?,?,?)'
            );
            $stmt->execute([$title, $description, $fallbackPrice, $staffelId, $eventId, $safeFilename]);
            $message = 'Video toegevoegd.';
            $action  = 'dashboard';
        }
    }

    // --- Video bewerken ------------------------------------
    elseif ($action === 'edit_video') {
        $id          = (int) ($_POST['id'] ?? 0);
        $title       = trim($_POST['title']       ?? '');
        $description = trim($_POST['description'] ?? '');
        $price       = $_POST['price']    ?? '';
        $filename    = trim($_POST['filename']    ?? '');
        $active      = isset($_POST['active']) ? 1 : 0;
        $isGratis    = isset($_POST['gratis']);
        $staffelId   = (!$isGratis && ($_POST['staffel_id'] ?? '') !== '') ? (int) $_POST['staffel_id'] : null;
        $eventId     = ($_POST['event_id'] ?? '') !== '' ? (int) $_POST['event_id'] : null;

        if ($id <= 0 || $title === '' || $filename === '') {
            $error = 'Vul alle verplichte velden in.';
        } elseif (!$isGratis && $staffelId === null && ($price === '' || !is_numeric($price) || (float) $price < 0.01)) {
            $error = 'Voer een geldige prijs in, kies een staffel, of vink “Gratis” aan.';
        } else {
            $safeFilename  = basename($filename);
            $fallbackPrice = $isGratis ? 0.0 : (($price !== '' && is_numeric($price)) ? (float) $price : 0.01);
            $stmt = db()->prepare(
                'UPDATE videos SET title=?, description=?, price=?, staffel_id=?, event_id=?, filename=?, active=? WHERE id=?'
            );
            $stmt->execute([$title, $description, $fallbackPrice, $staffelId, $eventId, $safeFilename, $active, $id]);
            $message = 'Video bijgewerkt.';
            $action  = 'dashboard';
        }
    }

    // --- Video verwijderen -----------------------------------
    elseif ($action === 'delete_video') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $error = 'Ongeldig videonummer.';
        } else {
            // Controleren of er al aankopen zijn
            $stmt = db()->prepare('SELECT COUNT(*) FROM purchases WHERE video_id = ?');
            $stmt->execute([$id]);
            $purchaseCount = (int) $stmt->fetchColumn();
            if ($purchaseCount > 0 && ($_POST['confirm_delete'] ?? '') !== 'verwijderen') {
                $error = 'Deze video is al ' . $purchaseCount . ' keer gekocht. Type "verwijderen" in het veld om toch te verwijderen.';
            } else {
                $stmt = db()->prepare('DELETE FROM videos WHERE id = ?');
                $stmt->execute([$id]);
                $message = 'Video verwijderd.';
            }
            $action = 'dashboard';
        }
    }

    // --- Betaling verversen via Mollie API ------------------
    elseif ($action === 'refresh_payment') {
        $purchaseId = (int) ($_POST['purchase_id'] ?? 0);
        if ($purchaseId <= 0) {
            $error = 'Ongeldig aankoopnummer.';
        } else {
            $stmt = db()->prepare('SELECT id, mollie_payment_id, status FROM purchases WHERE id = ? LIMIT 1');
            $stmt->execute([$purchaseId]);
            $purchase = $stmt->fetch();

            if (!$purchase || !$purchase['mollie_payment_id']) {
                $error = 'Geen Mollie-betaling gevonden voor deze aankoop.';
            } elseif (!in_array($purchase['status'], ['open', 'pending'], true)) {
                $error = 'Status is al definitief (' . htmlspecialchars($purchase['status'], ENT_QUOTES, 'UTF-8') . ').';
            } else {
                try {
                    require_once __DIR__ . '/../vendor/autoload.php';
                    $mollie = new \Mollie\Api\MollieApiClient();
                    $mollie->setApiKey(MOLLIE_API_KEY);
                    $payment = $mollie->payments->get($purchase['mollie_payment_id']);

                    $newStatus = 'open';
                    if ($payment->isPaid())      { $newStatus = 'paid'; }
                    elseif ($payment->isExpired())  { $newStatus = 'expired'; }
                    elseif ($payment->isFailed())   { $newStatus = 'failed'; }
                    elseif ($payment->isCanceled()) { $newStatus = 'canceled'; }
                    elseif ($payment->isPending())  { $newStatus = 'pending'; }

                    if ($newStatus === 'paid') {
                        $stmt = db()->prepare(
                            "UPDATE purchases SET status = 'paid', paid_at = NOW() WHERE id = ? AND status != 'paid'"
                        );
                    } else {
                        $stmt = db()->prepare('UPDATE purchases SET status = ? WHERE id = ?');
                    }
                    $stmt->execute([$newStatus, $purchaseId]);

                    // Stuur aankoopbevestiging bij handmatige overgang naar paid
                    if ($newStatus === 'paid' && $stmt->rowCount() > 0) {
                        sendPurchaseConfirmation($purchaseId);
                    }

                    $message = 'Betaling bijgewerkt naar: ' . $newStatus;
                } catch (\Mollie\Api\Exceptions\ApiException $e) {
                    $error = 'Mollie API-fout: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
                }
            }
            $action = 'purchases';
        }
    }

    // --- Betaling annuleren bij Mollie ----------------------
    elseif ($action === 'cancel_payment') {
        $purchaseId = (int) ($_POST['purchase_id'] ?? 0);
        if ($purchaseId <= 0) {
            $error = 'Ongeldig aankoopnummer.';
        } else {
            $stmt = db()->prepare('SELECT id, mollie_payment_id, status FROM purchases WHERE id = ? LIMIT 1');
            $stmt->execute([$purchaseId]);
            $purchase = $stmt->fetch();

            if (!$purchase || !$purchase['mollie_payment_id']) {
                $error = 'Geen Mollie-betaling gevonden voor deze aankoop.';
            } elseif (!in_array($purchase['status'], ['open', 'pending'], true)) {
                $error = 'Deze betaling kan niet meer geannuleerd worden (status: ' . htmlspecialchars($purchase['status'], ENT_QUOTES, 'UTF-8') . ').';
            } else {
                try {
                    require_once __DIR__ . '/../vendor/autoload.php';
                    $mollie = new \Mollie\Api\MollieApiClient();
                    $mollie->setApiKey(MOLLIE_API_KEY);
                    $mollie->payments->cancel($purchase['mollie_payment_id']);

                    $stmt = db()->prepare("UPDATE purchases SET status = 'canceled' WHERE id = ?");
                    $stmt->execute([$purchaseId]);
                    $message = 'Betaling geannuleerd bij Mollie.';
                } catch (\Mollie\Api\Exceptions\ApiException $e) {
                    // Cancel niet mogelijk (bv. betaling al verlopen/afgerond).
                    // Probeer dan de actuele status op te halen.
                    try {
                        $payment = $mollie->payments->get($purchase['mollie_payment_id']);
                        $newStatus = 'open';
                        if ($payment->isPaid())      { $newStatus = 'paid'; }
                        elseif ($payment->isExpired())  { $newStatus = 'expired'; }
                        elseif ($payment->isFailed())   { $newStatus = 'failed'; }
                        elseif ($payment->isCanceled()) { $newStatus = 'canceled'; }
                        elseif ($payment->isPending())  { $newStatus = 'pending'; }

                        if ($newStatus === 'paid') {
                            $stmt = db()->prepare(
                                "UPDATE purchases SET status = 'paid', paid_at = NOW() WHERE id = ? AND status != 'paid'"
                            );
                        } else {
                            $stmt = db()->prepare('UPDATE purchases SET status = ? WHERE id = ?');
                        }
                        $stmt->execute([$newStatus, $purchaseId]);

                        // Stuur aankoopbevestiging als cancel niet lukte maar status wél paid bleek
                        if ($newStatus === 'paid' && $stmt->rowCount() > 0) {
                            sendPurchaseConfirmation($purchaseId);
                        }

                        $message = 'Annuleren niet mogelijk, status opgehaald bij Mollie: ' . $newStatus;
                    } catch (\Mollie\Api\Exceptions\ApiException $e2) {
                        $error = 'Kon status niet ophalen bij Mollie: ' . htmlspecialchars($e2->getMessage(), ENT_QUOTES, 'UTF-8');
                    }
                }
            }
            $action = 'purchases';
        }
    }
}

// ============================================================
// Data ophalen per actie
// ============================================================

$video = null;

if ($action === 'edit_video' && empty($error)) {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id > 0) {
        $stmt = db()->prepare('SELECT * FROM videos WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $video = $stmt->fetch();
        if (!$video) {
            $action = 'dashboard';
        }
    }
}

$videos    = [];
$purchases = [];
$staffels  = db()->query('SELECT id, naam FROM staffels ORDER BY naam')->fetchAll();
$events    = db()->query('SELECT id, naam FROM events ORDER BY naam')->fetchAll();

if ($action === 'dashboard') {
    $videos = db()->query(
        'SELECT v.*, s.naam AS staffel_naam, e.naam AS event_naam,
                COUNT(DISTINCT vv.id) AS view_count,
                COUNT(DISTINCT p.id)  AS purchase_count
         FROM videos v
         LEFT JOIN staffels s ON s.id = v.staffel_id
         LEFT JOIN events   e ON e.id = v.event_id
         LEFT JOIN video_views vv ON vv.video_id = v.id
         LEFT JOIN purchases    p  ON p.video_id = v.id
         GROUP BY v.id
         ORDER BY v.created_at DESC'
    )->fetchAll();
}

if ($action === 'purchases') {
    $purchases = db()->query(
        'SELECT p.id, u.name AS user_name, u.email, v.title AS video_title,
                p.amount, p.status, p.created_at, p.paid_at,
                p.mollie_payment_id
         FROM purchases p
         JOIN users  u ON u.id = p.user_id
         JOIN videos v ON v.id = p.video_id
         ORDER BY p.created_at DESC
         LIMIT 200'
    )->fetchAll();
}

$salesOverview = null;
if ($action === 'sales_overview') {
    // Alle unieke bedragen (kolommen) — aflopend sorteren
    $amounts = db()->query(
        "SELECT DISTINCT CAST(amount AS CHAR) FROM purchases WHERE status = 'paid' ORDER BY amount DESC"
    )->fetchAll(\PDO::FETCH_COLUMN, 0);

    // Per video, per bedrag: aantal aankopen + totaalopbrengst
    $rows = db()->query(
        "SELECT v.id, v.title,
                CAST(p.amount AS CHAR) AS amount_str,
                p.amount,
                COUNT(*) AS cnt,
                SUM(p.amount) AS subtotal
         FROM purchases p
         JOIN videos v ON v.id = p.video_id
         WHERE p.status = 'paid'
         GROUP BY v.id, v.title, amount_str, p.amount
         ORDER BY v.title"
    )->fetchAll();

    // Bouw pivot: video_id => [ title, amounts => [amount => count], row_total ]
    $pivot = [];
    foreach ($rows as $r) {
        $vid = (int) $r['id'];
        if (!isset($pivot[$vid])) {
            $pivot[$vid] = [
                'title'   => $r['title'],
                'amounts' => [],
                'total'   => 0.0,
            ];
        }
        $amt = $r['amount_str'];
        $pivot[$vid]['amounts'][$amt] = (int) $r['cnt'];
        $pivot[$vid]['total'] += (float) $r['subtotal'];
    }

    // Kolomtotalen + grand total
    $colTotals = [];
    $grandTotal = 0.0;
    foreach ($amounts as $amt) {
        $colTotals[$amt] = 0;
    }
    foreach ($pivot as $vid => $data) {
        foreach ($data['amounts'] as $amt => $cnt) {
            $colTotals[$amt] = ($colTotals[$amt] ?? 0) + $cnt;
        }
        $grandTotal += $data['total'];
    }

    $salesOverview = [
        'amounts'    => $amounts,
        'pivot'      => $pivot,
        'colTotals'  => $colTotals,
        'grandTotal' => $grandTotal,
    ];
}

// ============================================================
// View
// ============================================================
$pageTitle = 'Beheer — HB Foto & Video';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($message !== ''): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<!-- Navigatie tabs -->
<nav style="display:flex;gap:.75rem;margin-bottom:1.75rem;border-bottom:1px solid var(--border);padding-bottom:.75rem;flex-wrap:wrap;">
    <a href="?action=dashboard"  class="btn btn-sm <?= $action === 'dashboard'  ? 'btn-primary' : 'btn-secondary' ?>">Video's</a>
    <a href="?action=purchases"  class="btn btn-sm <?= $action === 'purchases'  ? 'btn-primary' : 'btn-secondary' ?>">Verkopen</a>
    <a href="?action=sales_overview" class="btn btn-sm <?= $action === 'sales_overview' ? 'btn-primary' : 'btn-secondary' ?>">Overzicht</a>
    <a href="?action=add_video"  class="btn btn-sm <?= $action === 'add_video'  ? 'btn-primary' : 'btn-secondary' ?>">+ Video toevoegen</a>
    <a href="<?= BASE_URL ?>/admin/users.php"    class="btn btn-sm btn-secondary">&#9654; Gebruikers</a>
    <a href="<?= BASE_URL ?>/admin/staffels.php" class="btn btn-sm btn-secondary">&#9654; Staffels</a>
    <a href="<?= BASE_URL ?>/admin/events.php"   class="btn btn-sm btn-secondary">&#9654; Events</a>
    <a href="<?= BASE_URL ?>/admin/help.php"     class="btn btn-sm btn-secondary">&#9432; Handleiding</a>
</nav>

<?php
// ---- Dashboard: videooverzicht ----------------------------
if ($action === 'dashboard'): ?>

<div class="page-header">
    <h1>Video's beheren</h1>
</div>

<?php if (empty($videos)): ?>
    <p class="text-muted">Nog geen video's. <a href="?action=add_video">Voeg er een toe</a>.</p>
<?php else: ?>
<div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Titel</th>
                <th class="tt" data-tooltip="Vaste prijs of staffel (trapsgewijze korting)">Prijs / Staffel</th>
                <th class="tt" data-tooltip="Besloten event: video is alleen zichtbaar voor gebruikers met de toegangscode">Event</th>
                <th>Bestand</th>
                <th>Bekeken</th>
                <th>Status</th>
                <th>Acties</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($videos as $v): ?>
            <tr>
                <td><?= (int) $v['id'] ?></td>
                <td><?= htmlspecialchars($v['title'], ENT_QUOTES, 'UTF-8') ?></td>
                <td>
                    <?php if ($v['staffel_naam']): ?>
                        <span class="tt" data-tooltip="Trapsgewijze prijs — vaste terugvalprijs: &euro; <?= number_format((float)$v['price'], 2, ',', '.') ?>. Beheer via Staffels.">
                            &#9654; <?= htmlspecialchars($v['staffel_naam'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    <?php elseif ((float)$v['price'] === 0.0): ?>
                        <span class="badge-free">Gratis</span>
                    <?php else: ?>
                        &euro; <?= number_format((float) $v['price'], 2, ',', '.') ?>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($v['event_naam']): ?>
                        <span class="status-paid" title="Besloten: alleen zichtbaar na invoer van de toegangscode van dit event">&#128274; <?= htmlspecialchars($v['event_naam'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php else: ?>
                        <span class="text-muted" title="Zichtbaar voor alle ingelogde gebruikers">Openbaar</span>
                    <?php endif; ?>
                </td>
                <td><code style="font-size:.8rem"><?= htmlspecialchars($v['filename'], ENT_QUOTES, 'UTF-8') ?></code></td>
                <td style="text-align:center">
                    <?php if ((int) $v['view_count'] > 0): ?>
                        <?= (int) $v['view_count'] ?>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($v['active']): ?>
                        <span class="status-paid">Actief</span>
                    <?php else: ?>
                        <span class="status-inactive">Inactief</span>
                    <?php endif; ?>
                </td>
                <td class="actions">
                    <a href="?action=edit_video&id=<?= (int) $v['id'] ?>" class="btn btn-secondary btn-sm">Bewerken</a>
                    <form method="post" action="?action=delete_video" style="display:inline"
                          onsubmit="var pc=parseInt(this.getAttribute('data-purchases')||'0');if(pc>0){var inp=prompt('Deze video is al '+pc+' keer gekocht.\n\nType \"verwijderen\" om de video tóch te verwijderen.\nLet op: doe dit alleen bij test-video\'s.','');if(inp!=='verwijderen'){return false;}var hi=document.createElement('input');hi.type='hidden';hi.name='confirm_delete';hi.value='verwijderen';this.appendChild(hi);return true;}return confirm('Weet je zeker dat je deze video wilt verwijderen?');"
                          data-purchases="<?= (int) $v['purchase_count'] ?>">
                        <?= csrfField() ?>
                        <input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm">Verwijderen</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php
// ---- Video toevoegen --------------------------------------
elseif ($action === 'add_video'): ?>

<h1>Video toevoegen</h1>
<p class="text-muted mb-2" style="font-size:.9rem">
    Upload het videobestand eerst via Plesk Bestandsbeheer of FTP naar
    <code><?= htmlspecialchars(VIDEO_PATH, ENT_QUOTES, 'UTF-8') ?></code>,
    en vul hier daarna de bestandsnaam in (bijv. <code>les1.mp4</code>).
</p>

<div class="form-card" style="max-width:600px;margin:0;">
    <form method="post" action="?action=add_video">
        <?= csrfField() ?>

        <div class="form-group">
            <label for="title">Titel <span style="color:var(--danger)">*</span></label>
            <input type="text" id="title" name="title" required maxlength="255"
                   value="<?= htmlspecialchars($_POST['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="form-group">
            <label for="description">Omschrijving</label>
            <textarea id="description" name="description" maxlength="5000"><?= htmlspecialchars($_POST['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>

        <div class="form-group">
            <label style="display:flex;align-items:center;gap:.6rem;cursor:pointer;">
                <input type="checkbox" id="gratis-add" name="gratis" value="1"
                       <?= isset($_POST['gratis']) ? 'checked' : '' ?>>
                <span><strong>Gratis</strong> &mdash; video is gratis te bekijken voor ingelogde gebruikers</span>
            </label>
        </div>

        <div class="form-group" id="staffel-group-add">
            <label for="staffel_id">Staffel (optioneel)</label>
            <select id="staffel_id" name="staffel_id">
                <option value="">— Geen staffel (vaste prijs) —</option>
                <?php foreach ($staffels as $s): ?>
                    <option value="<?= (int)$s['id'] ?>"
                        <?= ((int)($_POST['staffel_id'] ?? 0) === (int)$s['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['naam'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="form-hint">Als een staffel gekozen is, overschrijft de staffelprijs de vaste prijs hieronder.</p>
        </div>

        <div class="form-group">
            <label for="event_id">Event (privacy, optioneel)</label>
            <select id="event_id" name="event_id">
                <option value="">— Openbaar (zichtbaar voor iedereen) —</option>
                <?php foreach ($events as $ev): ?>
                    <option value="<?= (int)$ev['id'] ?>"
                        <?= ((int)($_POST['event_id'] ?? 0) === (int)$ev['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($ev['naam'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="form-hint">Bij een event is de video alleen zichtbaar voor gebruikers die de toegangscode hebben ingevoerd.</p>
        </div>

        <div class="form-group" id="price-group-add">
            <label for="price">Vaste prijs (EUR) <span id="price-required-add" style="color:var(--danger)">*</span></label>
            <input type="number" id="price" name="price" min="0.01" step="0.01"
                   value="<?= htmlspecialchars($_POST['price'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <p class="form-hint">Wordt gebruikt als er geen staffel is, of als terugval.</p>
        </div>

        <div class="form-group">
            <label for="filename">Bestandsnaam <span style="color:var(--danger)">*</span></label>
            <input type="text" id="filename" name="filename" required placeholder="les1.mp4"
                   value="<?= htmlspecialchars($_POST['filename'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <p class="form-hint">Alleen de bestandsnaam, zonder pad. Bijv: <code>les1.mp4</code></p>
        </div>

        <div style="display:flex;gap:.75rem;">
            <button type="submit" class="btn btn-primary">Opslaan</button>
            <a href="?action=dashboard" class="btn btn-secondary">Annuleren</a>
        </div>
    </form>
</div>
<script>
(function(){
    var chk        = document.getElementById('gratis-add');
    var sel        = document.getElementById('staffel_id');
    var inp        = document.getElementById('price');
    var req        = document.getElementById('price-required-add');
    var staffelGrp = document.getElementById('staffel-group-add');
    var priceGrp   = document.getElementById('price-group-add');
    function toggle() {
        var gratis     = chk.checked;
        var hasStaffel = sel.value !== '';
        staffelGrp.style.display = gratis ? 'none' : '';
        priceGrp.style.display   = gratis ? 'none' : '';
        inp.required = !gratis && !hasStaffel;
        inp.min      = gratis ? '0' : '0.01';
        req.style.display = (gratis || hasStaffel) ? 'none' : '';
    }
    chk.addEventListener('change', toggle);
    sel.addEventListener('change', toggle);
    toggle();
})();
</script>

<?php
// ---- Video bewerken ---------------------------------------
elseif ($action === 'edit_video' && $video): ?>

<h1>Video bewerken</h1>

<div class="form-card" style="max-width:600px;margin:0;">
    <form method="post" action="?action=edit_video">
        <?= csrfField() ?>
        <input type="hidden" name="id" value="<?= (int) $video['id'] ?>">

        <div class="form-group">
            <label for="title">Titel <span style="color:var(--danger)">*</span></label>
            <input type="text" id="title" name="title" required maxlength="255"
                   value="<?= htmlspecialchars($_POST['title'] ?? $video['title'], ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="form-group">
            <label for="description">Omschrijving</label>
            <textarea id="description" name="description" maxlength="5000"><?= htmlspecialchars($_POST['description'] ?? $video['description'], ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>

        <?php $isGratisEdit = isset($_POST['gratis']) || ((float)($video['price'] ?? 1) === 0.0 && empty($video['staffel_id'])); ?>
        <div class="form-group">
            <label style="display:flex;align-items:center;gap:.6rem;cursor:pointer;">
                <input type="checkbox" id="gratis-edit" name="gratis" value="1"
                       <?= $isGratisEdit ? 'checked' : '' ?>>
                <span><strong>Gratis</strong> &mdash; video is gratis te bekijken voor ingelogde gebruikers</span>
            </label>
        </div>

        <div class="form-group" id="staffel-group-edit">
            <label for="staffel_id">Staffel (optioneel)</label>
            <select id="staffel_id" name="staffel_id">
                <option value="">— Geen staffel (vaste prijs) —</option>
                <?php
                $currentStaffelId = (int) ($_POST['staffel_id'] ?? $video['staffel_id'] ?? 0);
                foreach ($staffels as $s): ?>
                    <option value="<?= (int)$s['id'] ?>"
                        <?= ($currentStaffelId === (int)$s['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['naam'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="form-hint">Als een staffel gekozen is, overschrijft de staffelprijs de vaste prijs hieronder.</p>
        </div>

        <div class="form-group">
            <label for="event_id">Event (privacy, optioneel)</label>
            <select id="event_id" name="event_id">
                <option value="">— Openbaar (zichtbaar voor iedereen) —</option>
                <?php
                $currentEventId = (int) ($_POST['event_id'] ?? $video['event_id'] ?? 0);
                foreach ($events as $ev): ?>
                    <option value="<?= (int)$ev['id'] ?>"
                        <?= ($currentEventId === (int)$ev['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($ev['naam'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="form-hint">Bij een event is de video alleen zichtbaar voor gebruikers die de toegangscode hebben ingevoerd.</p>
        </div>

        <div class="form-group" id="price-group-edit">
            <label for="price">Vaste prijs (EUR) <span id="price-required-edit" style="color:var(--danger)">*</span></label>
            <input type="number" id="price" name="price" min="0.01" step="0.01"
                   value="<?= htmlspecialchars((string)($_POST['price'] ?? $video['price']), ENT_QUOTES, 'UTF-8') ?>">
            <p class="form-hint">Wordt gebruikt als er geen staffel is, of als terugval.</p>
        </div>

        <div class="form-group">
            <label for="filename">Bestandsnaam <span style="color:var(--danger)">*</span></label>
            <input type="text" id="filename" name="filename" required
                   value="<?= htmlspecialchars($_POST['filename'] ?? $video['filename'], ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="form-group">
            <label>
                <input type="checkbox" name="active" value="1"
                    <?= ($video['active'] ? 'checked' : '') ?>>
                &nbsp;Actief (zichtbaar voor gebruikers)
            </label>
        </div>

        <div style="display:flex;gap:.75rem;">
            <button type="submit" class="btn btn-primary">Opslaan</button>
            <a href="?action=dashboard" class="btn btn-secondary">Annuleren</a>
        </div>
    </form>
</div>
<script>
(function(){
    var chk        = document.getElementById('gratis-edit');
    var sel        = document.getElementById('staffel_id');
    var inp        = document.getElementById('price');
    var req        = document.getElementById('price-required-edit');
    var staffelGrp = document.getElementById('staffel-group-edit');
    var priceGrp   = document.getElementById('price-group-edit');
    function toggle() {
        var gratis     = chk.checked;
        var hasStaffel = sel.value !== '';
        staffelGrp.style.display = gratis ? 'none' : '';
        priceGrp.style.display   = gratis ? 'none' : '';
        inp.required = !gratis && !hasStaffel;
        inp.min      = gratis ? '0' : '0.01';
        req.style.display = (gratis || hasStaffel) ? 'none' : '';
    }
    chk.addEventListener('change', toggle);
    sel.addEventListener('change', toggle);
    toggle();
})();
</script>

<?php
// ---- Verkopenoverzicht ------------------------------------
elseif ($action === 'purchases'): ?>

<div class="page-header">
    <h1>Verkopen</h1>
    <span class="text-muted" style="font-size:.9rem">Laatste 200 transacties</span>
</div>

<?php if (empty($purchases)): ?>
    <p class="text-muted">Nog geen aankopen.</p>
<?php else: ?>
<div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr>
                <th>Datum</th>
                <th>Gebruiker</th>
                <th>Video</th>
                <th>Bedrag</th>
                <th>Status</th>
                <th>Betaald op</th>
                <th>Mollie</th>
                <th>Acties</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($purchases as $p): ?>
            <tr>
                <td style="white-space:nowrap"><?= htmlspecialchars(substr($p['created_at'], 0, 16), ENT_QUOTES, 'UTF-8') ?></td>
                <td>
                    <?= htmlspecialchars($p['user_name'], ENT_QUOTES, 'UTF-8') ?><br>
                    <span class="text-muted" style="font-size:.8rem"><?= htmlspecialchars($p['email'], ENT_QUOTES, 'UTF-8') ?></span>
                </td>
                <td><?= htmlspecialchars($p['video_title'], ENT_QUOTES, 'UTF-8') ?></td>
                <td>&euro; <?= number_format((float) $p['amount'], 2, ',', '.') ?></td>
                <td><span class="status-<?= htmlspecialchars($p['status'], ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($p['status'], ENT_QUOTES, 'UTF-8') ?>
                </span></td>
                <td style="white-space:nowrap">
                    <?= $p['paid_at'] ? htmlspecialchars(substr($p['paid_at'], 0, 16), ENT_QUOTES, 'UTF-8') : '—' ?>
                </td>
                <td style="max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.75rem;">
                    <?= $p['mollie_payment_id'] ? htmlspecialchars($p['mollie_payment_id'], ENT_QUOTES, 'UTF-8') : '—' ?>
                </td>
                <td style="white-space:nowrap">
                    <?php if ($p['mollie_payment_id'] && in_array($p['status'], ['open', 'pending'], true)): ?>
                        <form method="post" action="?action=refresh_payment" style="display:inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="purchase_id" value="<?= (int) $p['id'] ?>">
                            <button type="submit" class="btn btn-secondary btn-sm" title="Status opvragen bij Mollie">Ververs</button>
                        </form>
                        <form method="post" action="?action=cancel_payment" style="display:inline"
                              onsubmit="return confirm('Betaling annuleren bij Mollie?');">
                            <?= csrfField() ?>
                            <input type="hidden" name="purchase_id" value="<?= (int) $p['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm" title="Annuleer deze betaling bij Mollie">Annuleer</button>
                        </form>
                    <?php else: ?>
                        <span class="text-muted" style="font-size:.8rem">—</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php
// ---- Verkoopoverzicht (pivot) -----------------------------
elseif ($action === 'sales_overview' && $salesOverview): ?>
<div class="page-header">
    <h1>Verkoopoverzicht</h1>
    <span class="text-muted" style="font-size:.9rem">Aantallen betaalde aankopen per video × bedrag</span>
</div>

<?php if (empty($salesOverview['pivot'])): ?>
    <p class="text-muted">Nog geen betaalde aankopen.</p>
<?php else: ?>
<div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr>
                <th>Video</th>
                <?php foreach ($salesOverview['amounts'] as $amt): ?>
                    <th style="text-align:right;">€&nbsp;<?= number_format((float) $amt, 2, ',', '.') ?></th>
                <?php endforeach; ?>
                <th style="text-align:right;">Totaal</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($salesOverview['pivot'] as $vid => $data): ?>
            <tr>
                <td><?= htmlspecialchars($data['title'], ENT_QUOTES, 'UTF-8') ?></td>
                <?php foreach ($salesOverview['amounts'] as $amt): ?>
                    <td style="text-align:right;">
                        <?= ($data['amounts'][$amt] ?? 0) > 0 ? (int)($data['amounts'][$amt] ?? 0) : '<span class="text-muted">—</span>' ?>
                    </td>
                <?php endforeach; ?>
                <td style="text-align:right;font-weight:600;">
                    &euro;&nbsp;<?= number_format($data['total'], 2, ',', '.') ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="font-weight:700;background:var(--surface-hover, rgba(255,255,255,.04));">
                <td>Aantal</td>
                <?php $grandCount = 0; foreach ($salesOverview['amounts'] as $amt): ?>
                    <?php $cnt = (int)($salesOverview['colTotals'][$amt] ?? 0); $grandCount += $cnt; ?>
                    <td style="text-align:right;"><?= $cnt > 0 ? $cnt : '<span class="text-muted">—</span>' ?></td>
                <?php endforeach; ?>
                <td style="text-align:right;"><?= $grandCount ?></td>
            </tr>
            <tr style="font-weight:700;background:var(--surface-hover, rgba(255,255,255,.04));">
                <td>Omzet</td>
                <?php foreach ($salesOverview['amounts'] as $amt): ?>
                    <td style="text-align:right;"></td>
                <?php endforeach; ?>
                <td style="text-align:right;">
                    &euro;&nbsp;<?= number_format($salesOverview['grandTotal'], 2, ',', '.') ?>
                </td>
            </tr>
        </tfoot>
    </table>
</div>
<?php endif; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
