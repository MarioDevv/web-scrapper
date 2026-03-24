<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Application\GetPageDetail;

final readonly class GetPageDetailResponse
{
    /**
     * @param IssueSummary[] $issues
     * @param string[] $h1s
     * @param array{from: string, to: string, statusCode: int}[] $redirectChain
     * @param array{language: string, region: ?string, href: string}[] $hreflangs
     */
    public function __construct(
        public string $pageId,
        public string $auditId,
        public string $url,
        public int $statusCode,
        public string $contentType,
        public int $bodySize,
        public float $responseTime,
        public int $crawlDepth,
        public bool $isIndexable,
        public ?string $title,
        public ?int $titleLength,
        public ?string $metaDescription,
        public ?int $metaDescriptionLength,
        public array $h1s,
        public int $wordCount,
        public ?string $canonical,
        public string $canonicalStatus,  // 'self' | 'other' | 'missing'
        public bool $noindex,
        public bool $nofollow,
        public array $redirectChain,
        public array $hreflangs,
        public int $internalLinkCount,
        public int $externalLinkCount,
        public array $issues,
        public string $crawledAt,
    ) {
    }
}
