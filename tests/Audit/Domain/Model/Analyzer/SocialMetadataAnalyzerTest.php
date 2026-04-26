<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Domain\Model\Analyzer;

use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Domain\Model\Analyzer\SocialMetadataAnalyzer;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\HttpStatusCode;
use SeoSpider\Audit\Domain\Model\Page\Page;
use SeoSpider\Audit\Domain\Model\Page\PageId;
use SeoSpider\Audit\Domain\Model\Page\PageMetadata;
use SeoSpider\Audit\Domain\Model\Page\PageResponse;
use SeoSpider\Audit\Domain\Model\Page\RedirectChain;
use SeoSpider\Audit\Domain\Model\Url;

final class SocialMetadataAnalyzerTest extends TestCase
{
    public function test_does_not_flag_when_all_three_og_tags_present(): void
    {
        $page = $this->pageWithMetadata(
            ogTitle: 'Title',
            ogDescription: 'A description.',
            ogImage: 'https://example.com/social-card.png',
        );

        (new SocialMetadataAnalyzer())->analyze($page);

        $this->assertSame([], $page->issues());
    }

    public function test_flags_when_og_title_missing(): void
    {
        $page = $this->pageWithMetadata(
            ogTitle: null,
            ogDescription: 'A description.',
            ogImage: 'https://example.com/social-card.png',
        );

        (new SocialMetadataAnalyzer())->analyze($page);

        $issues = $page->issues();
        $this->assertCount(1, $issues);
        $this->assertSame('open_graph_incomplete', $issues[0]->code());
        $this->assertStringContainsString('og:title', $issues[0]->message());
        $this->assertStringNotContainsString('og:description', $issues[0]->message());
    }

    public function test_lists_all_missing_tags_in_message(): void
    {
        $page = $this->pageWithMetadata(
            ogTitle: null,
            ogDescription: null,
            ogImage: null,
        );

        (new SocialMetadataAnalyzer())->analyze($page);

        $issues = $page->issues();
        $this->assertCount(1, $issues);
        $message = $issues[0]->message();
        $this->assertStringContainsString('og:title', $message);
        $this->assertStringContainsString('og:description', $message);
        $this->assertStringContainsString('og:image', $message);
    }

    public function test_treats_blank_strings_as_missing(): void
    {
        $page = $this->pageWithMetadata(
            ogTitle: '   ',
            ogDescription: 'A description.',
            ogImage: 'https://example.com/social-card.png',
        );

        (new SocialMetadataAnalyzer())->analyze($page);

        $issues = $page->issues();
        $this->assertCount(1, $issues);
        $this->assertStringContainsString('og:title', $issues[0]->message());
    }

    public function test_skips_non_html_responses(): void
    {
        $page = $this->pageWithMetadata(
            ogTitle: null,
            ogDescription: null,
            ogImage: null,
            contentType: 'application/pdf',
        );

        (new SocialMetadataAnalyzer())->analyze($page);

        $this->assertSame([], $page->issues());
    }

    public function test_skips_pages_with_no_metadata(): void
    {
        $page = $this->pageWithoutMetadata();

        (new SocialMetadataAnalyzer())->analyze($page);

        $this->assertSame([], $page->issues());
    }

    public function test_skips_failed_responses(): void
    {
        $page = $this->pageWithMetadata(
            ogTitle: null,
            ogDescription: null,
            ogImage: null,
            statusCode: 500,
        );

        (new SocialMetadataAnalyzer())->analyze($page);

        $this->assertSame([], $page->issues());
    }

    private function pageWithMetadata(
        ?string $ogTitle,
        ?string $ogDescription,
        ?string $ogImage,
        string $contentType = 'text/html; charset=utf-8',
        int $statusCode = 200,
    ): Page {
        $page = $this->basePage($contentType, $statusCode);
        $page->enrichWithMetadata(new PageMetadata(
            title: 'A title',
            metaDescription: 'A description.',
            h1s: ['Heading'],
            h2s: [],
            headingHierarchy: [['level' => 1, 'text' => 'Heading']],
            charset: 'utf-8',
            viewport: 'width=device-width, initial-scale=1',
            ogTitle: $ogTitle,
            ogDescription: $ogDescription,
            ogImage: $ogImage,
            wordCount: 200,
            lang: 'en',
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
