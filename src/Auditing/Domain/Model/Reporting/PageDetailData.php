<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Domain\Model\Reporting;

final readonly class PageDetailData
{
    /**
     * @param string[]                                                                                $h1s
     * @param array<array{from: string, to: string, statusCode: int}>                                 $redirectChain
     * @param array<array{language: string, region: ?string, href: string}>                           $hreflangs
     * @param array<array{url: string, type: string, anchor: ?string, relation: string, internal: bool}> $links
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
        public string $canonicalStatus,
        public bool $noindex,
        public bool $nofollow,
        public array $redirectChain,
        public array $hreflangs,
        public int $internalLinkCount,
        public int $externalLinkCount,
        public array $links,
        public string $crawledAt,
    ) {
    }
}
