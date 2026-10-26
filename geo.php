<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';
require __DIR__ . '/inc/results.php';
require_login();

$max = (int) config('max_urls_geo', 10);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $analyzer = new GeoAnalyzer();
    handle_analysis('geo', (string) ($_POST['urls'] ?? ''), $max, static fn (string $url) => $analyzer->analyze($url));
}

$report = isset($_GET['id']) ? load_report('geo', (string) $_GET['id']) : null;
if (isset($_GET['id']) && !$report) {
    flash('error', 'L’informe no existeix o ha caducat.');
}

layout_start('GEO · Visibilitat en IA', 'geo');
?>
<section class="intro card">
  <h2>Què és el GEO?</h2>
  <p><strong>Generative Engine Optimization</strong> és l’optimització perquè els cercadors amb IA (ChatGPT, Claude, Perplexity, Gemini, Google AI Overviews, Copilot) puguin <strong>accedir</strong> al teu web, <strong>entendre</strong> el teu contingut i <strong>citar-te</strong> com a font a les seves respostes.</p>
  <div class="pillars">
    <div><strong>1 · Accés</strong><span>robots.txt, tallafoc, noindex/nosnippet i renderització sense JavaScript.</span></div>
    <div><strong>2 · Citabilitat</strong><span>Preguntes, respostes directes, llistes, taules i dades concretes.</span></div>
    <div><strong>3 · Autoritat</strong><span>Autoria, actualitat, fonts i entitat de marca (schema).</span></div>
    <div><strong>4 · Senyals tècnics</strong><span>llms.txt, dades estructurades i HTML semàntic.</span></div>
  </div>
  <p class="muted small">Anàlisi 100 % local i heurística: no s’envia res a cap IA ni es consumeixen tokens.</p>
</section>
<?php
render_url_form('geo', $report ? implode("\n", array_column($report['results'], 'url')) : '', $max,
    'Es comprova l’accés de 14 rastrejadors d’IA, els bloquejos del tallafoc, el llms.txt i l’estructura del contingut per ser citat.');
if ($report) {
    render_report('geo', $report, GeoAnalyzer::CATEGORIES);
}
layout_end();
