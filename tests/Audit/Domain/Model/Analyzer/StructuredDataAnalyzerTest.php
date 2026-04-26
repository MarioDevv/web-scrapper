<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Domain\Model\Analyzer;

use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Domain\Model\Analyzer\StructuredDataAnalyzer;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\HttpStatusCode;
use SeoSpider\Audit\Domain\Model\Page\Page;
use SeoSpider\Audit\Domain\Model\Page\PageId;
use SeoSpider\Audit\Domain\Model\Page\PageMetadata;
use SeoSpider\Audit\Domain\Model\Page\PageResponse;
use SeoSpider\Audit\Domain\Model\Page\RedirectChain;
use SeoSpider\Audit\Domain\Model\Url;

final class StructuredDataAnalyzerTest extends TestCase
{
    public function test_does_not_flag_when_json_ld_types_are_present(): void
    {
        $page = $this->pageWithStructuredData(jsonLdTypes: ['Article', 'Person']);

        (new StructuredDataAnalyzer())->analyze($page);

        $this->assertSame([], $page->issues());
    }

    public function test_does_not_flag_when_microdata_present(): void
    {
        $page = $this->pageWithStructuredData(jsonLdTypes: [], hasMicrodata: true);

        (new StructuredDataAnalyzer())->analyze($page);

        $this->assertSame([], $page->issues());
    }

    public function test_flags_when_no_json_ld_and_no_microdata(): void
    {
        $page = $this->pageWithStructuredData(jsonLdTypes: [], hasMicrodata: false);

        (new StructuredDataAnalyzer())->analyze($page);

        $issues = $page->issues();
        $this->assertCount(1, $issues);
        $this->assertSame('schema_org_missing', $issues[0]->code());
    }

    public function test_skips_non_html_responses(): void
    {
        $page = $this->pageWithStructuredData(
            jsonLdTypes: [],
            hasMicrodata: false,
            contentType: 'application/pdf',
        );

        (new StructuredDataAnalyzer())->analyze($page);

        $this->assertSame([], $page->issues());
    }

    public function test_skips_pages_without_metadata(): void
    {
        $page = $this->pageWithoutMetadata();

        (new StructuredDataAnalyzer())->analyze($page);

        $this->assertSame([], $page->issues());
    }

    public function test_skips_failed_responses(): void
    {
        $page = $this->pageWithStructuredData(
            jsonLdTypes: [],
            hasMicrodata: false,
            statusCode: 500,
        );

        (new StructuredDataAnalyzer())->analyze($page);

        $this->assertSame([], $page->issues());
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

    private function pageWithoutMetadata(): Page
    {
        return $this->basePage('text/html', 200);
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
