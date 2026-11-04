<?php
declare(strict_types=1);

/**
 * Pulsar · API per a altres aplicacions (p. ex. Asteroid): analitza una pàgina i en retorna un resum.
 *
 *   POST api.php
 *   Authorization: Bearer EL_TOKEN          (config 'api_tokens' a config.local.php)
 *   Content-Type: application/json
 *   {"site": "www.exemple.cat/", "type": "seo"}     type: seo | geo
 *
 * "site" pot anar sense https:// (alguns tallafocs, com el de Webempresa, bloquegen peticions amb URL als paràmetres).
 * Resposta: puntuació global i per categoria, els problemes (fail/warn) ordenats per importància amb la solució, i
 * l’identificador de l’informe desat (es pot obrir a Pulsar: seo.php?id=… i descarregar en PDF: report.php).
 * Una anàlisi pot trigar uns segons (la GEO prova diversos rastrejadors).
 */
const PULSAR_API = true;
require __DIR__ . '/inc/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function api_out(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    api_out(['ok' => false, 'error' => 'method'], 405);
}

// Token: capçalera Authorization: Bearer … (o X-Pulsar-Token)
$header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
$token = preg_match('/^Bearer\s+(\S+)$/i', $header, $m) ? $m[1] : (string) ($_SERVER['HTTP_X_PULSAR_TOKEN'] ?? '');
$client = null;
foreach ((array) config('api_tokens', []) as $name => $valid) {
    if (is_string($valid) && strlen($valid) >= 24 && $token !== '' && hash_equals($valid, $token)) {
        $client = (string) $name;
        break;
    }
}
if ($client === null) {
    api_out(['ok' => false, 'error' => 'token'], 401);
}

$in = json_decode((string) file_get_contents('php://input'), true);
$in = is_array($in) ? $in : $_POST;
$type = (string) ($in['type'] ?? 'seo');
if (!in_array($type, ['seo', 'geo'], true)) {
    api_out(['ok' => false, 'error' => 'type'], 422);
}
$url = parse_url_list((string) ($in['site'] ?? ''), 1)[0] ?? null;
if ($url === null) {
    api_out(['ok' => false, 'error' => 'site'], 422);
}

@set_time_limit(180);
ignore_user_abort(true);

$analyzer = $type === 'seo' ? new SeoAnalyzer() : new GeoAnalyzer();
$result = $analyzer->analyze($url);
$id = save_report($type, ['results' => [$result], 'average' => (int) $result['score'], 'user' => 'api:' . $client]);

// Puntuació per categoria (com al PDF) i problemes per ordre d’importància
$labels = $type === 'seo' ? SeoAnalyzer::CATEGORIES : GeoAnalyzer::CATEGORIES;
$byCat = [];
foreach ($result['checks'] as $c) {
    $byCat[$c['cat']][] = $c;
}
$categories = [];
foreach ($labels as $key => $label) {
    if (!empty($byCat[$key]) && array_filter($byCat[$key], static fn ($c) => $c['status'] !== 'info')) {
        $categories[$key] = ['label' => $label, 'score' => compute_score($byCat[$key])];
    }
}
$issues = array_values(array_filter($result['checks'], static fn ($c) => in_array($c['status'], ['fail', 'warn'], true)));
usort($issues, static fn ($a, $b) => [$b['status'] === 'fail', $b['weight']] <=> [$a['status'] === 'fail', $a['weight']]);

api_out([
    'ok' => true,
    'type' => $type,
    'url' => $result['url'],
    'final_url' => $result['final_url'],
    'title' => $result['title'] ?? null,
    'error' => $result['error'],
    'score' => (int) $result['score'],
    'categories' => $categories,
    'issues' => array_map(static fn ($c) => [
        'id' => $c['id'], 'cat' => $c['cat'], 'status' => $c['status'], 'title' => $c['title'],
        'detail' => $c['detail'], 'solution' => $c['solution'], 'weight' => $c['weight'],
    ], $issues),
    'passed' => count(array_filter($result['checks'], static fn ($c) => $c['status'] === 'pass')),
    'total' => count(array_filter($result['checks'], static fn ($c) => $c['status'] !== 'info')),
    'report_id' => $id,
    'created_at' => date('c'),
]);
