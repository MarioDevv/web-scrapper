<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Auditing\Domain\Model\Analysis;

use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Application\Analysis\LegacyPageToPageSignals;
use SeoSpider\Auditing\Domain\Model\Analysis\SecurityHeaderAnalyzer;
use SeoSpider\Tests\Audit\Domain\Model\Analyzer\AnalyzerTestHelpers;

final class SecurityHeaderAnalyzerTest extends TestCase
{
    use AnalyzerTestHelpers;

    public function test_flags_all_missing_headers_when_response_carries_none(): void
    {
        $codes = $this->runOn(
            $this->pageAt('https://example.com/', headers: ['content-type' => 'text/html']),
        )->codes();

        $this->assertContains('csp_missing', $codes);
        $this->assertContains('x_frame_missing', $codes);
        $this->assertContains('x_content_type_missing', $codes);
        $this->assertContains('referrer_policy_missing', $codes);
    }

    public function test_does_not_flag_when_all_security_headers_set(): void
    {
        $collector = $this->runOn($this->pageAt('https://example.com/', headers: [
            'content-type' => 'text/html',
            'Content-Security-Policy' => "default-src 'self'",
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
        ]));

        $this->assertSame([], $collector->codes());
    }

    public function test_flags_insecure_referrer_policy(): void
    {
        $codes = $this->runOn($this->pageAt('https://example.com/', headers: [
            'content-type' => 'text/html',
            'Content-Security-Policy' => "default-src 'self'",
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'unsafe-url',
        ]))->codes();

        $this->assertContains('referrer_policy_insecure', $codes);
        $this->assertNotContains('referrer_policy_missing', $codes);
    }

    public function test_skips_non_html_responses(): void
    {
        $collector = $this->runOn($this->pageAt(
            'https://example.com/file.pdf',
            contentType: 'application/pdf',
            headers: ['content-type' => 'application/pdf'],
        ));

        $this->assertSame([], $collector->codes());
    }

    public function test_skips_failed_responses(): void
    {
        $collector = $this->runOn($this->pageAt(
            'https://example.com/missing',
            statusCode: 404,
            headers: ['content-type' => 'text/html'],
        ));

        $this->assertSame([], $collector->codes());
    }

    private function runOn(\SeoSpider\Audit\Domain\Model\Page\Page $page): InMemoryIssueCollector
    {
        $signals = new LegacyPageToPageSignals($page);
        $collector = new InMemoryIssueCollector();

        (new SecurityHeaderAnalyzer())->analyze($signals, $collector);

        return $collector;
    }
}
