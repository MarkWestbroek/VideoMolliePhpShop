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

/**
 * Genereer een leesbare, willekeurige toegangscode.
 */
function genereerToegangscode(): string
{
    return strtoupper(bin2hex(random_bytes(4))); // bv. "A1B2C3D4"
}

// ============================================================
// POST-acties
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    // --- Event toevoegen ------------------------------------
    if ($action === 'add') {
        $naam         = trim($_POST['naam']         ?? '');
        $organisator  = trim($_POST['organisator']  ?? '');
        $beschrijving = trim($_POST['beschrijving'] ?? '');
        $code         = trim($_POST['toegangscode'] ?? '');
        $active       = isset($_POST['active']) ? 1 : 0;

        if ($code === '') {
            $code = genereerToegangscode();
        }

        if ($naam === '' || $organisator === '') {
            $error  = 'Naam en organisator zijn verplicht.';
            $action = 'add';
        } else {
            try {
                db()->prepare(
                    'INSERT INTO events (naam, organisator, beschrijving, toegangscode, active) VALUES (?,?,?,?,?)'
                )->execute([$naam, $organisator, $beschrijving ?: null, $code, $active]);
                $message = 'Event aangemaakt. Toegangscode: ' . $code;
                $action  = 'list';
            } catch (\PDOException $e) {
                $error  = 'Deze toegangscode bestaat al. Kies een andere of laat het veld leeg voor een automatische code.';
                $action = 'add';
            }
        }
    }

    // --- Event bewerken -------------------------------------
    elseif ($action === 'edit') {
        $id           = (int) ($_POST['id'] ?? 0);
        $naam         = trim($_POST['naam']         ?? '');
        $organisator  = trim($_POST['organisator']  ?? '');
        $beschrijving = trim($_POST['beschrijving'] ?? '');
        $code         = trim($_POST['toegangscode'] ?? '');
        $active       = isset($_POST['active']) ? 1 : 0;

        if ($id <= 0 || $naam === '' || $organisator === '' || $code === '') {
            $error  = 'Vul alle verplichte velden in.';
            $action = 'edit';
            $_GET['id'] = $id;
        } else {
            try {
                db()->prepare(
                    'UPDATE events SET naam=?, organisator=?, beschrijving=?, toegangscode=?, active=? WHERE id=?'
                )->execute([$naam, $organisator, $beschrijving ?: null, $code, $active, $id]);
                $message = 'Event bijgewerkt.';
                $action  = 'list';
            } catch (\PDOException $e) {
                $error  = 'Deze toegangscode is al in gebruik bij een ander event.';
                $action = 'edit';
                $_GET['id'] = $id;
            }
        }
    }

    // --- Event verwijderen ----------------------------------
    elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            db()->prepare('DELETE FROM events WHERE id=?')->execute([$id]);
            $message = 'Event verwijderd.';
        }
        $action = 'list';
    }
}

// ============================================================
// Data ophalen
// ============================================================
$events = db()->query('SELECT * FROM events ORDER BY created_at DESC')->fetchAll();

$editEvent = null;
if ($action === 'edit') {
    $id = (int) ($_GET['id'] ?? 0);
    $stmt = db()->prepare('SELECT * FROM events WHERE id=? LIMIT 1');
    $stmt->execute([$id]);
    $editEvent = $stmt->fetch();
    if (!$editEvent) {
        $action = 'list';
    }
}

// ============================================================
// View
// ============================================================
$pageTitle = 'Events beheren — HB Foto & Video';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($message !== ''): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<nav style="display:flex;gap:.75rem;margin-bottom:1.75rem;border-bottom:1px solid var(--border);padding-bottom:.75rem;flex-wrap:wrap;">
    <a href="<?= BASE_URL ?>/admin/"            class="btn btn-sm btn-secondary">&larr; Terug naar beheer</a>
    <a href="?action=list" class="btn btn-sm <?= $action === 'list' ? 'btn-primary' : 'btn-secondary' ?>">Alle events</a>
    <a href="?action=add"  class="btn btn-sm <?= $action === 'add'  ? 'btn-primary' : 'btn-secondary' ?>">+ Event toevoegen</a>
</nav>

<?php if ($action === 'list'): ?>
<!-- ======================================================== -->
<!-- LIJST                                                     -->
<!-- ======================================================== -->
<h1>Events</h1>
<p class="text-muted" style="margin-bottom:1rem;">
    Een event bundelt video's die alleen zichtbaar zijn voor gebruikers die de
    bijbehorende toegangscode hebben ingevoerd. Deel de code met de bezoekers van het event.
</p>

<?php if (empty($events)): ?>
    <p class="text-muted">Nog geen events. <a href="?action=add">Voeg er een toe</a>.</p>
<?php else: ?>
<div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr>
                <th>Naam</th><th>Organisator</th><th>Toegangscode</th>
                <th>Video's</th><th>Toegang</th><th>Status</th><th>Acties</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($events as $e):
            $stmt = db()->prepare('SELECT COUNT(*) FROM videos WHERE event_id=?');
            $stmt->execute([$e['id']]);
            $videoCount = (int) $stmt->fetchColumn();

            $stmt = db()->prepare('SELECT COUNT(*) FROM event_access WHERE event_id=?');
            $stmt->execute([$e['id']]);
            $userCount = (int) $stmt->fetchColumn();
        ?>
            <tr>
                <td><?= htmlspecialchars($e['naam'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($e['organisator'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><code><?= htmlspecialchars($e['toegangscode'], ENT_QUOTES, 'UTF-8') ?></code></td>
                <td><?= $videoCount ?></td>
                <td><?= $userCount ?> gebruiker<?= $userCount !== 1 ? 's' : '' ?></td>
                <td>
                    <?php if ($e['active']): ?>
                        <span class="status-paid">Actief</span>
                    <?php else: ?>
                        <span class="status-inactive">Inactief</span>
                    <?php endif; ?>
                </td>
                <td class="actions" style="display:flex;gap:.5rem;flex-wrap:wrap;">
                    <a href="?action=edit&id=<?= (int)$e['id'] ?>" class="btn btn-secondary btn-sm">Bewerken</a>
                    <form method="post" action="?action=delete" style="margin:0;"
                          onsubmit="return confirm('Event verwijderen? Video\'s verliezen hun eventkoppeling en worden weer openbaar.');">
                        <?= csrfField() ?>
                        <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
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
<h1>Event toevoegen</h1>
<div class="form-card" style="max-width:560px;margin:0;">
    <form method="post" action="?action=add">
        <?= csrfField() ?>
        <div class="form-group">
            <label for="naam">Naam <span style="color:var(--danger)">*</span></label>
            <input type="text" id="naam" name="naam" required maxlength="150"
                   value="<?= htmlspecialchars($_POST['naam'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="form-group">
            <label for="organisator">Organisator <span style="color:var(--danger)">*</span></label>
            <input type="text" id="organisator" name="organisator" required maxlength="150"
                   value="<?= htmlspecialchars($_POST['organisator'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="form-group">
            <label for="beschrijving">Beschrijving</label>
            <textarea id="beschrijving" name="beschrijving" maxlength="1000"><?= htmlspecialchars($_POST['beschrijving'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
        <div class="form-group">
            <label for="toegangscode">Toegangscode</label>
            <input type="text" id="toegangscode" name="toegangscode" maxlength="64"
                   value="<?= htmlspecialchars($_POST['toegangscode'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <p class="form-hint">Laat leeg om automatisch een code te genereren.</p>
        </div>
        <div class="form-group">
            <label>
                <input type="checkbox" name="active" value="1" checked>
                &nbsp;Actief (code kan worden ingevoerd)
            </label>
        </div>
        <div style="display:flex;gap:.75rem;">
            <button type="submit" class="btn btn-primary">Opslaan</button>
            <a href="?action=list" class="btn btn-secondary">Annuleren</a>
        </div>
    </form>
</div>

<?php elseif ($action === 'edit' && $editEvent): ?>
<!-- ======================================================== -->
<!-- BEWERKEN                                                  -->
<!-- ======================================================== -->
<h1>Event bewerken</h1>
<div class="form-card" style="max-width:560px;margin:0;">
    <form method="post" action="?action=edit">
        <?= csrfField() ?>
        <input type="hidden" name="id" value="<?= (int)$editEvent['id'] ?>">
        <div class="form-group">
            <label for="naam">Naam <span style="color:var(--danger)">*</span></label>
            <input type="text" id="naam" name="naam" required maxlength="150"
                   value="<?= htmlspecialchars($_POST['naam'] ?? $editEvent['naam'], ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="form-group">
            <label for="organisator">Organisator <span style="color:var(--danger)">*</span></label>
            <input type="text" id="organisator" name="organisator" required maxlength="150"
                   value="<?= htmlspecialchars($_POST['organisator'] ?? $editEvent['organisator'], ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="form-group">
            <label for="beschrijving">Beschrijving</label>
            <textarea id="beschrijving" name="beschrijving" maxlength="1000"><?= htmlspecialchars($_POST['beschrijving'] ?? (string)($editEvent['beschrijving'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
        <div class="form-group">
            <label for="toegangscode">Toegangscode <span style="color:var(--danger)">*</span></label>
            <input type="text" id="toegangscode" name="toegangscode" required maxlength="64"
                   value="<?= htmlspecialchars($_POST['toegangscode'] ?? $editEvent['toegangscode'], ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="form-group">
            <label>
                <input type="checkbox" name="active" value="1" <?= $editEvent['active'] ? 'checked' : '' ?>>
                &nbsp;Actief (code kan worden ingevoerd)
            </label>
        </div>
        <div style="display:flex;gap:.75rem;">
            <button type="submit" class="btn btn-primary">Opslaan</button>
            <a href="?action=list" class="btn btn-secondary">Annuleren</a>
        </div>
    </form>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
