<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Application;

use RuntimeException;

final class AuditNotFound extends RuntimeException
{
    public static function withId(string $auditId): self
    {
        return new self(
            sprintf('Audit "%s" not found.', $auditId),
        );
    }
}
