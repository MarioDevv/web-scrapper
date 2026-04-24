<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Infrastructure\Http;

use SeoSpider\Audit\Domain\Model\HttpStatusCode;
use SeoSpider\Audit\Domain\Model\Page\FetchOutcome;
use SeoSpider\Audit\Domain\Model\Page\PageResponse;
use SeoSpider\Audit\Domain\Model\Page\RedirectChain;
use SeoSpider\Audit\Domain\Model\Page\RedirectHop;
use SeoSpider\Audit\Domain\Model\PageFetcher;
use SeoSpider\Audit\Domain\Model\Url;
use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Throwable;

/**
 * Fetches a batch of URLs in parallel via Symfony HttpClient's streaming API
 * while preserving a per-URL redirect chain. Issues N concurrent requests,
 * polls them collectively and fires follow-up requests for 3xx responses
 * without blocking the rest of the batch.
 */
final class ConcurrentPageFetcher implements PageFetcher
{
    private const int MAX_REDIRECTS = 10;
    private const float DEFAULT_TIMEOUT = 30.0;

    private HttpClientInterface $client;

    public function __construct(?HttpClientInterface $client = null)
    {
        $this->client = $client ?? new CurlHttpClient([
            'max_redirects' => 0,
            'http_version' => '2.0',
        ]);
    }

    public function fetchBatch(array $urls, ?string $userAgent = null): array
    {
        if ($urls === []) {
            return [];
        }

        $options = ['timeout' => self::DEFAULT_TIMEOUT, 'max_redirects' => 0];
        if ($userAgent !== null) {
            $options['headers'] = ['User-Agent' => $userAgent];
        }

        /** @var array<string, PendingFetch> */
        $state = [];
        /** @var array<string, ResponseInterface> */
        $pending = [];
        /** @var array<string, FetchOutcome> */
        $outcomes = [];

        foreach ($urls as $url) {
            $key = $url->toString();
            $state[$key] = new PendingFetch($url, $url);

            try {
                $pending[$key] = $this->client->request('GET', $key, $options);
            } catch (TransportExceptionInterface $e) {
                $outcomes[$key] = FetchOutcome::failure($url, $e->getMessage());
                unset($state[$key]);
            }
        }

        while ($pending !== []) {
            $keyByResponseId = [];
            foreach ($pending as $key => $response) {
                $keyByResponseId[spl_object_id($response)] = $key;
            }

            $progressed = false;
            $stream = $this->client->stream(array_values($pending));

            try {
                foreach ($stream as $response => $chunk) {
                    $key = $keyByResponseId[spl_object_id($response)] ?? null;
                    if ($key === null || !isset($pending[$key], $state[$key])) {
                        continue;
                    }

                    try {
                        if (!$chunk->isLast()) {
                            continue;
                        }
                    } catch (TransportExceptionInterface $e) {
                        $outcomes[$key] = FetchOutcome::failure($state[$key]->originalUrl, $e->getMessage());
                        unset($pending[$key], $state[$key]);
                        $progressed = true;
                        continue 2;
                    }

                    $this->resolveCompleted($key, $response, $pending, $state, $outcomes, $options);
                    $progressed = true;
                    continue 2;
                }
                break;
            } catch (HttpExceptionInterface $e) {
                $errorResponse = $e->getResponse();
                $key = $keyByResponseId[spl_object_id($errorResponse)] ?? null;
                if ($key !== null && isset($pending[$key], $state[$key])) {
                    $this->resolveCompleted($key, $errorResponse, $pending, $state, $outcomes, $options);
                    $progressed = true;
                }
            }

            if (!$progressed) {
                foreach ($pending as $key => $_) {
                    if (!isset($state[$key])) {
                        continue;
                    }
                    $outcomes[$key] = FetchOutcome::failure(
                        $state[$key]->originalUrl,
                        'stream exhausted without completing',
                    );
                }
                break;
            }
        }

        return $outcomes;
    }

    /**
     * @param array<string, ResponseInterface> $pending
     * @param array<string, PendingFetch> $state
     * @param array<string, FetchOutcome> $outcomes
     * @param array<string, mixed> $options
     */
    private function resolveCompleted(
        string $key,
        ResponseInterface $response,
        array &$pending,
        array &$state,
        array &$outcomes,
        array $options,
    ): void {
        $fetch = $state[$key];
        $status = $response->getStatusCode();
        $headers = $response->getHeaders(false);
        $statusCode = new HttpStatusCode($status);

        if ($statusCode->isRedirect()) {
            $location = $this->headerValue($headers, 'location');
            if ($location !== null) {
                $nextUrl = $this->resolveLocation($fetch->currentUrl, $location);

                if ($nextUrl === null) {
                    $outcomes[$key] = FetchOutcome::failure(
                        $fetch->originalUrl,
                        'invalid Location header: ' . $location,
                    );
                    unset($pending[$key], $state[$key]);
                    return;
                }

                $fetch->hops[] = new RedirectHop($fetch->currentUrl, $nextUrl, $statusCode);

                if (count($fetch->hops) >= self::MAX_REDIRECTS) {
                    $pageResponse = $this->buildPageResponse($response, $headers, $status);
                    $outcomes[$key] = FetchOutcome::success(
                        $fetch->originalUrl,
                        $pageResponse,
                        RedirectChain::fromHops($fetch->hops),
                    );
                    unset($pending[$key], $state[$key]);
                    return;
                }

                $fetch->currentUrl = $nextUrl;
                try {
                    $pending[$key] = $this->client->request('GET', $nextUrl->toString(), $options);
                } catch (TransportExceptionInterface $e) {
                    $outcomes[$key] = FetchOutcome::failure($fetch->originalUrl, $e->getMessage());
                    unset($pending[$key], $state[$key]);
                }
                return;
            }
        }

        $pageResponse = $this->buildPageResponse($response, $headers, $status);
        $chain = $fetch->hops === []
            ? RedirectChain::none()
            : RedirectChain::fromHops($fetch->hops);

        $outcomes[$key] = FetchOutcome::success($fetch->originalUrl, $pageResponse, $chain);
        unset($pending[$key], $state[$key]);
    }

    /**
     * @param array<string, string[]> $headers
     */
    private function buildPageResponse(ResponseInterface $response, array $headers, int $status): PageResponse
    {
        $contentType = $this->headerValue($headers, 'content-type');

        $body = null;
        if ($this->shouldDownloadBody($contentType)) {
            try {
                $body = $response->getContent(false);
            } catch (TransportExceptionInterface) {
                $body = null;
            }
        }

        return new PageResponse(
            statusCode: new HttpStatusCode($status),
            headers: $this->normalizeHeaders($headers),
            body: $body,
            contentType: $contentType,
            bodySize: strlen($body ?? ''),
            responseTime: $this->extractResponseTime($response),
            finalUrl: null,
        );
    }

    private function resolveLocation(Url $base, string $location): ?Url
    {
        try {
            return $base->resolve($location);
        } catch (Throwable) {
            return null;
        }
    }

    /** @param array<string, string[]> $headers */
    private function headerValue(array $headers, string $name): ?string
    {
        $lower = strtolower($name);
        foreach ($headers as $k => $values) {
            if (strtolower($k) === $lower) {
                return $values[0] ?? null;
            }
        }

        return null;
    }

    private function shouldDownloadBody(?string $contentType): bool
    {
        if ($contentType === null) {
            return true;
        }
        $lower = strtolower($contentType);

        return str_contains($lower, 'text/')
            || str_contains($lower, 'application/json')
            || str_contains($lower, 'application/xml')
            || str_contains($lower, 'application/xhtml');
    }

    /**
     * @param array<string, string[]> $headers
     * @return array<string, string|string[]>
     */
    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $name => $values) {
            $normalized[$name] = count($values) === 1 ? $values[0] : $values;
        }

        return $normalized;
    }

    private function extractResponseTime(ResponseInterface $response): float
    {
        try {
            $totalTime = $response->getInfo('total_time');

            return is_numeric($totalTime) ? (float) $totalTime * 1000 : 0.0;
        } catch (Throwable) {
            return 0.0;
        }
    }
}
