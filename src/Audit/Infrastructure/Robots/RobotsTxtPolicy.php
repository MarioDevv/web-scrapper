<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Infrastructure\Robots;

use SeoSpider\Audit\Domain\Model\HttpClient;
use SeoSpider\Audit\Domain\Model\HttpRequestFailed;
use SeoSpider\Audit\Domain\Model\RobotsPolicy;
use SeoSpider\Audit\Domain\Model\Url;

final class RobotsTxtPolicy implements RobotsPolicy
{
    private const string USER_AGENT = 'SeoSpider';

    /** @var array{allow: string[], disallow: string[]} */
    private array $rules = ['allow' => [], 'disallow' => []];

    private ?float $delay = null;

    /** @var Url[] */
    private array $sitemaps = [];

    private bool $loaded = false;
    private bool $blocked = false;

    public function __construct(
        private readonly HttpClient $httpClient,
    )
    {
    }

    public function load(Url $baseUrl): void
    {
        $this->rules = ['allow' => [], 'disallow' => []];
        $this->delay = null;
        $this->sitemaps = [];
        $this->loaded = true;
        $this->blocked = false;

        $robotsUrl = $baseUrl->withPath('/robots.txt')->withQuery(null)->withoutFragment();

        try {
            $response = $this->httpClient->get($robotsUrl, null);
        } catch (HttpRequestFailed) {
            $this->blocked = true;
            return;
        }

        $statusCode = $response->statusCode()->code();

        if ($statusCode >= 500) {
            $this->blocked = true;
            return;
        }

        if ($statusCode >= 400) {
            return;
        }

        $body = $response->body();
        if ($body === null || $body === '') {
            return;
        }

        $this->parse($body);
    }

    public function isAllowed(Url $url): bool
    {
        if (!$this->loaded) {
            return true;
        }

        if ($this->blocked) {
            return false;
        }

        $path = $url->path();
        if ($url->query() !== null) {
            $path .= '?' . $url->query();
        }

        // Find the longest matching Allow and Disallow
        $longestAllow = $this->longestMatch($path, $this->rules['allow']);
        $longestDisallow = $this->longestMatch($path, $this->rules['disallow']);

        if ($longestDisallow === 0 && $longestAllow === 0) {
            return true; // No rules match
        }

        // Longer match wins. If equal length, Allow wins (Google spec).
        return $longestAllow >= $longestDisallow;
    }

    public function crawlDelay(): ?float
    {
        return $this->delay;
    }

    /** @return Url[] */
    public function sitemapUrls(): array
    {
        return $this->sitemaps;
    }

    private function parse(string $content): void
    {
        $lines = preg_split('/\r?\n/', $content);
        if ($lines === false) {
            return;
        }

        $currentAgents = [];
        $isRelevantGroup = false;

        foreach ($lines as $line) {
            // Remove comments
            $commentPos = strpos($line, '#');
            if ($commentPos !== false) {
                $line = substr($line, 0, $commentPos);
            }

            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $colonPos = strpos($line, ':');
            if ($colonPos === false) {
                continue;
            }

            $directive = strtolower(trim(substr($line, 0, $colonPos)));
            $value = trim(substr($line, $colonPos + 1));

            if ($value === '') {
                continue;
            }

            // Sitemaps are global, not per user-agent
            if ($directive === 'sitemap') {
                $sitemapUrl = Url::tryFromString($value);
                if ($sitemapUrl !== null) {
                    $this->sitemaps[] = $sitemapUrl;
                }
                continue;
            }

            if ($directive === 'user-agent') {
                // Starting a new group
                if ($isRelevantGroup && count($currentAgents) > 0) {
                    // We already found our group, stop parsing agents
                    // (but keep going in case there's a more specific match)
                }

                $currentAgents[] = strtolower($value);
                $isRelevantGroup = $this->matchesUserAgent($currentAgents);
                continue;
            }

            // If we hit a directive that isn't user-agent, the agent list is complete
            if (!$isRelevantGroup) {
                // Reset for next group
                if ($directive !== 'disallow' && $directive !== 'allow' && $directive !== 'crawl-delay') {
                    continue;
                }
                // This group doesn't apply to us, skip
                continue;
            }

            // We're in a relevant group
            match ($directive) {
                'disallow' => $this->rules['disallow'][] = $value,
                'allow' => $this->rules['allow'][] = $value,
                'crawl-delay' => $this->delay = (float)$value,
                default => null,
            };
        }
    }

    /**
     * @param string[] $agents
     */
    private function matchesUserAgent(array $agents): bool
    {
        foreach ($agents as $agent) {
            if ($agent === '*' || str_contains(strtolower(self::USER_AGENT), $agent)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the length of the longest matching pattern, 0 if no match.
     *
     * Supports wildcards (*) and end-of-url anchor ($) per Google spec.
     *
     * @param string[] $patterns
     */
    private function longestMatch(string $path, array $patterns): int
    {
        $longest = 0;

        foreach ($patterns as $pattern) {
            if ($this->pathMatches($path, $pattern)) {
                $longest = max($longest, strlen($pattern));
            }
        }

        return $longest;
    }

    /**
     * Match a path against a robots.txt pattern.
     *
     * Rules (Google spec):
     * - Pattern is matched against the start of the URL path
     * - `*` matches any sequence of characters
     * - `$` at end of pattern means exact end-of-string match
     * - Empty pattern matches nothing (Disallow: means allow all)
     */
    private function pathMatches(string $path, string $pattern): bool
    {
        if ($pattern === '') {
            return false;
        }

        // Convert robots.txt pattern to regex
        $anchored = false;
        if (str_ends_with($pattern, '$')) {
            $anchored = true;
            $pattern = substr($pattern, 0, -1);
        }

        // Escape regex special chars except *
        $regex = '';
        for ($i = 0, $len = strlen($pattern); $i < $len; $i++) {
            $char = $pattern[$i];
            if ($char === '*') {
                $regex .= '.*';
            } else {
                $regex .= preg_quote($char, '#');
            }
        }

        $regex = '#^' . $regex . ($anchored ? '$' : '') . '#';

        return preg_match($regex, $path) === 1;
    }
}