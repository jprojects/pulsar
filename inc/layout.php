<?php
declare(strict_types=1);

function icon(string $name): string
{
    $paths = [
        'home'   => '<path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
        'seo'    => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/><path d="M8 11h6M11 8v6"/>',
        'geo'    => '<path d="M12 3l1.9 4.6L18.5 9.5l-4.6 1.9L12 16l-1.9-4.6L5.5 9.5l4.6-1.9z"/><path d="M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z"/>',
        'legal'  => '<path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5"/><path d="M10 13h6M10 17h6"/>',
        'logout' => '<path d="M15 4h4v16h-4"/><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/>',
        'pdf'    => '<path d="M12 4v11"/><path d="m7 10 5 5 5-5"/><path d="M5 20h14"/>',
        'copy'   => '<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V5a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h3"/>',
        'menu'   => '<path d="M4 6h16M4 12h16M4 18h16"/>',
    ];
    return '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . ($paths[$name] ?? '') . '</svg>';
}

function pulsar_logo(): string
{
    return '<svg class="logo-mark" viewBox="0 0 32 32" aria-hidden="true"><defs><linearGradient id="lg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#a78bfa"/><stop offset="1" stop-color="#22d3ee"/></linearGradient></defs>'
        . '<circle cx="16" cy="16" r="14" fill="none" stroke="url(#lg)" stroke-width="2" opacity=".45"/>'
        . '<circle cx="16" cy="16" r="9" fill="none" stroke="url(#lg)" stroke-width="2" opacity=".75"/>'
        . '<circle cx="16" cy="16" r="4" fill="url(#lg)"/></svg>';
}

function layout_start(string $title, string $active = ''): void
{
    $app = config('app_name', 'Pulsar');
    $nav = [
        'home'  => ['index.php', 'Tauler'],
        'seo'   => ['seo.php', 'Anàlisi SEO'],
        'geo'   => ['geo.php', 'GEO · IA'],
        'legal' => ['legal.php', 'Textos legals'],
    ];
    ?>
<!doctype html>
<html lang="ca">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> · <?= e($app) ?></title>
<link rel="icon" href="assets/img/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="assets/css/app.css?v=<?= PULSAR_VERSION ?>">
</head>
<body>
<div class="shell">
  <aside class="sidebar" id="sidebar">
    <a class="brand" href="index.php"><?= pulsar_logo() ?><span><?= e($app) ?></span></a>
    <nav>
      <?php foreach ($nav as $key => [$href, $label]): ?>
        <a href="<?= $href ?>" class="<?= $active === $key ? 'active' : '' ?>"><?= icon($key) ?><span><?= e($label) ?></span></a>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar-foot">
      <div class="user"><span class="avatar"><?= e(mb_strtoupper(mb_substr((string) current_user(), 0, 1))) ?></span><?= e(current_user()) ?></div>
      <a href="logout.php" class="logout"><?= icon('logout') ?><span>Surt</span></a>
    </div>
  </aside>
  <main class="main">
    <header class="topbar">
      <button class="menu-btn" type="button" data-toggle-sidebar aria-label="Menú"><?= icon('menu') ?></button>
      <h1><?= e($title) ?></h1>
    </header>
    <?php foreach (take_flashes() as $f): ?>
      <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
    <?php endforeach; ?>
<?php
}

function layout_end(): void
{
    ?>
  </main>
</div>
<div class="loading" id="loading" hidden>
  <div class="loading-box">
    <div class="pulse"><span></span><span></span><span></span></div>
    <strong id="loading-title">Analitzant…</strong>
    <p id="loading-text">Pot trigar uns segons per URL.</p>
  </div>
</div>
<script src="assets/js/app.js?v=<?= PULSAR_VERSION ?>"></script>
</body>
</html>
<?php
}
