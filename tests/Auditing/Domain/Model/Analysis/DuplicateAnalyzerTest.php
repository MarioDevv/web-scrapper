<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Auditing\Domain\Model\Analysis;

use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Application\Analysis\LegacyPageToPageSignals;
use SeoSpider\Audit\Domain\Model\Page\Page;
use SeoSpider\Auditing\Domain\Model\Analysis\DuplicateAnalyzer;
use SeoSpider\Auditing\Domain\Model\Analysis\Signal\Fingerprint as AuditingFingerprint;
use SeoSpider\Crawling\Domain\Model\Page\Fingerprint;
use SeoSpider\Tests\Audit\Domain\Model\Analyzer\AnalyzerTestHelpers;

final class DuplicateAnalyzerTest extends TestCase
{
    use AnalyzerTestHelpers;

    public function test_flags_exact_duplicate_when_another_page_has_identical_fingerprint(): void
    {
        $body = 'Identical body content used to test exact duplicate detection.';
        $a = $this->pageAt('https://example.com/a');
        $a->enrichWithFingerprint(Fingerprint::fromContent($body));
        $b = $this->pageAt('https://example.com/b');
        $b->enrichWithFingerprint(Fingerprint::fromContent($body));

        $collector = $this->runOn($a, [$b]);

        $this->assertContains('exact_duplicate', $collector->codes());
    }

    public function test_does_not_flag_when_only_one_page_in_audit(): void
    {
        $a = $this->pageAt('https://example.com/a');
        $a->enrichWithFingerprint(Fingerprint::fromContent('Lonely page.'));

        $collector = $this->runOn($a, []);

        $this->assertSame([], $collector->codes());
    }

    public function test_skips_pages_without_fingerprint(): void
    {
        $a = $this->pageAt('https://example.com/a');

        $collector = $this->runOn($a, []);

        $this->assertSame([], $collector->codes());
    }

    public function test_skips_failed_responses(): void
    {
        $a = $this->pageAt('https://example.com/a', statusCode: 500);
        $a->enrichWithFingerprint(Fingerprint::fromContent('content'));

        $collector = $this->runOn($a, []);

        $this->assertSame([], $collector->codes());
    }

    /** @param Page[] $otherPages */
    private function runOn(Page $page, array $otherPages): InMemoryIssueCollector
    {
        $signals = new LegacyPageToPageSignals($page);
        $index = new InMemoryFingerprintIndex();
        $auditId = $page->auditId()->value();

        $self = $page->fingerprint();
        if ($self !== null) {
            $index->put($auditId, $page->url()->toString(), new AuditingFingerprint($self->exactHash(), $self->simHash()));
        }
        foreach ($otherPages as $other) {
            $fp = $other->fingerprint();
            if ($fp !== null) {
                $index->put($auditId, $other->url()->toString(), new AuditingFingerprint($fp->exactHash(), $fp->simHash()));
            }
        }

        $collector = new InMemoryIssueCollector();
        (new DuplicateAnalyzer($index))->analyze($signals, $collector);

        return $collector;
    }
}
