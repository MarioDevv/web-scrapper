<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Infrastructure\InMemory;

use SeoSpider\Audit\Domain\Model\HttpClient;
use SeoSpider\Audit\Domain\Model\HttpRequestFailed;
use SeoSpider\Audit\Domain\Model\Page\FetchOutcome;
use SeoSpider\Audit\Domain\Model\PageFetcher;
use SeoSpider\Audit\Domain\Model\Url;

/**
 * Test-only PageFetcher that delegates to the existing StubHttpClient so
 * concurrent-mode tests can reuse the same response scripting the serial
 * tests already rely on.
 */
final class StubPageFetcher implements PageFetcher
{
    public int $batchesFetched = 0;

    public function __construct(private readonly HttpClient $httpClient)
    {
    }

    public function fetchBatch(array $urls, ?string $userAgent = null): array
    {
        $this->batchesFetched++;

        $outcomes = [];
        foreach ($urls as $url) {
            $key = $url->toString();
            try {
                $result = $this->httpClient->followRedirects($url, $userAgent);
                $outcomes[$key] = FetchOutcome::success($url, $result['response'], $result['chain']);
            } catch (HttpRequestFailed $e) {
                $outcomes[$key] = FetchOutcome::failure($url, $e->getMessage());
            }
        }

        return $outcomes;
    }
}
