<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model;

use InvalidArgumentException;

final readonly class HttpStatusCode
{
    public function __construct(private int $code)
    {
        if ($code < 100 || $code > 599) {
            throw new InvalidArgumentException(
                sprintf('HTTP status code must be between 100 and 599, got %d.', $code),
            );
        }
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
        return in_array($this->code, [302, 303, 307], true);
    }

    public function signalsIntentionalRemoval(): bool
    {
        return $this->code === 410;
    }

    public function isRateLimited(): bool
    {
        return $this->code === 429;
    }

    public function equals(self $other): bool
    {
        return $this->code === $other->code;
    }

    public function __toString(): string
    {
        return (string) $this->code;
    }
}
