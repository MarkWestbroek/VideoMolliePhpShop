<?php
declare(strict_types=1);

// Vereist dat $pageTitle gezet is vóór dit bestand geïncludeerd wordt
// Vereist dat config.php al geladen is (zodat BASE_URL beschikbaar is)

$_currentUser = currentUser();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'HB Foto & Video', ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/style.css">
</head>
<body>
<header class="site-header">
    <div class="container header-inner">
        <a href="<?= BASE_URL ?>" class="logo">HB Foto &amp; Video</a>
        <nav>
            <?php if ($_currentUser): ?>
                <a href="<?= BASE_URL ?>/members/">Mijn video&rsquo;s</a>
                <a href="<?= BASE_URL ?>/members/account.php">Mijn account</a>
                <a href="<?= BASE_URL ?>/members/help.php">Help</a>
                <?php if ($_currentUser['is_admin']): ?>
                    <a href="<?= BASE_URL ?>/admin/">Beheer</a>
                    <a href="<?= BASE_URL ?>/admin/help.php">Handleiding</a>
                <?php endif; ?>
                <a href="<?= BASE_URL ?>/logout.php" class="btn-nav">
                    Uitloggen
                    <span class="nav-name">(<?= htmlspecialchars($_currentUser['name'], ENT_QUOTES, 'UTF-8') ?>)</span>
                </a>
            <?php else: ?>
                <a href="<?= BASE_URL ?>/login.php">Inloggen</a>
                <a href="<?= BASE_URL ?>/register.php" class="btn-nav">Registreren</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main class="container">
<?php if ($_currentUser && $_currentUser['email_verified_at'] === null): ?>
    <div class="alert alert-warning" style="margin-top:1rem;">
        &#9888; Je e-mailadres is nog niet bevestigd.
        <a href="<?= BASE_URL ?>/verify.php?resend=1" style="color:inherit;font-weight:600;text-decoration:underline;">Verificatie-e-mail opnieuw versturen</a>
    </div>
<?php endif; ?>
