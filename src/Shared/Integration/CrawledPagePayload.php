<?php

declare(strict_types=1);

namespace SeoSpider\Shared\Integration;

/**
 * Published-Language payload describing one crawled page, in primitives
 * only. Crosses the Crawling -> Auditing seam via {@see CrawledPageReady}.
 * No domain objects: this is the contract, not a model. v1 — extended to
 * the full PageSignals contract in Phase 3.
 */
final readonly class CrawledPagePayload
{
    public function __construct(
        public string $auditId,
        public string $url,
        public int $crawlDepth,
        public bool $isHtml,
        public bool $isIndexable,
        public int $statusCode,
        public ?string $contentType,
        public int $bodySize,
        public float $responseTime,
    ) {
    }
}
