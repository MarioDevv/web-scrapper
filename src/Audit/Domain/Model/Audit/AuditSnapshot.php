<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Audit;

use DateTimeImmutable;

/**
 * Frozen overview of a finished audit, persisted once when the crawl
 * completes so the dashboard can render a 1000-page audit in
 * sub-millisecond time without recomputing aggregations.
 */
final readonly class AuditSnapshot
{
    /** @param array<string, mixed> $overview */
    public function __construct(
        public AuditId $auditId,
        public array $overview,
        public DateTimeImmutable $generatedAt,
    ) {
    }
}
