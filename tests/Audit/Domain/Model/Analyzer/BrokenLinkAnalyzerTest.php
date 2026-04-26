<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Domain\Model\Analyzer;

use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Domain\Model\Analyzer\BrokenLinkAnalyzer;
use SeoSpider\Audit\Domain\Model\Page\LinkRelation;
use SeoSpider\Audit\Domain\Model\Page\RedirectChain;

final class BrokenLinkAnalyzerTest extends TestCase
{
    use AnalyzerTestHelpers;

    public function test_flags_client_error(): void
    {
        $page = $this->pageAt('https://example.com/missing', statusCode: 404);

        (new BrokenLinkAnalyzer())->analyze($page);

        $this->assertContains('client_error', $this->codes($page));
    }

    public function test_flags_server_error(): void
    {
        $page = $this->pageAt('https://example.com/oops', statusCode: 500);

        (new BrokenLinkAnalyzer())->analyze($page);

        $this->assertContains('server_error', $this->codes($page));
    }

    public function test_flags_redirect_chain_with_two_or_more_hops(): void
    {
        $chain = RedirectChain::fromHops([
            $this->redirectHop('https://example.com/a', 'https://example.com/b'),
            $this->redirectHop('https://example.com/b', 'https://example.com/c'),
        ]);

        $page = $this->pageAt('https://example.com/a', redirectChain: $chain);

        (new BrokenLinkAnalyzer())->analyze($page);

        $this->assertContains('redirect_chain', $this->codes($page));
    }

    public function test_flags_redirect_loop(): void
    {
        $chain = RedirectChain::fromHops([
            $this->redirectHop('https://example.com/a', 'https://example.com/b'),
            $this->redirectHop('https://example.com/b', 'https://example.com/a'),
        ]);

        $page = $this->pageAt('https://example.com/a', redirectChain: $chain);

        (new BrokenLinkAnalyzer())->analyze($page);

        $this->assertContains('redirect_loop', $this->codes($page));
    }

    public function test_flags_mixed_protocol_redirect(): void
    {
        $chain = RedirectChain::fromHops([
            $this->redirectHop('http://example.com/', 'https://example.com/'),
        ]);

        $page = $this->pageAt('http://example.com/', redirectChain: $chain);

        (new BrokenLinkAnalyzer())->analyze($page);

        $this->assertContains('mixed_protocol_redirect', $this->codes($page));
    }

    public function test_flags_redirect_not_permanent(): void
    {
        $chain = RedirectChain::fromHops([
            $this->redirectHop('https://example.com/old', 'https://example.com/new', statusCode: 302),
        ]);

        $page = $this->pageAt('https://example.com/old', redirectChain: $chain);

        (new BrokenLinkAnalyzer())->analyze($page);

        $this->assertContains('redirect_not_permanent', $this->codes($page));
    }

    public function test_flags_internal_nofollow_anchors(): void
    {
        $page = $this->pageAt('https://example.com/', links: [
            $this->anchor('https://example.com/private', internal: true, relation: LinkRelation::NOFOLLOW),
        ]);

        (new BrokenLinkAnalyzer())->analyze($page);

        $this->assertContains('internal_nofollow', $this->codes($page));
    }

    public function test_does_not_flag_when_clean_2xx_no_redirects_no_nofollow(): void
    {
        $page = $this->pageAt('https://example.com/');

        (new BrokenLinkAnalyzer())->analyze($page);

        $this->assertSame([], $this->codes($page));
    }

    /** @return string[] */
    private function codes(\SeoSpider\Audit\Domain\Model\Page\Page $page): array
    {
        return array_map(static fn($i) => $i->code(), $page->issues());
    }
}
