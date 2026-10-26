<?php
declare(strict_types=1);

/**
 * Analitzador HTML lleuger basat en expressions regulars (no requereix ext-dom).
 * Suficient per extreure els senyals SEO/GEO que necessitem.
 */
final class Html
{
    private string $html;
    private string $head;

    public function __construct(string $html)
    {
        if (!mb_check_encoding($html, 'UTF-8')) {
            $html = mb_convert_encoding($html, 'UTF-8', 'ISO-8859-1');
        }
        $this->html = preg_replace('/<!--.*?-->/s', '', $html) ?? $html;
        $this->head = preg_match('#<head\b[^>]*>(.*?)</head>#is', $this->html, $m) ? $m[1] : '';
    }

    public function raw(): string
    {
        return $this->html;
    }

    public function head(): string
    {
        return $this->head;
    }

    public static function attrs(string $attrString): array
    {
        $attrs = [];
        preg_match_all('/([a-zA-Z_:@][-a-zA-Z0-9_:.@]*)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+)))?/s', $attrString, $m, PREG_SET_ORDER);
        foreach ($m as $a) {
            $value = $a[2] ?? '';
            if (($a[3] ?? '') !== '') {
                $value = $a[3];
            } elseif (($a[4] ?? '') !== '') {
                $value = $a[4];
            }
            $attrs[strtolower($a[1])] = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        return $attrs;
    }

    /** Etiquetas de apertura (atributos) de un elemento. */
    public function tags(string $name, ?string $scope = null): array
    {
        preg_match_all('/<' . $name . '\b([^>]*)>/i', $scope ?? $this->html, $m);
        return array_map([self::class, 'attrs'], $m[1]);
    }

    /** Elements amb contingut: [['attrs'=>[], 'inner'=>'', 'text'=>'']]. */
    public function elements(string $name, ?string $scope = null): array
    {
        preg_match_all('/<' . $name . '\b([^>]*)>(.*?)<\/' . $name . '\s*>/is', $scope ?? $this->html, $m, PREG_SET_ORDER);
        return array_map(static fn ($e) => [
            'attrs' => self::attrs($e[1]),
            'inner' => $e[2],
            'text'  => self::toText($e[2]),
        ], $m);
    }

    public function title(): ?string
    {
        $t = $this->elements('title', $this->head ?: $this->html);
        return $t ? $t[0]['text'] : null;
    }

    /** Totes les meta com a llista d’atributs. */
    public function metas(): array
    {
        return $this->tags('meta');
    }

    /** Cerca meta per name o property (sense distingir majúscules). Retorna tots els valors. */
    public function meta(string $key): array
    {
        $out = [];
        foreach ($this->metas() as $a) {
            $n = strtolower($a['name'] ?? $a['property'] ?? $a['http-equiv'] ?? '');
            if ($n === strtolower($key)) {
                $out[] = trim($a['content'] ?? '');
            }
        }
        return $out;
    }

    public function firstMeta(string $key): ?string
    {
        return $this->meta($key)[0] ?? null;
    }

    /** <link rel="..."> que contienen el rel indicado. */
    public function links(string $rel): array
    {
        return array_values(array_filter($this->tags('link'), static function ($a) use ($rel) {
            $rels = preg_split('/\s+/', strtolower($a['rel'] ?? '')) ?: [];
            return in_array(strtolower($rel), $rels, true);
        }));
    }

    public function htmlAttrs(): array
    {
        return preg_match('/<html\b([^>]*)>/i', $this->html, $m) ? self::attrs($m[1]) : [];
    }

    /** Encabezados en orden de aparición: [['level'=>1,'text'=>'…']]. */
    public function headings(): array
    {
        preg_match_all('/<h([1-6])\b[^>]*>(.*?)<\/h\1\s*>/is', $this->html, $m, PREG_SET_ORDER);
        return array_map(static fn ($h) => ['level' => (int) $h[1], 'text' => self::toText($h[2])], $m);
    }

    public function body(): string
    {
        return preg_match('#<body\b[^>]*>(.*)</body>#is', $this->html, $m) ? $m[1] : $this->html;
    }

    /** Text visible del body. */
    public function text(): string
    {
        return self::toText($this->body());
    }

    public function wordCount(): int
    {
        $t = $this->text();
        return $t === '' ? 0 : count(preg_split('/\s+/u', $t) ?: []);
    }

    /** Bloques JSON-LD decodificados (aplanados, incluyendo @graph). */
    public function jsonLd(): array
    {
        $items = [];
        foreach ($this->elements('script') as $s) {
            if (stripos($s['attrs']['type'] ?? '', 'ld+json') === false) {
                continue;
            }
            $data = json_decode(trim(html_entity_decode($s['inner'])), true);
            if (!is_array($data)) {
                continue;
            }
            $stack = array_is_list($data) ? $data : [$data];
            while ($stack) {
                $node = array_shift($stack);
                if (!is_array($node)) {
                    continue;
                }
                if (isset($node['@graph']) && is_array($node['@graph'])) {
                    array_push($stack, ...$node['@graph']);
                }
                if (isset($node['@type'])) {
                    $items[] = $node;
                }
            }
        }
        return $items;
    }

    /** Tipos schema.org presentes (JSON-LD + microdatos). */
    public function schemaTypes(): array
    {
        $types = [];
        foreach ($this->jsonLd() as $node) {
            foreach ((array) $node['@type'] as $t) {
                $types[] = (string) $t;
            }
        }
        preg_match_all('/itemtype\s*=\s*["\']https?:\/\/schema\.org\/([A-Za-z]+)/i', $this->html, $m);
        return array_values(array_unique(array_merge($types, $m[1])));
    }

    public static function toText(string $html): string
    {
        $html = preg_replace('#<(script|style|noscript|svg|template|iframe)\b[^>]*>.*?</\1\s*>#is', ' ', $html) ?? $html;
        $html = preg_replace('#<(br|/p|/div|/li|/h[1-6]|/tr|/section|/article)\b[^>]*>#i', "$0 ", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }
}
