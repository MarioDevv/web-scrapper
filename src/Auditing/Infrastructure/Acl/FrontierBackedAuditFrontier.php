<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Infrastructure\Acl;

use SeoSpider\Auditing\Domain\Model\Audit\AuditFrontier;
use SeoSpider\Crawling\Application\Frontier;
use SeoSpider\Crawling\Domain\Model\DiscoverySource;
use SeoSpider\Crawling\Domain\Model\Url;

final readonly class FrontierBackedAuditFrontier implements AuditFrontier
{
    public function __construct(private Frontier $frontier)
    {
    }

    public function enqueueSeed(string $auditId, string $seedUrl): void
    {
        $this->frontier->enqueue($auditId, Url::fromString($seedUrl), depth: 0, source: DiscoverySource::SEED);
    }

    public function clear(string $auditId): void
    {
        $this->frontier->clear($auditId);
    }
}
