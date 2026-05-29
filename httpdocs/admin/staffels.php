<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

requireAdmin();

$action  = $_GET['action'] ?? 'list';
$message = '';
$error   = '';

// ============================================================
// POST-acties
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    // --- Staffel toevoegen ----------------------------------
    if ($action === 'add') {
        $naam        = trim($_POST['naam']        ?? '');
        $beschrijving = trim($_POST['beschrijving'] ?? '');
        if ($naam === '') {
            $error = 'Naam is verplicht.';
        } else {
            db()->prepare('INSERT INTO staffels (naam, beschrijving) VALUES (?,?)')
               ->execute([$naam, $beschrijving ?: null]);
            $message = 'Staffel aangemaakt.';
            $action  = 'list';
        }
    }

    // --- Staffel bewerken -----------------------------------
    elseif ($action === 'edit') {
        $id          = (int) ($_POST['id'] ?? 0);
        $naam        = trim($_POST['naam']        ?? '');
        $beschrijving = trim($_POST['beschrijving'] ?? '');
        if ($id <= 0 || $naam === '') {
            $error = 'Ongeldige invoer.';
        } else {
            db()->prepare('UPDATE staffels SET naam=?, beschrijving=? WHERE id=?')
               ->execute([$naam, $beschrijving ?: null, $id]);
            $message = 'Staffel bijgewerkt.';
            $action  = 'list';
        }
    }

    // --- Staffel verwijderen --------------------------------
    elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            db()->prepare('DELETE FROM staffels WHERE id=?')->execute([$id]);
            $message = 'Staffel verwijderd.';
        }
        $action = 'list';
    }

    // --- Prijstrap toevoegen --------------------------------
    elseif ($action === 'add_trap') {
        $staffelId  = (int) ($_POST['staffel_id'] ?? 0);
        $aantalVan  = (int) ($_POST['aantal_van'] ?? 0);
        $aantalTot  = (int) ($_POST['aantal_tot'] ?? 0);
        $prijs      = $_POST['prijs'] ?? '';

        if ($staffelId <= 0 || $aantalVan < 1 || $aantalTot < $aantalVan || !is_numeric($prijs) || (float)$prijs < 0.01) {
            $error = 'Controleer de invoer: aantal_tot moet ≥ aantal_van zijn en prijs minimaal € 0,01.';
            $action = 'edit_trappen';
        } else {
            try {
                db()->prepare(
                    'INSERT INTO staffelprijzen (staffel_id, aantal_van, aantal_tot, prijs) VALUES (?,?,?,?)'
                )->execute([$staffelId, $aantalVan, $aantalTot, (float)$prijs]);
                $message = 'Prijstrap toegevoegd.';
            } catch (\PDOException $e) {
                $error = 'Aantal_van moet uniek zijn per staffel. Kies een ander startnummer.';
            }
            $action = 'edit_trappen';
        }
        $_GET['staffel_id'] = $staffelId;
    }

    // --- Prijstrap verwijderen ------------------------------
    elseif ($action === 'delete_trap') {
        $trapId    = (int) ($_POST['trap_id']    ?? 0);
        $staffelId = (int) ($_POST['staffel_id'] ?? 0);
        if ($trapId > 0) {
            db()->prepare('DELETE FROM staffelprijzen WHERE id=?')->execute([$trapId]);
            $message = 'Trap verwijderd.';
        }
        $action = 'edit_trappen';
        $_GET['staffel_id'] = $staffelId;
    }
}

// ============================================================
// Data ophalen
// ============================================================
$staffels = db()->query('SELECT * FROM staffels ORDER BY naam')->fetchAll();

$staffel  = null;
$trappen  = [];
if ($action === 'edit_trappen' || ($action === 'edit' && empty($error))) {
    $staffelId = (int) ($_GET['staffel_id'] ?? $_POST['staffel_id'] ?? 0);
    if ($staffelId > 0) {
        $stmt = db()->prepare('SELECT * FROM staffels WHERE id=? LIMIT 1');
        $stmt->execute([$staffelId]);
        $staffel = $stmt->fetch();
        $stmt2 = db()->prepare('SELECT * FROM staffelprijzen WHERE staffel_id=? ORDER BY aantal_van');
        $stmt2->execute([$staffelId]);
        $trappen = $stmt2->fetchAll();
    }
}

$editStaffel = null;
if ($action === 'edit') {
    $id = (int) ($_GET['id'] ?? 0);
    $stmt = db()->prepare('SELECT * FROM staffels WHERE id=? LIMIT 1');
    $stmt->execute([$id]);
    $editStaffel = $stmt->fetch();
    if (!$editStaffel) $action = 'list';
}

// ============================================================
// View
// ============================================================
$pageTitle = 'Staffels beheren — HB Foto & Video';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($message !== ''): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<nav style="display:flex;gap:.75rem;margin-bottom:1.75rem;border-bottom:1px solid var(--border);padding-bottom:.75rem;">
    <a href="<?= BASE_URL ?>/admin/"                class="btn btn-sm btn-secondary">&larr; Terug naar beheer</a>
    <a href="?action=list"  class="btn btn-sm <?= $action === 'list'  ? 'btn-primary' : 'btn-secondary' ?>">Alle staffels</a>
    <a href="?action=add"   class="btn btn-sm <?= $action === 'add'   ? 'btn-primary' : 'btn-secondary' ?>">+ Staffel toevoegen</a>
</nav>

<?php if ($action === 'list'): ?>
<!-- ======================================================== -->
<!-- LIJST                                                     -->
<!-- ======================================================== -->
<h1>Staffels</h1>
<p class="text-muted" style="margin-bottom:1rem;">
    Een staffel bepaalt de prijs per video op basis van het aantal al gekochte video's in dezelfde staffel.
</p>

<?php if (empty($staffels)): ?>
    <p class="text-muted">Nog geen staffels. <a href="?action=add">Voeg er een toe</a>.</p>
<?php else: ?>
<div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr><th>ID</th><th>Naam</th><th>Beschrijving</th><th>Trappen</th><th>Acties</th></tr>
        </thead>
        <tbody>
        <?php foreach ($staffels as $s):
            $stmt = db()->prepare('SELECT COUNT(*) FROM staffelprijzen WHERE staffel_id=?');
            $stmt->execute([$s['id']]);
            $trapCount = (int) $stmt->fetchColumn();
        ?>
            <tr>
                <td><?= (int) $s['id'] ?></td>
                <td><?= htmlspecialchars($s['naam'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string)($s['beschrijving'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= $trapCount ?> trap<?= $trapCount !== 1 ? 'pen' : '' ?></td>
                <td class="actions" style="display:flex;gap:.5rem;flex-wrap:wrap;">
                    <a href="?action=edit&id=<?= (int)$s['id'] ?>" class="btn btn-secondary btn-sm">Naam</a>
                    <a href="?action=edit_trappen&staffel_id=<?= (int)$s['id'] ?>" class="btn btn-primary btn-sm">Prijstrappen</a>
                    <form method="post" action="?action=delete" style="margin:0;"
                          onsubmit="return confirm('Staffel verwijderen? Video\'s verliezen hun staffelkoppeling.');">
                        <?= csrfField() ?>
                        <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm">Verwijder</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php elseif ($action === 'add'): ?>
<!-- ======================================================== -->
<!-- TOEVOEGEN                                                 -->
<!-- ======================================================== -->
<h1>Staffel toevoegen</h1>
<div class="form-card" style="max-width:520px;margin:0;">
    <form method="post" action="?action=add">
        <?= csrfField() ?>
        <div class="form-group">
            <label for="naam">Naam <span style="color:var(--danger)">*</span></label>
            <input type="text" id="naam" name="naam" required maxlength="100"
                   value="<?= htmlspecialchars($_POST['naam'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="form-group">
            <label for="beschrijving">Beschrijving</label>
            <textarea id="beschrijving" name="beschrijving" maxlength="1000"><?= htmlspecialchars($_POST['beschrijving'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
        <div style="display:flex;gap:.75rem;">
            <button type="submit" class="btn btn-primary">Opslaan</button>
            <a href="?action=list" class="btn btn-secondary">Annuleren</a>
        </div>
    </form>
</div>

<?php elseif ($action === 'edit' && $editStaffel): ?>
<!-- ======================================================== -->
<!-- NAAM BEWERKEN                                             -->
<!-- ======================================================== -->
<h1>Staffel bewerken</h1>
<div class="form-card" style="max-width:520px;margin:0;">
    <form method="post" action="?action=edit">
        <?= csrfField() ?>
        <input type="hidden" name="id" value="<?= (int)$editStaffel['id'] ?>">
        <div class="form-group">
            <label for="naam">Naam <span style="color:var(--danger)">*</span></label>
            <input type="text" id="naam" name="naam" required maxlength="100"
                   value="<?= htmlspecialchars($_POST['naam'] ?? $editStaffel['naam'], ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="form-group">
            <label for="beschrijving">Beschrijving</label>
            <textarea id="beschrijving" name="beschrijving" maxlength="1000"><?= htmlspecialchars($_POST['beschrijving'] ?? (string)($editStaffel['beschrijving'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
        <div style="display:flex;gap:.75rem;">
            <button type="submit" class="btn btn-primary">Opslaan</button>
            <a href="?action=list" class="btn btn-secondary">Annuleren</a>
        </div>
    </form>
</div>

<?php elseif ($action === 'edit_trappen' && $staffel): ?>
<!-- ======================================================== -->
<!-- PRIJSTRAPPEN BEHEREN                                      -->
<!-- ======================================================== -->
<h1>Prijstrappen: <em><?= htmlspecialchars($staffel['naam'], ENT_QUOTES, 'UTF-8') ?></em></h1>
<p class="text-muted" style="margin-bottom:1.5rem;">
    Elke trap zegt: "als dit de <strong>N-de</strong> video is die de klant koopt (binnen deze staffel),
    dan betaalt hij <strong>€ X</strong>."<br>
    Gebruik <code>aantal_tot = 999</code> voor "alle overige aankopen".
</p>

<!-- Bestaande trappen -->
<?php if (empty($trappen)): ?>
    <p class="text-muted">Nog geen trappen voor deze staffel.</p>
<?php else: ?>
<div class="table-wrap" style="margin-bottom:2rem;">
    <table class="data-table">
        <thead>
            <tr><th>Aankoop #</th><th>Prijs</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($trappen as $t): ?>
            <tr>
                <td>
                    <?php if ((int)$t['aantal_van'] === (int)$t['aantal_tot']): ?>
                        <?= (int)$t['aantal_van'] ?>e aankoop
                    <?php elseif ((int)$t['aantal_tot'] >= 999): ?>
                        <?= (int)$t['aantal_van'] ?>e aankoop en verder
                    <?php else: ?>
                        <?= (int)$t['aantal_van'] ?>e – <?= (int)$t['aantal_tot'] ?>e aankoop
                    <?php endif; ?>
                </td>
                <td>&euro; <?= number_format((float)$t['prijs'], 2, ',', '.') ?></td>
                <td>
                    <form method="post" action="?action=delete_trap&staffel_id=<?= (int)$staffel['id'] ?>"
                          style="margin:0;" onsubmit="return confirm('Trap verwijderen?');">
                        <?= csrfField() ?>
                        <input type="hidden" name="trap_id"    value="<?= (int)$t['id'] ?>">
                        <input type="hidden" name="staffel_id" value="<?= (int)$staffel['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm">Verwijder</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Trap toevoegen -->
<h2 style="font-size:1.1rem;margin-bottom:1rem;">Trap toevoegen</h2>
<div class="form-card" style="max-width:520px;margin:0;">
    <form method="post" action="?action=add_trap&staffel_id=<?= (int)$staffel['id'] ?>">
        <?= csrfField() ?>
        <input type="hidden" name="staffel_id" value="<?= (int)$staffel['id'] ?>">

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
            <div class="form-group">
                <label for="aantal_van">Aankoop van <span style="color:var(--danger)">*</span></label>
                <input type="number" id="aantal_van" name="aantal_van" required min="1" max="999"
                       value="<?= htmlspecialchars($_POST['aantal_van'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <p class="form-hint">bijv. 1</p>
            </div>
            <div class="form-group">
                <label for="aantal_tot">t/m aankoop <span style="color:var(--danger)">*</span></label>
                <input type="number" id="aantal_tot" name="aantal_tot" required min="1" max="999"
                       value="<?= htmlspecialchars($_POST['aantal_tot'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <p class="form-hint">bijv. 1 of 999</p>
            </div>
            <div class="form-group">
                <label for="prijs">Prijs (EUR) <span style="color:var(--danger)">*</span></label>
                <input type="number" id="prijs" name="prijs" required min="0.01" step="0.01"
                       value="<?= htmlspecialchars($_POST['prijs'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
        </div>

        <div style="display:flex;gap:.75rem;">
            <button type="submit" class="btn btn-primary">Toevoegen</button>
            <a href="?action=list" class="btn btn-secondary">Klaar</a>
        </div>
    </form>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
