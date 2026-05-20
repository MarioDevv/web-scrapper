<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Domain\Model\Analysis\Signal;

final readonly class PageResponseInfo
{
    /** @param array<string, string|string[]> $headers */
    public function __construct(
        private StatusCode $statusCode,
        private array $headers,
        private ?string $contentType,
        private int $bodySize,
        private float $responseTime,
        private ?string $finalUrl,
    ) {
    }

    public function statusCode(): StatusCode
    {
        return $this->statusCode;
    }

    public function header(string $name): ?string
    {
        $lower = strtolower($name);
        foreach ($this->headers as $key => $value) {
            if (strtolower((string) $key) === $lower) {
                if (is_array($value)) {
                    return $value[0] ?? null;
                }
                return $value;
            }
        }
        return null;
    }

    /** @return array<string, string|string[]> */
    public function headers(): array
    {
        return $this->headers;
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

    public function finalUrl(): ?string
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
