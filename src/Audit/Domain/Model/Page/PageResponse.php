<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Page;

use SeoSpider\Audit\Domain\Model\HttpStatusCode;
use SeoSpider\Audit\Domain\Model\Url;

final readonly class PageResponse
{
    /**
     * @param array<string, string|string[]> $headers
     */
    public function __construct(
        private HttpStatusCode $statusCode,
        private array $headers,
        private ?string $body,
        private ?string $contentType,
        private int $bodySize,
        private float $responseTime,
        private ?Url $finalUrl,
    ) {
    }

    public function statusCode(): HttpStatusCode
    {
        return $this->statusCode;
    }

    public function header(string $name): ?string
    {
        $lower = strtolower($name);

        foreach ($this->headers as $key => $value) {
            if (strtolower($key) === $lower) {
                return is_array($value) ? array_first($value) : $value;
            }
        }

        return null;
    }

    /**
     * @return array<string, string|string[]>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    public function body(): ?string
    {
        return $this->body;
    }

    public function contentType(): ?string
    {
        return $this->contentType;
    }

    public function bodySize(): int
    {
        return $this->bodySize;
    }

    public function responseTime(): float
    {
        return $this->responseTime;
    }

    public function finalUrl(): ?Url
    {
        return $this->finalUrl;
    }

    public function isHtml(): bool
    {
        if ($this->contentType === null) {
            return false;
        }

        return str_contains(strtolower($this->contentType), 'text/html');
    }

    public function wasRedirected(): bool
    {
        return $this->finalUrl !== null;
    }
}
