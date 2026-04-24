<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Page;

/**
 * Aggregate result of a single HTML parsing pass. Read-model VO: the fields
 * are the extractions the crawler needs per page and are computed together
 * so the DOM is built only once.
 */
final readonly class ParsedPage
{
    /**
     * @param Link[]     $links
     * @param Hreflang[] $hreflangs
     */
    public function __construct(
        public PageMetadata $metadata,
        public array $links,
        public array $hreflangs,
        public Directive $directive,
        public string $cleanContent,
    ) {
    }
}
