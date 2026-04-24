<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Infrastructure\InMemory;

use SeoSpider\Audit\Domain\Model\HtmlParser;
use SeoSpider\Audit\Domain\Model\Page\Directive;
use SeoSpider\Audit\Domain\Model\Page\Hreflang;
use SeoSpider\Audit\Domain\Model\Page\Link;
use SeoSpider\Audit\Domain\Model\Page\PageMetadata;
use SeoSpider\Audit\Domain\Model\Url;

final class StubHtmlParser implements HtmlParser
{
    private ?PageMetadata $metadata = null;
    private ?Directive $directives = null;

    /** @var Link[] */
    private array $links = [];

    /** @var Hreflang[] */
    private array $hreflangs = [];

    private string $cleanContent = '';

    public function withMetadata(PageMetadata $metadata): void
    {
        $this->metadata = $metadata;
    }

    public function withDirectives(Directive $directives): void
    {
        $this->directives = $directives;
    }

    /** @param Link[] $links */
    public function withLinks(array $links): void
    {
        $this->links = $links;
    }

    /** @param Hreflang[] $hreflangs */
    public function withHreflangs(array $hreflangs): void
    {
        $this->hreflangs = $hreflangs;
    }

    public function withCleanContent(string $content): void
    {
        $this->cleanContent = $content;
    }

    public function extractMetadata(string $html): PageMetadata
    {
        return $this->metadata ?? new PageMetadata(
            title: 'Default Title',
            metaDescription: 'Default description',
            h1s: ['Default H1'],
            h2s: [],
            headingHierarchy: [['level' => 1, 'text' => 'Default H1']],
            charset: 'UTF-8',
            viewport: 'width=device-width, initial-scale=1',
            ogTitle: null,
            ogDescription: null,
            ogImage: null,
            wordCount: 10,
            lang: 'en',
        );
    }

    public function extractDirectives(string $html, Url $baseUrl): Directive
    {
        return $this->directives ?? new Directive();
    }

    /** @return Link[] */
    public function extractLinks(string $html, Url $baseUrl): array
    {
        return $this->links;
    }

    /** @return Hreflang[] */
    public function extractHreflangs(string $html, Url $baseUrl): array
    {
        return $this->hreflangs;
    }

    public function extractCleanContent(string $html): string
    {
        return $this->cleanContent ?: 'Default clean content for fingerprinting';
    }
}
