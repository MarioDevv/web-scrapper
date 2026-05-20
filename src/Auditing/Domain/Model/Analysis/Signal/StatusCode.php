<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Domain\Model\Analysis\Signal;

final readonly class StatusCode
{
    public function __construct(private int $code)
    {
    }

    public function code(): int
    {
        return $this->code;
    }

    public function isInformational(): bool
    {
        return $this->code >= 100 && $this->code < 200;
    }

    public function isSuccessful(): bool
    {
        return $this->code >= 200 && $this->code < 300;
    }

    public function isRedirect(): bool
    {
        return $this->code >= 300 && $this->code < 400;
    }

    public function isClientError(): bool
    {
        return $this->code >= 400 && $this->code < 500;
    }

    public function isServerError(): bool
    {
        return $this->code >= 500 && $this->code < 600;
    }

    public function isBroken(): bool
    {
        return $this->isClientError() || $this->isServerError();
    }

    public function isPermanentRedirect(): bool
    {
        return $this->code === 301 || $this->code === 308;
    }

    public function isTemporaryRedirect(): bool
    {
        return $this->code === 302 || $this->code === 303 || $this->code === 307;
    }

    public function signalsIntentionalRemoval(): bool
    {
        return $this->code === 410;
    }

    public function isRateLimited(): bool
    {
        return $this->code === 429;
    }
}
