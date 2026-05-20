<?php

declare(strict_types=1);

namespace SeoSpider\Crawling\Domain\Model\Page;

use DateTimeImmutable;
use SeoSpider\Crawling\Domain\Model\Url;

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
