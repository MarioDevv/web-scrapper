<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Crawling\Application;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SeoSpider\Auditing\Domain\Model\Audit\AuditId;
use SeoSpider\Auditing\Domain\Model\Issue\Issue;
use SeoSpider\Auditing\Domain\Model\Issue\IssueCategory;
use SeoSpider\Auditing\Domain\Model\Issue\IssueId;
use SeoSpider\Auditing\Domain\Model\Issue\IssueSeverity;
use SeoSpider\Audit\Domain\Model\Page\Page;
use SeoSpider\Audit\Domain\Model\Page\PageId;
use SeoSpider\Crawling\Application\LegacyPageToCrawledPage;
use SeoSpider\Crawling\Domain\Model\HttpStatusCode;
use SeoSpider\Crawling\Domain\Model\Page\Directive;
use SeoSpider\Crawling\Domain\Model\Page\DirectiveSource;
use SeoSpider\Crawling\Domain\Model\Page\Fingerprint;
use SeoSpider\Crawling\Domain\Model\Page\Link;
use SeoSpider\Crawling\Domain\Model\Page\LinkRelation;
use SeoSpider\Crawling\Domain\Model\Page\LinkType;
use SeoSpider\Crawling\Domain\Model\Page\PageMetadata;
use SeoSpider\Crawling\Domain\Model\Page\PageResponse;
use SeoSpider\Crawling\Domain\Model\Page\RedirectChain;
use SeoSpider\Crawling\Domain\Model\Url;

final class LegacyPageToCrawledPageTest extends TestCase
{
    public function test_captures_every_crawl_field_and_drops_issues(): void
    {
        $url = Url::fromString('https://example.com/about');
        $response = new PageResponse(
            statusCode: new HttpStatusCode(200),
            headers: ['content-type' => 'text/html'],
            body: '<html><body>hi</body></html>',
            contentType: 'text/html',
            bodySize: 28,
            responseTime: 0.2,
            finalUrl: $url,
        );
        $metadata = new PageMetadata(
            title: 'About',
            metaDescription: 'About us',
            h1s: ['About'],
            h2s: [],
            headingHierarchy: [['level' => 1, 'text' => 'About']],
            charset: 'utf-8',
            viewport: 'width=device-width',
            ogTitle: null,
            ogDescription: null,
            ogImage: null,
            wordCount: 120,
            lang: 'en',
        );
        $directive = new Directive(
            noindex: false,
            nofollow: false,
            noarchive: false,
            nosnippet: false,
            noimageindex: false,
            maxSnippet: null,
            maxImagePreview: null,
            maxVideoPreview: null,
            canonical: $url,
            source: DirectiveSource::META_TAG,
        );
        $fingerprint = Fingerprint::fromContent('hi');
        $links = [new Link(
            targetUrl: Url::fromString('https://example.com/contact'),
            type: LinkType::ANCHOR,
            anchorText: 'Contact',
            relation: LinkRelation::FOLLOW,
            isInternal: true,
        )];
        $crawledAt = new DateTimeImmutable('2026-05-19T10:00:00+00:00');

        $page = Page::reconstitute(
            id: PageId::generate(),
            auditId: AuditId::generate(),
            url: $url,
            response: $response,
            redirectChain: RedirectChain::none(),
            crawlDepth: 2,
            metadata: $metadata,
            directives: $directive,
            fingerprint: $fingerprint,
            links: $links,
            hreflangs: [],
            issues: [new Issue(
                id: IssueId::generate(),
                category: IssueCategory::CONTENT,
                severity: IssueSeverity::WARNING,
                code: 'content_thin',
                message: 'x',
            )],
            crawledAt: $crawledAt,
        );

        $crawled = (new LegacyPageToCrawledPage())($page);

        $this->assertSame($url, $crawled->url);
        $this->assertSame($response, $crawled->response);
        $this->assertSame($page->redirectChain(), $crawled->redirectChain);
        $this->assertSame(2, $crawled->crawlDepth);
        $this->assertSame($metadata, $crawled->metadata);
        $this->assertSame($directive, $crawled->directives);
        $this->assertSame($fingerprint, $crawled->fingerprint);
        $this->assertSame($links, $crawled->links);
        $this->assertSame([], $crawled->hreflangs);
        $this->assertSame($crawledAt, $crawled->crawledAt);
        $this->assertSame($page->isHtml(), $crawled->isHtml());
        $this->assertSame($page->isIndexable(), $crawled->isIndexable());
        $this->assertSame($page->isBroken(), $crawled->isBroken());
        $this->assertSame($page->isRedirect(), $crawled->isRedirect());
        $this->assertCount(1, $crawled->internalLinks());
        $this->assertSame([], $crawled->externalLinks());
        $this->assertFalse(property_exists($crawled, 'issues'));
    }
}
