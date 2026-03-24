<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Application\GetAuditPages;

final readonly class PageSummary
{
    public function __construct(
        public string $pageId,
        public string $url,
        public int $statusCode,
        public string $contentType,
        public int $bodySize,
        public float $responseTime,
        public int $crawlDepth,
        public int $errorCount,
        public int $warningCount,
        public bool $isIndexable,
        public ?string $title,
        public int $wordCount,
        public int $internalLinkCount,
        public int $externalLinkCount,
        public int $imageCount,
        public string $canonicalStatus,
        public int $h1Count,
        public string $crawledAt,
    ) {
    }
}
