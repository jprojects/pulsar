<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';

if (current_user() !== null) {
    redirect('index.php');
}

$error = null;
$username = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if (($wait = login_locked_for()) > 0) {
        $error = 'Massa intents fallits. Torna-ho a provar d’aquí a ' . ceil($wait / 60) . ' minut(s).';
    } elseif (attempt_login($username, $password)) {
        redirect('index.php');
    } else {
        $error = 'Usuari o contrasenya incorrectes.';
    }
}
$app = config('app_name', 'Pulsar');
?>
<!doctype html>
<html lang="ca">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Accés · <?= e($app) ?></title>
<link rel="icon" href="assets/img/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="assets/css/app.css?v=<?= PULSAR_VERSION ?>">
</head>
<body class="login-page">
  <div class="login-glow" aria-hidden="true"></div>
  <form class="login-card" method="post" autocomplete="on">
    <div class="login-brand"><?= pulsar_logo() ?><span><?= e($app) ?></span></div>
    <p class="login-sub">Auditories SEO, GEO i textos legals</p>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <?= csrf_field() ?>
    <label>Usuari
      <input type="text" name="username" value="<?= e($username) ?>" required autofocus autocomplete="username">
    </label>
    <label>Contrasenya
      <input type="password" name="password" required autocomplete="current-password">
    </label>
    <button class="btn btn-primary btn-block" type="submit">Entra</button>
  </form>
</body>
</html>
