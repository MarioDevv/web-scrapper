<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Domain\Model\Analyzer;

use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Domain\Model\Analyzer\SecurityHeaderAnalyzer;

final class SecurityHeaderAnalyzerTest extends TestCase
{
    use AnalyzerTestHelpers;

    public function test_flags_all_missing_headers_when_response_carries_none(): void
    {
        $page = $this->pageAt('https://example.com/', headers: ['content-type' => 'text/html']);

        (new SecurityHeaderAnalyzer())->analyze($page);

        $codes = $this->codes($page);
        $this->assertContains('csp_missing', $codes);
        $this->assertContains('x_frame_missing', $codes);
        $this->assertContains('x_content_type_missing', $codes);
        $this->assertContains('referrer_policy_missing', $codes);
    }

    public function test_does_not_flag_when_all_security_headers_set(): void
    {
        $page = $this->pageAt('https://example.com/', headers: [
            'content-type' => 'text/html',
            'Content-Security-Policy' => "default-src 'self'",
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
        ]);

        (new SecurityHeaderAnalyzer())->analyze($page);

        $this->assertSame([], $this->codes($page));
    }

    public function test_flags_insecure_referrer_policy(): void
    {
        $page = $this->pageAt('https://example.com/', headers: [
            'content-type' => 'text/html',
            'Content-Security-Policy' => "default-src 'self'",
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'unsafe-url',
        ]);

        (new SecurityHeaderAnalyzer())->analyze($page);

        $codes = $this->codes($page);
        $this->assertContains('referrer_policy_insecure', $codes);
        $this->assertNotContains('referrer_policy_missing', $codes);
    }

    public function test_skips_non_html_responses(): void
    {
        $page = $this->pageAt(
            'https://example.com/file.pdf',
            contentType: 'application/pdf',
            headers: ['content-type' => 'application/pdf'],
        );

        (new SecurityHeaderAnalyzer())->analyze($page);

        $this->assertSame([], $this->codes($page));
    }

    public function test_skips_failed_responses(): void
    {
        $page = $this->pageAt(
            'https://example.com/missing',
            statusCode: 404,
            headers: ['content-type' => 'text/html'],
        );

        (new SecurityHeaderAnalyzer())->analyze($page);

        $this->assertSame([], $this->codes($page));
    }

    /** @return string[] */
    private function codes(\SeoSpider\Audit\Domain\Model\Page\Page $page): array
    {
        return array_map(static fn($i) => $i->code(), $page->issues());
    }
}
