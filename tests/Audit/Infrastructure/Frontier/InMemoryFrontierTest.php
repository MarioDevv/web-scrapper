<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Infrastructure\Frontier;

use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\DiscoverySource;
use SeoSpider\Audit\Domain\Model\Url;
use SeoSpider\Audit\Domain\Model\UrlCanonicalizer;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\InMemoryFrontier;

final class InMemoryFrontierTest extends TestCase
{
    public function test_dedupes_urls_that_normalize_to_the_same_key(): void
    {
        $frontier = new InMemoryFrontier(new UrlCanonicalizer());
        $auditId = AuditId::generate();

        $first = $frontier->enqueue($auditId, Url::fromString('https://example.com/page'), 1, DiscoverySource::LINK);
        $secondWithFragment = $frontier->enqueue($auditId, Url::fromString('https://example.com/page#top'), 1, DiscoverySource::LINK);
        $thirdWithUtm = $frontier->enqueue($auditId, Url::fromString('https://example.com/page?utm_source=x'), 1, DiscoverySource::LINK);
        $fourthMixedCaseHost = $frontier->enqueue($auditId, Url::fromString('https://Example.COM/page'), 1, DiscoverySource::LINK);
        $fifthDefaultPort = $frontier->enqueue($auditId, Url::fromString('https://example.com:443/page'), 1, DiscoverySource::LINK);

        $this->assertTrue($first);
        $this->assertFalse($secondWithFragment);
        $this->assertFalse($thirdWithUtm);
        $this->assertFalse($fourthMixedCaseHost);
        $this->assertFalse($fifthDefaultPort);
        $this->assertSame(1, $frontier->pendingCount($auditId));
    }

    public function test_stores_normalized_url_in_the_entry(): void
    {
        $frontier = new InMemoryFrontier(new UrlCanonicalizer());
        $auditId = AuditId::generate();

        $frontier->enqueue($auditId, Url::fromString('HTTPS://Example.COM:443/path?fbclid=abc#frag'), 0, DiscoverySource::LINK);

        $entry = $frontier->dequeue($auditId);
        $this->assertNotNull($entry);
        $this->assertSame('https://example.com/path', $entry->url->toString());
    }

    public function test_records_the_source_of_each_enqueued_url(): void
    {
        $frontier = new InMemoryFrontier(new UrlCanonicalizer());
        $auditId = AuditId::generate();

        $frontier->enqueue($auditId, Url::fromString('https://example.com/seed'), 0, DiscoverySource::SEED);
        $frontier->enqueue($auditId, Url::fromString('https://example.com/from-sitemap'), 0, DiscoverySource::SITEMAP);
        $frontier->enqueue($auditId, Url::fromString('https://example.com/from-link'), 1, DiscoverySource::LINK);

        $this->assertSame(DiscoverySource::SEED, $frontier->sourceOf($auditId, Url::fromString('https://example.com/seed')));
        $this->assertSame(DiscoverySource::SITEMAP, $frontier->sourceOf($auditId, Url::fromString('https://example.com/from-sitemap')));
        $this->assertSame(DiscoverySource::LINK, $frontier->sourceOf($auditId, Url::fromString('https://example.com/from-link')));
    }

    public function test_first_source_wins_when_the_same_url_is_enqueued_again(): void
    {
        $frontier = new InMemoryFrontier(new UrlCanonicalizer());
        $auditId = AuditId::generate();

        $frontier->enqueue($auditId, Url::fromString('https://example.com/p'), 0, DiscoverySource::SITEMAP);
        $frontier->enqueue($auditId, Url::fromString('https://example.com/p'), 1, DiscoverySource::LINK);

        $this->assertSame(DiscoverySource::SITEMAP, $frontier->sourceOf($auditId, Url::fromString('https://example.com/p')));
    }
}
