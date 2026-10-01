<?php

namespace App\Support\Net;

/** The subset of robots.txt that matters for a few page reads: Allow/Disallow for our agent or "*". */
final class RobotsTxt
{
    /** @param array<int, array{allow: bool, path: string}> $rules */
    private function __construct(private readonly array $rules) {}

    public static function parse(string $content, string $agent): self
    {
        $groups = [];
        $agents = [];
        $readingAgents = false;

        foreach (preg_split('/\R/', $content) as $line) {
            $line = trim(preg_replace('/#.*/', '', $line));
            if (!str_contains($line, ':')) {
                continue;
            }

            [$field, $value] = array_map('trim', explode(':', $line, 2));
            $field = strtolower($field);

            if ($field === 'user-agent') {
                $agents = $readingAgents ? [...$agents, strtolower($value)] : [strtolower($value)];
                $readingAgents = true;
                continue;
            }

            $readingAgents = false;
            if (in_array($field, ['allow', 'disallow'], true)) {
                foreach ($agents as $name) {
                    $groups[$name][] = ['allow' => $field === 'allow', 'path' => $value];
                }
            }
        }

        // Our own group wins over "*", as crawlers do.
        $own = $groups[strtolower($agent)] ?? null;

        return new self($own ?? $groups['*'] ?? []);
    }

    /** Longest matching rule decides; an empty Disallow allows everything. */
    public function allows(string $path): bool
    {
        $best = null;

        foreach ($this->rules as $rule) {
            if ($rule['path'] === '') {
                continue;
            }

            $pattern = '#^' . str_replace(['\*', '\$'], ['.*', '$'], preg_quote($rule['path'], '#')) . '#';
            if (preg_match($pattern, $path) && ($best === null || strlen($rule['path']) > strlen($best['path']))) {
                $best = $rule;
            }
        }

        return $best === null || $best['allow'];
    }
}
