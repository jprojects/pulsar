<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';
require __DIR__ . '/inc/results.php';
require_login();

$max = (int) config('max_urls_seo', 20);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $analyzer = new SeoAnalyzer();
    handle_analysis('seo', (string) ($_POST['urls'] ?? ''), $max, static fn (string $url) => $analyzer->analyze($url));
}

$report = isset($_GET['id']) ? load_report('seo', (string) $_GET['id']) : null;
if (isset($_GET['id']) && !$report) {
    flash('error', 'L’informe no existeix o ha caducat.');
}

layout_start('Anàlisi SEO', 'seo');
render_url_form('seo', $report ? implode("\n", array_column($report['results'], 'url')) : '', $max,
    'Es revisen més de 35 factors: indexació, metaetiquetes, encapçalaments, contingut, imatges, enllaços, rendiment, seguretat, dades estructurades i mòbil.');
if ($report) {
    render_report('seo', $report, SeoAnalyzer::CATEGORIES);
}
layout_end();
