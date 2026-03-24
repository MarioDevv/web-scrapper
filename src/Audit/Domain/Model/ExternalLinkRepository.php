<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model;

use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\Page\PageId;

interface ExternalLinkRepository
{
    public function exists(AuditId $auditId, Url $url): bool;

    public function save(
        AuditId $auditId,
        Url $url,
        int $statusCode,
        float $responseTime,
        ?string $error,
        PageId $sourcePageId,
        ?string $anchorText,
    ): void;
}