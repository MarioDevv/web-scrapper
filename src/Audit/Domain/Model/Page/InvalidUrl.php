<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Page;

use SeoSpider\Shared\Domain\DomainException;

final class InvalidUrl extends DomainException
{
    public static function becauseItCannotBeParsed(string $url): self
    {
        return new self(
            sprintf('The URL "%s" is not a valid RFC 3986 URI.', $url),
        );
    }

    public static function becauseSchemeIsMissing(string $url): self
    {
        return new self(
            sprintf('The URL "%s" is missing a scheme (http/https).', $url),
        );
    }
}
