<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Domain\Model\Analyzer;

use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\HttpStatusCode;
use SeoSpider\Audit\Domain\Model\Page\Directive;
use SeoSpider\Audit\Domain\Model\Page\DirectiveSource;
use SeoSpider\Audit\Domain\Model\Page\Hreflang;
use SeoSpider\Audit\Domain\Model\Page\HreflangSource;
use SeoSpider\Audit\Domain\Model\Page\Link;
use SeoSpider\Audit\Domain\Model\Page\LinkRelation;
use SeoSpider\Audit\Domain\Model\Page\LinkType;
use SeoSpider\Audit\Domain\Model\Page\Page;
use SeoSpider\Audit\Domain\Model\Page\PageId;
use SeoSpider\Audit\Domain\Model\Page\PageMetadata;
use SeoSpider\Audit\Domain\Model\Page\PageResponse;
use SeoSpider\Audit\Domain\Model\Page\RedirectChain;
use SeoSpider\Audit\Domain\Model\Page\RedirectHop;
use SeoSpider\Audit\Domain\Model\Url;

/**
 * Shared builders for analyzer tests. Both per-page and site-wide
 * analyzers consume Page aggregates with selectively enriched
 * metadata, links, redirect chains and headers — the helpers below
 * keep tests focused on the rule under test rather than ceremony.
 */
trait AnalyzerTestHelpers
{
    protected AuditId $auditId;

    protected function buildAuditId(): AuditId
    {
        return $this->auditId ??= AuditId::generate();
    }

    /**
     * @param Hreflang[]                  $hreflangs
     * @param Link[]                      $links
     * @param array<string, string|array> $headers
     */
    protected function pageAt(
        string $url,
        int $statusCode = 200,
        ?string $canonical = null,
        bool $noindex = false,
        bool $nofollow = false,
        array $hreflangs = [],
        array $links = [],
        ?RedirectChain $redirectChain = null,
        ?string $finalUrl = null,
        ?PageMetadata $metadata = null,
        ?string $contentType = 'text/html; charset=utf-8',
        array $headers = ['content-type' => 'text/html'],
        float $responseTime = 0.1,
        int $bodySize = 28,
    ): Page {
        $requestUrl = Url::fromString($url);
        $resolvedFinalUrl = $finalUrl !== null ? Url::fromString($finalUrl) : $requestUrl;

        $response = new PageResponse(
            statusCode: new HttpStatusCode($statusCode),
            headers: $headers,
            body: '<html><body>ok</body></html>',
            contentType: $contentType,
            bodySize: $bodySize,
            responseTime: $responseTime,
            finalUrl: $resolvedFinalUrl,
        );

        $page = Page::fromCrawl(
            id: PageId::generate(),
            auditId: $this->buildAuditId(),
            url: $requestUrl,
            response: $response,
            redirectChain: $redirectChain ?? RedirectChain::none(),
            crawlDepth: 0,
        );

        if ($canonical !== null || $noindex || $nofollow) {
            $page->enrichWithDirectives(new Directive(
                noindex: $noindex,
                nofollow: $nofollow,
                noarchive: false,
                nosnippet: false,
                noimageindex: false,
                maxSnippet: null,
                maxImagePreview: null,
                maxVideoPreview: null,
                canonical: $canonical !== null ? Url::fromString($canonical) : null,
                source: DirectiveSource::META_TAG,
            ));
        }

        if ($hreflangs !== []) {
            $page->enrichWithHreflangs($hreflangs);
        }

        if ($links !== []) {
            $page->enrichWithLinks($links);
        }

        if ($metadata !== null) {
            $page->enrichWithMetadata($metadata);
        }

        return $page;
    }

    /**
     * @param string[]                                   $h1s
     * @param string[]                                   $h2s
     * @param array<array{level: int, text: string}>    $headingHierarchy
     */
    protected function metadata(
        ?string $title = 'Title',
        ?string $metaDescription = 'A description.',
        array $h1s = ['Heading'],
        array $h2s = [],
        ?array $headingHierarchy = null,
        ?string $charset = 'utf-8',
        ?string $viewport = 'width=device-width, initial-scale=1',
        ?string $ogTitle = null,
        ?string $ogDescription = null,
        ?string $ogImage = null,
        int $wordCount = 200,
        ?string $lang = 'en',
    ): PageMetadata {
        if ($headingHierarchy === null) {
            $headingHierarchy = array_map(
                static fn(string $text) => ['level' => 1, 'text' => $text],
                $h1s,
            );
            foreach ($h2s as $text) {
                $headingHierarchy[] = ['level' => 2, 'text' => $text];
            }
        }

        return new PageMetadata(
            title: $title,
            metaDescription: $metaDescription,
            h1s: $h1s,
            h2s: $h2s,
            headingHierarchy: $headingHierarchy,
            charset: $charset,
            viewport: $viewport,
            ogTitle: $ogTitle,
            ogDescription: $ogDescription,
            ogImage: $ogImage,
            wordCount: $wordCount,
            lang: $lang,
        );
    }

    protected function hreflang(string $code, string $href): Hreflang
    {
        if (strtolower($code) === 'x-default') {
            return new Hreflang(
                language: 'x-default',
                region: null,
                href: Url::fromString($href),
                source: HreflangSource::HTML_HEAD,
            );
        }

        $parts = explode('-', $code, 2);

        return new Hreflang(
            language: $parts[0],
            region: $parts[1] ?? null,
            href: Url::fromString($href),
            source: HreflangSource::HTML_HEAD,
        );
    }

    protected function redirectHop(string $from, string $to, int $statusCode = 301): RedirectHop
    {
        return new RedirectHop(
            from: Url::fromString($from),
            to: Url::fromString($to),
            statusCode: new HttpStatusCode($statusCode),
        );
    }

    protected function anchor(
        string $url,
        bool $internal = true,
        LinkRelation $relation = LinkRelation::FOLLOW,
        ?string $anchorText = null,
    ): Link {
        return new Link(
            targetUrl: Url::fromString($url),
            type: LinkType::ANCHOR,
            anchorText: $anchorText,
            relation: $relation,
            isInternal: $internal,
        );
    }
}
