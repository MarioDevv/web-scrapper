<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Infrastructure\InMemory;

use SeoSpider\Audit\Domain\Model\HttpClient;
use SeoSpider\Audit\Domain\Model\HttpRequestFailed;
use SeoSpider\Audit\Domain\Model\HttpStatusCode;
use SeoSpider\Audit\Domain\Model\Page\PageResponse;
use SeoSpider\Audit\Domain\Model\Page\RedirectChain;
use SeoSpider\Audit\Domain\Model\Url;

final class StubHttpClient implements HttpClient
{
    /** @var array<string, PageResponse> */
    private array $responses = [];

    /** @var array<string, string> */
    private array $failures = [];

    public function respondWith(string $url, PageResponse $response): void
    {
        $this->responses[$url] = $response;
    }

    public function failWith(string $url, string $reason): void
    {
        $this->failures[$url] = $reason;
    }

    public function get(Url $url, ?string $userAgent = null, float $timeout = 30.0): PageResponse
    {
        $key = $url->toString();

        if (isset($this->failures[$key])) {
            throw HttpRequestFailed::becauseOfNetworkError($url, $this->failures[$key]);
        }

        return $this->responses[$key] ?? new PageResponse(
            statusCode: new HttpStatusCode(200),
            headers: ['Content-Type' => 'text/html'],
            body: '<html><head><title>Default</title></head><body></body></html>',
            contentType: 'text/html',
            bodySize: 60,
            responseTime: 100.0,
            finalUrl: null,
        );
    }

    /** @return array{response: PageResponse, chain: RedirectChain} */
    public function followRedirects(Url $url, ?string $userAgent = null, int $maxHops = 10): array
    {
        return [
            'response' => $this->get($url, $userAgent),
            'chain' => RedirectChain::none(),
        ];
    }
}
