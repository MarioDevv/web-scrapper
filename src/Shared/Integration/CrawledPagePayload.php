<?php

declare(strict_types=1);

namespace SeoSpider\Shared\Integration;

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
