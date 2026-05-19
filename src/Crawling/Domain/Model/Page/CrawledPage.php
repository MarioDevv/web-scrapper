<?php

declare(strict_types=1);

namespace SeoSpider\Crawling\Domain\Model\Page;

use DateTimeImmutable;
use SeoSpider\Crawling\Domain\Model\Url;

/**
 * Immutable result of fetching and parsing one URL. Built in a single
 * step — no enrichWith*(), no Issue, no domain events. This is the
 * Crawling context's notion of a "page": raw transport + parsed
 * structure, nothing about SEO findings (those belong to Auditing).
 *
 * The Auditing context never imports this type; from 3c its ACL maps
 * the persisted crawl row into its own PageSignals/AuditedPage.
 *
 * @phase3 Not yet wired into the crawl hot path; consumers switch in 3d.
 */
final readonly class CrawledPage
{
    /**
     * @param Link[]     $links
     * @param Hreflang[] $hreflangs
     */
    public function __construct(
        public Url $url,
        public PageResponse $response,
        public RedirectChain $redirectChain,
        public int $crawlDepth,
        public ?PageMetadata $metadata,
        public ?Directive $directives,
        public ?Fingerprint $fingerprint,
        public array $links,
        public array $hreflangs,
        public DateTimeImmutable $crawledAt,
    ) {
    }

    public function isHtml(): bool
    {
        return $this->response->isHtml();
    }

    public function isBroken(): bool
    {
        return $this->response->statusCode()->isBroken();
    }

    public function isRedirect(): bool
    {
        return $this->response->statusCode()->isRedirect();
    }

    public function isIndexable(): bool
    {
        if (!$this->response->statusCode()->isSuccessful()) {
            return false;
        }

        if ($this->directives !== null && $this->directives->noindex()) {
            return false;
        }

        if ($this->directives !== null
            && $this->directives->hasCanonical()
            && !$this->directives->isSelfCanonical($this->url)) {
            return false;
        }

        return true;
    }

    /** @return Link[] */
    public function internalLinks(): array
    {
        return array_values(array_filter(
            $this->links,
            static fn (Link $link): bool => $link->isInternal(),
        ));
    }

    /** @return Link[] */
    public function externalLinks(): array
    {
        return array_values(array_filter(
            $this->links,
            static fn (Link $link): bool => $link->isExternal(),
        ));
    }
}
