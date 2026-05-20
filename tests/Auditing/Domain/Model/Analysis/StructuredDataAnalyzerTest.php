<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Auditing\Domain\Model\Analysis;

use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Application\Analysis\LegacyPageToPageSignals;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\Page\Page;
use SeoSpider\Audit\Domain\Model\Page\PageId;
use SeoSpider\Auditing\Domain\Model\Analysis\StructuredDataAnalyzer;
use SeoSpider\Crawling\Domain\Model\HttpStatusCode;
use SeoSpider\Crawling\Domain\Model\Page\PageMetadata;
use SeoSpider\Crawling\Domain\Model\Page\PageResponse;
use SeoSpider\Crawling\Domain\Model\Page\RedirectChain;
use SeoSpider\Crawling\Domain\Model\Url;

final class StructuredDataAnalyzerTest extends TestCase
{
    public function test_does_not_flag_when_json_ld_types_are_present(): void
    {
        $collector = $this->runOn($this->pageWithStructuredData(jsonLdTypes: ['Article', 'Person']));

        $this->assertSame([], $collector->codes());
    }

    public function test_does_not_flag_when_microdata_present(): void
    {
        $collector = $this->runOn($this->pageWithStructuredData(jsonLdTypes: [], hasMicrodata: true));

        $this->assertSame([], $collector->codes());
    }

    public function test_flags_when_no_json_ld_and_no_microdata(): void
    {
        $codes = $this->runOn($this->pageWithStructuredData(jsonLdTypes: [], hasMicrodata: false))->codes();

        $this->assertSame(['schema_org_missing'], $codes);
    }

    public function test_skips_non_html_responses(): void
    {
        $collector = $this->runOn($this->pageWithStructuredData(
            jsonLdTypes: [],
            hasMicrodata: false,
            contentType: 'application/pdf',
        ));

        $this->assertSame([], $collector->codes());
    }

    public function test_skips_pages_without_metadata(): void
    {
        $collector = $this->runOn($this->basePage('text/html', 200));

        $this->assertSame([], $collector->codes());
    }

    public function test_skips_failed_responses(): void
    {
        $collector = $this->runOn($this->pageWithStructuredData(
            jsonLdTypes: [],
            hasMicrodata: false,
            statusCode: 500,
        ));

        $this->assertSame([], $collector->codes());
    }

    private function runOn(Page $page): InMemoryIssueCollector
    {
        $signals = new LegacyPageToPageSignals($page);
        $collector = new InMemoryIssueCollector();

        (new StructuredDataAnalyzer())->analyze($signals, $collector);

        return $collector;
    }

    /** @param string[] $jsonLdTypes */
    private function pageWithStructuredData(
        array $jsonLdTypes,
        bool $hasMicrodata = false,
        string $contentType = 'text/html; charset=utf-8',
        int $statusCode = 200,
    ): Page {
        $page = $this->basePage($contentType, $statusCode);
        $page->enrichWithMetadata(new PageMetadata(
            title: 'Title',
            metaDescription: 'Description',
            h1s: ['Heading'],
            h2s: [],
            headingHierarchy: [['level' => 1, 'text' => 'Heading']],
            charset: 'utf-8',
            viewport: 'width=device-width, initial-scale=1',
            ogTitle: null,
            ogDescription: null,
            ogImage: null,
            wordCount: 200,
            lang: 'en',
            twitterCard: null,
            twitterTitle: null,
            twitterDescription: null,
            twitterImage: null,
            jsonLdTypes: $jsonLdTypes,
            hasMicrodata: $hasMicrodata,
        ));

        return $page;
    }

    private function basePage(string $contentType, int $statusCode): Page
    {
        $url = Url::fromString('https://example.com/');
        $response = new PageResponse(
            statusCode: new HttpStatusCode($statusCode),
            headers: ['content-type' => $contentType],
            body: '<html><body>ok</body></html>',
            contentType: $contentType,
            bodySize: 28,
            responseTime: 0.1,
            finalUrl: $url,
        );

        return Page::fromCrawl(
            id: PageId::generate(),
            auditId: AuditId::generate(),
            url: $url,
            response: $response,
            redirectChain: RedirectChain::none(),
            crawlDepth: 0,
        );
    }
}
