<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Application\GetAuditPages;

final readonly class GetAuditPagesQuery
{
    /**
     * @param ?string $since ISO-8601 timestamp; when set, the handler
     *                       only returns pages crawled strictly after it
     *                       (delta fetch for the polling UI).
     */
    public function __construct(
        public string $auditId,
        public ?string $since = null,
    ) {
    }
}
