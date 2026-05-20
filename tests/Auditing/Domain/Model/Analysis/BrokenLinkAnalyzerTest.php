<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Auditing\Domain\Model\Analysis;

use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Application\Analysis\LegacyPageToPageSignals;
use SeoSpider\Auditing\Domain\Model\Analysis\BrokenLinkAnalyzer;
use SeoSpider\Crawling\Domain\Model\Page\LinkRelation;
use SeoSpider\Crawling\Domain\Model\Page\RedirectChain;
use SeoSpider\Tests\Audit\Domain\Model\Analyzer\AnalyzerTestHelpers;

final class BrokenLinkAnalyzerTest extends TestCase
{
    use AnalyzerTestHelpers;

    public function test_flags_client_error(): void
    {
        $collector = $this->runOn($this->pageAt('https://example.com/missing', statusCode: 404));

        $this->assertContains('client_error', $collector->codes());
    }

    public function test_flags_server_error(): void
    {
        $collector = $this->runOn($this->pageAt('https://example.com/oops', statusCode: 500));

        $this->assertContains('server_error', $collector->codes());
    }

    public function test_flags_redirect_chain_with_two_or_more_hops(): void
    {
        $chain = RedirectChain::fromHops([
            $this->redirectHop('https://example.com/a', 'https://example.com/b'),
            $this->redirectHop('https://example.com/b', 'https://example.com/c'),
        ]);

        $collector = $this->runOn($this->pageAt('https://example.com/a', redirectChain: $chain));

        $this->assertContains('redirect_chain', $collector->codes());
    }

    public function test_flags_redirect_loop(): void
    {
        $chain = RedirectChain::fromHops([
            $this->redirectHop('https://example.com/a', 'https://example.com/b'),
            $this->redirectHop('https://example.com/b', 'https://example.com/a'),
        ]);

        $collector = $this->runOn($this->pageAt('https://example.com/a', redirectChain: $chain));

        $this->assertContains('redirect_loop', $collector->codes());
    }

    public function test_flags_mixed_protocol_redirect(): void
    {
        $chain = RedirectChain::fromHops([
            $this->redirectHop('http://example.com/', 'https://example.com/'),
        ]);

        $collector = $this->runOn($this->pageAt('http://example.com/', redirectChain: $chain));

        $this->assertContains('mixed_protocol_redirect', $collector->codes());
    }

    public function test_flags_redirect_not_permanent(): void
    {
        $chain = RedirectChain::fromHops([
            $this->redirectHop('https://example.com/old', 'https://example.com/new', statusCode: 302),
        ]);

        $collector = $this->runOn($this->pageAt('https://example.com/old', redirectChain: $chain));

        $this->assertContains('redirect_not_permanent', $collector->codes());
    }

    public function test_flags_internal_nofollow_anchors(): void
    {
        $collector = $this->runOn($this->pageAt('https://example.com/', links: [
            $this->anchor('https://example.com/private', internal: true, relation: LinkRelation::NOFOLLOW),
        ]));

        $this->assertContains('internal_nofollow', $collector->codes());
    }

    public function test_does_not_flag_when_clean_2xx_no_redirects_no_nofollow(): void
    {
        $collector = $this->runOn($this->pageAt('https://example.com/'));

        $this->assertSame([], $collector->codes());
    }

    private function runOn(\SeoSpider\Audit\Domain\Model\Page\Page $page): InMemoryIssueCollector
    {
        $signals = new LegacyPageToPageSignals($page);
        $collector = new InMemoryIssueCollector();

        (new BrokenLinkAnalyzer())->analyze($signals, $collector);

        return $collector;
    }
}
