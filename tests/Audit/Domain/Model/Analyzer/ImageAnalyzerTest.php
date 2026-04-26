<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Domain\Model\Analyzer;

use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Domain\Model\Analyzer\ImageAnalyzer;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\HttpStatusCode;
use SeoSpider\Audit\Domain\Model\Page\Link;
use SeoSpider\Audit\Domain\Model\Page\LinkRelation;
use SeoSpider\Audit\Domain\Model\Page\LinkType;
use SeoSpider\Audit\Domain\Model\Page\Page;
use SeoSpider\Audit\Domain\Model\Page\PageId;
use SeoSpider\Audit\Domain\Model\Page\PageResponse;
use SeoSpider\Audit\Domain\Model\Page\RedirectChain;
use SeoSpider\Audit\Domain\Model\Url;

/**
 * Only covers the image_missing_dimensions rule introduced in phase A.2.
 * The older img_alt_* rules remain without dedicated unit tests; that
 * is pre-existing tech debt tracked separately.
 */
final class ImageAnalyzerTest extends TestCase
{
    public function test_does_not_flag_when_all_images_have_dimensions(): void
    {
        $page = $this->pageWithImages([
            $this->image('https://example.com/a.png', 'alt a', width: 800, height: 600),
            $this->image('https://example.com/b.png', 'alt b', width: 200, height: 200),
        ]);

        (new ImageAnalyzer())->analyze($page);

        $codes = array_map(static fn($issue) => $issue->code(), $page->issues());
        $this->assertNotContains('image_missing_dimensions', $codes);
    }

    public function test_flags_images_missing_both_dimensions(): void
    {
        $page = $this->pageWithImages([
            $this->image('https://example.com/a.png', 'alt'),
        ]);

        (new ImageAnalyzer())->analyze($page);

        $codes = array_map(static fn($issue) => $issue->code(), $page->issues());
        $this->assertContains('image_missing_dimensions', $codes);
    }

    public function test_flags_images_missing_only_one_dimension(): void
    {
        $page = $this->pageWithImages([
            $this->image('https://example.com/a.png', 'alt', width: 800, height: null),
        ]);

        (new ImageAnalyzer())->analyze($page);

        $codes = array_map(static fn($issue) => $issue->code(), $page->issues());
        $this->assertContains('image_missing_dimensions', $codes);
    }

    public function test_lists_sample_urls_in_context_with_overflow_marker(): void
    {
        $images = [];
        for ($i = 0; $i < 8; $i++) {
            $images[] = $this->image(sprintf('https://example.com/img-%d.png', $i), 'alt');
        }

        $page = $this->pageWithImages($images);

        (new ImageAnalyzer())->analyze($page);

        $issue = array_values(array_filter(
            $page->issues(),
            static fn($i) => $i->code() === 'image_missing_dimensions',
        ))[0];

        $this->assertStringContainsString('8 image(s)', $issue->message());
        $this->assertStringContainsString('+3 more', $issue->context() ?? '');
    }

    /** @param Link[] $images */
    private function pageWithImages(array $images): Page
    {
        $url = Url::fromString('https://example.com/');
        $response = new PageResponse(
            statusCode: new HttpStatusCode(200),
            headers: ['content-type' => 'text/html'],
            body: '<html><body>ok</body></html>',
            contentType: 'text/html',
            bodySize: 28,
            responseTime: 0.1,
            finalUrl: $url,
        );

        $page = Page::fromCrawl(
            id: PageId::generate(),
            auditId: AuditId::generate(),
            url: $url,
            response: $response,
            redirectChain: RedirectChain::none(),
            crawlDepth: 0,
        );
        $page->enrichWithLinks($images);

        return $page;
    }

    private function image(
        string $url,
        ?string $alt,
        ?int $width = null,
        ?int $height = null,
    ): Link {
        return new Link(
            targetUrl: Url::fromString($url),
            type: LinkType::IMAGE,
            anchorText: $alt,
            relation: LinkRelation::FOLLOW,
            isInternal: false,
            width: $width,
            height: $height,
        );
    }
}
