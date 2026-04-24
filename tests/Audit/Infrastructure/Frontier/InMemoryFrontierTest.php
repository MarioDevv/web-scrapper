<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Infrastructure\Frontier;

use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\Url;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\InMemoryFrontier;

final class InMemoryFrontierTest extends TestCase
{
    public function test_dedupes_urls_that_normalize_to_the_same_key(): void
    {
        $frontier = new InMemoryFrontier();
        $auditId = AuditId::generate();

        $first = $frontier->enqueue($auditId, Url::fromString('https://example.com/page'), 1);
        $secondWithFragment = $frontier->enqueue($auditId, Url::fromString('https://example.com/page#top'), 1);
        $thirdWithUtm = $frontier->enqueue($auditId, Url::fromString('https://example.com/page?utm_source=x'), 1);
        $fourthMixedCaseHost = $frontier->enqueue($auditId, Url::fromString('https://Example.COM/page'), 1);
        $fifthDefaultPort = $frontier->enqueue($auditId, Url::fromString('https://example.com:443/page'), 1);

        $this->assertTrue($first);
        $this->assertFalse($secondWithFragment);
        $this->assertFalse($thirdWithUtm);
        $this->assertFalse($fourthMixedCaseHost);
        $this->assertFalse($fifthDefaultPort);
        $this->assertSame(1, $frontier->pendingCount($auditId));
    }

    public function test_stores_normalized_url_in_the_entry(): void
    {
        $frontier = new InMemoryFrontier();
        $auditId = AuditId::generate();

        $frontier->enqueue($auditId, Url::fromString('HTTPS://Example.COM:443/path?fbclid=abc#frag'), 0);

        $entry = $frontier->dequeue($auditId);
        $this->assertNotNull($entry);
        $this->assertSame('https://example.com/path', $entry->url->toString());
    }
}
