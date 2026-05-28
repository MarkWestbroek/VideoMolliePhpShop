<?php
session_start();
// Als al ingelogd, direct naar members
if (!empty($_SESSION['user_id'])) {
    header('Location: /members/');
    exit;
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HB Foto & Video</title>
    <link rel="stylesheet" href="/assets/style.css">
    <style>
        .landing {
            max-width: 600px;
            margin: 80px auto;
            text-align: center;
            padding: 0 20px;
        }
        .landing h1 { font-size: 2.4rem; margin-bottom: 12px; }
        .landing p  { color: #aaa; font-size: 1.1rem; margin-bottom: 40px; }
        .btn-group  { display: flex; gap: 16px; justify-content: center; flex-wrap: wrap; }
        .btn-lg     { padding: 14px 36px; font-size: 1rem; border-radius: 4px; text-decoration: none; font-weight: 600; transition: opacity .2s; }
        .btn-lg:hover { opacity: .85; }
        .btn-primary { background: #e8a000; color: #111; }
        .btn-outline { border: 2px solid #e8a000; color: #e8a000; background: transparent; }
    </style>
</head>
<body>
    <div class="landing">
        <h1>HB Foto &amp; Video</h1>
        <p>Professionele fotografie- en videocursussen.<br>
           Bekijk en koop video's na het inloggen.</p>
        <div class="btn-group">
            <a href="/login.php"    class="btn-lg btn-primary">Inloggen</a>
            <a href="/register.php" class="btn-lg btn-outline">Account aanmaken</a>
        </div>
    </div>
</body>
</html>
