<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Domain\Model\Analyzer;

use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Domain\Model\Analyzer\DuplicateAnalyzer;
use SeoSpider\Audit\Domain\Model\Page\Fingerprint;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\InMemoryPageRepository;

final class DuplicateAnalyzerTest extends TestCase
{
    use AnalyzerTestHelpers;

    public function test_flags_exact_duplicate_when_another_page_has_identical_fingerprint(): void
    {
        $repo = new InMemoryPageRepository();

        $a = $this->pageAt('https://example.com/a');
        $a->enrichWithFingerprint(Fingerprint::fromContent('Identical body content used to test exact duplicate detection.'));

        $b = $this->pageAt('https://example.com/b');
        $b->enrichWithFingerprint(Fingerprint::fromContent('Identical body content used to test exact duplicate detection.'));

        $repo->save($a);
        $repo->save($b);

        (new DuplicateAnalyzer($repo))->analyze($a);

        $this->assertContains('exact_duplicate', $this->codes($a));
    }

    public function test_does_not_flag_when_only_one_page_in_audit(): void
    {
        $repo = new InMemoryPageRepository();
        $a = $this->pageAt('https://example.com/a');
        $a->enrichWithFingerprint(Fingerprint::fromContent('Lonely page.'));
        $repo->save($a);

        (new DuplicateAnalyzer($repo))->analyze($a);

        $this->assertSame([], $this->codes($a));
    }

    public function test_skips_pages_without_fingerprint(): void
    {
        $repo = new InMemoryPageRepository();
        $a = $this->pageAt('https://example.com/a');
        $repo->save($a);

        (new DuplicateAnalyzer($repo))->analyze($a);

        $this->assertSame([], $this->codes($a));
    }

    public function test_skips_failed_responses(): void
    {
        $repo = new InMemoryPageRepository();
        $a = $this->pageAt('https://example.com/a', statusCode: 500);
        $a->enrichWithFingerprint(Fingerprint::fromContent('content'));
        $repo->save($a);

        (new DuplicateAnalyzer($repo))->analyze($a);

        $this->assertSame([], $this->codes($a));
    }

    /** @return string[] */
    private function codes(\SeoSpider\Audit\Domain\Model\Page\Page $page): array
    {
        return array_map(static fn($i) => $i->code(), $page->issues());
    }
}
