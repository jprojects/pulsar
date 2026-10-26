<?php
declare(strict_types=1);

/** Intèrpret senzill de robots.txt (grups per user-agent, Allow/Disallow, comodins). */
final class Robots
{
    /** @var array<int, array{agents: string[], rules: array<int, array{type:string, path:string}>}> */
    private array $groups = [];
    public array $sitemaps = [];

    public function __construct(public readonly string $content)
    {
        $current = null;
        $lastWasAgent = false;
        foreach (preg_split('/\r\n|\r|\n/', $content) ?: [] as $line) {
            $line = trim(preg_replace('/#.*$/', '', $line) ?? '');
            if ($line === '' || !str_contains($line, ':')) {
                continue;
            }
            [$field, $value] = array_map('trim', explode(':', $line, 2));
            $field = strtolower($field);

            if ($field === 'sitemap') {
                $this->sitemaps[] = $value;
                continue;
            }
            if ($field === 'user-agent') {
                if (!$lastWasAgent || $current === null) {
                    $this->groups[] = ['agents' => [], 'rules' => []];
                    $current = array_key_last($this->groups);
                }
                $this->groups[$current]['agents'][] = strtolower($value);
                $lastWasAgent = true;
                continue;
            }
            $lastWasAgent = false;
            if ($current !== null && in_array($field, ['allow', 'disallow'], true)) {
                $this->groups[$current]['rules'][] = ['type' => $field, 'path' => $value];
            }
        }
    }

    /** ¿Tiene el agente un grupo propio (no solo el comodín *)? */
    public function hasSpecificGroup(string $agent): bool
    {
        return $this->groupFor($agent, false) !== null;
    }

    private function groupFor(string $agent, bool $fallback = true): ?array
    {
        $agent = strtolower($agent);
        $best = null;
        $bestLen = -1;
        foreach ($this->groups as $g) {
            foreach ($g['agents'] as $a) {
                if ($a !== '*' && str_contains($agent, $a) && strlen($a) > $bestLen) {
                    $best = $g;
                    $bestLen = strlen($a);
                }
            }
        }
        if ($best === null && $fallback) {
            $rules = [];
            $found = false;
            foreach ($this->groups as $g) {
                if (in_array('*', $g['agents'], true)) {
                    $rules = array_merge($rules, $g['rules']);
                    $found = true;
                }
            }
            return $found ? ['agents' => ['*'], 'rules' => $rules] : null;
        }
        return $best;
    }

    public function isAllowed(string $agent, string $path): bool
    {
        $group = $this->groupFor($agent);
        if ($group === null) {
            return true;
        }
        $path = $path === '' ? '/' : $path;
        $match = null;
        $matchLen = -1;
        foreach ($group['rules'] as $rule) {
            if ($rule['path'] === '') {
                continue; // "Disallow:" vacío = permitir todo
            }
            $regex = '#^' . str_replace(['\*', '\$'], ['.*', '$'], preg_quote($rule['path'], '#')) . '#';
            if (preg_match($regex, $path)) {
                $len = strlen($rule['path']);
                if ($len > $matchLen || ($len === $matchLen && $rule['type'] === 'allow')) {
                    $match = $rule['type'];
                    $matchLen = $len;
                }
            }
        }
        return $match !== 'disallow';
    }
}
