<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Domain\Model\Analyzer;

use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Domain\Model\Analyzer\PageSignals;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\Page\Page;
use SeoSpider\Audit\Domain\Model\Page\PageId;
use SeoSpider\Crawling\Domain\Model\HttpStatusCode;
use SeoSpider\Crawling\Domain\Model\Page\PageResponse;
use SeoSpider\Crawling\Domain\Model\Page\RedirectChain;
use SeoSpider\Crawling\Domain\Model\Url;

final class PageSignalsTest extends TestCase
{
    public function test_page_aggregate_satisfies_the_signals_port(): void
    {
        $page = Page::fromCrawl(
            id: PageId::generate(),
            auditId: AuditId::generate(),
            url: Url::fromString('https://example.com/'),
            response: new PageResponse(
                statusCode: new HttpStatusCode(200),
                headers: [],
                body: null,
                contentType: 'text/html',
                bodySize: 1,
                responseTime: 0.1,
                finalUrl: null,
            ),
            redirectChain: RedirectChain::none(),
            crawlDepth: 0,
        );

        $this->assertInstanceOf(PageSignals::class, $page);
        $this->assertSame('https://example.com/', $page->url()->toString());
        $this->assertTrue($page->isHtml());
    }
}
