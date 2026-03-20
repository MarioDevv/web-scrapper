<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Audit;

use SeoSpider\Shared\Domain\DomainException;

final class InvalidAuditStateTransition extends DomainException
{
    public static function because(AuditStatus $current, string $action): self
    {
        return new self(
            sprintf(
                'Cannot "%s" an audit that is "%s".',
                $action,
                $current->value,
            ),
        );
    }
}
