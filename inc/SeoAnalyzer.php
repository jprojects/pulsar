<?php
declare(strict_types=1);

/**
 * Auditoria SEO on-page i tècnica d’una URL.
 * Cada comprovació produeix: status (pass|warn|fail|info), títol, detall i solució.
 */
final class SeoAnalyzer
{
    public const CATEGORIES = [
        'crawl'     => 'Rastreig i indexació',
        'meta'      => 'Metaetiquetes',
        'content'   => 'Contingut i estructura',
        'media'     => 'Imatges',
        'links'     => 'Enllaços',
        'perf'      => 'Rendiment',
        'security'  => 'Seguretat',
        'social'    => 'Xarxes socials',
        'schema'    => 'Dades estructurades',
        'mobile'    => 'Mòbil i accessibilitat',
    ];

    private array $checks = [];

    public function analyze(string $url): array
    {
        $this->checks = [];
        $res = Http::get($url);
        $result = [
            'url'       => $url,
            'final_url' => $res['url'],
            'status'    => $res['status'],
            'error'     => $res['error'],
            'metrics'   => [
                'Codi HTTP'        => $res['status'] ?: '—',
                'Temps de càrrega' => self::num($res['time'], 2) . ' s',
                'TTFB'             => self::num($res['ttfb'], 2) . ' s',
                'Pes HTML'         => self::bytes($res['size']),
            ],
            'checks'    => [],
            'score'     => 0,
        ];

        if (!$res['ok']) {
            $this->add('crawl', 'fail', 'La pàgina no respon', $res['error'] ?? 'No s’ha pogut descarregar la URL.',
                $res['ssl_error']
                    ? 'Renova o corregeix el certificat SSL (amb la cadena intermèdia inclosa). Pots fer servir Let’s Encrypt i comprovar-ho a ssllabs.com.'
                    : 'Verifica que el domini es resol, que el servidor està en línia i que no bloqueja peticions automatitzades.', 10);
            $result['checks'] = $this->checks;
            return $result;
        }

        $this->checkStatus($res);
        if ($res['status'] >= 400) {
            $result['checks'] = $this->checks;
            $result['score'] = compute_score($this->checks);
            return $result;
        }

        $html = new Html($res['body']);
        $final = $res['url'];
        $words = $html->wordCount();
        $result['metrics']['Paraules'] = number_format($words, 0, ',', '.');

        $this->checkHttps($final);
        $this->checkPerformance($res, $html);
        $this->checkIndexability($res, $html, $final);
        $this->checkMeta($html);
        $this->checkContent($html, $words, $res['size']);
        $this->checkImages($html);
        $this->checkLinks($html, $final);
        $this->checkSocial($html);
        $this->checkSchema($html);
        $this->checkMobile($html);
        $this->checkSecurity($res, $html, $final);

        $result['title'] = $html->title();
        $result['checks'] = $this->checks;
        $result['score'] = compute_score($this->checks);
        return $result;
    }

    private function add(string $cat, string $status, string $title, string $detail, string $solution = '', float $weight = 1): void
    {
        $this->checks[] = compact('cat', 'status', 'title', 'detail', 'solution', 'weight');
    }

    // -----------------------------------------------------------------------

    private function checkStatus(array $res): void
    {
        $s = $res['status'];
        $hops = count($res['redirects']);
        if ($s >= 500) {
            $this->add('crawl', 'fail', "Error del servidor ($s)", 'El servidor retorna un error 5xx: Google no podrà indexar la pàgina i, si persisteix, la traurà de l’índex.',
                'Revisa els registres del servidor (PHP, base de dades, memòria) i corregeix l’error. Fes servir un sistema de monitoratge per detectar caigudes.', 10);
        } elseif ($s >= 400) {
            $this->add('crawl', 'fail', "Pàgina no trobada o inaccessible ($s)", 'La URL retorna un codi d’error de client, per tant no és indexable.',
                'Si el contingut existeix en una altra URL, crea una redirecció 301. Si s’ha eliminat, actualitza els enllaços interns i treu-la del sitemap.', 10);
        } else {
            $this->add('crawl', 'pass', "Resposta correcta ($s)", 'La pàgina respon amb un codi HTTP vàlid.', '', 3);
        }

        if ($hops > 1) {
            $chain = implode(' → ', array_map(static fn ($r) => $r['status'], $res['redirects']));
            $this->add('crawl', 'warn', "Cadena de $hops redireccions", "Abans d’arribar a la URL final s’encadenen diverses redireccions ($chain). Cada salt afegeix latència i dilueix senyals.",
                'Fes que la URL original redirigeixi directament (un únic 301) a la URL final i actualitza els enllaços interns perquè apuntin a la URL definitiva.', 2);
        } elseif ($hops === 1) {
            $r = $res['redirects'][0];
            $permanent = in_array($r['status'], [301, 308], true);
            $this->add('crawl', $permanent ? 'info' : 'warn', 'Redirecció ' . ($permanent ? 'permanent' : 'temporal') . " ({$r['status']})",
                'La URL analitzada redirigeix a ' . $res['url'] . '.',
                $permanent ? '' : 'Si el canvi és definitiu, fes servir una redirecció 301 en lloc de 302/307 per transferir l’autoritat.', 1);
        }
    }

    private function checkHttps(string $final): void
    {
        if (str_starts_with($final, 'https://')) {
            $this->add('security', 'pass', 'Lloc servit per HTTPS', 'La connexió està xifrada; l’HTTPS és un factor de posicionament confirmat.', '', 3);
        } else {
            $this->add('security', 'fail', 'El lloc no fa servir HTTPS', 'La pàgina se serveix sense xifrar. Els navegadors la marquen com a «No segura» i Google prioritza l’HTTPS.',
                'Instal·la un certificat SSL (Let’s Encrypt és gratuït), força la redirecció 301 d’http:// a https:// i actualitza canonicals, sitemap i enllaços interns.', 3);
        }
    }

    private function checkPerformance(array $res, Html $html): void
    {
        $t = $res['ttfb'];
        if ($t < 0.6) {
            $this->add('perf', 'pass', 'Temps de resposta del servidor ràpid', 'TTFB de ' . self::num($t, 2) . ' s.', '', 2);
        } elseif ($t < 1.5) {
            $this->add('perf', 'warn', 'Temps de resposta del servidor millorable', 'TTFB de ' . self::num($t, 2) . ' s (recomanat < 0,6 s).',
                'Activa la memòria cau de pàgina (un connector de cau o cau al servidor), fes servir una CDN i revisa les consultes lentes a la base de dades.', 2);
        } else {
            $this->add('perf', 'fail', 'Servidor lent', 'TTFB de ' . self::num($t, 2) . ' s: afecta les Core Web Vitals (LCP) i l’experiència de l’usuari.',
                'Millora l’allotjament o el pla, habilita la cau de pàgina completa, l’OPcache i una CDN. Revisa connectors o processos que bloquegin la resposta.', 2);
        }

        $size = $res['size'];
        if ($size > 1_500_000) {
            $this->add('perf', 'fail', 'Document HTML molt pesant', 'L’HTML pesa ' . self::bytes($size) . '.',
                'Elimina CSS/JS en línia innecessari, SVG o imatges en base64 incrustades i contingut duplicat. Objectiu: menys de 500 KB.', 1);
        } elseif ($size > 500_000) {
            $this->add('perf', 'warn', 'Document HTML pesant', 'L’HTML pesa ' . self::bytes($size) . '.',
                'Redueix el marcatge: externalitza els estils i scripts en línia i evita imatges en base64.', 1);
        } else {
            $this->add('perf', 'pass', 'Pes de l’HTML adequat', 'L’HTML pesa ' . self::bytes($size) . '.', '', 1);
        }

        $enc = strtolower($res['headers']['content-encoding'] ?? '');
        if (preg_match('/gzip|br|zstd|deflate/', $enc)) {
            $this->add('perf', 'pass', 'Compressió activada', "El servidor comprimeix les respostes ($enc).", '', 1);
        } else {
            $this->add('perf', 'warn', 'No s’ha detectat compressió', 'L’HTML s’envia sense comprimir, cosa que augmenta el temps de descàrrega.',
                'Activa Brotli o Gzip al servidor (mod_deflate/mod_brotli a Apache, gzip on; a Nginx) o des de la CDN.', 1);
        }

        $blocking = 0;
        foreach ($html->tags('script', $html->head()) as $s) {
            if (isset($s['src']) && !isset($s['async']) && !isset($s['defer']) && strtolower($s['type'] ?? '') !== 'module') {
                $blocking++;
            }
        }
        if ($blocking > 2) {
            $this->add('perf', 'warn', "$blocking scripts que bloquegen la renderització", 'Hi ha scripts al <head> sense async ni defer; el navegador atura el pintat fins que els descarrega.',
                'Afegeix l’atribut defer (o async si són independents) als scripts no crítics, o mou-los al final del <body>.', 1);
        } else {
            $this->add('perf', 'pass', 'Pocs scripts bloquejants', 'La càrrega de JavaScript al <head> no bloqueja excessivament la renderització.', '', 1);
        }
    }

    private function checkIndexability(array $res, Html $html, string $final): void
    {
        $robotsMeta = strtolower(implode(',', array_merge($html->meta('robots'), $html->meta('googlebot'))));
        $xRobots = strtolower($res['headers']['x-robots-tag'] ?? '');
        if (str_contains($robotsMeta . $xRobots, 'noindex')) {
            $this->add('crawl', 'fail', 'Pàgina marcada com a noindex', 'La metaetiqueta robots o la capçalera X-Robots-Tag impedeix que Google indexi aquesta pàgina.',
                'Si vols posicionar aquesta URL, elimina «noindex» de la meta robots / capçalera X-Robots-Tag (a WordPress, revisa Configuració › Lectura i el connector SEO).', 5);
        } else {
            $this->add('crawl', 'pass', 'Pàgina indexable', 'No hi ha directives noindex.', '', 5);
        }
        if (str_contains($robotsMeta, 'nofollow')) {
            $this->add('crawl', 'warn', 'Meta robots amb nofollow', 'Els cercadors no seguiran els enllaços d’aquesta pàgina, cosa que talla el flux d’autoritat interna.',
                'Elimina «nofollow» de la meta robots, llevat que sigui intencionat.', 1);
        }

        // Canonical
        $canon = $html->links('canonical');
        if (count($canon) === 0) {
            $this->add('crawl', 'warn', 'Falta l’etiqueta canonical', 'Sense canonical, els cercadors han d’endevinar la versió preferida davant de paràmetres o duplicats.',
                'Afegeix al <head>: <link rel="canonical" href="' . $final . '"> (autoreferenciada amb la URL absoluta definitiva).', 2);
        } elseif (count($canon) > 1) {
            $this->add('crawl', 'fail', 'Diverses etiquetes canonical', 'Hi ha ' . count($canon) . ' canonicals; Google pot ignorar-les totes.',
                'Deixa una única etiqueta canonical. Sol passar perquè el tema i un connector SEO l’afegeixen alhora.', 2);
        } else {
            $href = Http::absolute($final, $canon[0]['href'] ?? '');
            if (rtrim($href, '/') === rtrim($final, '/')) {
                $this->add('crawl', 'pass', 'Canonical autoreferenciada', 'La canonical apunta a la mateixa URL.', '', 2);
            } else {
                $this->add('crawl', 'warn', 'La canonical apunta a una altra URL', "Canonical: $href. Probablement aquesta pàgina no s’indexarà per si mateixa.",
                    'Comprova que sigui intencionat. Si aquesta pàgina ha de posicionar, fes que la canonical apunti a ella mateixa.', 2);
            }
        }

        // robots.txt
        $origin = Http::origin($final);
        $robots = Site::robots($origin);
        if (!$robots['exists']) {
            $this->add('crawl', 'warn', 'No s’ha trobat el robots.txt', "$origin/robots.txt no existeix o no és accessible.",
                "Crea un robots.txt a l’arrel amb, com a mínim:\nUser-agent: *\nAllow: /\nSitemap: $origin/sitemap.xml", 1);
        } else {
            $path = (parse_url($final, PHP_URL_PATH) ?: '/') . (($q = parse_url($final, PHP_URL_QUERY)) ? '?' . $q : '');
            if ($robots['robots']->isAllowed('Googlebot', $path)) {
                $this->add('crawl', 'pass', 'El robots.txt permet el rastreig', 'Googlebot pot rastrejar aquesta URL.', '', 2);
            } else {
                $this->add('crawl', 'fail', 'Bloquejada pel robots.txt', 'Una regla Disallow impedeix que Googlebot rastregi aquesta URL.',
                    'Revisa les regles Disallow del robots.txt i elimina la que afecta aquesta ruta si vols que es posicioni.', 4);
            }
        }

        // Sitemap
        $sm = Site::sitemap($origin);
        if ($sm['exists']) {
            $this->add('crawl', 'pass', 'Sitemap XML disponible', "Sitemap trobat a {$sm['url']}.", '', 1);
        } else {
            $this->add('crawl', 'warn', 'No s’ha trobat cap sitemap XML', 'Ni el robots.txt declara cap sitemap ni existeix /sitemap.xml.',
                'Genera un sitemap XML (els connectors SEO ho fan automàticament), declara’l al robots.txt amb «Sitemap: URL» i envia’l a Google Search Console.', 1);
        }

        // URL
        $issues = [];
        if (strlen($final) > 115) {
            $issues[] = 'és molt llarga (' . strlen($final) . ' caràcters)';
        }
        $path = (string) parse_url($final, PHP_URL_PATH);
        if (preg_match('/[A-Z]/', $path)) {
            $issues[] = 'conté majúscules';
        }
        if (str_contains($path, '_')) {
            $issues[] = 'fa servir guions baixos en lloc de guions';
        }
        if (substr_count((string) parse_url($final, PHP_URL_QUERY), '=') > 2) {
            $issues[] = 'té molts paràmetres';
        }
        if ($issues) {
            $this->add('crawl', 'warn', 'URL poc amigable', 'La URL ' . implode(', ', $issues) . '.',
                'Fes servir URL curtes, en minúscules, amb paraules clau separades per guions (-) i sense paràmetres innecessaris. Si la canvies, redirigeix l’antiga amb un 301.', 1);
        } else {
            $this->add('crawl', 'pass', 'URL amigable', 'La URL és curta i llegible.', '', 1);
        }

        $hreflang = array_filter($html->links('alternate'), static fn ($l) => isset($l['hreflang']));
        if ($hreflang) {
            $hasDefault = (bool) array_filter($hreflang, static fn ($l) => strtolower($l['hreflang']) === 'x-default');
            $this->add('crawl', $hasDefault ? 'pass' : 'warn', 'Etiquetes hreflang: ' . count($hreflang),
                'La pàgina declara versions en altres idiomes.' . ($hasDefault ? '' : ' Falta la variant x-default.'),
                $hasDefault ? '' : 'Afegeix <link rel="alternate" hreflang="x-default" href="..."> apuntant a la versió per defecte.', 1);
        }
    }

    private function checkMeta(Html $html): void
    {
        $title = $html->title();
        $len = $title ? mb_strlen($title) : 0;
        if (!$title) {
            $this->add('meta', 'fail', 'Falta l’etiqueta <title>', 'El títol és un dels factors on-page més importants i és el que apareix al resultat de Google.',
                'Afegeix un <title> únic de 30-60 caràcters que inclogui la paraula clau principal al principi i la marca al final.', 5);
        } elseif ($len < 30) {
            $this->add('meta', 'warn', "Títol massa curt ($len caràcters)", "«{$title}»",
                'Amplia el títol fins a 30-60 caràcters incloent-hi la paraula clau principal i un benefici o element diferenciador.', 5);
        } elseif ($len > 60) {
            $this->add('meta', 'warn', "Títol massa llarg ($len caràcters)", "«{$title}». Google el truncarà als resultats.",
                'Redueix el títol a un màxim d’uns 60 caràcters, amb el més important al principi.', 5);
        } else {
            $this->add('meta', 'pass', "Títol amb una longitud òptima ($len caràcters)", "«{$title}»", '', 5);
        }

        $descs = $html->meta('description');
        $desc = $descs[0] ?? '';
        $dlen = mb_strlen($desc);
        if ($desc === '') {
            $this->add('meta', 'fail', 'Falta la meta description', 'Sense descripció, Google genera un fragment automàtic, normalment menys atractiu i amb un CTR pitjor.',
                'Afegeix <meta name="description" content="..."> de 120-160 caràcters, amb la paraula clau i una crida a l’acció.', 3);
        } elseif ($dlen < 70) {
            $this->add('meta', 'warn', "Meta description curta ($dlen caràcters)", "«{$desc}»",
                'Amplia-la fins a 120-160 caràcters resumint el valor de la pàgina i incloent-hi una crida a l’acció.', 3);
        } elseif ($dlen > 160) {
            $this->add('meta', 'warn', "Meta description llarga ($dlen caràcters)", 'Es tallarà als resultats de cerca.',
                'Escurça-la fins a un màxim d’uns 160 caràcters.', 3);
        } else {
            $this->add('meta', 'pass', "Meta description òptima ($dlen caràcters)", "«{$desc}»", '', 3);
        }
        if (count($descs) > 1) {
            $this->add('meta', 'warn', 'Meta description duplicada', 'Hi ha ' . count($descs) . ' etiquetes description a la pàgina.',
                'Deixa només una meta description (sol duplicar-se entre el tema i el connector SEO).', 1);
        }

        if ($html->firstMeta('keywords') !== null) {
            $this->add('meta', 'info', 'Meta keywords present', 'Google ignora aquesta etiqueta des del 2009 i revela les teves paraules clau a la competència.',
                'Pots eliminar-la sense cap impacte negatiu.', 0);
        }

        $hasCharset = (bool) preg_match('/<meta[^>]+charset\s*=/i', $html->head());
        $this->add('meta', $hasCharset ? 'pass' : 'warn', $hasCharset ? 'Codificació de caràcters declarada' : 'Falta declarar la codificació',
            $hasCharset ? 'El document declara el charset.' : 'Sense <meta charset> es poden veure malament els accents i caràcters especials.',
            $hasCharset ? '' : 'Afegeix <meta charset="utf-8"> com a primera etiqueta del <head>.', 1);
    }

    private function checkContent(Html $html, int $words, int $size): void
    {
        $headings = $html->headings();
        $h1 = array_values(array_filter($headings, static fn ($h) => $h['level'] === 1));
        if (count($h1) === 0) {
            $this->add('content', 'fail', 'No hi ha cap encapçalament H1', 'L’H1 indica als cercadors i als usuaris el tema principal de la pàgina.',
                'Afegeix un únic <h1> descriptiu que inclogui la paraula clau principal (pot ser semblant al títol, però no idèntic).', 3);
        } elseif (count($h1) > 1) {
            $this->add('content', 'warn', count($h1) . ' encapçalaments H1', 'Diversos H1 dilueixen el tema principal: «' . implode('», «', array_slice(array_column($h1, 'text'), 0, 3)) . '».',
                'Deixa un sol H1 i converteix la resta en H2. Revisa que el logotip o el tema no facin servir H1.', 3);
        } elseif (trim($h1[0]['text']) === '') {
            $this->add('content', 'fail', 'L’H1 és buit', 'L’etiqueta H1 existeix però no conté text (possiblement només una imatge).',
                'Posa text a l’H1 o, si és una imatge, afegeix-hi un atribut alt descriptiu.', 3);
        } else {
            $this->add('content', 'pass', 'Un únic H1', '«' . mb_strimwidth($h1[0]['text'], 0, 120, '…') . '»', '', 3);
        }

        $levels = array_column($headings, 'level');
        $skips = 0;
        for ($i = 1; $i < count($levels); $i++) {
            if ($levels[$i] > $levels[$i - 1] + 1) {
                $skips++;
            }
        }
        if (!in_array(2, $levels, true)) {
            $this->add('content', 'warn', 'Sense subencapçalaments H2', 'El contingut no està estructurat en seccions.',
                'Divideix el contingut amb H2 (i H3 a dins) que incloguin variacions de la paraula clau i preguntes freqüents.', 2);
        } elseif ($skips > 0) {
            $this->add('content', 'warn', 'Jerarquia d’encapçalaments amb salts', "Se salten nivells $skips cop/cops (per exemple, d’H2 a H4).",
                'Respecta l’ordre H1 › H2 › H3 sense saltar nivells. Fes servir CSS per a la mida visual, no l’etiqueta.', 1);
        } else {
            $this->add('content', 'pass', 'Jerarquia d’encapçalaments correcta', count($headings) . ' encapçalaments en un ordre lògic.', '', 2);
        }

        if ($words < 300) {
            $this->add('content', 'warn', "Contingut escàs ($words paraules)", 'Les pàgines amb poc text se solen considerar «thin content» i posicionen pitjor.',
                'Amplia el contingut amb informació útil i original (objectiu orientatiu: 600+ paraules en pàgines informatives, 300+ en fitxes o serveis).', 3);
        } else {
            $this->add('content', 'pass', "Volum de contingut adequat ($words paraules)", 'La pàgina conté prou text per ser avaluada.', '', 3);
        }

        $textLen = strlen($html->text());
        $ratio = $size > 0 ? $textLen / $size * 100 : 0;
        if ($ratio < 10) {
            $this->add('content', 'warn', 'Ràtio text/HTML baixa (' . self::num($ratio, 1) . ' %)', 'Hi ha molt de codi en relació amb el contingut visible.',
                'Redueix el codi innecessari (constructors visuals, CSS/JS en línia) i augmenta el contingut útil.', 1);
        } else {
            $this->add('content', 'pass', 'Ràtio text/HTML correcta (' . self::num($ratio, 1) . ' %)', 'Bona proporció de contingut respecte al codi.', '', 1);
        }

        $lang = $html->htmlAttrs()['lang'] ?? '';
        if ($lang === '') {
            $this->add('content', 'warn', 'Falta l’atribut lang', 'No s’indica l’idioma del document a <html>.',
                'Afegeix l’idioma, per exemple <html lang="ca">. Ajuda els cercadors, els traductors i els lectors de pantalla.', 1);
        } else {
            $this->add('content', 'pass', "Idioma declarat ($lang)", 'El document declara el seu idioma.', '', 1);
        }
    }

    private function checkImages(Html $html): void
    {
        $imgs = $html->tags('img', $html->body());
        $total = count($imgs);
        if ($total === 0) {
            $this->add('media', 'info', 'Sense imatges', 'No s’han detectat imatges a la pàgina.', 'Valora afegir imatges rellevants i optimitzades: milloren l’experiència i aporten trànsit des de Google Imatges.', 0);
            return;
        }
        $noAlt = array_filter($imgs, static fn ($i) => !array_key_exists('alt', $i));
        $n = count($noAlt);
        if ($n === 0) {
            $this->add('media', 'pass', "Totes les imatges tenen alt ($total)", 'Els textos alternatius ajuden l’accessibilitat i el SEO d’imatges.', '', 2);
        } else {
            $examples = array_slice(array_map(static fn ($i) => basename((string) parse_url($i['src'] ?? $i['data-src'] ?? '?', PHP_URL_PATH)), $noAlt), 0, 5);
            $this->add('media', $n / $total > 0.5 ? 'fail' : 'warn', "$n de $total imatges sense atribut alt",
                'Exemples: ' . implode(', ', $examples) . '.',
                'Afegeix un alt descriptiu a cada imatge informativa (què mostra, amb la paraula clau si és natural). Per a imatges decoratives, fes servir alt="".', 2);
        }

        $noDims = count(array_filter($imgs, static fn ($i) => !isset($i['width'], $i['height'])));
        if ($noDims > 0) {
            $this->add('media', 'warn', "$noDims imatges sense dimensions", 'Sense width i height el navegador no reserva espai i es produeixen salts de disseny (CLS).',
                'Indica els atributs width i height a les etiquetes <img> (o aspect-ratio al CSS).', 1);
        } else {
            $this->add('media', 'pass', 'Imatges amb dimensions definides', 'Ajuda a evitar salts de disseny (CLS).', '', 1);
        }

        $lazy = count(array_filter($imgs, static fn ($i) => strtolower($i['loading'] ?? '') === 'lazy' || isset($i['data-src']) || isset($i['data-lazy-src'])));
        if ($total >= 4 && $lazy === 0) {
            $this->add('media', 'warn', 'Sense càrrega diferida d’imatges', 'Totes les imatges es descarreguen d’entrada encara que no siguin visibles.',
                'Afegeix loading="lazy" a les imatges que no són a la primera pantalla (mai a la imatge principal/LCP).', 1);
        } else {
            $this->add('media', 'pass', 'Càrrega d’imatges optimitzada', $lazy > 0 ? "$lazy imatges amb càrrega diferida." : 'Poques imatges a la pàgina.', '', 1);
        }
    }

    private function checkLinks(Html $html, string $final): void
    {
        $host = strtolower((string) parse_url($final, PHP_URL_HOST));
        $internal = $external = $empty = 0;
        foreach ($html->elements('a', $html->body()) as $a) {
            $href = trim($a['attrs']['href'] ?? '');
            if ($href === '' || str_starts_with($href, '#') || preg_match('#^(javascript|mailto|tel):#i', $href)) {
                continue;
            }
            $abs = Http::absolute($final, $href);
            $h = strtolower((string) parse_url($abs, PHP_URL_HOST));
            if ($h === $host || self::baseDomain($h) === self::baseDomain($host)) {
                $internal++;
            } else {
                $external++;
            }
            $hasLabel = $a['text'] !== '' || !empty($a['attrs']['aria-label']) || !empty($a['attrs']['title'])
                || preg_match('/<img[^>]+alt\s*=\s*["\'][^"\']+/i', $a['inner']);
            if (!$hasLabel) {
                $empty++;
            }
        }
        $this->add('links', 'info', "Enllaços: $internal interns, $external externs", 'Recompte dels enllaços trobats al cos de la pàgina.', '', 0);

        if ($internal < 3) {
            $this->add('links', 'warn', 'Pocs enllaços interns', 'La pàgina gairebé no enllaça a altres pàgines del lloc, cosa que limita el rastreig i el repartiment d’autoritat.',
                'Afegeix enllaços interns contextuals cap a pàgines relacionades, amb un text d’ancoratge descriptiu.', 2);
        } else {
            $this->add('links', 'pass', 'Enllaçat intern present', "$internal enllaços interns.", '', 2);
        }

        if ($empty > 0) {
            $this->add('links', 'warn', "$empty enllaços sense text descriptiu", 'Enllaços sense text, aria-label ni imatge amb alt: els cercadors i els lectors de pantalla no saben on porten.',
                'Afegeix text a l’enllaç o un aria-label (per exemple, a les icones de xarxes socials).', 1);
        } else {
            $this->add('links', 'pass', 'Tots els enllaços tenen text', 'Els textos d’ancoratge són interpretables.', '', 1);
        }
    }

    private function checkSocial(Html $html): void
    {
        $og = ['og:title' => $html->firstMeta('og:title'), 'og:description' => $html->firstMeta('og:description'), 'og:image' => $html->firstMeta('og:image')];
        $missing = array_keys(array_filter($og, static fn ($v) => $v === null || $v === ''));
        if (!$missing) {
            $this->add('social', 'pass', 'Open Graph complet', 'Títol, descripció i imatge definits per compartir a les xarxes.', '', 1);
        } else {
            $this->add('social', count($missing) === 3 ? 'fail' : 'warn', 'Open Graph incomplet', 'Falten: ' . implode(', ', $missing) . '. En compartir-lo a les xarxes, l’enllaç es veurà sense imatge o amb dades incorrectes.',
                'Afegeix <meta property="og:title">, og:description, og:image (1200×630 px) i og:url. Els connectors SEO ho fan automàticament.', 1);
        }
        $tw = $html->firstMeta('twitter:card');
        $this->add('social', $tw ? 'pass' : 'warn', $tw ? "Twitter/X Card ($tw)" : 'Falta la Twitter/X Card',
            $tw ? 'Previsualització optimitzada per a X.' : 'Sense twitter:card la previsualització a X serà bàsica.',
            $tw ? '' : 'Afegeix <meta name="twitter:card" content="summary_large_image">.', 0.5);
    }

    private function checkSchema(Html $html): void
    {
        $types = $html->schemaTypes();
        if ($types) {
            $this->add('schema', 'pass', 'Dades estructurades presents', 'Tipus detectats: ' . implode(', ', array_slice($types, 0, 10)) . '.', '', 2);
        } else {
            $this->add('schema', 'warn', 'Sense dades estructurades', 'No hi ha marcatge schema.org; la pàgina no podrà optar a resultats enriquits (estrelles, FAQ, rutes de navegació, etc.).',
                'Afegeix el JSON-LD adequat al tipus de pàgina: Organization/LocalBusiness a la portada, Article al blog, Product a les fitxes, BreadcrumbList i FAQPage. Valida’l a search.google.com/test/rich-results.', 2);
        }
    }

    private function checkMobile(Html $html): void
    {
        $vp = $html->firstMeta('viewport');
        if ($vp === null) {
            $this->add('mobile', 'fail', 'Falta la meta viewport', 'Sense viewport la pàgina no s’adapta als mòbils; Google indexa amb el rastrejador mòbil (mobile-first).',
                'Afegeix <meta name="viewport" content="width=device-width, initial-scale=1"> i assegura’t que el disseny sigui responsiu.', 4);
        } elseif (preg_match('/user-scalable\s*=\s*no|maximum-scale\s*=\s*1(\.0)?\b/i', $vp)) {
            $this->add('mobile', 'warn', 'El zoom està bloquejat', 'La meta viewport impedeix ampliar la pàgina, un problema d’accessibilitat.',
                'Elimina user-scalable=no i maximum-scale=1 de la meta viewport.', 1);
        } else {
            $this->add('mobile', 'pass', 'Meta viewport correcta', 'La pàgina està preparada per a mòbils.', '', 4);
        }

        $fav = array_merge($html->links('icon'), $html->links('apple-touch-icon'));
        $this->add('mobile', $fav ? 'pass' : 'warn', $fav ? 'Favicon definit' : 'Falta el favicon',
            $fav ? 'El lloc declara una icona.' : 'Google mostra el favicon al costat dels resultats en mòbil.',
            $fav ? '' : 'Afegeix <link rel="icon" href="/favicon.png"> (mínim 48×48 px) i un apple-touch-icon de 180×180 px.', 0.5);
    }

    private function checkSecurity(array $res, Html $html, string $final): void
    {
        if (str_starts_with($final, 'https://')) {
            preg_match_all('/<(?:img|script|iframe|source|audio|video)\b[^>]*\ssrc\s*=\s*["\']http:\/\/[^"\']+|<link\b[^>]*rel\s*=\s*["\']stylesheet["\'][^>]*href\s*=\s*["\']http:\/\/[^"\']+/i', $html->raw(), $m);
            $mixed = count($m[0]);
            if ($mixed > 0) {
                $this->add('security', 'fail', "Contingut mixt ($mixed recursos)", 'La pàgina HTTPS carrega recursos per HTTP; el navegador els pot bloquejar i mostrar avisos de seguretat.',
                    'Canvia les URL d’aquests recursos a https:// (o rutes relatives). A WordPress pots fer un cerca i substitueix a la base de dades.', 2);
            } else {
                $this->add('security', 'pass', 'Sense contingut mixt', 'Tots els recursos es carreguen de manera segura.', '', 2);
            }
        }

        $h = $res['headers'];
        $present = [];
        foreach (['strict-transport-security' => 'HSTS', 'x-content-type-options' => 'X-Content-Type-Options', 'referrer-policy' => 'Referrer-Policy', 'content-security-policy' => 'CSP', 'x-frame-options' => 'X-Frame-Options'] as $k => $label) {
            if (isset($h[$k])) {
                $present[] = $label;
            }
        }
        if (count($present) >= 3) {
            $this->add('security', 'pass', 'Capçaleres de seguretat configurades', 'Presents: ' . implode(', ', $present) . '.', '', 1);
        } else {
            $this->add('security', 'warn', 'Capçaleres de seguretat insuficients', $present ? 'Només presents: ' . implode(', ', $present) . '.' : 'No s’ha detectat cap capçalera de seguretat.',
                'Configura al servidor: Strict-Transport-Security (HSTS), X-Content-Type-Options: nosniff, Referrer-Policy: strict-origin-when-cross-origin i X-Frame-Options: SAMEORIGIN.', 1);
        }
    }

    public static function num(float $n, int $dec): string
    {
        return number_format($n, $dec, ',', '.');
    }

    /** Domini registrable aproximat (exemple.cat, exemple.co.uk, exemple.com.es). */
    public static function baseDomain(string $host): string
    {
        $parts = explode('.', strtolower($host));
        $n = count($parts);
        if ($n <= 2) {
            return strtolower($host);
        }
        $take = in_array($parts[$n - 2], ['co', 'com', 'org', 'net', 'gob', 'edu', 'ac', 'gov', 'nom'], true) ? 3 : 2;
        return implode('.', array_slice($parts, -$take));
    }

    public static function bytes(int $b): string
    {
        return $b >= 1048576 ? number_format($b / 1048576, 2, ',', '.') . ' MB' : number_format($b / 1024, 1, ',', '.') . ' KB';
    }
}
