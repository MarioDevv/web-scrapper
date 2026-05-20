<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Domain\Exception;

use RuntimeException;

final class PageNotFound extends RuntimeException
{
    public static function withId(string $pageId): self
    {
        return new self(
            sprintf('Page "%s" not found.', $pageId),
        );
    }
}
