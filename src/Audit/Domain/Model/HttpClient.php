<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model;

use SeoSpider\Audit\Domain\Model\Page\PageResponse;
use SeoSpider\Audit\Domain\Model\Page\RedirectChain;

interface HttpClient
{
    /** @throws HttpRequestFailed */
    public function get(Url $url, ?string $userAgent = null, float $timeout = 30.0): PageResponse;

    /**
     * HEAD request — only fetches headers, no body. Returns status code and response time.
     * @return array{statusCode: int, responseTime: float}
     * @throws HttpRequestFailed
     */
    public function head(Url $url, ?string $userAgent = null, float $timeout = 10.0): array;

    /**
     * @return array{response: PageResponse, chain: RedirectChain}
     * @throws HttpRequestFailed
     */
    public function followRedirects(Url $url, ?string $userAgent = null, int $maxHops = 10): array;
}
