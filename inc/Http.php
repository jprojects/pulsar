<?php
declare(strict_types=1);

/**
 * Client HTTP segur: segueix les redireccions manualment validant cada salt
 * (sense IP privades, llevat que es permeti a la configuració) i fixa la IP
 * resolta per evitar el DNS rebinding.
 */
final class Http
{
    private const MAX_BYTES = 6 * 1024 * 1024;
    private const MAX_REDIRECTS = 6;

    /** @var array<string, array> memòria cau per petició */
    private static array $cache = [];

    public static function get(string $url, ?string $userAgent = null, bool $useCache = true): array
    {
        $ua = $userAgent ?? (string) config('user_agent');
        $key = $ua . '|' . $url;
        if ($useCache && isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $result = [
            'ok' => false, 'error' => null, 'status' => 0, 'url' => $url, 'redirects' => [],
            'headers' => [], 'body' => '', 'time' => 0.0, 'ttfb' => 0.0, 'size' => 0,
            'content_type' => '', 'ssl_error' => false,
        ];

        $current = $url;
        $start = microtime(true);
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $parts = parse_url($current);
            $scheme = strtolower($parts['scheme'] ?? '');
            $host = $parts['host'] ?? '';
            if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
                $result['error'] = 'URL no vàlida: ' . $current;
                break;
            }
            $ip = self::resolve($host);
            if ($ip === null) {
                $result['error'] = 'No s’ha pogut resoldre el domini ' . $host;
                break;
            }
            if (!config('allow_private_hosts', false) && !self::isPublicIp($ip)) {
                $result['error'] = 'Adreça no permesa (xarxa privada o reservada): ' . $host;
                break;
            }
            $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);

            $headers = [];
            $body = '';
            $ch = curl_init($current);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_TIMEOUT        => (int) config('http_timeout', 15),
                CURLOPT_USERAGENT      => $ua,
                CURLOPT_ENCODING       => '',
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_RESOLVE        => [$host . ':' . $port . ':' . (str_contains($ip, ':') ? '[' . $ip . ']' : $ip)],
                CURLOPT_HTTPHEADER     => [
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language: es-ES,es;q=0.9,en;q=0.8',
                ],
                CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$headers): int {
                    $len = strlen($line);
                    if (preg_match('#^HTTP/\S+\s+\d+#', $line)) {
                        $headers = [];
                    } elseif (str_contains($line, ':')) {
                        [$name, $value] = explode(':', $line, 2);
                        $headers[strtolower(trim($name))] = trim($value);
                    }
                    return $len;
                },
                CURLOPT_WRITEFUNCTION  => static function ($ch, string $chunk) use (&$body): int {
                    $body .= $chunk;
                    return strlen($body) > self::MAX_BYTES ? 0 : strlen($chunk);
                },
            ]);
            curl_exec($ch);
            $errno = curl_errno($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $ttfb = (float) curl_getinfo($ch, CURLINFO_STARTTRANSFER_TIME);
            $error = curl_error($ch);
            curl_close($ch);

            if ($errno && !($errno === CURLE_WRITE_ERROR && $body !== '')) {
                $result['ssl_error'] = in_array($errno, [CURLE_SSL_CACERT, CURLE_SSL_CERTPROBLEM, CURLE_SSL_CONNECT_ERROR, 60, 51], true);
                $result['error'] = $result['ssl_error']
                    ? 'Error de certificat SSL: ' . $error
                    : 'Error de connexió: ' . $error;
                break;
            }

            if ($status >= 300 && $status < 400 && isset($headers['location'])) {
                $result['redirects'][] = ['url' => $current, 'status' => $status];
                $current = self::absolute($current, $headers['location']);
                continue;
            }

            $result['ok'] = true;
            $result['status'] = $status;
            $result['url'] = $current;
            $result['headers'] = $headers;
            $result['body'] = $body;
            $result['size'] = strlen($body);
            $result['ttfb'] = $ttfb;
            $result['content_type'] = strtolower($headers['content-type'] ?? '');
            break;
        }

        if (!$result['ok'] && $result['error'] === null) {
            $result['error'] = 'Massa redireccions';
        }
        $result['time'] = microtime(true) - $start;

        return self::$cache[$key] = $result;
    }

    private static function resolve(string $host): ?string
    {
        $host = trim($host, '[]');
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $host;
        }
        $ips = @gethostbynamel($host);
        if ($ips) {
            return $ips[0];
        }
        $records = @dns_get_record($host, DNS_AAAA);
        return $records[0]['ipv6'] ?? null;
    }

    private static function isPublicIp(string $ip): bool
    {
        return (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    /** Converteix una URL relativa en absoluta respecte a $base. */
    public static function absolute(string $base, string $rel): string
    {
        $rel = trim(html_entity_decode($rel, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($rel === '') {
            return $base;
        }
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $rel)) {
            return $rel;
        }
        $b = parse_url($base);
        $scheme = $b['scheme'] ?? 'https';
        $hostPart = ($b['host'] ?? '') . (isset($b['port']) ? ':' . $b['port'] : '');
        if (str_starts_with($rel, '//')) {
            return $scheme . ':' . $rel;
        }
        if ($rel[0] === '#') {
            return preg_replace('/#.*$/', '', $base) . $rel;
        }
        if ($rel[0] === '?') {
            return $scheme . '://' . $hostPart . ($b['path'] ?? '/') . $rel;
        }
        if ($rel[0] === '/') {
            $path = $rel;
        } else {
            $dir = preg_replace('#/[^/]*$#', '/', $b['path'] ?? '/');
            $path = $dir . $rel;
        }
        // Resoldre ./ i ../
        $query = '';
        if (($q = strpos($path, '?')) !== false) {
            $query = substr($path, $q);
            $path = substr($path, 0, $q);
        }
        $segments = [];
        foreach (explode('/', $path) as $seg) {
            if ($seg === '..') {
                array_pop($segments);
            } elseif ($seg !== '.') {
                $segments[] = $seg;
            }
        }
        $path = '/' . ltrim(implode('/', $segments), '/');
        return $scheme . '://' . $hostPart . $path . $query;
    }

    public static function origin(string $url): string
    {
        $p = parse_url($url);
        return ($p['scheme'] ?? 'https') . '://' . ($p['host'] ?? '') . (isset($p['port']) ? ':' . $p['port'] : '');
    }
}
