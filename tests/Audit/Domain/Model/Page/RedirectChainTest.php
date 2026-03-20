<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Domain\Model\Page;

use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Domain\Model\HttpStatusCode;
use SeoSpider\Audit\Domain\Model\Page\RedirectChain;
use SeoSpider\Audit\Domain\Model\Page\RedirectHop;
use SeoSpider\Audit\Domain\Model\Url;

final class RedirectChainTest extends TestCase
{
    public function test_none_creates_empty_chain(): void
    {
        $chain = RedirectChain::none();

        $this->assertTrue($chain->isEmpty());
        $this->assertSame(0, $chain->length());
        $this->assertNull($chain->finalUrl());
    }

    public function test_single_hop(): void
    {
        $chain = RedirectChain::fromHops([
            new RedirectHop(
                Url::fromString('https://example.com/old'),
                Url::fromString('https://example.com/new'),
                new HttpStatusCode(301),
            ),
        ]);

        $this->assertFalse($chain->isEmpty());
        $this->assertSame(1, $chain->length());
        $this->assertSame('https://example.com/new', $chain->finalUrl()->toString());
    }

    public function test_multi_hop_chain(): void
    {
        $chain = RedirectChain::fromHops([
            new RedirectHop(
                Url::fromString('https://example.com/a'),
                Url::fromString('https://example.com/b'),
                new HttpStatusCode(301),
            ),
            new RedirectHop(
                Url::fromString('https://example.com/b'),
                Url::fromString('https://example.com/c'),
                new HttpStatusCode(302),
            ),
        ]);

        $this->assertSame(2, $chain->length());
        $this->assertSame('https://example.com/c', $chain->finalUrl()->toString());
    }

    public function test_detects_loop(): void
    {
        $chain = RedirectChain::fromHops([
            new RedirectHop(
                Url::fromString('https://example.com/a'),
                Url::fromString('https://example.com/b'),
                new HttpStatusCode(301),
            ),
            new RedirectHop(
                Url::fromString('https://example.com/b'),
                Url::fromString('https://example.com/a'),
                new HttpStatusCode(301),
            ),
        ]);

        $this->assertTrue($chain->hasLoop());
    }

    public function test_no_loop_in_linear_chain(): void
    {
        $chain = RedirectChain::fromHops([
            new RedirectHop(
                Url::fromString('https://example.com/a'),
                Url::fromString('https://example.com/b'),
                new HttpStatusCode(301),
            ),
            new RedirectHop(
                Url::fromString('https://example.com/b'),
                Url::fromString('https://example.com/c'),
                new HttpStatusCode(301),
            ),
        ]);

        $this->assertFalse($chain->hasLoop());
    }

    public function test_detects_mixed_protocols(): void
    {
        $chain = RedirectChain::fromHops([
            new RedirectHop(
                Url::fromString('http://example.com/page'),
                Url::fromString('https://example.com/page'),
                new HttpStatusCode(301),
            ),
        ]);

        $this->assertTrue($chain->hasMixedProtocols());
    }

    public function test_no_mixed_protocols_when_same_scheme(): void
    {
        $chain = RedirectChain::fromHops([
            new RedirectHop(
                Url::fromString('https://example.com/old'),
                Url::fromString('https://example.com/new'),
                new HttpStatusCode(301),
            ),
        ]);

        $this->assertFalse($chain->hasMixedProtocols());
    }

    public function test_is_all_permanent(): void
    {
        $chain = RedirectChain::fromHops([
            new RedirectHop(
                Url::fromString('https://example.com/a'),
                Url::fromString('https://example.com/b'),
                new HttpStatusCode(301),
            ),
            new RedirectHop(
                Url::fromString('https://example.com/b'),
                Url::fromString('https://example.com/c'),
                new HttpStatusCode(308),
            ),
        ]);

        $this->assertTrue($chain->isAllPermanent());
    }

    public function test_is_not_all_permanent_with_302(): void
    {
        $chain = RedirectChain::fromHops([
            new RedirectHop(
                Url::fromString('https://example.com/a'),
                Url::fromString('https://example.com/b'),
                new HttpStatusCode(301),
            ),
            new RedirectHop(
                Url::fromString('https://example.com/b'),
                Url::fromString('https://example.com/c'),
                new HttpStatusCode(302),
            ),
        ]);

        $this->assertFalse($chain->isAllPermanent());
    }

    public function test_empty_chain_is_not_all_permanent(): void
    {
        $this->assertFalse(RedirectChain::none()->isAllPermanent());
    }
}
