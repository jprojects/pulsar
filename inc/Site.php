<?php
declare(strict_types=1);

/** Recursos a nivell de domini (robots.txt, sitemap, llms.txt) amb memòria cau per petició. */
final class Site
{
    private static array $cache = [];

    /** @return array{exists:bool, robots:?Robots, status:int} */
    public static function robots(string $origin): array
    {
        return self::$cache['robots'][$origin] ??= (static function () use ($origin) {
            $r = Http::get($origin . '/robots.txt');
            $exists = $r['ok'] && $r['status'] === 200 && !str_contains($r['content_type'], 'html');
            return [
                'exists' => $exists,
                'robots' => $exists ? new Robots($r['body']) : null,
                'status' => $r['status'],
            ];
        })();
    }

    /** @return array{exists:bool, url:?string, urls:int} */
    public static function sitemap(string $origin): array
    {
        return self::$cache['sitemap'][$origin] ??= (static function () use ($origin) {
            $robots = self::robots($origin)['robots'];
            $candidates = $robots ? array_slice($robots->sitemaps, 0, 3) : [];
            $candidates[] = $origin . '/sitemap.xml';
            $candidates[] = $origin . '/sitemap_index.xml';
            foreach (array_unique($candidates) as $url) {
                $r = Http::get($url);
                if ($r['ok'] && $r['status'] === 200 && preg_match('/<(urlset|sitemapindex)\b/i', $r['body'])) {
                    return ['exists' => true, 'url' => $url, 'urls' => preg_match_all('/<loc>/i', $r['body'])];
                }
            }
            return ['exists' => false, 'url' => null, 'urls' => 0];
        })();
    }

    /** @return array{exists:bool, url:string, body:string} */
    public static function llmsTxt(string $origin): array
    {
        return self::$cache['llms'][$origin] ??= (static function () use ($origin) {
            $url = $origin . '/llms.txt';
            $r = Http::get($url);
            $exists = $r['ok'] && $r['status'] === 200 && !str_contains($r['content_type'], 'html') && trim($r['body']) !== '';
            return ['exists' => $exists, 'url' => $url, 'body' => $exists ? mb_substr($r['body'], 0, 4000) : ''];
        })();
    }
}
