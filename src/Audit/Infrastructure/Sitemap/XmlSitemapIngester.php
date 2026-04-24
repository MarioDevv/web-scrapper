<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Infrastructure\Sitemap;

use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\DiscoverySource;
use SeoSpider\Audit\Domain\Model\Frontier;
use SeoSpider\Audit\Domain\Model\HttpClient;
use SeoSpider\Audit\Domain\Model\HttpRequestFailed;
use SeoSpider\Audit\Domain\Model\Sitemap\SitemapIngester;
use SeoSpider\Audit\Domain\Model\Url;

final readonly class XmlSitemapIngester implements SitemapIngester
{
    /** Hard caps, prevent runaway crawls if a site serves a malicious or misconfigured sitemap. */
    private const int MAX_SITEMAPS_PER_AUDIT = 50;
    private const int MAX_URLS_PER_SITEMAP = 50_000;
    private const int MAX_INDEX_DEPTH = 2;

    public function __construct(
        private HttpClient $httpClient,
        private Frontier $frontier,
    ) {
    }

    public function ingest(AuditId $auditId, Url $seedUrl, ?string $userAgent = null): int
    {
        $sitemapUrls = $this->discoverSitemapUrls($seedUrl, $userAgent);
        if ($sitemapUrls === []) {
            return 0;
        }

        $processed = 0;
        $enqueued = 0;

        foreach ($sitemapUrls as $sitemapUrl) {
            if ($processed >= self::MAX_SITEMAPS_PER_AUDIT) {
                break;
            }

            $enqueued += $this->ingestSitemap($auditId, $sitemapUrl, $userAgent, depth: 0, processed: $processed);
        }

        return $enqueued;
    }

    /** @return Url[] */
    private function discoverSitemapUrls(Url $seedUrl, ?string $userAgent): array
    {
        $declared = $this->extractSitemapUrlsFromRobots($seedUrl, $userAgent);
        if ($declared !== []) {
            return $declared;
        }

        $fallback = Url::tryFromString($seedUrl->origin() . '/sitemap.xml');

        return $fallback !== null ? [$fallback] : [];
    }

    /** @return Url[] */
    private function extractSitemapUrlsFromRobots(Url $seedUrl, ?string $userAgent): array
    {
        $robotsUrl = Url::tryFromString($seedUrl->origin() . '/robots.txt');
        if ($robotsUrl === null) {
            return [];
        }

        try {
            $response = $this->httpClient->get($robotsUrl, $userAgent);
        } catch (HttpRequestFailed) {
            return [];
        }

        $body = $response->body();
        if ($body === null || $body === '') {
            return [];
        }

        $sitemaps = [];
        foreach (preg_split('/\r?\n/', $body) ?: [] as $line) {
            $commentPos = strpos($line, '#');
            if ($commentPos !== false) {
                $line = substr($line, 0, $commentPos);
            }

            $line = trim($line);
            if (!str_starts_with(strtolower($line), 'sitemap:')) {
                continue;
            }

            $value = trim(substr($line, strlen('sitemap:')));
            if ($value === '') {
                continue;
            }

            $url = Url::tryFromString($value);
            if ($url !== null) {
                $sitemaps[] = $url;
            }
        }

        return $sitemaps;
    }

    private function ingestSitemap(
        AuditId $auditId,
        Url $sitemapUrl,
        ?string $userAgent,
        int $depth,
        int &$processed,
    ): int {
        if ($depth > self::MAX_INDEX_DEPTH) {
            return 0;
        }

        if ($processed >= self::MAX_SITEMAPS_PER_AUDIT) {
            return 0;
        }
        $processed++;

        $xml = $this->fetchSitemap($sitemapUrl, $userAgent);
        if ($xml === null) {
            return 0;
        }

        if ($xml->getName() === 'sitemapindex') {
            return $this->ingestSitemapIndex($auditId, $xml, $userAgent, $depth, $processed);
        }

        if ($xml->getName() === 'urlset') {
            return $this->enqueueUrlset($auditId, $xml);
        }

        return 0;
    }

    private function fetchSitemap(Url $url, ?string $userAgent): ?\SimpleXMLElement
    {
        try {
            $response = $this->httpClient->get($url, $userAgent);
        } catch (HttpRequestFailed) {
            return null;
        }

        if (!$response->statusCode()->isSuccessful()) {
            return null;
        }

        $body = $response->body();
        if ($body === null || $body === '') {
            return null;
        }

        // Suppress libxml warnings: we handle the null return.
        $previous = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($body);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return $xml !== false ? $xml : null;
    }

    private function ingestSitemapIndex(
        AuditId $auditId,
        \SimpleXMLElement $index,
        ?string $userAgent,
        int $depth,
        int &$processed,
    ): int {
        $enqueued = 0;

        foreach ($index->sitemap as $child) {
            $loc = trim((string) $child->loc);
            if ($loc === '') {
                continue;
            }

            $childUrl = Url::tryFromString($loc);
            if ($childUrl === null) {
                continue;
            }

            $enqueued += $this->ingestSitemap($auditId, $childUrl, $userAgent, $depth + 1, $processed);

            if ($processed >= self::MAX_SITEMAPS_PER_AUDIT) {
                break;
            }
        }

        return $enqueued;
    }

    private function enqueueUrlset(AuditId $auditId, \SimpleXMLElement $urlset): int
    {
        $enqueued = 0;
        $seen = 0;

        foreach ($urlset->url as $entry) {
            if ($seen >= self::MAX_URLS_PER_SITEMAP) {
                break;
            }
            $seen++;

            $loc = trim((string) $entry->loc);
            if ($loc === '') {
                continue;
            }

            $url = Url::tryFromString($loc);
            if ($url === null) {
                continue;
            }

            if ($this->frontier->enqueue($auditId, $url, depth: 0, source: DiscoverySource::SITEMAP)) {
                $enqueued++;
            }
        }

        return $enqueued;
    }
}
