<?php
declare(strict_types=1);

/**
 * Generador de Política de privadesa, Política de cookies i (per a botigues
 * en línia) Política de cancel·lació i devolucions en català, castellà, anglès o francès (RGPD + LOPDGDD + LSSI-CE per a Espanya,
 * o RGPD genèric UE). Plantilles orientatives: cal revisar-les amb un professional.
 * Els textos de cada idioma són a inc/legal/{idioma}.php.
 */
final class LegalGenerator
{
    public const LANGS = ['ca' => 'Català', 'es' => 'Castellano', 'en' => 'English', 'fr' => 'Français'];

    public const PURPOSES = [
        'contacto'    => 'Formulari de contacte / pressupostos',
        'newsletter'  => 'Butlletí / comunicacions comercials',
        'clientes'    => 'Registre d’usuaris / àrea de clients',
        'tienda'      => 'Botiga en línia / comandes i pagaments',
        'servicios'   => 'Gestió de clients i prestació de serveis',
        'cv'          => 'Recepció de currículums',
        'comentarios' => 'Comentaris al blog',
        'redes'       => 'Perfils a xarxes socials',
    ];

    public const COOKIE_SERVICES = [
        'wordpress'   => 'WordPress (sessió d’usuaris registrats)',
        'woocommerce' => 'WooCommerce (cistella)',
        'php'         => 'Sessió del servidor (PHPSESSID)',
        'idioma'      => 'Preferència d’idioma',
        'recaptcha'   => 'Google reCAPTCHA',
        'ga4'         => 'Google Analytics 4',
        'clarity'     => 'Microsoft Clarity',
        'hotjar'      => 'Hotjar',
        'google_ads'  => 'Google Ads / DoubleClick',
        'meta_pixel'  => 'Meta Pixel (Facebook/Instagram)',
        'linkedin'    => 'LinkedIn Insight Tag',
        'tiktok'      => 'TikTok Pixel',
        'youtube'     => 'Vídeos de YouTube incrustats',
        'maps'        => 'Google Maps incrustat',
    ];

    /** Béns exclosos del dret de desistiment (art. 103 TRLGDCU / art. 16 Directiva 2011/83/UE) */
    public const RETURN_EXCLUSIONS = [
        'custom'     => 'Productes personalitzats o fets a mida',
        'perishable' => 'Productes peribles o de caducitat ràpida',
        'hygiene'    => 'Productes precintats d’higiene o salut desprecintats',
        'media'      => 'Música, vídeo o programari precintats i desprecintats',
        'digital'    => 'Contingut digital sense suport material (descàrregues)',
        'press'      => 'Diaris, revistes o publicacions periòdiques',
        'mixed'      => 'Béns que s’han barrejat inseparablement amb altres',
    ];

    /** [nom visible, nom de la cookie de consentiment] — null = nom genèric traduït */
    public const CMPS = [
        'cookiebot' => ['Cookiebot', 'CookieConsent'],
        'cookieyes' => ['CookieYes', 'cookieyes-consent'],
        'complianz' => ['Complianz', 'cmplz_*'],
        'borlabs'   => ['Borlabs Cookie', 'borlabs-cookie'],
        'iubenda'   => ['iubenda', '_iub_cs-*'],
        'otro'      => [null, 'cookie_consent'],
    ];

    /** [nom, titular (null = pròpia), clau de finalitat, durada, tipus, EUA?] */
    private const COOKIES = [
        'wordpress' => [
            ['wordpress_logged_in_*', null, 'wp_logged', 'session', 'tech', false],
            ['wordpress_sec_*', null, 'wp_sec', 'session', 'tech', false],
            ['wordpress_test_cookie', null, 'wp_test', 'session', 'tech', false],
            ['wp-settings-*', null, 'wp_settings', '1y', 'pref', false],
        ],
        'woocommerce' => [
            ['woocommerce_cart_hash', null, 'wc_hash', 'session', 'tech', false],
            ['woocommerce_items_in_cart', null, 'wc_items', 'session', 'tech', false],
            ['wp_woocommerce_session_*', null, 'wc_session', '2d', 'tech', false],
        ],
        'php' => [
            ['PHPSESSID', null, 'php', 'session', 'tech', false],
        ],
        'idioma' => [
            ['lang / pll_language', null, 'lang', '1y', 'pref', false],
        ],
        'recaptcha' => [
            ['_GRECAPTCHA', 'Google LLC', 'recaptcha', '6m', 'security', true],
        ],
        'ga4' => [
            ['_ga', 'Google LLC', 'ga', '2y', 'analytics', true],
            ['_ga_*', 'Google LLC', 'ga_session', '2y', 'analytics', true],
        ],
        'clarity' => [
            ['_clck', 'Microsoft', 'clck', '1y', 'analytics', true],
            ['_clsk', 'Microsoft', 'clsk', '1d', 'analytics', true],
            ['CLID', 'Microsoft', 'clid', '1y', 'analytics', true],
            ['MUID', 'Microsoft', 'muid', '1y', 'analytics', true],
        ],
        'hotjar' => [
            ['_hjSessionUser_*', 'Hotjar Ltd.', 'hj_user', '1y', 'analytics', false],
            ['_hjSession_*', 'Hotjar Ltd.', 'hj_session', '30min', 'analytics', false],
        ],
        'google_ads' => [
            ['_gcl_au', 'Google LLC', 'gcl', '3m', 'ads', true],
            ['IDE', 'Google LLC (doubleclick.net)', 'ide', '13m', 'ads', true],
            ['test_cookie', 'Google LLC (doubleclick.net)', 'gtest', '15min', 'ads', true],
        ],
        'meta_pixel' => [
            ['_fbp', 'Meta Platforms', 'fbp', '3m', 'ads', true],
            ['fr', 'Meta Platforms (facebook.com)', 'fr', '3m', 'ads', true],
        ],
        'linkedin' => [
            ['li_sugr', 'LinkedIn', 'li_sugr', '3m', 'ads', true],
            ['bcookie', 'LinkedIn', 'bcookie', '1y', 'ads', true],
            ['lidc', 'LinkedIn', 'lidc', '1d', 'tech3', true],
            ['UserMatchHistory', 'LinkedIn', 'umh', '30d', 'ads', true],
        ],
        'tiktok' => [
            ['_ttp', 'TikTok', 'ttp', '13m', 'ads', true],
        ],
        'youtube' => [
            ['YSC', 'Google LLC (YouTube)', 'ysc', 'session', 'analytics', true],
            ['VISITOR_INFO1_LIVE', 'Google LLC (YouTube)', 'visitor', '6m', 'ads', true],
        ],
        'maps' => [
            ['NID', 'Google LLC', 'nid', '6m', 'prefads', true],
        ],
    ];

    private array $d;
    private bool $es;
    private string $lang;
    private array $L;

    public function __construct(array $input)
    {
        $s = static fn ($k) => trim((string) ($input[$k] ?? ''));
        $this->d = [
            'lang'        => array_key_exists($s('lang'), self::LANGS) ? $s('lang') : 'ca',
            'owner_type'  => $s('owner_type') === 'autonomo' ? 'autonomo' : 'empresa',
            'name'        => $s('name'),
            'trade_name'  => $s('trade_name'),
            'nif'         => $s('nif'),
            'address'     => $s('address'),
            'email'       => $s('email'),
            'phone'       => $s('phone'),
            'website'     => $s('website'),
            'registry'    => $s('registry'),
            'dpo'         => $s('dpo'),
            'jurisdiction'=> $s('jurisdiction') === 'eu' ? 'eu' : 'es',
            'hosting'     => $s('hosting'),
            'email_tool'  => $s('email_tool'),
            'payment'     => $s('payment'),
            'other_processors' => $s('other_processors'),
            'cmp'         => array_key_exists($s('cmp'), self::CMPS) ? $s('cmp') : 'otro',
            'custom_cookies' => $s('custom_cookies'),
            'purposes'    => array_values(array_intersect((array) ($input['purposes'] ?? []), array_keys(self::PURPOSES))),
            'cookies'     => array_values(array_intersect((array) ($input['cookies'] ?? []), array_keys(self::COOKIE_SERVICES))),
            'ret_days'    => (int) ($s('ret_days') ?: 14),
            'ret_costs'   => $s('ret_costs') === 'shop' ? 'shop' : 'client',
            'ret_cancel'  => $s('ret_cancel') === 'no' ? 'no' : 'yes',
            'ret_exchange'=> $s('ret_exchange') === '1',
            'ret_address' => $s('ret_address'),
            'ret_email'   => $s('ret_email'),
            'ret_url'     => $s('ret_url'),
            'ret_exclusions' => array_values(array_intersect((array) ($input['ret_exclusions'] ?? []), array_keys(self::RETURN_EXCLUSIONS))),
        ];
        $this->es = $this->d['jurisdiction'] === 'es';
        $this->lang = $this->d['lang'];
        $this->L = require __DIR__ . '/legal/' . $this->lang . '.php';
    }

    public function lang(): string
    {
        return $this->lang;
    }

    /** Text traduït; amb arguments s'aplica sprintf. */
    private function t(string $key, mixed ...$args): mixed
    {
        $value = $this->L[$key] ?? $key;
        return $args ? vsprintf($value, $args) : $value;
    }

    public function slug(string $doc): string
    {
        return $this->t('slug_' . (in_array($doc, ['cookies', 'returns'], true) ? $doc : 'privacy'));
    }

    /** La Política de cancel·lació i devolucions només es genera per a botigues en línia. */
    public function isShop(): bool
    {
        return in_array('tienda', $this->d['purposes'], true);
    }

    public function errors(): array
    {
        $errors = [];
        foreach (['name' => 'Nom o raó social', 'nif' => 'NIF/CIF', 'address' => 'Domicili', 'email' => 'Correu de contacte', 'website' => 'Lloc web'] as $k => $label) {
            if ($this->d[$k] === '') {
                $errors[] = "El camp «{$label}» és obligatori.";
            }
        }
        if ($this->d['email'] !== '' && !filter_var($this->d['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'El correu de contacte no és vàlid.';
        }
        if ($this->d['dpo'] !== '' && !filter_var($this->d['dpo'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'El correu del delegat de protecció de dades no és vàlid.';
        }
        if ($this->isShop()) {
            if ($this->d['ret_days'] < 14 || $this->d['ret_days'] > 365) {
                $errors[] = 'El termini de desistiment ha de ser d’entre 14 (mínim legal) i 365 dies.';
            }
            if ($this->d['ret_email'] !== '' && !filter_var($this->d['ret_email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'El correu per a devolucions no és vàlid.';
            }
            if ($this->d['ret_url'] !== '' && !preg_match('#^https?://#i', $this->d['ret_url'])) {
                $errors[] = 'La URL de la funció de desistiment ha de començar per http:// o https://.';
            }
        }
        return $errors;
    }

    // -----------------------------------------------------------------------
    // Política de privadesa
    // -----------------------------------------------------------------------

    public function privacy(): string
    {
        $d = $this->d;
        $name = e($d['name']);
        $site = e($this->domain());
        $mail = $this->mailto($d['email']);

        $h = '<h1>' . $this->t('p_title') . '</h1>';
        $h .= '<p class="legal-updated">' . $this->t('updated', e($this->today())) . '</p>';
        $h .= '<p>' . $this->t('p_intro', $name, $site, $this->t($this->es ? 'law_es' : 'law_eu')) . '</p>';

        // Responsable
        $n = 1;
        $h .= "<h2>{$n}. " . $this->t('h_owner') . '</h2><ul>';
        $h .= '<li><strong>' . $this->t('f_owner') . $this->t('colon') . '</strong> ' . $name . ($d['trade_name'] !== '' ? ' («' . e($d['trade_name']) . '»)' : '') . '</li>';
        $h .= '<li><strong>' . $this->t($d['owner_type'] === 'empresa' ? 'f_cif' : 'f_nif') . $this->t('colon') . '</strong> ' . e($d['nif']) . '</li>';
        $h .= '<li><strong>' . $this->t('f_address') . $this->t('colon') . '</strong> ' . e($d['address']) . '</li>';
        $h .= '<li><strong>' . $this->t('f_email') . $this->t('colon') . '</strong> ' . $mail . '</li>';
        if ($d['phone'] !== '') {
            $h .= '<li><strong>' . $this->t('f_phone') . $this->t('colon') . '</strong> ' . e($d['phone']) . '</li>';
        }
        if ($d['registry'] !== '') {
            $h .= '<li><strong>' . $this->t('f_registry') . $this->t('colon') . '</strong> ' . e($d['registry']) . '</li>';
        }
        $h .= '<li><strong>' . $this->t('f_website') . $this->t('colon') . '</strong> ' . $site . '</li></ul>';
        $n++;

        if ($d['dpo'] !== '') {
            $h .= "<h2>{$n}. " . $this->t('h_dpo') . '</h2><p>' . $this->t('dpo', $this->mailto($d['dpo'])) . '</p>';
            $n++;
        }

        // Finalitats
        $h .= "<h2>{$n}. " . $this->t('h_purposes') . '</h2><p>' . $this->t('purposes_intro') . '</p>';
        $purposes = $d['purposes'] ?: ['contacto'];
        $repl = [
            '{lssi}'      => $this->es ? $this->t('lssi_short') : '',
            '{retention}' => $this->t($this->es ? 'retention_es' : 'retention_eu'),
        ];
        foreach ($purposes as $p) {
            [$title, $fin, $base, $data, $keep] = array_map(static fn ($s) => strtr($s, $repl), $this->L['purposes'][$p]);
            $h .= '<h3>' . $title . '</h3><ul>';
            $h .= '<li><strong>' . $this->t('f_purpose') . $this->t('colon') . '</strong> ' . $fin . '</li>';
            $h .= '<li><strong>' . $this->t('f_base') . $this->t('colon') . '</strong> ' . $base . '</li>';
            $h .= '<li><strong>' . $this->t('f_data') . $this->t('colon') . '</strong> ' . $data . '</li>';
            $h .= '<li><strong>' . $this->t('f_keep') . $this->t('colon') . '</strong> ' . $keep . '</li></ul>';
        }
        if ($d['cookies']) {
            $h .= '<h3>' . $this->t('h_nav') . '</h3><p>' . $this->t('nav', $this->t('url_cookies')) . '</p>';
        }
        $h .= '<p>' . $this->t('no_automated') . '</p>';
        $n++;

        // Destinataris
        $h .= "<h2>{$n}. " . $this->t('h_recipients') . '</h2><p>' . $this->t('recipients') . '</p>';
        if ($processors = $this->processors()) {
            $h .= '<p>' . $this->t('processors_intro') . '</p><ul>';
            foreach ($processors as [$label, $value]) {
                $h .= '<li><strong>' . $label . $this->t('colon') . '</strong> ' . $value . '</li>';
            }
            $h .= '</ul>';
        }
        $n++;

        // Transferències
        $h .= "<h2>{$n}. " . $this->t('h_transfers') . '</h2><p>' . $this->t($this->hasInternationalTransfers() ? 'transfers_yes' : 'transfers_no') . '</p>';
        $n++;

        // Drets
        $h .= "<h2>{$n}. " . $this->t('h_rights') . '</h2><p>' . $this->t('rights_intro') . '</p><ul>';
        foreach ($this->L['rights'] as $right) {
            $h .= '<li>' . $right . '</li>';
        }
        $h .= '</ul><p>' . $this->t('rights_how', $mail, e($d['address'])) . '</p>';
        $h .= '<p>' . $this->t('complaint', $this->t($this->es ? 'authority_es' : 'authority_eu')) . '</p>';
        $n++;

        $h .= "<h2>{$n}. " . $this->t('h_minors') . '</h2><p>' . $this->t('minors', $this->es ? 14 : 16) . '</p>';
        $n++;
        $h .= "<h2>{$n}. " . $this->t('h_accuracy') . '</h2><p>' . $this->t('accuracy') . '</p>';
        $n++;
        $h .= "<h2>{$n}. " . $this->t('h_security') . '</h2><p>' . $this->t('security') . '</p>';
        $n++;

        if (in_array('newsletter', $purposes, true)) {
            $h .= "<h2>{$n}. " . $this->t('h_marketing') . '</h2><p>' . $this->t('marketing', $this->es ? $this->t('lssi_clause') : '', $mail) . '</p>';
            $n++;
        }
        if (in_array('redes', $purposes, true)) {
            $h .= "<h2>{$n}. " . $this->t('h_social') . '</h2><p>' . $this->t('social', $name) . '</p>';
            $n++;
        }

        $h .= "<h2>{$n}. " . $this->t('h_changes') . '</h2><p>' . $this->t('changes') . '</p>';
        return $h;
    }

    private function processors(): array
    {
        $d = $this->d;
        $out = [];
        if ($d['hosting'] !== '') {
            $out[] = [$this->t('proc_hosting'), e($d['hosting'])];
        }
        if ($d['email_tool'] !== '') {
            $out[] = [$this->t('proc_email'), e($d['email_tool'])];
        }
        if ($d['payment'] !== '') {
            $out[] = [$this->t('proc_payment'), e($d['payment'])];
        }
        foreach (preg_split('/\r\n|\n/', $d['other_processors']) ?: [] as $line) {
            if (trim($line) !== '') {
                $out[] = [$this->t('proc_other'), e(trim($line))];
            }
        }
        $providers = [];
        foreach ($this->cookieRows() as $row) {
            if ($row['third']) {
                $providers[preg_replace('/\s*\(.*\)/', '', $row['owner'])] = true;
            }
        }
        if ($providers) {
            $out[] = [$this->t('proc_cookies'), e(implode(', ', array_keys($providers))) . ' ' . $this->t('proc_cookies_note')];
        }
        return $out;
    }

    private function hasInternationalTransfers(): bool
    {
        $text = strtolower($this->d['hosting'] . ' ' . $this->d['email_tool'] . ' ' . $this->d['payment'] . ' ' . $this->d['other_processors']);
        if (preg_match('/mailchimp|google|gmail|workspace|microsoft|outlook|aws|amazon|stripe|paypal|hubspot|meta|facebook|sendgrid|mailgun|zoom|slack|dropbox|shopify|squarespace|wix|cloudflare|vercel|netlify|digitalocean|activecampaign|klaviyo|calendly|typeform|zapier/', $text)) {
            return true;
        }
        foreach ($this->cookieRows() as $row) {
            if ($row['us']) {
                return true;
            }
        }
        return false;
    }

    // -----------------------------------------------------------------------
    // Política de cookies
    // -----------------------------------------------------------------------

    /** @return array<int, array{name:string, owner:string, purpose:string, duration:string, type:string, exempt:bool, third:bool, us:bool}> */
    public function cookieRows(): array
    {
        $own = $this->t('own');
        $rows = [[
            'name' => self::CMPS[$this->d['cmp']][1], 'owner' => $own, 'purpose' => $this->L['cookie_purposes']['consent'],
            'duration' => $this->L['dur']['1y'], 'type' => $this->L['types']['tech'], 'exempt' => true, 'third' => false, 'us' => false,
        ]];
        foreach ($this->d['cookies'] as $svc) {
            foreach (self::COOKIES[$svc] ?? [] as [$name, $owner, $purpose, $dur, $type, $us]) {
                $rows[] = [
                    'name' => $name, 'owner' => $owner ?? $own, 'purpose' => $this->L['cookie_purposes'][$purpose],
                    'duration' => $this->L['dur'][$dur], 'type' => $this->L['types'][$type],
                    'exempt' => in_array($type, ['tech', 'security'], true), 'third' => $owner !== null, 'us' => $us,
                ];
            }
        }
        foreach (preg_split('/\r\n|\n/', $this->d['custom_cookies']) ?: [] as $line) {
            $p = array_map('trim', explode('|', $line));
            if (count($p) >= 3 && $p[0] !== '') {
                $type = $p[4] ?? $this->L['types']['tech'];
                $rows[] = [
                    'name' => $p[0], 'owner' => $p[1] ?: $own, 'purpose' => $p[2], 'duration' => $p[3] ?? '—', 'type' => $type,
                    'exempt' => (bool) preg_match('/t[èée]cni|technical|technique|necess|nécess/iu', $type),
                    'third' => $p[1] !== '' && mb_strtolower($p[1]) !== mb_strtolower($own), 'us' => false,
                ];
            }
        }
        return $rows;
    }

    public function cookies(): string
    {
        $d = $this->d;
        $name = e($d['name']);
        $site = e($this->domain());
        $mail = $this->mailto($d['email']);
        $cmpName = self::CMPS[$d['cmp']][0] ?? $this->t('cmp_generic');
        $rows = $this->cookieRows();
        $types = array_unique(array_map(static fn ($r) => mb_strtolower($r['type']), $rows));
        $needsConsent = (bool) array_filter($rows, static fn ($r) => !$r['exempt']);

        $h = '<h1>' . $this->t('c_title') . '</h1>';
        $h .= '<p class="legal-updated">' . $this->t('updated', e($this->today())) . '</p>';
        $h .= '<p>' . $this->t('c_intro', $this->t($this->es ? 'c_law_es' : 'c_law_eu'), $name, $site) . '</p>';

        $h .= '<h2>1. ' . $this->t('h_what') . '</h2><p>' . $this->t('what') . '</p>';

        $h .= '<h2>2. ' . $this->t('h_types') . '</h2>';
        $h .= '<p><strong>' . $this->t('types_owner_label') . '</strong></p><ul><li>' . implode('</li><li>', $this->L['types_owner']) . '</li></ul>';
        $h .= '<p><strong>' . $this->t('types_purpose_label') . '</strong></p><ul><li>' . implode('</li><li>', $this->L['types_purpose']) . '</li></ul>';
        $h .= '<p>' . $this->t('types_duration') . '</p>';

        $h .= '<h2>3. ' . $this->t('h_used') . '</h2><p>' . $this->t('used_intro', e(implode(', ', $types))) . '</p>';
        $h .= '<div class="legal-table"><table><thead><tr><th>' . implode('</th><th>', $this->L['th']) . '</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $h .= '<tr><td><code>' . e($r['name']) . '</code></td><td>' . e($r['owner']) . '</td><td>' . e($r['purpose']) . '</td><td>' . e($r['duration']) . '</td><td>' . e($r['type']) . '</td></tr>';
        }
        $h .= '</tbody></table></div><p>' . $this->t('third_party_note') . '</p>';

        $h .= '<h2>4. ' . $this->t('h_consent') . '</h2>';
        $h .= $needsConsent
            ? '<p>' . $this->t('consent_yes', e($cmpName)) . '</p><p>' . $this->t('consent_keep') . '</p>'
            : '<p>' . $this->t('consent_no') . '</p>';

        $h .= '<h2>5. ' . $this->t('h_manage') . '</h2>';
        if ($needsConsent) {
            $h .= '<p>' . $this->t('manage_link') . '</p>';
        }
        $h .= '<p>' . $this->t('manage_browser') . '</p><ul>';
        $browsers = [
            'Google Chrome'   => 'https://support.google.com/chrome/answer/95647?hl=' . $this->lang,
            'Mozilla Firefox' => 'https://support.mozilla.org/kb/clear-cookies-and-site-data-firefox',
            'Safari'          => 'https://support.apple.com/guide/safari/sfri11471/mac',
            'Microsoft Edge'  => 'https://support.microsoft.com/microsoft-edge/delete-cookies-in-microsoft-edge-63947406-40ac-c3b8-57b9-2a946a29ae09',
        ];
        foreach ($browsers as $label => $url) {
            $h .= '<li><a href="' . e($url) . '" rel="noopener">' . $label . '</a></li>';
        }
        $h .= '</ul><p>' . $this->t('yoc') . '</p>';

        $n = 6;
        if (array_filter($rows, static fn ($r) => $r['us'])) {
            $h .= "<h2>{$n}. " . $this->t('h_transfers') . '</h2><p>' . $this->t('transfers_c', $this->t('url_privacy')) . '</p>';
            $n++;
        }
        $h .= "<h2>{$n}. " . $this->t('h_updates') . '</h2><p>' . $this->t('updates', $mail) . '</p>';
        return $h;
    }


    // -----------------------------------------------------------------------
    // Política de cancel·lació, desistiment i devolucions (botigues en línia)
    // -----------------------------------------------------------------------

    public function returns(): string
    {
        $d = $this->d;
        $name = e($d['name']);
        $site = e($this->domain());
        $retEmail = $d['ret_email'] !== '' ? $d['ret_email'] : $d['email'];
        $mail = $this->mailto($retEmail);
        $address = e($d['ret_address'] !== '' ? $d['ret_address'] : $d['address']);
        $days = $d['ret_days'];

        $h = '<h1>' . $this->t('r_title') . '</h1>';
        $h .= '<p class="legal-updated">' . $this->t('updated', e($this->today())) . '</p>';
        $h .= '<p>' . $this->t('r_intro', $name, $site, $this->t($this->es ? 'r_law_es' : 'r_law_eu')) . '</p>';

        $n = 1;
        $h .= "<h2>{$n}. " . $this->t('r_h_seller') . '</h2><ul>';
        $h .= '<li><strong>' . $this->t('f_owner') . $this->t('colon') . '</strong> ' . $name . ($d['trade_name'] !== '' ? ' («' . e($d['trade_name']) . '»)' : '') . '</li>';
        $h .= '<li><strong>' . $this->t($d['owner_type'] === 'empresa' ? 'f_cif' : 'f_nif') . $this->t('colon') . '</strong> ' . e($d['nif']) . '</li>';
        $h .= '<li><strong>' . $this->t('f_address') . $this->t('colon') . '</strong> ' . e($d['address']) . '</li>';
        $h .= '<li><strong>' . $this->t('f_email') . $this->t('colon') . '</strong> ' . $mail . '</li>';
        if ($d['phone'] !== '') {
            $h .= '<li><strong>' . $this->t('f_phone') . $this->t('colon') . '</strong> ' . e($d['phone']) . '</li>';
        }
        $h .= '</ul><p>' . $this->t('r_scope') . '</p>';
        $n++;

        $h .= "<h2>{$n}. " . $this->t('r_h_cancel') . '</h2><p>' . $this->t($d['ret_cancel'] === 'yes' ? 'r_cancel_yes' : 'r_cancel_no', $mail) . '</p>';
        $n++;

        $h .= "<h2>{$n}. " . $this->t('r_h_withdrawal') . '</h2><p>' . $this->t('r_withdrawal', $days) . '</p><ul>';
        foreach ($this->L['r_withdrawal_start'] as $item) {
            $h .= '<li>' . $item . '</li>';
        }
        $h .= '</ul>';
        if ($days > 14) {
            $h .= '<p>' . $this->t('r_withdrawal_extended', $days) . '</p>';
        }
        $n++;

        $h .= "<h2>{$n}. " . $this->t('r_h_how') . '</h2><p>' . $this->t('r_how_intro') . '</p><ul>';
        if ($d['ret_url'] !== '') {
            $h .= '<li>' . $this->t('r_how_url', e($d['ret_url'])) . '</li>';
        }
        $h .= '<li>' . $this->t('r_how_email', $mail) . '</li>';
        $h .= '<li>' . $this->t('r_how_post', $address) . '</li></ul>';
        $h .= '<p>' . $this->t('r_how_note') . '</p>';
        $n++;

        $h .= "<h2>{$n}. " . $this->t('r_h_return') . '</h2><p>' . $this->t('r_return', $address) . '</p>';
        $h .= '<p>' . $this->t($d['ret_costs'] === 'shop' ? 'r_costs_shop' : 'r_costs_client') . '</p>';
        $h .= '<p>' . $this->t('r_condition') . '</p>';
        $n++;

        $h .= "<h2>{$n}. " . $this->t('r_h_refund') . '</h2><p>' . $this->t('r_refund') . '</p>';
        $n++;

        if ($d['ret_exclusions']) {
            $h .= "<h2>{$n}. " . $this->t('r_h_exceptions') . '</h2><p>' . $this->t('r_exceptions_intro') . '</p><ul>';
            foreach ($d['ret_exclusions'] as $ex) {
                $h .= '<li>' . $this->L['r_exclusions'][$ex] . '</li>';
            }
            $h .= '</ul>';
            $n++;
        }

        if ($d['ret_exchange']) {
            $h .= "<h2>{$n}. " . $this->t('r_h_exchange') . '</h2><p>' . $this->t('r_exchange', $mail) . '</p>';
            $n++;
        }

        $h .= "<h2>{$n}. " . $this->t('r_h_warranty') . '</h2><p>' . $this->t($this->es ? 'r_warranty_es' : 'r_warranty_eu') . '</p>';
        $h .= '<p>' . $this->t('r_warranty_how', $mail) . '</p>';
        $n++;

        $h .= "<h2>{$n}. " . $this->t('r_h_disputes') . '</h2><p>' . $this->t('r_disputes', $mail) . '</p>';
        $h .= '<p>' . $this->t($this->es ? 'r_disputes_es' : 'r_disputes_eu') . '</p>';
        $n++;

        // Model de formulari de desistiment (annex B del TRLGDCU / annex I.B de la Directiva 2011/83/UE)
        $h .= "<h2>{$n}. " . $this->t('r_h_form') . '</h2><p>' . $this->t('r_form_intro') . '</p>';
        $h .= '<blockquote class="legal-form-model"><p>' . $this->t('r_form_to', $name, $address, e($retEmail)) . '</p><ul>';
        foreach ($this->L['r_form_lines'] as $line) {
            $h .= '<li>' . $line . '</li>';
        }
        $h .= '</ul><p><em>' . $this->t('r_form_strike') . '</em></p></blockquote>';
        return $h;
    }

    // -----------------------------------------------------------------------

    private function mailto(string $email): string
    {
        $e = e($email);
        return "<a href=\"mailto:{$e}\">{$e}</a>";
    }

    private function domain(): string
    {
        $w = $this->d['website'];
        $host = parse_url(preg_match('#^https?://#i', $w) ? $w : 'https://' . $w, PHP_URL_HOST);
        return $host ?: $w;
    }

    private function today(): string
    {
        $day = (int) date('j');
        $month = $this->L['months'][(int) date('n') - 1];
        $year = date('Y');
        return match ($this->lang) {
            'ca'    => $day . (preg_match('/^[aeiouà]/u', $month) ? " d’{$month}" : " de {$month}") . " de {$year}",
            'en'    => "{$day} " . ucfirst($month) . " {$year}",
            'fr'    => ($day === 1 ? '1er' : $day) . " {$month} {$year}",
            default => "{$day} de {$month} de {$year}",
        };
    }
}
