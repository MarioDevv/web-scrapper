<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Infrastructure\Http;

use SeoSpider\Audit\Domain\Model\Page\RedirectHop;
use SeoSpider\Audit\Domain\Model\Url;

/**
 * Internal mutable state tracked by ConcurrentPageFetcher for each in-flight
 * URL: the URL that entered the batch, the URL currently being fetched (which
 * advances through redirects) and the chain of hops seen so far.
 */
final class PendingFetch
{
    /** @var list<RedirectHop> */
    public array $hops = [];

    public function __construct(
        public readonly Url $originalUrl,
        public Url $currentUrl,
    ) {
    }
}
