<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Application\Analysis;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SeoSpider\Auditing\Infrastructure\Acl\LegacyPageToPageSignals;
use SeoSpider\Auditing\Domain\Model\Audit\AuditId;
use SeoSpider\Crawling\Domain\Model\Page\Page as LegacyPage;
use SeoSpider\Crawling\Domain\Model\Page\PageId;
use SeoSpider\Crawling\Domain\Model\HttpStatusCode;
use SeoSpider\Crawling\Domain\Model\Page\Directive as CrawlingDirective;
use SeoSpider\Crawling\Domain\Model\Page\DirectiveSource;
use SeoSpider\Crawling\Domain\Model\Page\Fingerprint as CrawlingFingerprint;
use SeoSpider\Crawling\Domain\Model\Page\Hreflang as CrawlingHreflang;
use SeoSpider\Crawling\Domain\Model\Page\HreflangSource;
use SeoSpider\Crawling\Domain\Model\Page\Link as CrawlingLink;
use SeoSpider\Crawling\Domain\Model\Page\LinkRelation;
use SeoSpider\Crawling\Domain\Model\Page\LinkType;
use SeoSpider\Crawling\Domain\Model\Page\PageMetadata as CrawlingPageMetadata;
use SeoSpider\Crawling\Domain\Model\Page\PageResponse;
use SeoSpider\Crawling\Domain\Model\Page\RedirectChain as CrawlingRedirectChain;
use SeoSpider\Crawling\Domain\Model\Page\RedirectHop as CrawlingRedirectHop;
use SeoSpider\Crawling\Domain\Model\Url;

final class LegacyPageToPageSignalsTest extends TestCase
{
    public function test_translates_every_signal_into_auditing_typed_primitives(): void
    {
        $url = Url::fromString('https://example.com/about');
        $finalUrl = Url::fromString('https://example.com/about/');
        $response = new PageResponse(
            statusCode: new HttpStatusCode(301),
            headers: ['content-type' => 'text/html', 'x-frame-options' => 'DENY'],
            body: null,
            contentType: 'text/html; charset=utf-8',
            bodySize: 1234,
            responseTime: 0.42,
            finalUrl: $finalUrl,
        );
        $metadata = new CrawlingPageMetadata(
            title: 'About',
            metaDescription: 'About us',
            h1s: ['About'],
            h2s: ['Team'],
            headingHierarchy: [['level' => 1, 'text' => 'About']],
            charset: 'utf-8',
            viewport: 'width=device-width',
            ogTitle: 'About — Example',
            ogDescription: null,
            ogImage: null,
            wordCount: 120,
            lang: 'en',
        );
        $directive = new CrawlingDirective(
            noindex: true,
            nofollow: false,
            noarchive: false,
            nosnippet: false,
            noimageindex: false,
            maxSnippet: 200,
            maxImagePreview: 'large',
            maxVideoPreview: null,
            canonical: $url,
            source: DirectiveSource::META_TAG,
        );
        $fingerprint = CrawlingFingerprint::fromContent('hello world');
        $links = [
            new CrawlingLink(
                targetUrl: Url::fromString('https://example.com/contact'),
                type: LinkType::ANCHOR,
                anchorText: 'Contact',
                relation: LinkRelation::FOLLOW,
                isInternal: true,
            ),
            new CrawlingLink(
                targetUrl: Url::fromString('https://elsewhere.test/'),
                type: LinkType::ANCHOR,
                anchorText: null,
                relation: LinkRelation::NOFOLLOW,
                isInternal: false,
            ),
        ];
        $hreflangs = [
            new CrawlingHreflang(
                language: 'en',
                region: 'US',
                href: Url::fromString('https://example.com/about'),
                source: HreflangSource::HTML_HEAD,
            ),
        ];
        $chain = CrawlingRedirectChain::fromHops([
            new CrawlingRedirectHop(
                from: $url,
                to: $finalUrl,
                statusCode: new HttpStatusCode(301),
            ),
        ]);

        $legacy = LegacyPage::reconstitute(
            id: PageId::generate(),
            auditId: AuditId::generate()->value(),
            url: $url,
            response: $response,
            redirectChain: $chain,
            crawlDepth: 2,
            metadata: $metadata,
            directives: $directive,
            fingerprint: $fingerprint,
            links: $links,
            hreflangs: $hreflangs,
            crawledAt: new DateTimeImmutable('2026-05-19T10:00:00+00:00'),
        );

        $signals = new LegacyPageToPageSignals($legacy);

        $this->assertSame('https://example.com/about', $signals->url());
        $this->assertSame(2, $signals->crawlDepth());

        $r = $signals->response();
        $this->assertSame(301, $r->statusCode()->code());
        $this->assertTrue($r->statusCode()->isPermanentRedirect());
        $this->assertSame('DENY', $r->header('X-Frame-Options'));
        $this->assertSame(1234, $r->bodySize());
        $this->assertSame(0.42, $r->responseTime());
        $this->assertSame('https://example.com/about/', $r->finalUrl());

        $m = $signals->metadata();
        $this->assertNotNull($m);
        $this->assertSame('About', $m->title());
        $this->assertSame(['About'], $m->h1s());
        $this->assertFalse($m->hasNoH1());
        $this->assertSame(120, $m->wordCount());
        $this->assertSame('en', $m->lang());

        $d = $signals->directives();
        $this->assertNotNull($d);
        $this->assertTrue($d->noindex());
        $this->assertSame('https://example.com/about', $d->canonical());
        $this->assertTrue($d->isSelfCanonical('https://example.com/about'));

        $f = $signals->fingerprint();
        $this->assertNotNull($f);
        $this->assertSame($fingerprint->exactHash(), $f->exactHash());

        $rc = $signals->redirectChain();
        $this->assertSame(1, $rc->length());
        $this->assertSame('https://example.com/about', $rc->hops()[0]->from());
        $this->assertSame(301, $rc->hops()[0]->statusCode()->code());
        $this->assertTrue($rc->isAllPermanent());

        $this->assertCount(2, $signals->links());
        $this->assertCount(1, $signals->internalLinks());
        $this->assertCount(1, $signals->externalLinks());
        $this->assertSame('https://example.com/contact', $signals->internalLinks()[0]->targetUrl());
        $this->assertTrue($signals->internalLinks()[0]->isAnchor());
        $this->assertTrue($signals->internalLinks()[0]->isFollowable());

        $this->assertCount(1, $signals->hreflangs());
        $this->assertSame('en-US', $signals->hreflangs()[0]->languageRegionCode());

        $this->assertTrue($signals->isHtml());
        $this->assertTrue($signals->isRedirect());
        $this->assertFalse($signals->isBroken());
        $this->assertFalse($signals->isIndexable());
    }
}
