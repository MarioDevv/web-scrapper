<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Domain\Model\Analyzer;

use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Domain\Model\Analyzer\CanonicalTargetAnalyzer;
use SeoSpider\Audit\Domain\Model\Analyzer\SiteAuditContext;
use SeoSpider\Audit\Domain\Model\Page\RedirectChain;
use SeoSpider\Audit\Domain\Model\Url;

final class CanonicalTargetAnalyzerTest extends TestCase
{
    use SiteAnalyzerTestHelpers;

    public function test_does_not_flag_self_canonical(): void
    {
        $page = $this->pageAt('https://example.com/', canonical: 'https://example.com/');

        $this->runAnalyzer($page);

        $this->assertSame([], $page->issues());
    }

    public function test_does_not_flag_pages_with_no_canonical(): void
    {
        $page = $this->pageAt('https://example.com/');

        $this->runAnalyzer($page);

        $this->assertSame([], $page->issues());
    }

    public function test_flags_when_canonical_target_returns_4xx(): void
    {
        $source = $this->pageAt('https://example.com/old/', canonical: 'https://example.com/new/');
        $target = $this->pageAt('https://example.com/new/', statusCode: 404);

        $this->runAnalyzer($source, $target);

        $codes = array_map(static fn($i) => $i->code(), $source->issues());
        $this->assertSame(['canonical_broken_target'], $codes);
        $this->assertStringContainsString('404', $source->issues()[0]->message());
    }

    public function test_flags_when_canonical_target_returns_5xx(): void
    {
        $source = $this->pageAt('https://example.com/a/', canonical: 'https://example.com/b/');
        $target = $this->pageAt('https://example.com/b/', statusCode: 503);

        $this->runAnalyzer($source, $target);

        $codes = array_map(static fn($i) => $i->code(), $source->issues());
        $this->assertSame(['canonical_broken_target'], $codes);
    }

    public function test_flags_when_canonical_target_redirects(): void
    {
        $chain = RedirectChain::fromHops([
            $this->redirectHop('https://example.com/b/', 'https://example.com/c/', statusCode: 301),
        ]);

        $source = $this->pageAt('https://example.com/a/', canonical: 'https://example.com/b/');
        $target = $this->pageAt('https://example.com/b/', redirectChain: $chain);

        $this->runAnalyzer($source, $target);

        $codes = array_map(static fn($i) => $i->code(), $source->issues());
        $this->assertSame(['canonical_broken_target'], $codes);
        $this->assertStringContainsString('redirect', $source->issues()[0]->message());
    }

    public function test_flags_when_canonical_target_is_noindexed(): void
    {
        $source = $this->pageAt('https://example.com/a/', canonical: 'https://example.com/b/');
        $target = $this->pageAt('https://example.com/b/', noindex: true);

        $this->runAnalyzer($source, $target);

        $codes = array_map(static fn($i) => $i->code(), $source->issues());
        $this->assertSame(['canonical_broken_target'], $codes);
        $this->assertStringContainsString('noindex', $source->issues()[0]->message());
    }

    public function test_does_not_flag_when_canonical_target_is_outside_audit(): void
    {
        $source = $this->pageAt('https://example.com/', canonical: 'https://other.example/');

        $this->runAnalyzer($source);

        $this->assertSame([], $source->issues());
    }

    private function runAnalyzer(\SeoSpider\Audit\Domain\Model\Page\Page ...$pages): void
    {
        $context = new SiteAuditContext(
            auditId: $this->buildAuditId(),
            seedUrl: Url::fromString('https://example.com/'),
            pages: $pages,
        );

        (new CanonicalTargetAnalyzer())->analyze($context);
    }
}
