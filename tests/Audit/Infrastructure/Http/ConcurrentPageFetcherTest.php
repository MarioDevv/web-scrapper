<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Infrastructure\Http;

use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Domain\Model\Url;
use SeoSpider\Audit\Infrastructure\Http\ConcurrentPageFetcher;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ConcurrentPageFetcherTest extends TestCase
{
    public function test_returns_empty_array_for_empty_batch(): void
    {
        $fetcher = new ConcurrentPageFetcher(new MockHttpClient([]));

        $this->assertSame([], $fetcher->fetchBatch([]));
    }

    public function test_fetches_multiple_urls_concurrently_and_returns_successful_outcomes(): void
    {
        $client = new MockHttpClient([
            new MockResponse('A', ['http_code' => 200, 'response_headers' => ['Content-Type: text/html']]),
            new MockResponse('B', ['http_code' => 200, 'response_headers' => ['Content-Type: text/html']]),
            new MockResponse('C', ['http_code' => 200, 'response_headers' => ['Content-Type: text/html']]),
        ]);
        $fetcher = new ConcurrentPageFetcher($client);

        $urls = [
            Url::fromString('https://a.test/'),
            Url::fromString('https://b.test/'),
            Url::fromString('https://c.test/'),
        ];

        $outcomes = $fetcher->fetchBatch($urls);

        $this->assertCount(3, $outcomes);
        foreach ($urls as $url) {
            $this->assertArrayHasKey($url->toString(), $outcomes);
            $outcome = $outcomes[$url->toString()];
            $this->assertTrue($outcome->isSuccessful());
            $this->assertNotNull($outcome->response);
            $this->assertSame(200, $outcome->response->statusCode()->code());
            $this->assertNotNull($outcome->chain);
            $this->assertTrue($outcome->chain->isEmpty());
        }
    }

    public function test_follows_redirects_and_records_the_full_chain(): void
    {
        $client = new MockHttpClient([
            new MockResponse('', [
                'http_code' => 301,
                'response_headers' => ['Location: https://example.test/final'],
            ]),
            new MockResponse('<html>final</html>', [
                'http_code' => 200,
                'response_headers' => ['Content-Type: text/html'],
            ]),
        ]);
        $fetcher = new ConcurrentPageFetcher($client);

        $outcomes = $fetcher->fetchBatch([Url::fromString('https://example.test/old')]);

        $outcome = $outcomes['https://example.test/old'];
        $this->assertTrue($outcome->isSuccessful());
        $this->assertSame(200, $outcome->response->statusCode()->code());
        $this->assertFalse($outcome->chain->isEmpty());
        $this->assertCount(1, $outcome->chain->hops());
        $this->assertSame(301, $outcome->chain->hops()[0]->statusCode()->code());
    }

    public function test_resolves_relative_location_headers_against_the_current_url(): void
    {
        $client = new MockHttpClient([
            new MockResponse('', ['http_code' => 302, 'response_headers' => ['Location: /new-path']]),
            new MockResponse('ok', ['http_code' => 200, 'response_headers' => ['Content-Type: text/plain']]),
        ]);
        $fetcher = new ConcurrentPageFetcher($client);

        $outcomes = $fetcher->fetchBatch([Url::fromString('https://example.test/old')]);
        $outcome = $outcomes['https://example.test/old'];

        $this->assertTrue($outcome->isSuccessful());
        $this->assertSame(
            'https://example.test/new-path',
            $outcome->chain->hops()[0]->to()->toString(),
        );
    }

    public function test_one_failed_url_does_not_abort_the_batch(): void
    {
        $client = new MockHttpClient([
            new MockResponse('ok', ['http_code' => 200, 'response_headers' => ['Content-Type: text/html']]),
            new MockResponse('', ['error' => 'simulated transport error']),
            new MockResponse('ok', ['http_code' => 200, 'response_headers' => ['Content-Type: text/html']]),
        ]);
        $fetcher = new ConcurrentPageFetcher($client);

        $outcomes = $fetcher->fetchBatch([
            Url::fromString('https://a.test/'),
            Url::fromString('https://b.test/'),
            Url::fromString('https://c.test/'),
        ]);

        $this->assertTrue($outcomes['https://a.test/']->isSuccessful());
        $this->assertFalse($outcomes['https://b.test/']->isSuccessful());
        $this->assertNotNull($outcomes['https://b.test/']->error);
        $this->assertTrue($outcomes['https://c.test/']->isSuccessful());
    }

    public function test_stops_following_redirects_past_the_max_hops_and_returns_the_last_response(): void
    {
        // 11 redirects, each to the next /r1, /r2, ..., /r11
        $responses = [];
        for ($i = 1; $i <= 11; $i++) {
            $responses[] = new MockResponse('', [
                'http_code' => 301,
                'response_headers' => ["Location: /r{$i}"],
            ]);
        }
        $client = new MockHttpClient($responses);
        $fetcher = new ConcurrentPageFetcher($client);

        $outcomes = $fetcher->fetchBatch([Url::fromString('https://example.test/')]);
        $outcome = $outcomes['https://example.test/'];

        $this->assertTrue($outcome->isSuccessful());
        $this->assertLessThanOrEqual(10, count($outcome->chain->hops()));
        $this->assertSame(301, $outcome->response->statusCode()->code());
    }

    public function test_returns_failure_when_redirect_has_no_location_header_is_treated_as_final(): void
    {
        $client = new MockHttpClient([
            new MockResponse('', ['http_code' => 302, 'response_headers' => ['Content-Type: text/html']]),
        ]);
        $fetcher = new ConcurrentPageFetcher($client);

        $outcomes = $fetcher->fetchBatch([Url::fromString('https://example.test/')]);
        $outcome = $outcomes['https://example.test/'];

        // Without a Location header the fetcher treats the 3xx as final.
        $this->assertTrue($outcome->isSuccessful());
        $this->assertSame(302, $outcome->response->statusCode()->code());
        $this->assertTrue($outcome->chain->isEmpty());
    }
}
