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
        $staffelId   = ($_POST['staffel_id'] ?? '') !== '' ? (int) $_POST['staffel_id'] : null;

        if ($title === '' || $filename === '') {
            $error = 'Vul alle verplichte velden in.';
        } elseif ($staffelId === null && ($price === '' || !is_numeric($price) || (float) $price < 0.01)) {
            $error = 'Voer een geldige prijs in (minimaal € 0,01) of kies een staffel.';
        } else {
            $safeFilename = basename($filename);
            $fallbackPrice = ($price !== '' && is_numeric($price)) ? (float) $price : 0.01;
            $stmt = db()->prepare(
                'INSERT INTO videos (title, description, price, staffel_id, filename) VALUES (?,?,?,?,?)'
            );
            $stmt->execute([$title, $description, $fallbackPrice, $staffelId, $safeFilename]);
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
        $staffelId   = ($_POST['staffel_id'] ?? '') !== '' ? (int) $_POST['staffel_id'] : null;

        if ($id <= 0 || $title === '' || $filename === '') {
            $error = 'Vul alle verplichte velden in.';
        } elseif ($staffelId === null && ($price === '' || !is_numeric($price) || (float) $price < 0.01)) {
            $error = 'Voer een geldige prijs in of kies een staffel.';
        } else {
            $safeFilename = basename($filename);
            $fallbackPrice = ($price !== '' && is_numeric($price)) ? (float) $price : 0.01;
            $stmt = db()->prepare(
                'UPDATE videos SET title=?, description=?, price=?, staffel_id=?, filename=?, active=? WHERE id=?'
            );
            $stmt->execute([$title, $description, $fallbackPrice, $staffelId, $safeFilename, $active, $id]);
            $message = 'Video bijgewerkt.';
            $action  = 'dashboard';
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

if ($action === 'dashboard') {
    $videos = db()->query(
        'SELECT v.*, s.naam AS staffel_naam
         FROM videos v
         LEFT JOIN staffels s ON s.id = v.staffel_id
         ORDER BY v.created_at DESC'
    )->fetchAll();
}

if ($action === 'purchases') {
    $purchases = db()->query(
        'SELECT p.id, u.name AS user_name, u.email, v.title AS video_title,
                p.amount, p.status, p.created_at, p.paid_at
         FROM purchases p
         JOIN users  u ON u.id = p.user_id
         JOIN videos v ON v.id = p.video_id
         ORDER BY p.created_at DESC
         LIMIT 200'
    )->fetchAll();
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
    <a href="?action=add_video"  class="btn btn-sm <?= $action === 'add_video'  ? 'btn-primary' : 'btn-secondary' ?>">+ Video toevoegen</a>
    <a href="<?= BASE_URL ?>/admin/staffels.php" class="btn btn-sm btn-secondary">&#9654; Staffels</a>
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
                <th>Prijs / Staffel</th>
                <th>Bestand</th>
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
                        <span title="Vaste terugvalprijs: &euro; <?= number_format((float)$v['price'], 2, ',', '.') ?>">
                            &#9654; <?= htmlspecialchars($v['staffel_naam'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    <?php else: ?>
                        &euro; <?= number_format((float) $v['price'], 2, ',', '.') ?>
                    <?php endif; ?>
                </td>
                <td><code style="font-size:.8rem"><?= htmlspecialchars($v['filename'], ENT_QUOTES, 'UTF-8') ?></code></td>
                <td>
                    <?php if ($v['active']): ?>
                        <span class="status-paid">Actief</span>
                    <?php else: ?>
                        <span class="status-inactive">Inactief</span>
                    <?php endif; ?>
                </td>
                <td class="actions">
                    <a href="?action=edit_video&id=<?= (int) $v['id'] ?>" class="btn btn-secondary btn-sm">Bewerken</a>
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
    var sel = document.getElementById('staffel_id');
    var inp = document.getElementById('price');
    var req = document.getElementById('price-required-add');
    function toggle() {
        var hasStaffel = sel.value !== '';
        inp.required = !hasStaffel;
        req.style.display = hasStaffel ? 'none' : '';
    }
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

        <div class="form-group">
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
    var sel = document.getElementById('staffel_id');
    var inp = document.getElementById('price');
    var req = document.getElementById('price-required-edit');
    function toggle() {
        var hasStaffel = sel.value !== '';
        inp.required = !hasStaffel;
        req.style.display = hasStaffel ? 'none' : '';
    }
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
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
