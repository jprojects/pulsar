<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';
require_login();

$reports = list_reports(12);
layout_start('Tauler', 'home');
?>
<section class="hero">
  <div>
    <h2>Hola, <?= e(current_user()) ?> 👋</h2>
    <p>Analitza webs, detecta problemes i lliura informes professionals en pocs minuts.</p>
  </div>
</section>

<section class="modules">
  <a class="module" href="seo.php">
    <span class="module-icon seo"><?= icon('seo') ?></span>
    <h3>Anàlisi SEO</h3>
    <p>Enganxa una llista d’URL i obtén una auditoria tècnica i on-page amb solucions, exportable a PDF.</p>
    <span class="module-cta">Comença →</span>
  </a>
  <a class="module" href="geo.php">
    <span class="module-icon geo"><?= icon('geo') ?></span>
    <h3>GEO · Visibilitat en IA</h3>
    <p>Comprova si ChatGPT, Claude, Perplexity o Google AI poden llegir i citar el teu contingut. Sense cost de tokens.</p>
    <span class="module-cta">Comença →</span>
  </a>
  <a class="module" href="legal.php">
    <span class="module-icon legal"><?= icon('legal') ?></span>
    <h3>Textos legals</h3>
    <p>Genera la política de privadesa i la de cookies (RGPD, LOPDGDD i LSSI) en català, castellà, anglès o francès.</p>
    <span class="module-cta">Comença →</span>
  </a>
</section>

<section class="card">
  <div class="card-head"><h3>Informes recents</h3></div>
  <?php if (!$reports): ?>
    <p class="muted">Encara no hi ha informes. Llança la teva primera anàlisi SEO o GEO.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Tipus</th><th>URL</th><th>Núm. URL</th><th>Puntuació</th><th>Data</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($reports as $r): ?>
          <tr>
            <td><span class="tag tag-<?= e($r['type']) ?>"><?= strtoupper(e($r['type'])) ?></span></td>
            <td class="url-cell"><?= e($r['first_url']) ?></td>
            <td><?= (int) $r['urls'] ?></td>
            <td><span class="score-pill <?= score_class($r['score']) ?>"><?= (int) $r['score'] ?></span></td>
            <td class="muted"><?= e(date('d/m/Y H:i', strtotime($r['created_at']))) ?></td>
            <td class="actions">
              <a href="<?= e($r['type']) ?>.php?id=<?= e($r['id']) ?>">Veure</a>
              <a href="report.php?type=<?= e($r['type']) ?>&amp;id=<?= e($r['id']) ?>">PDF</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
<?php layout_end();
