<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require_login();

$type = (string) ($_GET['type'] ?? '');
$id = (string) ($_GET['id'] ?? '');
$report = load_report($type, $id);
if ($report === null) {
    http_response_code(404);
    exit('Informe no trobat o caducat.');
}

$pdf = (new PdfReport($type, $report))->build();
$host = (string) parse_url($report['results'][0]['final_url'] ?: $report['results'][0]['url'], PHP_URL_HOST);
$filename = sprintf('pulsar-%s-%s-%s.pdf', $type, preg_replace('/[^a-z0-9.-]/i', '', $host) ?: 'informe', date('Ymd', strtotime($report['created_at'])));

header('Content-Type: application/pdf');
header('Content-Disposition: ' . (isset($_GET['inline']) ? 'inline' : 'attachment') . '; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdf));
header('Cache-Control: private, no-store');
echo $pdf;
