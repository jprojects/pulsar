<?php
declare(strict_types=1);

/** Processa el formulari d’URL, executa l’analitzador i redirigeix a l’informe. */
function handle_analysis(string $type, string $raw, int $max, callable $analyzer): void
{
    $urls = parse_url_list($raw, $max);
    if (!$urls) {
        flash('error', 'No s’ha trobat cap URL vàlida. Escriu-ne una per línia, per exemple https://elmeudomini.cat/');
        redirect("$type.php");
    }
    $totalLines = count(array_filter(preg_split('/[\s,;]+/', $raw) ?: []));
    if ($totalLines > $max) {
        flash('warning', "Només s’han analitzat les primeres $max URL (límit configurat).");
    }

    @set_time_limit(0);
    ignore_user_abort(true);
    session_write_close(); // no bloquejar altres pestanyes mentre analitza

    $results = [];
    foreach ($urls as $url) {
        $results[] = $analyzer($url);
    }
    $scores = array_column($results, 'score');
    session_start();
    $id = save_report($type, [
        'results' => $results,
        'average' => $scores ? (int) round(array_sum($scores) / count($scores)) : 0,
    ]);
    redirect("$type.php?id=$id");
}

function status_label(string $s): string
{
    return ['pass' => 'Correcte', 'warn' => 'Millorable', 'fail' => 'Crític', 'info' => 'Info'][$s] ?? $s;
}

function render_url_form(string $type, string $value, int $max, string $help): void
{
    ?>
<form class="card analyze-form" method="post" data-loading="Analitzant URL…">
  <?= csrf_field() ?>
  <label for="urls" class="label">URL a analitzar <span class="muted">(una per línia · màxim <?= $max ?>)</span></label>
  <textarea id="urls" name="urls" rows="6" placeholder="https://elmeudomini.cat/&#10;https://elmeudomini.cat/serveis/&#10;https://unaltredomini.com/blog/article" required><?= e($value) ?></textarea>
  <div class="form-foot">
    <p class="muted small"><?= $help ?></p>
    <button class="btn btn-primary" type="submit"><?= icon($type) ?> Analitza</button>
  </div>
</form>
<?php
}

function render_report(string $type, array $report, array $categories): void
{
    $results = $report['results'];
    $avg = (int) $report['average'];
    ?>
<section class="report-head card">
  <div class="score-ring <?= score_class($avg) ?>" style="--p: <?= $avg ?>">
    <span><?= $avg ?></span><small>mitjana</small>
  </div>
  <div class="report-meta">
    <h2>Informe <?= strtoupper($type) ?> · <?= count($results) ?> URL</h2>
    <p class="muted">Generat el <?= e(date('d/m/Y \a \l\e\s H:i', strtotime($report['created_at']))) ?></p>
  </div>
  <div class="report-actions">
    <a class="btn btn-primary" href="report.php?type=<?= $type ?>&amp;id=<?= e($report['id']) ?>"><?= icon('pdf') ?> Descarrega el PDF</a>
    <a class="btn btn-ghost" href="report.php?type=<?= $type ?>&amp;id=<?= e($report['id']) ?>&amp;inline=1" target="_blank" rel="noopener">Previsualització</a>
  </div>
</section>

<?php foreach ($results as $i => $r):
    $counts = ['fail' => 0, 'warn' => 0, 'pass' => 0, 'info' => 0];
    foreach ($r['checks'] as $c) {
        $counts[$c['status']]++;
    }
    $sorted = $r['checks'];
    usort($sorted, static fn ($a, $b) => array_search($a['status'], ['fail', 'warn', 'info', 'pass'], true) <=> array_search($b['status'], ['fail', 'warn', 'info', 'pass'], true)
        ?: $b['weight'] <=> $a['weight']);
    ?>
<details class="card result" <?= $i === 0 ? 'open' : '' ?>>
  <summary>
    <span class="score-pill lg <?= score_class((int) $r['score']) ?>"><?= (int) $r['score'] ?></span>
    <span class="result-title">
      <strong><?= e($r['url']) ?></strong>
      <?php if (!empty($r['title'])): ?><small class="muted"><?= e($r['title']) ?></small><?php endif; ?>
      <?php if ($r['final_url'] && $r['final_url'] !== $r['url']): ?><small class="muted">→ <?= e($r['final_url']) ?></small><?php endif; ?>
    </span>
    <span class="counts">
      <span class="c fail" title="Crítics"><?= $counts['fail'] ?></span>
      <span class="c warn" title="Millorables"><?= $counts['warn'] ?></span>
      <span class="c pass" title="Correctes"><?= $counts['pass'] ?></span>
    </span>
  </summary>

  <?php if (!empty($r['metrics'])): ?>
  <div class="metrics">
    <?php foreach ($r['metrics'] as $label => $value): ?>
      <div class="metric"><small><?= e($label) ?></small><strong><?= e($value) ?></strong></div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($type === 'geo' && !empty($r['bots'])): ?>
  <h4 class="section-title">Rastrejadors d’IA segons el robots.txt</h4>
  <div class="bots">
    <?php foreach ($r['bots'] as $b): ?>
      <div class="bot <?= $b['allowed'] ? 'ok' : ($b['type'] === 'search' ? 'bad' : 'mid') ?>" title="<?= e($b['role']) ?>">
        <strong><?= e($b['ua']) ?></strong>
        <small><?= e($b['company']) ?> · <?= $b['type'] === 'search' ? 'cerca' : 'entrenament' ?></small>
        <span><?= $b['allowed'] ? 'Permès' : 'Bloquejat' ?></span>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <h4 class="section-title">Comprovacions</h4>
  <div class="filters" data-filters>
    <button type="button" class="active" data-filter="all">Totes</button>
    <button type="button" data-filter="fail">Crítiques (<?= $counts['fail'] ?>)</button>
    <button type="button" data-filter="warn">Millorables (<?= $counts['warn'] ?>)</button>
    <button type="button" data-filter="pass">Correctes (<?= $counts['pass'] ?>)</button>
  </div>
  <ul class="checks">
    <?php foreach ($sorted as $c): ?>
      <li class="check <?= e($c['status']) ?>" data-status="<?= e($c['status']) ?>">
        <span class="badge <?= e($c['status']) ?>"><?= status_label($c['status']) ?></span>
        <div>
          <div class="check-title"><?= e($c['title']) ?> <span class="cat"><?= e($categories[$c['cat']] ?? '') ?></span></div>
          <?php if ($c['detail'] !== ''): ?><p class="check-detail"><?= e($c['detail']) ?></p><?php endif; ?>
          <?php if ($c['solution'] !== '' && $c['status'] !== 'pass'): ?>
            <div class="solution"><strong>Solució</strong><p><?= nl2br(e($c['solution'])) ?></p></div>
          <?php endif; ?>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>

  <?php if ($type === 'geo' && !empty($r['llms_draft'])): ?>
  <h4 class="section-title">Esborrany de llms.txt <span class="muted small">(<?= ($r['metrics']['llms.txt'] ?? '') === 'Sí' ? 'el lloc ja en té un; fes-lo servir com a referència' : 'adapta’l i puja’l a l’arrel del domini' ?>)</span></h4>
  <div class="code-block">
    <button type="button" class="btn btn-ghost btn-sm" data-copy="#llms-<?= $i ?>"><?= icon('copy') ?> Copiar</button>
    <pre id="llms-<?= $i ?>"><?= e($r['llms_draft']) ?></pre>
  </div>
  <?php endif; ?>
</details>
<?php endforeach;
}
