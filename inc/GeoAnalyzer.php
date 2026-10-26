<?php
declare(strict_types=1);

/**
 * GEO (Generative Engine Optimization): avalua fins a quin punt una URL està
 * preparada per ser rastrejada, entesa i citada pels cercadors amb IA
 * (ChatGPT, Claude, Perplexity, Gemini, AI Overviews, Copilot…).
 * 100 % heurístic: no consumeix tokens ni API de pagament.
 */
final class GeoAnalyzer
{
    public const CATEGORIES = [
        'access'    => 'Accés dels rastrejadors d’IA',
        'content'   => 'Contingut citable',
        'authority' => 'Autoritat i confiança (E-E-A-T)',
        'technical' => 'Senyals tècnics per a la IA',
    ];

    /** Rastrejadors d’IA: [user-agent, empresa, funció, tipus] — tipus: search | training */
    public const BOTS = [
        ['OAI-SearchBot', 'OpenAI', 'Cerca a ChatGPT', 'search'],
        ['ChatGPT-User', 'OpenAI', 'Visites sol·licitades pels usuaris de ChatGPT', 'search'],
        ['GPTBot', 'OpenAI', 'Entrenament de models', 'training'],
        ['Claude-SearchBot', 'Anthropic', 'Cerca a Claude', 'search'],
        ['Claude-User', 'Anthropic', 'Visites sol·licitades pels usuaris de Claude', 'search'],
        ['ClaudeBot', 'Anthropic', 'Entrenament de models', 'training'],
        ['PerplexityBot', 'Perplexity', 'Índex de respostes de Perplexity', 'search'],
        ['Perplexity-User', 'Perplexity', 'Visites sol·licitades pels usuaris', 'search'],
        ['Googlebot', 'Google', 'Cerca, AI Overviews i AI Mode', 'search'],
        ['Google-Extended', 'Google', 'Entrenament de Gemini', 'training'],
        ['Bingbot', 'Microsoft', 'Bing i Copilot', 'search'],
        ['Applebot-Extended', 'Apple', 'Entrenament d’Apple Intelligence', 'training'],
        ['Meta-ExternalAgent', 'Meta', 'Entrenament de Meta AI', 'training'],
        ['CCBot', 'Common Crawl', 'Conjunt de dades obert que fan servir molts LLM', 'training'],
    ];

    private const BOT_UAS = [
        'GPTBot'        => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.2; +https://openai.com/gptbot)',
        'ClaudeBot'     => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)',
        'PerplexityBot' => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; PerplexityBot/1.0; +https://perplexity.ai/perplexitybot)',
    ];

    private array $checks = [];

    public function analyze(string $url): array
    {
        $this->checks = [];
        $res = Http::get($url);
        $result = ['url' => $url, 'final_url' => $res['url'], 'status' => $res['status'], 'error' => $res['error'],
            'metrics' => [], 'checks' => [], 'bots' => [], 'llms_draft' => '', 'score' => 0];

        if (!$res['ok'] || $res['status'] >= 400) {
            $this->add('access', 'fail', 'La pàgina no és accessible', $res['error'] ?? "Codi HTTP {$res['status']}.",
                'Cap IA no podrà llegir ni citar una pàgina que no respon. Corregeix primer l’error d’accés.', 10);
            $result['checks'] = $this->checks;
            return $result;
        }

        $final = $res['url'];
        $origin = Http::origin($final);
        $html = new Html($res['body']);
        $text = $html->text();
        $words = $html->wordCount();

        $result['bots'] = $this->checkRobots($origin, $final);
        $this->checkBotFetch($final, $res);
        $this->checkLlmsTxt($origin);
        $this->checkSnippetControls($res, $html);
        $this->checkRendering($html, $words);
        $this->checkContent($html, $text, $words);
        $this->checkAuthority($html, $final);
        $this->checkTechnical($html);

        $result['title'] = $html->title();
        $result['metrics'] = [
            'Paraules'           => number_format($words, 0, ',', '.'),
            'Bots d’IA permesos' => count(array_filter($result['bots'], static fn ($b) => $b['allowed'])) . ' / ' . count($result['bots']),
            'Esquemes'           => count($html->schemaTypes()),
            'llms.txt'           => Site::llmsTxt($origin)['exists'] ? 'Sí' : 'No',
        ];
        $result['llms_draft'] = $this->llmsDraft($html, $final);
        $result['checks'] = $this->checks;
        $result['score'] = compute_score($this->checks);
        return $result;
    }

    private function add(string $cat, string $status, string $title, string $detail, string $solution = '', float $weight = 1): void
    {
        $this->checks[] = compact('cat', 'status', 'title', 'detail', 'solution', 'weight');
    }

    // -----------------------------------------------------------------------

    private function checkRobots(string $origin, string $final): array
    {
        $robots = Site::robots($origin);
        $path = parse_url($final, PHP_URL_PATH) ?: '/';
        $bots = [];
        foreach (self::BOTS as [$ua, $company, $role, $type]) {
            $allowed = $robots['robots'] ? $robots['robots']->isAllowed($ua, $path) : true;
            $bots[] = ['ua' => $ua, 'company' => $company, 'role' => $role, 'type' => $type, 'allowed' => $allowed,
                'explicit' => $robots['robots'] ? $robots['robots']->hasSpecificGroup($ua) : false];
        }

        $blockedSearch = array_column(array_filter($bots, static fn ($b) => $b['type'] === 'search' && !$b['allowed']), 'ua');
        $blockedTraining = array_column(array_filter($bots, static fn ($b) => $b['type'] === 'training' && !$b['allowed']), 'ua');

        if ($blockedSearch) {
            $this->add('access', 'fail', 'Bloqueges cercadors d’IA al robots.txt', 'Bloquejats: ' . implode(', ', $blockedSearch) . '. Aquestes IA no podran mostrar ni citar el teu contingut a les seves respostes.',
                "Permet explícitament els bots de cerca al robots.txt, per exemple:\nUser-agent: OAI-SearchBot\nUser-agent: Claude-SearchBot\nUser-agent: PerplexityBot\nAllow: /", 5);
        } else {
            $this->add('access', 'pass', 'Cercadors d’IA permesos', 'El robots.txt permet als bots de cerca de ChatGPT, Claude, Perplexity, Google i Bing accedir a aquesta URL.', '', 5);
        }

        if ($blockedTraining) {
            $this->add('access', 'info', 'Bots d’entrenament bloquejats', 'Bloquejats: ' . implode(', ', $blockedTraining) . '. No afecta directament la visibilitat a les cerques amb IA, però redueix la presència de la teva marca en el coneixement de base dels models.',
                'És una decisió de negoci: bloqueja’ls si no vols que el teu contingut s’utilitzi per entrenar; permet-los si busques la màxima notorietat de marca.', 0);
        } else {
            $this->add('access', 'info', 'Bots d’entrenament permesos', 'El teu contingut pot formar part del coneixement de futurs models (GPTBot, ClaudeBot, Google-Extended…).', '', 0);
        }

        $explicit = array_filter($bots, static fn ($b) => $b['explicit']);
        if (!$robots['exists']) {
            $this->add('access', 'warn', 'Sense robots.txt', 'No hi ha robots.txt: tot està permès, però no comuniques cap política als rastrejadors d’IA ni declares el sitemap.',
                "Crea un robots.txt amb la teva política per als bots d’IA i la línia Sitemap: $origin/sitemap.xml", 1);
        } elseif (!$explicit) {
            $this->add('access', 'warn', 'Sense política explícita per als bots d’IA', 'El robots.txt no esmenta cap rastrejador d’IA pel seu nom; depenen de les regles genèriques (User-agent: *).',
                'Afegeix blocs específics (OAI-SearchBot, Claude-SearchBot, PerplexityBot, GPTBot, ClaudeBot, Google-Extended…) amb Allow/Disallow segons la teva estratègia. Així evites bloquejos accidentals en canviar les regles generals.', 1);
        } else {
            $this->add('access', 'pass', 'Política explícita per als bots d’IA', count($explicit) . ' rastrejadors d’IA amb regles pròpies al robots.txt.', '', 1);
        }
        return $bots;
    }

    /** Comprova si el servidor o el tallafoc (p. ex. Cloudflare) bloqueja els bots d’IA pel user-agent. */
    private function checkBotFetch(string $final, array $normal): void
    {
        $blocked = [];
        foreach (self::BOT_UAS as $name => $ua) {
            $r = Http::get($final, $ua);
            $challenge = $r['ok'] && preg_match('/cf-chl|challenge-platform|captcha|access denied|attention required/i', substr($r['body'], 0, 20000))
                && !preg_match('/cf-chl|challenge-platform|captcha/i', substr($normal['body'], 0, 20000));
            if (!$r['ok'] || $r['status'] >= 400 || $challenge) {
                $blocked[] = $name . ($r['status'] ? " ({$r['status']})" : '');
            }
        }
        if ($blocked) {
            $this->add('access', 'fail', 'El servidor o el tallafoc bloqueja bots d’IA', 'En sol·licitar la pàgina com a ' . implode(', ', $blocked) . ' es rep un error o un desafiament antibots, tot i que un navegador normal hi accedeix sense problemes.',
                'Revisa el tallafoc/CDN: a Cloudflare, Seguretat › Bots («Block AI bots» / «AI Scrapers and Crawlers») i les regles WAF; als connectors de seguretat, les llistes de user-agents bloquejats. Nota: la prova es fa des de la nostra IP i alguns WAF només admeten la IP real del bot, així que confirma-ho als seus registres.', 4);
        } else {
            $this->add('access', 'pass', 'El servidor respon als bots d’IA', 'GPTBot, ClaudeBot i PerplexityBot reben la pàgina sense bloquejos ni desafiaments.', '', 4);
        }
    }

    private function checkLlmsTxt(string $origin): void
    {
        $llms = Site::llmsTxt($origin);
        if ($llms['exists']) {
            $this->add('technical', 'pass', 'Fitxer llms.txt present', "Trobat a {$llms['url']}. Ofereix als LLM un resum estructurat del lloc.", '', 1);
        } else {
            $this->add('technical', 'warn', 'Sense fitxer llms.txt', 'El llms.txt és un estàndard emergent: un Markdown a l’arrel que resumeix el lloc i enllaça les pàgines clau perquè els models l’entenguin ràpidament.',
                'Crea /llms.txt. Més avall tens un esborrany generat automàticament a partir d’aquesta pàgina per adaptar-lo i pujar-lo a l’arrel del domini.', 1);
        }
    }

    private function checkSnippetControls(array $res, Html $html): void
    {
        $directives = strtolower(implode(',', array_merge($html->meta('robots'), $html->meta('googlebot'))) . ',' . ($res['headers']['x-robots-tag'] ?? ''));
        if (preg_match('/nosnippet|max-snippet\s*:\s*0\b/', $directives)) {
            $this->add('access', 'fail', 'Fragments bloquejats (nosnippet)', 'Les directives nosnippet / max-snippet:0 impedeixen que Google faci servir el teu contingut als AI Overviews i a l’AI Mode, i limiten altres IA.',
                'Elimina nosnippet o fixa max-snippet:-1. Per amagar només parts concretes, fes servir l’atribut data-nosnippet en aquests elements.', 3);
        } elseif (str_contains($directives, 'noindex')) {
            $this->add('access', 'fail', 'Pàgina noindex', 'Com que no està indexada, els cercadors amb IA basats en índexs (Google, Bing/Copilot) no la faran servir com a font.',
                'Elimina el noindex si vols que aquesta pàgina sigui citada.', 3);
        } else {
            $this->add('access', 'pass', 'Contingut disponible per a fragments d’IA', 'No hi ha directives que impedeixin fer servir el text en respostes generades.', '', 3);
        }
    }

    private function checkRendering(Html $html, int $words): void
    {
        $scripts = count($html->tags('script'));
        $spaRoot = preg_match('/<div[^>]+id=["\'](root|app|__next|__nuxt)["\'][^>]*>\s*<\/div>/i', $html->body());
        if ($words < 120 && ($scripts > 5 || $spaRoot)) {
            $this->add('technical', 'fail', 'Contingut dependent de JavaScript', "L’HTML inicial només conté $words paraules. La majoria de rastrejadors d’IA (GPTBot, ClaudeBot, PerplexityBot) no executen JavaScript, així que veuen la pàgina gairebé buida.",
                'Fes servir renderització al servidor (SSR) o generació estàtica (Next.js, Nuxt, Astro…) o un servei de prerenderització perquè el contingut sigui a l’HTML.', 5);
        } else {
            $this->add('technical', 'pass', 'Contingut present a l’HTML', 'El text principal s’entrega a l’HTML inicial i es pot llegir sense executar JavaScript.', '', 5);
        }
    }

    private function checkContent(Html $html, string $text, int $words): void
    {
        // Profunditat
        if ($words >= 800) {
            $this->add('content', 'pass', "Contingut extens ($words paraules)", 'Els motors generatius prefereixen fonts completes que tractin el tema en detall.', '', 2);
        } elseif ($words >= 300) {
            $this->add('content', 'warn', "Contingut d’extensió mitjana ($words paraules)", 'Pot ser suficient per a pàgines de servei, però limita la varietat de preguntes que pot respondre.',
                'Amplia’l amb seccions de preguntes freqüents, exemples, dades i comparatives.', 2);
        } else {
            $this->add('content', 'fail', "Contingut escàs ($words paraules)", 'Amb tan poc text és poc probable que una IA consideri la pàgina com a font.',
                'Desenvolupa el contingut responent les preguntes reals del teu públic (objectiu orientatiu: 800+ paraules en continguts informatius).', 2);
        }

        // Encapçalaments en forma de pregunta (ca, es, en, fr)
        $headings = array_filter($html->headings(), static fn ($h) => $h['level'] >= 2);
        $questions = array_filter($headings, static fn ($h) => str_ends_with($h['text'], '?') || str_starts_with($h['text'], '¿')
            || preg_match('/^(què|que|com|per què|quin|quina|quins|quines|quan|quant|on|qui|qué|cómo|como|por qué|cuál|cuáles|cuándo|cuánto|dónde|quién|what|how|why|which|when|where|who|comment|pourquoi|quel|quelle|quand|combien|où)\b/iu', $h['text']));
        $q = count($questions);
        if ($q >= 3) {
            $this->add('content', 'pass', "$q encapçalaments en forma de pregunta", 'Les IA busquen fragments que responguin preguntes concretes; els encapçalaments interrogatius en faciliten l’extracció.', '', 2);
        } else {
            $this->add('content', $q > 0 ? 'warn' : 'fail', $q > 0 ? "Només $q encapçalament(s) en forma de pregunta" : 'Sense encapçalaments en forma de pregunta',
                'Els usuaris pregunten a la IA en llenguatge natural («com…?», «quant costa…?») i els motors busquen seccions que hi responguin exactament.',
                'Reformula alguns H2/H3 com a preguntes que faria el teu client i respon just a sota en 40-60 paraules, de manera directa, abans de desenvolupar.', 2);
        }

        // Resposta directa a l’inici
        $paras = array_values(array_filter($html->elements('p', $html->body()), static fn ($p) => self::words($p['text']) >= 8 || mb_strlen($p['text']) > 60));
        $first = $paras[0]['text'] ?? '';
        $fw = self::words($first);
        if ($fw >= 20 && $fw <= 120) {
            $this->add('content', 'pass', 'Introducció amb resposta directa', 'El primer paràgraf té una extensió adequada per ser citat com a resum (' . $fw . ' paraules).', '', 2);
        } else {
            $this->add('content', 'warn', 'Introducció poc citable', $fw === 0 ? 'No s’ha trobat cap paràgraf introductori de text.' : "El primer paràgraf té $fw paraules.",
                'Comença amb un paràgraf de 40-80 paraules que respongui directament a la intenció principal (què és, per a qui, benefici clau). És el fragment que més es cita.', 2);
        }

        // Paràgrafs llargs
        $long = count(array_filter($paras, static fn ($p) => self::words($p['text']) > 150));
        if ($paras && $long / count($paras) > 0.3) {
            $this->add('content', 'warn', 'Paràgrafs massa llargs', "$long paràgrafs superen les 150 paraules. Els blocs llargs són difícils d’extreure com a resposta.",
                'Divideix-los en paràgrafs de 2-4 frases, cadascun centrat en una sola idea.', 1);
        }

        // Llistes i taules
        $lists = count(array_filter(array_merge($html->elements('ul', $html->body()), $html->elements('ol', $html->body())), static fn ($l) => substr_count(strtolower($l['inner']), '<li') >= 3));
        $tables = count($html->tags('table', $html->body()));
        if ($tables > 0 || $lists >= 2) {
            $this->add('content', 'pass', 'Format estructurat (llistes/taules)', "Llistes: $lists · Taules: $tables. Els LLM reutilitzen sovint llistes i taules comparatives.", '', 2);
        } else {
            $this->add('content', 'warn', 'Poc contingut estructurat', "Llistes: $lists · Taules: $tables.",
                'Inclou llistes de passos, avantatges o requisits i taules comparatives (preus, característiques). Són formats que les IA citen gairebé literalment.', 2);
        }

        // Dades i estadístiques
        preg_match_all('/\b\d+(?:[.,]\d+)?\s?(?:%|€|\$|euros?|milions|millones|millions|mil|anys|años|years|ans|hores|horas|hours|heures|dies|días|days|jours|km|kg)\b|\b(?:19|20)\d{2}\b/iu', $text, $m);
        $stats = count($m[0]);
        if ($stats >= 5) {
            $this->add('content', 'pass', "Contingut amb dades concretes ($stats xifres)", 'Les estadístiques i xifres augmenten la probabilitat de ser citat (els estudis de GEO mostren millores de visibilitat del 30-40 %).', '', 2);
        } else {
            $this->add('content', 'warn', 'Poques dades i xifres', "Només es detecten $stats xifres o dades concretes.",
                'Afegeix estadístiques, preus, terminis, percentatges i dates, idealment amb la font. Les IA prefereixen afirmacions verificables.', 2);
        }
    }

    private function checkAuthority(Html $html, string $final): void
    {
        $host = SeoAnalyzer::baseDomain((string) parse_url($final, PHP_URL_HOST));
        $jsonLd = $html->jsonLd();

        // Autoria
        $author = $html->firstMeta('author') ?? $html->firstMeta('article:author');
        foreach ($jsonLd as $n) {
            if (!empty($n['author'])) {
                $author ??= is_array($n['author']) ? ($n['author']['name'] ?? $n['author'][0]['name'] ?? 'definit') : (string) $n['author'];
            }
        }
        if (!$author && ($html->links('author') || preg_match('/class=["\'][^"\']*(author|byline|autor)[^"\']*["\']/i', $html->body()))) {
            $author = 'detectat a la pàgina';
        }
        if ($author) {
            $this->add('authority', 'pass', 'Autoria identificada', "Autor: $author. Les IA valoren el contingut signat per persones o entitats reconeixibles.", '', 2);
        } else {
            $this->add('authority', 'warn', 'Sense autor identificat', 'No es troba l’autor a les metadades, a l’schema ni a la pàgina.',
                'Signa el contingut amb un autor real amb biografia i enllaç al seu perfil (LinkedIn, pàgina d’autor) i declara’l a l’schema (author: Person).', 2);
        }

        // Actualitat
        $dates = [];
        foreach (['article:modified_time', 'article:published_time', 'og:updated_time'] as $k) {
            if ($v = $html->firstMeta($k)) {
                $dates[] = $v;
            }
        }
        foreach ($jsonLd as $n) {
            foreach (['dateModified', 'datePublished'] as $k) {
                if (!empty($n[$k]) && is_string($n[$k])) {
                    $dates[] = $n[$k];
                }
            }
        }
        foreach ($html->tags('time') as $t) {
            if (!empty($t['datetime'])) {
                $dates[] = $t['datetime'];
            }
        }
        $ts = array_filter(array_map('strtotime', $dates));
        if (!$ts) {
            $this->add('authority', 'warn', 'Sense data de publicació o actualització', 'Els motors generatius afavoreixen el contingut recent i necessiten saber quan es va actualitzar.',
                'Mostra la data de darrera actualització a la pàgina i declara-la a l’schema (datePublished i dateModified) o amb article:modified_time.', 2);
        } else {
            $latest = max($ts);
            $months = (int) floor((time() - $latest) / (30 * 86400));
            $date = date('d/m/Y', $latest);
            if ($months <= 12) {
                $this->add('authority', 'pass', 'Contingut actualitzat', "Darrera data detectada: $date.", '', 2);
            } else {
                $this->add('authority', 'warn', 'Contingut desactualitzat', "Darrera data detectada: $date (fa uns $months mesos).",
                    'Revisa i actualitza el contingut (dades, exemples, any) i actualitza el dateModified. Les IA penalitzen la informació antiga en temes canviants.', 2);
            }
        }

        // Fonts externes
        $outbound = 0;
        foreach ($html->tags('a', $html->body()) as $a) {
            $h = SeoAnalyzer::baseDomain((string) parse_url(Http::absolute($final, $a['href'] ?? ''), PHP_URL_HOST));
            if ($h && $h !== $host && !preg_match('/(facebook|twitter|x\.com|instagram|linkedin|youtube|tiktok|pinterest|whatsapp)/', $h)) {
                $outbound++;
            }
        }
        if ($outbound >= 2) {
            $this->add('authority', 'pass', "Cita fonts externes ($outbound enllaços)", 'Referenciar fonts fiables reforça la credibilitat del contingut.', '', 1);
        } else {
            $this->add('authority', 'warn', 'No cita fonts externes', 'El contingut no enllaça cap font de referència.',
                'Enllaça estudis, organismes oficials o fonts reconegudes que avalin les teves dades. Afegir-hi cites millora la visibilitat a les respostes generatives.', 1);
        }

        // Entitat de marca
        $org = null;
        foreach ($jsonLd as $n) {
            $types = array_map('strval', (array) $n['@type']);
            if (preg_grep('/(Organization|Business|Corporation|Person|Service|Store|Restaurant|Agency|Office|Clinic)$/', $types)) {
                $org = $n;
                break;
            }
        }
        $sameAs = $org ? count((array) ($org['sameAs'] ?? [])) : 0;
        if ($org && $sameAs >= 2) {
            $this->add('authority', 'pass', 'Entitat de marca ben definida', "Schema d’organització amb $sameAs perfils enllaçats (sameAs).", '', 2);
        } elseif ($org) {
            $this->add('authority', 'warn', 'Entitat de marca incompleta', 'Hi ha schema d’organització, però sense prou perfils sameAs.',
                'Afegeix a l’schema Organization la propietat sameAs amb els teus perfils oficials (LinkedIn, Viquipèdia/Wikidata, Google Business, xarxes) perquè les IA connectin la teva marca com a entitat.', 2);
        } else {
            $this->add('authority', 'warn', 'Sense entitat de marca (Organization)', 'Les IA construeixen les respostes a partir d’entitats. Sense schema Organization/LocalBusiness és més difícil que associïn el contingut a la teva marca.',
                'Afegeix JSON-LD Organization (o LocalBusiness) amb name, url, logo, description, contactPoint i sameAs, com a mínim a la portada i a la pàgina «Qui som».', 2);
        }
    }

    private function checkTechnical(Html $html): void
    {
        $types = $html->schemaTypes();
        $useful = array_intersect($types, ['FAQPage', 'HowTo', 'Article', 'BlogPosting', 'NewsArticle', 'Product', 'Service', 'Recipe', 'QAPage', 'Course', 'Event', 'Review',
            'BreadcrumbList', 'WebPage', 'WebSite', 'CollectionPage', 'ItemList', 'AboutPage', 'ContactPage', 'Offer', 'VideoObject']);
        if (count($useful) >= 2) {
            $this->add('technical', 'pass', 'Schema de contingut complet', 'Tipus útils per a la IA: ' . implode(', ', $useful) . '.', '', 2);
        } elseif ($useful) {
            $this->add('technical', 'warn', 'Schema de contingut bàsic', 'Tipus detectats: ' . implode(', ', $useful) . '.',
                'Complementa’l amb FAQPage (preguntes freqüents), HowTo (guies pas a pas), Article amb author/dateModified o Product/Service segons el tipus de pàgina.', 2);
        } else {
            $this->add('technical', 'fail', 'Sense schema de contingut', 'No hi ha marcatge que descrigui el tipus de contingut. Les dades estructurades ajuden els motors a interpretar-lo sense ambigüitats.',
                'Afegeix JSON-LD: Article/BlogPosting als articles, FAQPage a les preguntes freqüents, Product/Service a les pàgines comercials i BreadcrumbList a tot el lloc.', 2);
        }

        $desc = $html->firstMeta('description') ?? $html->firstMeta('og:description');
        if ($desc && mb_strlen($desc) >= 70) {
            $this->add('technical', 'pass', 'Resum de pàgina disponible', 'La meta description ofereix un resum que els motors poden fer servir com a context.', '', 1);
        } else {
            $this->add('technical', 'warn', 'Falta un resum clar de la pàgina', 'No hi ha cap meta description útil.',
                'Escriu una meta description de 120-160 caràcters que resumeixi de manera factual de què tracta la pàgina.', 1);
        }

        $semantic = 0;
        foreach (['main', 'article', 'nav', 'header', 'footer', 'section'] as $tag) {
            if (preg_match('/<' . $tag . '\b/i', $html->body())) {
                $semantic++;
            }
        }
        if ($semantic >= 3) {
            $this->add('technical', 'pass', 'HTML semàntic', 'Fa servir etiquetes com <main>, <article> o <section>, que ajuden a separar el contingut principal de la resta.', '', 1);
        } else {
            $this->add('technical', 'warn', 'HTML poc semàntic', "Només $semantic de 6 etiquetes semàntiques clau.",
                'Embolcalla el contingut principal amb <main> i <article>, i fes servir <header>, <nav>, <section> i <footer>. Ajuda els rastrejadors a ignorar menús i peus de pàgina.', 1);
        }
    }

    private static function words(string $text): int
    {
        return trim($text) === '' ? 0 : count(preg_split('/\s+/u', trim($text)) ?: []);
    }

    private function llmsDraft(Html $html, string $final): string
    {
        $origin = Http::origin($final);
        $parts = array_filter(array_map('trim', preg_split('/\s[|\-–—·:]\s/u', (string) $html->title()) ?: []));
        usort($parts, static fn ($a, $b) => mb_strlen($a) <=> mb_strlen($b));
        $site = $html->firstMeta('og:site_name') ?: ($parts[0] ?? (string) parse_url($final, PHP_URL_HOST));
        $desc = $html->firstMeta('description') ?: $html->firstMeta('og:description') ?: 'Descriu aquí, en una o dues frases, què ofereix el lloc i a qui.';
        $host = SeoAnalyzer::baseDomain((string) parse_url($final, PHP_URL_HOST));

        $links = [];
        foreach ($html->elements('a', $html->body()) as $a) {
            $abs = strtok(Http::absolute($final, $a['attrs']['href'] ?? ''), '#');
            $h = SeoAnalyzer::baseDomain((string) parse_url((string) $abs, PHP_URL_HOST));
            $label = trim(mb_strimwidth($a['text'], 0, 70, '…'));
            if ($h !== $host || $label === '' || mb_strlen($label) < 3 || isset($links[$abs]) || preg_match('/\.(jpg|png|pdf|zip|webp)$|wp-login|\/feed|\?/i', (string) $abs)) {
                continue;
            }
            $links[$abs] = $label;
            if (count($links) >= 15) {
                break;
            }
        }

        $out = "# $site\n\n> $desc\n\n";
        $out .= "Informació clau sobre $site per a assistents i models d’IA.\n\n## Pàgines principals\n\n";
        $out .= "- [Inici]($origin/): Pàgina principal\n";
        foreach ($links as $url => $label) {
            $out .= "- [$label]($url)\n";
        }
        $out .= "\n## Contacte\n\n- Web: $origin\n- Correu: (afegeix el teu correu de contacte)\n\n## Optional\n\n- [Sitemap]($origin/sitemap.xml)\n";
        return $out;
    }
}
