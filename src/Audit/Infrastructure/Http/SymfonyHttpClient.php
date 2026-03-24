<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Infrastructure\Http;

use SeoSpider\Audit\Domain\Model\HttpClient;
use SeoSpider\Audit\Domain\Model\HttpRequestFailed;
use SeoSpider\Audit\Domain\Model\HttpStatusCode;
use SeoSpider\Audit\Domain\Model\Page\PageResponse;
use SeoSpider\Audit\Domain\Model\Page\RedirectChain;
use SeoSpider\Audit\Domain\Model\Page\RedirectHop;
use SeoSpider\Audit\Domain\Model\Url;
use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Throwable;

final class SymfonyHttpClient implements HttpClient
{
    private HttpClientInterface $client;

    public function __construct(?HttpClientInterface $client = null)
    {
        $this->client = $client ?? new CurlHttpClient([
            'max_redirects' => 0,
            'http_version' => '2.0',
        ]);
    }

    public function get(Url $url, ?string $userAgent = null, float $timeout = 30.0): PageResponse
    {
        $options = [
            'timeout' => $timeout,
            'max_redirects' => 0,
        ];

        if ($userAgent !== null) {
            $options['headers']['User-Agent'] = $userAgent;
        }

        try {
            $response = $this->client->request('GET', $url->toString(), $options);
            $statusCode = $response->getStatusCode();
            $headers = $response->getHeaders(false);

            $body = null;
            $contentType = $this->extractContentType($headers);

            if ($this->shouldDownloadBody($contentType)) {
                $body = $response->getContent(false);
            }

            return new PageResponse(
                statusCode: new HttpStatusCode($statusCode),
                headers: $this->normalizeHeaders($headers),
                body: $body,
                contentType: $contentType,
                bodySize: strlen($body ?? ''),
                responseTime: $this->extractResponseTime($response),
                finalUrl: null,
            );
        } catch (TransportExceptionInterface $e) {
            throw HttpRequestFailed::becauseOfNetworkError($url, $e->getMessage());
        }
    }

    /** @return array{statusCode: int, responseTime: float} */
    public function head(Url $url, ?string $userAgent = null, float $timeout = 10.0): array
    {
        $options = [
            'timeout' => $timeout,
            'max_redirects' => 5,
        ];

        if ($userAgent !== null) {
            $options['headers']['User-Agent'] = $userAgent;
        }

        try {
            $response = $this->client->request('HEAD', $url->toString(), $options);

            return [
                'statusCode' => $response->getStatusCode(),
                'responseTime' => $this->extractResponseTime($response),
            ];
        } catch (TransportExceptionInterface $e) {
            throw HttpRequestFailed::becauseOfNetworkError($url, $e->getMessage());
        }
    }

    /** @return array{response: PageResponse, chain: RedirectChain} */
    public function followRedirects(Url $url, ?string $userAgent = null, int $maxHops = 10): array
    {
        $hops = [];
        $currentUrl = $url;

        for ($i = 0; $i < $maxHops; $i++) {
            $response = $this->get($currentUrl, $userAgent);

            if (!$response->statusCode()->isRedirect()) {
                return [
                    'response' => $response,
                    'chain' => $hops === [] ? RedirectChain::none() : RedirectChain::fromHops($hops),
                ];
            }

            $location = $response->header('Location');
            if ($location === null) {
                return [
                    'response' => $response,
                    'chain' => $hops === [] ? RedirectChain::none() : RedirectChain::fromHops($hops),
                ];
            }

            $nextUrl = $currentUrl->resolve($location);

            $hops[] = new RedirectHop(
                $currentUrl,
                $nextUrl,
                $response->statusCode(),
            );

            $currentUrl = $nextUrl;
        }

        $finalResponse = $this->get($currentUrl, $userAgent);

        return [
            'response' => $finalResponse,
            'chain' => RedirectChain::fromHops($hops),
        ];
    }

    /**
     * @param array<string, string[]> $headers
     */
    private function extractContentType(array $headers): ?string
    {
        foreach ($headers as $name => $values) {
            if (strtolower($name) === 'content-type') {
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