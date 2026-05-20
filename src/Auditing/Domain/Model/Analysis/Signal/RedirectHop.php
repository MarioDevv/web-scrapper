<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Domain\Model\Analysis\Signal;

final readonly class RedirectHop
{
    public function __construct(
        private string $from,
        private string $to,
        private StatusCode $statusCode,
    ) {
    }

    public function from(): string
    {
        return $this->from;
    }

    public function to(): string
    {
        return $this->to;
    }

    public function statusCode(): StatusCode
    {
        return $this->statusCode;
    }

    public function isPermanent(): bool
    {
        return $this->statusCode->isPermanentRedirect();
    }
}
