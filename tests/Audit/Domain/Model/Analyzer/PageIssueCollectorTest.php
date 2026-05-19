<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Domain\Model\Analyzer;

use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Domain\Model\Analyzer\PageIssueCollector;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\Page\Issue;
use SeoSpider\Audit\Domain\Model\Page\IssueCategory;
use SeoSpider\Audit\Domain\Model\Page\IssueId;
use SeoSpider\Audit\Domain\Model\Page\IssueSeverity;
use SeoSpider\Audit\Domain\Model\Page\Page;
use SeoSpider\Audit\Domain\Model\Page\PageId;
use SeoSpider\Crawling\Domain\Model\HttpStatusCode;
use SeoSpider\Crawling\Domain\Model\Page\PageResponse;
use SeoSpider\Crawling\Domain\Model\Page\RedirectChain;
use SeoSpider\Crawling\Domain\Model\Url;

final class PageIssueCollectorTest extends TestCase
{
    public function test_forwards_added_issues_to_the_page(): void
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

        $collector = new PageIssueCollector($page);
        $collector->add(new Issue(
            id: IssueId::generate(),
            category: IssueCategory::CONTENT,
            severity: IssueSeverity::WARNING,
            code: 'content_empty',
            message: 'x',
        ));

        $this->assertSame(
            ['content_empty'],
            array_map(static fn (Issue $i): string => $i->code(), $page->issues()),
        );
    }
}
