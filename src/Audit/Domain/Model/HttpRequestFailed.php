<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model;

use SeoSpider\Shared\Domain\DomainException;

final class HttpRequestFailed extends DomainException
{
    public static function becauseOfTimeout(Url $url, float $timeout): self
    {
        return new self(
            sprintf('Request to "%s" timed out after %.1f seconds.', $url, $timeout),
        );
    }

    public static function becauseOfNetworkError(Url $url, string $reason): self
    {
        return new self(
            sprintf('Request to "%s" failed: %s', $url, $reason),
        );
    }

    public static function becauseOfDnsFailure(Url $url): self
    {
        return new self(
            sprintf('DNS resolution failed for "%s".', $url->host()),
        );
    }
}
