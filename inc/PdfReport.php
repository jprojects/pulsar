<?php
declare(strict_types=1);

/**
 * Informe PDF professional per a auditories SEO i GEO.
 */
final class PdfReport extends FPDF
{
    private const DARK   = [17, 12, 38];
    private const VIOLET = [109, 40, 217];
    private const CYAN   = [34, 211, 238];
    private const TEXT   = [31, 30, 46];
    private const MUTED  = [107, 106, 128];
    private const LIGHT  = [246, 245, 251];
    private const LINE   = [226, 224, 238];
    private const STATUS = [
        'pass' => [22, 163, 74],
        'warn' => [217, 119, 6],
        'fail' => [220, 38, 38],
        'info' => [37, 99, 235],
    ];
    private const LABELS = ['pass' => 'CORRECTE', 'warn' => 'MILLORABLE', 'fail' => 'CRÍTIC', 'info' => 'INFO'];

    private string $reportTitle;
    private array $categories;

    public function __construct(private readonly string $type, private readonly array $report)
    {
        parent::__construct('P', 'mm', 'A4');
        $this->reportTitle = $type === 'geo' ? 'Auditoria GEO · Visibilitat en IA' : 'Auditoria SEO';
        $this->categories = $type === 'geo' ? GeoAnalyzer::CATEGORIES : SeoAnalyzer::CATEGORIES;
        $this->SetMargins(18, 22, 18);
        $this->SetAutoPageBreak(true, 20);
        $this->AliasNbPages();
        $this->SetTitle($this->t($this->reportTitle . ' - ' . config('app_name')), false);
        $this->SetAuthor($this->t((string) config('company')), false);
        $this->SetCreator('Pulsar ' . PULSAR_VERSION);
    }

    public function build(): string
    {
        $this->cover();
        $this->summary();
        foreach ($this->report['results'] as $i => $r) {
            $this->urlSection($r, $i + 1, count($this->report['results']));
        }
        $this->closingPage();
        return $this->Output('S');
    }

    // -----------------------------------------------------------------------
    // Capçalera i peu
    // -----------------------------------------------------------------------

    public function Header(): void
    {
        if ($this->PageNo() === 1) {
            return;
        }
        $this->SetFillColor(...self::VIOLET);
        $this->Rect(0, 0, 210, 1.6, 'F');
        $this->logo(18, 9.5, 2.6);
        $this->SetXY(23, 7.3);
        $this->SetFont('Helvetica', 'B', 9);
        $this->SetTextColor(...self::DARK);
        $this->Cell(40, 5, $this->t(mb_strtoupper((string) config('app_name'))));
        $this->SetXY(100, 7.3);
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(...self::MUTED);
        $this->Cell(92, 5, $this->t($this->reportTitle), 0, 0, 'R');
        $this->SetDrawColor(...self::LINE);
        $this->Line(18, 14, 192, 14);
        $this->SetY(22);
    }

    public function Footer(): void
    {
        if ($this->PageNo() === 1) {
            return;
        }
        $this->SetY(-13);
        $this->SetDrawColor(...self::LINE);
        $this->Line(18, $this->GetY() - 2, 192, $this->GetY() - 2);
        $this->SetFont('Helvetica', '', 7.5);
        $this->SetTextColor(...self::MUTED);
        $this->Cell(120, 5, $this->t(config('company') . ' · ' . date('d/m/Y', strtotime($this->report['created_at']))));
        $this->Cell(54, 5, $this->t('Pàgina ' . $this->PageNo() . ' de {nb}'), 0, 0, 'R');
    }

    // -----------------------------------------------------------------------
    // Seccions
    // -----------------------------------------------------------------------

    private function cover(): void
    {
        $this->AddPage();
        $this->SetAutoPageBreak(false);
        $this->SetFillColor(...self::DARK);
        $this->Rect(0, 0, 210, 297, 'F');

        // Ones decoratives
        $this->SetLineWidth(0.4);
        foreach ([90, 72, 54, 36] as $i => $r) {
            $c = [self::VIOLET[0] + $i * 12, self::VIOLET[1] + $i * 30, self::VIOLET[2] + $i * 5];
            $this->SetDrawColor(...array_map(static fn ($v) => min(255, $v), $c));
            $this->Ellipse(178, 62, $r, $r, 'D');
        }
        $this->SetFillColor(...self::CYAN);
        $this->Ellipse(178, 62, 9, 9, 'F');
        $this->SetLineWidth(0.2);

        $this->logo(24, 34, 4.5);
        $this->SetXY(32, 30.5);
        $this->SetFont('Helvetica', 'B', 15);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(60, 7, $this->t(mb_strtoupper((string) config('app_name'))));

        $this->SetXY(22, 118);
        $this->SetFont('Helvetica', '', 11);
        $this->SetTextColor(...self::CYAN);
        $this->Cell(0, 6, $this->t($this->type === 'geo' ? 'INFORME D’OPTIMITZACIÓ PER A MOTORS GENERATIUS' : 'INFORME DE POSICIONAMENT WEB'));
        $this->SetXY(22, 127);
        $this->SetFont('Helvetica', 'B', 30);
        $this->SetTextColor(255, 255, 255);
        $this->MultiCell(166, 12, $this->t($this->reportTitle), 0, 'L');

        $hosts = array_values(array_unique(array_map(static fn ($r) => (string) parse_url($r['final_url'] ?: $r['url'], PHP_URL_HOST), $this->report['results'])));
        $this->SetX(22);
        $this->SetFont('Helvetica', '', 13);
        $this->SetTextColor(200, 196, 230);
        $label = count($hosts) === 1 ? $hosts[0] : count($hosts) . ' dominis · ' . implode(', ', array_slice($hosts, 0, 3)) . (count($hosts) > 3 ? '…' : '');
        $this->MultiCell(166, 7, $this->t($label), 0, 'L');

        // Puntuació global
        $avg = (int) $this->report['average'];
        $this->SetFillColor(...$this->scoreColor($avg));
        $this->Ellipse(40, 210, 18, 18, 'F');
        $this->SetFillColor(...self::DARK);
        $this->Ellipse(40, 210, 15.5, 15.5, 'F');
        $this->SetXY(22, 204);
        $this->SetFont('Helvetica', 'B', 22);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(36, 9, (string) $avg, 0, 0, 'C');
        $this->SetXY(22, 213);
        $this->SetFont('Helvetica', '', 7);
        $this->SetTextColor(200, 196, 230);
        $this->Cell(36, 4, '/ 100', 0, 0, 'C');

        [$f, $w, $p] = $this->totals();
        $this->SetXY(66, 197);
        $this->SetFont('Helvetica', 'B', 12);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(120, 7, $this->t('Puntuació mitjana · ' . $this->scoreLabel($avg)));
        $this->SetXY(66, 205);
        $this->SetFont('Helvetica', '', 10);
        $this->SetTextColor(200, 196, 230);
        $this->MultiCell(120, 5.5, $this->t(sprintf('%d URL %s · %d problemes crítics · %d millores recomanades · %d comprovacions superades',
            count($this->report['results']), count($this->report['results']) === 1 ? 'analitzada' : 'analitzades', $f, $w, $p)));

        $this->SetDrawColor(60, 52, 100);
        $this->Line(22, 262, 188, 262);
        $this->SetXY(22, 266);
        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(200, 196, 230);
        $this->Cell(83, 5, $this->t('Preparat per ' . config('company')));
        $this->Cell(83, 5, $this->t('Data: ' . $this->catalanDate(strtotime($this->report['created_at']))), 0, 0, 'R');
        if (config('company_url')) {
            $this->SetXY(22, 272);
            $this->SetTextColor(...self::CYAN);
            $this->Cell(83, 5, $this->t(preg_replace('#^https?://#', '', (string) config('company_url'))));
        }
        $this->SetAutoPageBreak(true, 20);
    }

    private function summary(): void
    {
        $this->AddPage();
        $this->h1('Resum executiu');
        $avg = (int) $this->report['average'];
        $intro = $this->type === 'geo'
            ? 'Aquest informe avalua fins a quin punt les pàgines analitzades estan preparades per ser rastrejades, enteses i citades pels cercadors basats en intel·ligència artificial (ChatGPT, Claude, Perplexity, Gemini, Google AI Overviews i Copilot). Es revisen l’accés dels rastrejadors d’IA, l’estructura i la citabilitat del contingut, els senyals d’autoritat i les dades estructurades.'
            : 'Aquest informe recull una auditoria tècnica i de contingut on-page de les pàgines analitzades. Es revisen el rastreig i la indexació, les metaetiquetes, l’estructura del contingut, les imatges, l’enllaçat, el rendiment, la seguretat i les dades estructurades. Cada problema inclou una explicació i la solució recomanada.';
        $this->para($intro);
        $this->Ln(2);
        $this->para(sprintf('La puntuació mitjana obtinguda és de %d/100 (%s). %s', $avg, mb_strtolower($this->scoreLabel($avg)), $this->scoreAdvice($avg)));
        $this->Ln(4);

        // Taula d’URL
        $this->h2('Puntuació per URL');
        $cols = [8, 104, 22, 14, 14, 12];
        $heads = ['#', 'URL', 'Puntuació', 'Crít.', 'Mill.', 'OK'];
        $this->SetFont('Helvetica', 'B', 8);
        $this->SetFillColor(...self::DARK);
        $this->SetTextColor(255, 255, 255);
        foreach ($heads as $i => $h) {
            $this->Cell($cols[$i], 7, $this->t($h), 0, 0, $i === 1 ? 'L' : 'C', true);
        }
        $this->Ln();
        $this->SetFont('Helvetica', '', 8);
        foreach ($this->report['results'] as $i => $r) {
            $counts = $this->counts($r['checks']);
            $this->SetFillColor(...($i % 2 ? [255, 255, 255] : self::LIGHT));
            $this->SetTextColor(...self::TEXT);
            $this->Cell($cols[0], 7, (string) ($i + 1), 0, 0, 'C', true);
            $this->Cell($cols[1], 7, $this->t($this->shorten($r['url'], 68)), 0, 0, 'L', true);
            $this->SetTextColor(...$this->scoreColor((int) $r['score']));
            $this->SetFont('Helvetica', 'B', 8);
            $this->Cell($cols[2], 7, $r['error'] && !$r['checks'] ? 'Error' : $r['score'] . '/100', 0, 0, 'C', true);
            $this->SetFont('Helvetica', '', 8);
            $this->SetTextColor(...self::STATUS['fail']);
            $this->Cell($cols[3], 7, (string) $counts['fail'], 0, 0, 'C', true);
            $this->SetTextColor(...self::STATUS['warn']);
            $this->Cell($cols[4], 7, (string) $counts['warn'], 0, 0, 'C', true);
            $this->SetTextColor(...self::STATUS['pass']);
            $this->Cell($cols[5], 7, (string) $counts['pass'], 0, 1, 'C', true);
        }
        $this->Ln(6);

        // Problemes més freqüents
        $agg = [];
        foreach ($this->report['results'] as $r) {
            foreach ($r['checks'] as $c) {
                if (in_array($c['status'], ['fail', 'warn'], true)) {
                    $key = preg_replace('/\s*\(.*?\)|\d+/', '', $c['title']);
                    $agg[$key] ??= ['title' => $c['title'], 'status' => $c['status'], 'count' => 0, 'weight' => $c['weight']];
                    $agg[$key]['count']++;
                    if ($c['status'] === 'fail') {
                        $agg[$key]['status'] = 'fail';
                    }
                }
            }
        }
        usort($agg, static fn ($a, $b) => [$b['status'] === 'fail', $b['weight'] * $b['count']] <=> [$a['status'] === 'fail', $a['weight'] * $a['count']]);
        if ($agg) {
            $this->h2('Prioritats d’actuació');
            $total = count($this->report['results']);
            foreach (array_slice($agg, 0, 10) as $n => $a) {
                $this->ensureSpace(8);
                $y = $this->GetY();
                $this->badge($a['status'], 18, $y + 0.8);
                $this->SetXY(42, $y);
                $this->SetFont('Helvetica', '', 9);
                $this->SetTextColor(...self::TEXT);
                $this->Cell(120, 6.5, $this->t(($n + 1) . '. ' . $a['title']));
                if ($total > 1) {
                    $this->SetTextColor(...self::MUTED);
                    $this->SetFont('Helvetica', '', 8);
                    $this->Cell(30, 6.5, $this->t($a['count'] . ' de ' . $total . ' URL'), 0, 0, 'R');
                }
                $this->Ln(7);
            }
            $this->Ln(4);
        }

        // Llegenda
        $this->ensureSpace(30);
        $this->h2('Com llegir aquest informe');
        $legend = [
            'fail' => 'Problema crític que afecta directament la visibilitat. Cal resoldre’l amb prioritat.',
            'warn' => 'Aspecte millorable. Corregir-lo aporta un avantatge competitiu.',
            'pass' => 'Comprovació superada. Cal mantenir-la.',
            'info' => 'Dada informativa o decisió estratègica; no puntua.',
        ];
        foreach ($legend as $s => $txt) {
            $y = $this->GetY();
            $this->badge($s, 18, $y + 0.8);
            $this->SetXY(42, $y);
            $this->SetFont('Helvetica', '', 8.5);
            $this->SetTextColor(...self::TEXT);
            $this->Cell(150, 6.5, $this->t($txt));
            $this->Ln(7);
        }
    }

    private function urlSection(array $r, int $n, int $total): void
    {
        $this->AddPage();

        // Capçalera de la URL
        $y = $this->GetY();
        $this->SetFillColor(...self::LIGHT);
        $this->RoundedRect(18, $y, 174, 34, 3, 'F');
        $this->SetXY(24, $y + 5);
        $this->SetFont('Helvetica', 'B', 7.5);
        $this->SetTextColor(...self::VIOLET);
        $this->Cell(120, 4, $this->t("URL $n DE $total"));
        $this->SetXY(24, $y + 10);
        $this->SetFont('Helvetica', 'B', 11);
        $this->SetTextColor(...self::DARK);
        $this->MultiCell(128, 5.5, $this->t($this->shorten($r['url'], 140)), 0, 'L');
        if (!empty($r['title'])) {
            $this->SetX(24);
            $this->SetFont('Helvetica', '', 8.5);
            $this->SetTextColor(...self::MUTED);
            $this->MultiCell(128, 4.5, $this->t($this->shorten($r['title'], 110)), 0, 'L');
        }
        $score = (int) $r['score'];
        $this->SetFillColor(...$this->scoreColor($score));
        $this->Ellipse(173, $y + 17, 12, 12, 'F');
        $this->SetFillColor(255, 255, 255);
        $this->Ellipse(173, $y + 17, 10, 10, 'F');
        $this->SetXY(161, $y + 12);
        $this->SetFont('Helvetica', 'B', 16);
        $this->SetTextColor(...$this->scoreColor($score));
        $this->Cell(24, 7, (string) $score, 0, 0, 'C');
        $this->SetXY(161, $y + 19);
        $this->SetFont('Helvetica', '', 6.5);
        $this->SetTextColor(...self::MUTED);
        $this->Cell(24, 3, '/ 100', 0, 0, 'C');
        $this->SetY($y + 40);

        // Mètriques
        $metrics = $r['metrics'] ?? [];
        if ($metrics) {
            $metrics = array_slice($metrics, 0, 5, true);
            $w = (174 - (count($metrics) - 1) * 3) / count($metrics);
            $x = 18;
            $y = $this->GetY();
            foreach ($metrics as $label => $value) {
                $this->SetDrawColor(...self::LINE);
                $this->RoundedRect($x, $y, $w, 16, 2, 'D');
                $this->SetXY($x, $y + 3);
                $this->SetFont('Helvetica', '', 7);
                $this->SetTextColor(...self::MUTED);
                $this->Cell($w, 3.5, $this->t(mb_strtoupper((string) $label)), 0, 0, 'C');
                $this->SetXY($x, $y + 8);
                $this->SetFont('Helvetica', 'B', 11);
                $this->SetTextColor(...self::DARK);
                $this->Cell($w, 5, $this->t((string) $value), 0, 0, 'C');
                $x += $w + 3;
            }
            $this->SetY($y + 22);
        }

        // Puntuació per categoria
        $catScores = $this->categoryScores($r['checks']);
        if ($catScores) {
            $this->h2('Puntuació per àrea');
            foreach ($catScores as $cat => $s) {
                $this->ensureSpace(7);
                $y = $this->GetY();
                $this->SetFont('Helvetica', '', 8.5);
                $this->SetTextColor(...self::TEXT);
                $this->SetXY(18, $y);
                $this->Cell(58, 5, $this->t($this->categories[$cat] ?? $cat));
                $this->SetFillColor(...self::LINE);
                $this->RoundedRect(78, $y + 1.3, 98, 2.6, 1.3, 'F');
                if ($s > 0) {
                    $this->SetFillColor(...$this->scoreColor($s));
                    $this->RoundedRect(78, $y + 1.3, max(2.6, 98 * $s / 100), 2.6, 1.3, 'F');
                }
                $this->SetXY(178, $y);
                $this->SetFont('Helvetica', 'B', 8.5);
                $this->Cell(14, 5, (string) $s, 0, 0, 'R');
                $this->Ln(6.5);
            }
            $this->Ln(3);
        }

        if ($this->type === 'geo' && !empty($r['bots'])) {
            $this->botsTable($r['bots']);
        }

        // Problemes
        $issues = array_values(array_filter($r['checks'], static fn ($c) => in_array($c['status'], ['fail', 'warn'], true)));
        usort($issues, static fn ($a, $b) => [$a['status'] === 'warn', -$a['weight']] <=> [$b['status'] === 'warn', -$b['weight']]);
        if ($issues) {
            $this->ensureSpace(30);
            $this->h2('Problemes detectats i solucions');
            foreach ($issues as $c) {
                $this->issueBlock($c);
            }
        }

        $info = array_filter($r['checks'], static fn ($c) => $c['status'] === 'info');
        if ($info) {
            $this->ensureSpace(20);
            $this->h2('Observacions');
            foreach ($info as $c) {
                $this->issueBlock($c);
            }
        }

        $passed = array_filter($r['checks'], static fn ($c) => $c['status'] === 'pass');
        if ($passed) {
            $this->ensureSpace(20);
            $this->h2('Aspectes correctes');
            foreach ($passed as $c) {
                $this->ensureSpace(6);
                $y = $this->GetY();
                $this->SetFillColor(...self::STATUS['pass']);
                $this->Ellipse(20.5, $y + 2.6, 1.2, 1.2, 'F');
                $this->SetXY(24, $y);
                $this->SetFont('Helvetica', 'B', 8.5);
                $this->SetTextColor(...self::TEXT);
                $title = $this->t($c['title']);
                $this->Cell($this->GetStringWidth($title) + 2, 5.2, $title);
                $this->SetFont('Helvetica', '', 8);
                $this->SetTextColor(...self::MUTED);
                $rest = 168 - $this->GetStringWidth($title) - 2;
                if ($rest > 30) {
                    $this->Cell($rest, 5.2, $this->t($this->fit($c['detail'], $rest)));
                }
                $this->Ln(5.6);
            }
        }

        if ($this->type === 'geo' && !empty($r['llms_draft']) && ($r['metrics']['llms.txt'] ?? '') === 'No') {
            $this->ensureSpace(40);
            $this->h2('Esborrany de llms.txt per a aquest lloc');
            $this->para('Adapta aquest esborrany, desa’l com a llms.txt i puja’l a l’arrel del domini.');
            $this->Ln(1);
            $this->SetFillColor(...self::LIGHT);
            $this->SetFont('Courier', '', 7.5);
            $this->SetTextColor(...self::TEXT);
            $this->MultiCell(174, 3.8, $this->t($r['llms_draft']), 0, 'L', true);
        }
    }

    private function botsTable(array $bots): void
    {
        $this->ensureSpace(30);
        $this->h2('Accés dels rastrejadors d’IA (robots.txt)');
        $cols = [38, 28, 82, 26];
        $this->SetFont('Helvetica', 'B', 7.5);
        $this->SetFillColor(...self::DARK);
        $this->SetTextColor(255, 255, 255);
        foreach (['Rastrejador', 'Empresa', 'Funció', 'Estat'] as $i => $h) {
            $this->Cell($cols[$i], 6, $this->t($h), 0, 0, $i === 3 ? 'C' : 'L', true);
        }
        $this->Ln();
        $this->SetFont('Helvetica', '', 7.5);
        foreach ($bots as $i => $b) {
            $this->ensureSpace(6);
            $this->SetFillColor(...($i % 2 ? [255, 255, 255] : self::LIGHT));
            $this->SetTextColor(...self::TEXT);
            $this->Cell($cols[0], 5.5, $this->t($b['ua']), 0, 0, 'L', true);
            $this->Cell($cols[1], 5.5, $this->t($b['company']), 0, 0, 'L', true);
            $this->Cell($cols[2], 5.5, $this->t($b['role']), 0, 0, 'L', true);
            $this->SetTextColor(...($b['allowed'] ? self::STATUS['pass'] : ($b['type'] === 'search' ? self::STATUS['fail'] : self::STATUS['warn'])));
            $this->SetFont('Helvetica', 'B', 7.5);
            $this->Cell($cols[3], 5.5, $this->t($b['allowed'] ? 'Permès' : 'Bloquejat'), 0, 1, 'C', true);
            $this->SetFont('Helvetica', '', 7.5);
        }
        $this->Ln(5);
    }

    private function issueBlock(array $c): void
    {
        $w = 162;
        $this->SetFont('Helvetica', 'B', 9.5);
        $hTitle = $this->nbLines($w - 26, $this->t($c['title'])) * 5;
        $this->SetFont('Helvetica', '', 8.5);
        $hDetail = $c['detail'] !== '' ? $this->nbLines($w, $this->t($c['detail'])) * 4.3 : 0;
        $hSol = $c['solution'] !== '' ? $this->nbLines($w - 8, $this->t($c['solution'])) * 4.3 + 9 : 0;
        $total = $hTitle + $hDetail + $hSol + 9;
        $this->ensureSpace($total);

        $y0 = $this->GetY();
        $color = self::STATUS[$c['status']];
        $this->SetFillColor(...$color);
        $this->Rect(18, $y0, 1.2, $total - 4, 'F');

        $this->badge($c['status'], 24, $y0 + 0.6);
        $this->SetXY(48, $y0);
        $this->SetFont('Helvetica', 'B', 9.5);
        $this->SetTextColor(...self::DARK);
        $this->MultiCell($w - 26, 5, $this->t($c['title']), 0, 'L');
        if ($c['detail'] !== '') {
            $this->SetXY(24, $this->GetY() + 0.8);
            $this->SetFont('Helvetica', '', 8.5);
            $this->SetTextColor(...self::TEXT);
            $this->MultiCell($w, 4.3, $this->t($c['detail']), 0, 'L');
        }
        if ($c['solution'] !== '') {
            $this->Ln(1.5);
            $y = $this->GetY();
            $this->SetFillColor(...self::LIGHT);
            $this->RoundedRect(24, $y, $w + 6, $hSol - 1.5, 1.5, 'F');
            $this->SetXY(28, $y + 2);
            $this->SetFont('Helvetica', 'B', 7.5);
            $this->SetTextColor(...self::VIOLET);
            $this->Cell(40, 3.5, $this->t($c['status'] === 'info' ? 'RECOMANACIÓ' : 'SOLUCIÓ'));
            $this->SetXY(28, $y + 6);
            $this->SetFont('Helvetica', '', 8.5);
            $this->SetTextColor(...self::TEXT);
            $this->MultiCell($w - 8, 4.3, $this->t($c['solution']), 0, 'L');
        }
        $this->SetY($y0 + $total);
    }

    private function closingPage(): void
    {
        $this->ensureSpace(80);
        $this->Ln(6);
        $this->h2('Propers passos recomanats');
        $steps = $this->type === 'geo' ? [
            'Desbloquejar l’accés dels rastrejadors de cerca d’IA al robots.txt i al tallafoc/CDN.',
            'Publicar un fitxer llms.txt amb la descripció del lloc i les seves pàgines clau.',
            'Reestructurar els continguts principals amb preguntes com a encapçalaments i respostes directes de 40-60 paraules.',
            'Afegir dades, xifres i fonts verificables, i signar els continguts amb autors reals.',
            'Implementar schema Organization amb sameAs, i Article/FAQPage/Product segons el tipus de pàgina.',
            'Mesurar la presència de la marca a ChatGPT, Perplexity i AI Overviews amb consultes periòdiques.',
        ] : [
            'Resoldre els problemes crítics (en vermell), començant pels que impedeixen el rastreig o la indexació.',
            'Optimitzar títols, meta descriptions i encapçalaments de les pàgines amb més potencial de trànsit.',
            'Millorar el rendiment del servidor i la càrrega de recursos per optimitzar les Core Web Vitals.',
            'Completar les dades estructurades i les etiquetes socials.',
            'Verificar els canvis a Google Search Console i sol·licitar la reindexació.',
            'Repetir aquesta auditoria després d’aplicar els canvis per mesurar-ne la millora.',
        ];
        foreach ($steps as $i => $s) {
            $this->ensureSpace(10);
            $y = $this->GetY();
            $this->SetFillColor(...self::VIOLET);
            $this->Ellipse(21, $y + 2.6, 2.6, 2.6, 'F');
            $this->SetXY(18.4, $y + 0.6);
            $this->SetFont('Helvetica', 'B', 7.5);
            $this->SetTextColor(255, 255, 255);
            $this->Cell(5.2, 4, (string) ($i + 1), 0, 0, 'C');
            $this->SetXY(27, $y);
            $this->SetFont('Helvetica', '', 9);
            $this->SetTextColor(...self::TEXT);
            $this->MultiCell(165, 5.2, $this->t($s), 0, 'L');
            $this->Ln(1.5);
        }
        $this->Ln(6);
        $this->SetFont('Helvetica', 'I', 7.5);
        $this->SetTextColor(...self::MUTED);
        $this->MultiCell(174, 4, $this->t('Informe generat automàticament per ' . config('app_name') . ' el ' . date('d/m/Y \a \l\e\s H:i', strtotime($this->report['created_at']))
            . '. Els resultats reflecteixen l’estat de les pàgines en el moment de l’anàlisi i s’han d’interpretar juntament amb altres eines (Google Search Console, PageSpeed Insights, analítica).'), 0, 'L');
    }

    // -----------------------------------------------------------------------
    // Components
    // -----------------------------------------------------------------------

    private function h1(string $text): void
    {
        $this->SetFont('Helvetica', 'B', 20);
        $this->SetTextColor(...self::DARK);
        $this->Cell(0, 10, $this->t($text), 0, 1);
        $this->SetFillColor(...self::VIOLET);
        $this->Rect(18, $this->GetY() + 0.5, 14, 1.2, 'F');
        $this->Ln(6);
    }

    private function h2(string $text): void
    {
        $this->ensureSpace(14);
        $this->SetFont('Helvetica', 'B', 12);
        $this->SetTextColor(...self::DARK);
        $this->Cell(0, 7, $this->t($text), 0, 1);
        $this->Ln(1.5);
    }

    private function para(string $text): void
    {
        $this->SetFont('Helvetica', '', 9.5);
        $this->SetTextColor(...self::TEXT);
        $this->MultiCell(0, 5.2, $this->t($text), 0, 'J');
    }

    private function badge(string $status, float $x, float $y): void
    {
        $this->SetFillColor(...self::STATUS[$status]);
        $this->RoundedRect($x, $y, 21, 4.6, 1.2, 'F');
        $this->SetXY($x, $y + 0.3);
        $this->SetFont('Helvetica', 'B', 6.5);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(21, 4, $this->t(self::LABELS[$status]), 0, 0, 'C');
    }

    private function logo(float $x, float $y, float $r): void
    {
        $this->SetDrawColor(...self::VIOLET);
        $this->SetLineWidth(0.35);
        $this->Ellipse($x, $y, $r, $r, 'D');
        $this->SetFillColor(...self::CYAN);
        $this->Ellipse($x, $y, $r * 0.38, $r * 0.38, 'F');
        $this->SetLineWidth(0.2);
    }

    // -----------------------------------------------------------------------
    // Utilitats
    // -----------------------------------------------------------------------

    private function t(string $s): string
    {
        $out = @iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $s);
        return $out === false ? mb_convert_encoding($s, 'ISO-8859-1', 'UTF-8') : $out;
    }

    private function ensureSpace(float $h): void
    {
        if ($this->GetY() + $h > $this->h - 20) {
            $this->AddPage();
        }
    }

    private function counts(array $checks): array
    {
        $c = ['pass' => 0, 'warn' => 0, 'fail' => 0, 'info' => 0];
        foreach ($checks as $ch) {
            $c[$ch['status']]++;
        }
        return $c;
    }

    private function totals(): array
    {
        $f = $w = $p = 0;
        foreach ($this->report['results'] as $r) {
            $c = $this->counts($r['checks']);
            $f += $c['fail'];
            $w += $c['warn'];
            $p += $c['pass'];
        }
        return [$f, $w, $p];
    }

    private function categoryScores(array $checks): array
    {
        $by = [];
        foreach ($checks as $c) {
            $by[$c['cat']][] = $c;
        }
        $out = [];
        foreach ($this->categories as $key => $label) {
            if (!empty($by[$key]) && array_filter($by[$key], static fn ($c) => $c['status'] !== 'info')) {
                $out[$key] = compute_score($by[$key]);
            }
        }
        return $out;
    }

    private function scoreColor(int $s): array
    {
        return $s >= 80 ? self::STATUS['pass'] : ($s >= 50 ? self::STATUS['warn'] : self::STATUS['fail']);
    }

    private function scoreLabel(int $s): string
    {
        return match (true) {
            $s >= 90 => 'Excel·lent',
            $s >= 80 => 'Bo',
            $s >= 65 => 'Acceptable',
            $s >= 50 => 'Millorable',
            default  => 'Deficient',
        };
    }

    private function scoreAdvice(int $s): string
    {
        return match (true) {
            $s >= 80 => 'La base és sòlida: la feina se centra a polir detalls i consolidar avantatges davant la competència.',
            $s >= 50 => 'Hi ha problemes rellevants la correcció dels quals es pot traduir en una millora apreciable de la visibilitat.',
            default  => 'Hi ha problemes importants que limiten seriosament la visibilitat. Es recomana abordar primer els punts crítics.',
        };
    }

    private function catalanDate(int $ts): string
    {
        $months = ['gener', 'febrer', 'març', 'abril', 'maig', 'juny', 'juliol', 'agost', 'setembre', 'octubre', 'novembre', 'desembre'];
        $m = $months[(int) date('n', $ts) - 1];
        return date('j', $ts) . (preg_match('/^[aeiou]/', $m) ? ' d’' : ' de ') . $m . ' de ' . date('Y', $ts);
    }

    private function shorten(string $s, int $max): string
    {
        return mb_strlen($s) > $max ? mb_substr($s, 0, $max - 1) . '…' : $s;
    }

    /** Retalla un text perquè càpiga en $w mm amb la font actual. */
    private function fit(string $s, float $w): string
    {
        if ($this->GetStringWidth($this->t($s)) <= $w) {
            return $s;
        }
        while ($s !== '' && $this->GetStringWidth($this->t($s . '…')) > $w) {
            $s = mb_substr($s, 0, -1);
        }
        return $s . '…';
    }

    /** Nombre de línies que ocuparà un MultiCell d’amplada $w (font actual). */
    private function nbLines(float $w, string $txt): int
    {
        $cw = $this->CurrentFont['cw'];
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', $txt);
        $nb = strlen($s);
        if ($nb > 0 && $s[$nb - 1] === "\n") {
            $nb--;
        }
        $sep = -1;
        $i = $j = $l = 0;
        $nl = 1;
        while ($i < $nb) {
            $c = $s[$i];
            if ($c === "\n") {
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
                continue;
            }
            if ($c === ' ') {
                $sep = $i;
            }
            $l += $cw[$c] ?? 500;
            if ($l > $wmax) {
                if ($sep === -1) {
                    if ($i === $j) {
                        $i++;
                    }
                } else {
                    $i = $sep + 1;
                }
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
            } else {
                $i++;
            }
        }
        return $nl;
    }

    private function Ellipse(float $x, float $y, float $rx, float $ry, string $style = 'D'): void
    {
        $op = $style === 'F' ? 'f' : (in_array($style, ['FD', 'DF'], true) ? 'B' : 'S');
        $lx = 4 / 3 * (M_SQRT2 - 1) * $rx;
        $ly = 4 / 3 * (M_SQRT2 - 1) * $ry;
        $k = $this->k;
        $h = $this->h;
        $this->_out(sprintf('%.2F %.2F m %.2F %.2F %.2F %.2F %.2F %.2F c',
            ($x + $rx) * $k, ($h - $y) * $k, ($x + $rx) * $k, ($h - ($y - $ly)) * $k, ($x + $lx) * $k, ($h - ($y - $ry)) * $k, $x * $k, ($h - ($y - $ry)) * $k));
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c',
            ($x - $lx) * $k, ($h - ($y - $ry)) * $k, ($x - $rx) * $k, ($h - ($y - $ly)) * $k, ($x - $rx) * $k, ($h - $y) * $k));
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c',
            ($x - $rx) * $k, ($h - ($y + $ly)) * $k, ($x - $lx) * $k, ($h - ($y + $ry)) * $k, $x * $k, ($h - ($y + $ry)) * $k));
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c %s',
            ($x + $lx) * $k, ($h - ($y + $ry)) * $k, ($x + $rx) * $k, ($h - ($y + $ly)) * $k, ($x + $rx) * $k, ($h - $y) * $k, $op));
    }

    private function RoundedRect(float $x, float $y, float $w, float $h, float $r, string $style = ''): void
    {
        $k = $this->k;
        $hp = $this->h;
        $op = $style === 'F' ? 'f' : (in_array($style, ['FD', 'DF'], true) ? 'B' : 'S');
        $r = min($r, $w / 2, $h / 2);
        $a = 4 / 3 * (M_SQRT2 - 1);
        $this->_out(sprintf('%.2F %.2F m', ($x + $r) * $k, ($hp - $y) * $k));
        $xc = $x + $w - $r;
        $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - $y) * $k));
        $this->arcTo($xc + $r * $a, $yc - $r, $xc + $r, $yc - $r * $a, $xc + $r, $yc);
        $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', ($x + $w) * $k, ($hp - $yc) * $k));
        $this->arcTo($xc + $r, $yc + $r * $a, $xc + $r * $a, $yc + $r, $xc, $yc + $r);
        $xc = $x + $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - ($y + $h)) * $k));
        $this->arcTo($xc - $r * $a, $yc + $r, $xc - $r, $yc + $r * $a, $xc - $r, $yc);
        $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', $x * $k, ($hp - $yc) * $k));
        $this->arcTo($xc - $r, $yc - $r * $a, $xc - $r * $a, $yc - $r, $xc, $yc - $r);
        $this->_out($op);
    }

    private function arcTo(float $x1, float $y1, float $x2, float $y2, float $x3, float $y3): void
    {
        $h = $this->h;
        $k = $this->k;
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c', $x1 * $k, ($h - $y1) * $k, $x2 * $k, ($h - $y2) * $k, $x3 * $k, ($h - $y3) * $k));
    }
}
