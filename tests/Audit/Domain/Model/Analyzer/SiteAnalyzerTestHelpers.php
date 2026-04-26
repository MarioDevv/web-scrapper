<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Domain\Model\Analyzer;

use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\HttpStatusCode;
use SeoSpider\Audit\Domain\Model\Page\Directive;
use SeoSpider\Audit\Domain\Model\Page\DirectiveSource;
use SeoSpider\Audit\Domain\Model\Page\Hreflang;
use SeoSpider\Audit\Domain\Model\Page\HreflangSource;
use SeoSpider\Audit\Domain\Model\Page\Page;
use SeoSpider\Audit\Domain\Model\Page\PageId;
use SeoSpider\Audit\Domain\Model\Page\PageResponse;
use SeoSpider\Audit\Domain\Model\Page\RedirectChain;
use SeoSpider\Audit\Domain\Model\Page\RedirectHop;
use SeoSpider\Audit\Domain\Model\Url;

trait SiteAnalyzerTestHelpers
{
    protected AuditId $auditId;

    protected function buildAuditId(): AuditId
    {
        return $this->auditId ??= AuditId::generate();
    }

    /** @param Hreflang[] $hreflangs */
    protected function pageAt(
        string $url,
        int $statusCode = 200,
        ?string $canonical = null,
        bool $noindex = false,
        array $hreflangs = [],
        ?RedirectChain $redirectChain = null,
        ?string $finalUrl = null,
    ): Page {
        $requestUrl = Url::fromString($url);
        $resolvedFinalUrl = $finalUrl !== null ? Url::fromString($finalUrl) : $requestUrl;

        $response = new PageResponse(
            statusCode: new HttpStatusCode($statusCode),
            headers: ['content-type' => 'text/html'],
            body: '<html><body>ok</body></html>',
            contentType: 'text/html; charset=utf-8',
            bodySize: 28,
            responseTime: 0.1,
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

        if ($canonical !== null || $noindex) {
            $page->enrichWithDirectives(new Directive(
                noindex: $noindex,
                nofollow: false,
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

        return $page;
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
}
