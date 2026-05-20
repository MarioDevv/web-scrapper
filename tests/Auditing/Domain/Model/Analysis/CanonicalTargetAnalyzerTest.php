<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Auditing\Domain\Model\Analysis;

use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Application\Analysis\LegacySiteContext;
use SeoSpider\Audit\Domain\Model\Page\Page;
use SeoSpider\Auditing\Domain\Model\Analysis\CanonicalTargetAnalyzer;
use SeoSpider\Crawling\Domain\Model\Page\RedirectChain;
use SeoSpider\Tests\Audit\Domain\Model\Analyzer\AnalyzerTestHelpers;

final class CanonicalTargetAnalyzerTest extends TestCase
{
    use AnalyzerTestHelpers;

    public function test_does_not_flag_self_canonical(): void
    {
        $page = $this->pageAt('https://example.com/', canonical: 'https://example.com/');

        $context = $this->runAnalyzer($page);

        $this->assertSame([], $this->codesFor($context, $page));
    }

    public function test_does_not_flag_pages_with_no_canonical(): void
    {
        $page = $this->pageAt('https://example.com/');

        $context = $this->runAnalyzer($page);

        $this->assertSame([], $this->codesFor($context, $page));
    }

    public function test_flags_when_canonical_target_returns_4xx(): void
    {
        $source = $this->pageAt('https://example.com/old/', canonical: 'https://example.com/new/');
        $target = $this->pageAt('https://example.com/new/', statusCode: 404);

        $context = $this->runAnalyzer($source, $target);

        $issues = $context->bufferedPageIssues()[$source->url()->toString()] ?? [];
        $codes = array_map(static fn ($i) => $i->code(), $issues);
        $this->assertSame(['canonical_broken_target'], $codes);
        $this->assertStringContainsString('404', $issues[0]->message());
    }

    public function test_flags_when_canonical_target_returns_5xx(): void
    {
        $source = $this->pageAt('https://example.com/a/', canonical: 'https://example.com/b/');
        $target = $this->pageAt('https://example.com/b/', statusCode: 503);

        $context = $this->runAnalyzer($source, $target);

        $this->assertSame(['canonical_broken_target'], $this->codesFor($context, $source));
    }

    public function test_flags_when_canonical_target_redirects(): void
    {
        $chain = RedirectChain::fromHops([
            $this->redirectHop('https://example.com/b/', 'https://example.com/c/', statusCode: 301),
        ]);

        $source = $this->pageAt('https://example.com/a/', canonical: 'https://example.com/b/');
        $target = $this->pageAt('https://example.com/b/', redirectChain: $chain);

        $context = $this->runAnalyzer($source, $target);

        $issues = $context->bufferedPageIssues()[$source->url()->toString()] ?? [];
        $codes = array_map(static fn ($i) => $i->code(), $issues);
        $this->assertSame(['canonical_broken_target'], $codes);
        $this->assertStringContainsString('redirect', $issues[0]->message());
    }

    public function test_flags_when_canonical_target_is_noindexed(): void
    {
        $source = $this->pageAt('https://example.com/a/', canonical: 'https://example.com/b/');
        $target = $this->pageAt('https://example.com/b/', noindex: true);

        $context = $this->runAnalyzer($source, $target);

        $issues = $context->bufferedPageIssues()[$source->url()->toString()] ?? [];
        $codes = array_map(static fn ($i) => $i->code(), $issues);
        $this->assertSame(['canonical_broken_target'], $codes);
        $this->assertStringContainsString('noindex', $issues[0]->message());
    }

    public function test_does_not_flag_when_canonical_target_is_outside_audit(): void
    {
        $source = $this->pageAt('https://example.com/', canonical: 'https://other.example/');

        $context = $this->runAnalyzer($source);

        $this->assertSame([], $this->codesFor($context, $source));
    }

    private function runAnalyzer(Page ...$pages): LegacySiteContext
    {
        $context = new LegacySiteContext(
            auditId: $this->buildAuditId()->value(),
            seedUrl: 'https://example.com/',
            pages: $pages,
        );

        (new CanonicalTargetAnalyzer())->analyze($context);

        return $context;
    }

    /** @return string[] */
    private function codesFor(LegacySiteContext $context, Page $page): array
    {
        $issues = $context->bufferedPageIssues()[$page->url()->toString()] ?? [];
        return array_map(static fn ($i) => $i->code(), $issues);
    }
}
