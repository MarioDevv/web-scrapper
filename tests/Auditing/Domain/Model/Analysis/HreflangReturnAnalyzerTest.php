<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Auditing\Domain\Model\Analysis;

use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Application\Analysis\LegacySiteContext;
use SeoSpider\Audit\Domain\Model\Page\Page;
use SeoSpider\Auditing\Domain\Model\Analysis\HreflangReturnAnalyzer;
use SeoSpider\Tests\Audit\Domain\Model\Analyzer\AnalyzerTestHelpers;

final class HreflangReturnAnalyzerTest extends TestCase
{
    use AnalyzerTestHelpers;

    public function test_does_not_flag_symmetric_cluster(): void
    {
        $es = $this->pageAt('https://example.com/', hreflangs: [
            $this->hreflang('es', 'https://example.com/'),
            $this->hreflang('en', 'https://example.com/en/'),
        ]);
        $en = $this->pageAt('https://example.com/en/', hreflangs: [
            $this->hreflang('es', 'https://example.com/'),
            $this->hreflang('en', 'https://example.com/en/'),
        ]);

        $context = $this->runAnalyzer($es, $en);

        $this->assertSame([], $context->bufferedPageIssues());
    }

    public function test_flags_when_target_does_not_return(): void
    {
        $es = $this->pageAt('https://example.com/', hreflangs: [
            $this->hreflang('en', 'https://example.com/en/'),
        ]);
        $en = $this->pageAt('https://example.com/en/', hreflangs: []);

        $context = $this->runAnalyzer($es, $en);

        $esIssues = $context->bufferedPageIssues()[$es->url()->toString()] ?? [];
        $enIssues = $context->bufferedPageIssues()[$en->url()->toString()] ?? [];
        $this->assertSame(['hreflang_no_return'], array_map(static fn ($i) => $i->code(), $esIssues));
        $this->assertSame([], $enIssues);
    }

    public function test_does_not_flag_when_target_was_not_crawled(): void
    {
        $es = $this->pageAt('https://example.com/', hreflangs: [
            $this->hreflang('en', 'https://example.com/en/'),
        ]);

        $context = $this->runAnalyzer($es);

        $this->assertSame([], $context->bufferedPageIssues());
    }

    public function test_ignores_x_default_in_reciprocity_check(): void
    {
        $es = $this->pageAt('https://example.com/', hreflangs: [
            $this->hreflang('x-default', 'https://example.com/en/'),
        ]);
        $en = $this->pageAt('https://example.com/en/', hreflangs: []);

        $context = $this->runAnalyzer($es, $en);

        $this->assertSame([], $context->bufferedPageIssues());
    }

    public function test_lists_all_missing_returns_in_context(): void
    {
        $es = $this->pageAt('https://example.com/', hreflangs: [
            $this->hreflang('en', 'https://example.com/en/'),
            $this->hreflang('fr', 'https://example.com/fr/'),
        ]);
        $en = $this->pageAt('https://example.com/en/', hreflangs: []);
        $fr = $this->pageAt('https://example.com/fr/', hreflangs: []);

        $context = $this->runAnalyzer($es, $en, $fr);

        $esIssues = $context->bufferedPageIssues()[$es->url()->toString()] ?? [];
        $issueContext = $esIssues[0]->context() ?? '';
        $this->assertStringContainsString('en/', $issueContext);
        $this->assertStringContainsString('fr/', $issueContext);
    }

    public function test_accepts_return_link_via_final_url(): void
    {
        $es = $this->pageAt('https://example.com/', hreflangs: [
            $this->hreflang('en', 'https://example.com/en/'),
        ]);
        $en = $this->pageAt(
            url: 'https://example.com/en/',
            finalUrl: 'https://example.com/en/',
            hreflangs: [
                $this->hreflang('es', 'https://example.com/'),
            ],
        );

        $context = $this->runAnalyzer($es, $en);

        $this->assertSame([], $context->bufferedPageIssues());
    }

    private function runAnalyzer(Page ...$pages): LegacySiteContext
    {
        $context = new LegacySiteContext(
            auditId: $this->buildAuditId()->value(),
            seedUrl: 'https://example.com/',
            pages: $pages,
        );

        (new HreflangReturnAnalyzer())->analyze($context);

        return $context;
    }
}
