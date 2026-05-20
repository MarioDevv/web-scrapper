<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Application\Reporting\CompareAudits;

use DateTimeImmutable;

final readonly class CompareAuditsResponse
{
    /**
     * @param IssueChangeRow[] $issuesAdded
     * @param IssueChangeRow[] $issuesRemoved
     * @param IssueChangeRow[] $issuesPersistent
     */
    public function __construct(
        public string $baseAuditId,
        public string $targetAuditId,
        public string $host,
        public DateTimeImmutable $baseCompletedAt,
        public DateTimeImmutable $targetCompletedAt,
        public int $pagesAddedCount,
        public int $pagesRemovedCount,
        public int $pagesMovedCount,
        public int $pagesUnchangedCount,
        public array $issuesAdded,
        public array $issuesRemoved,
        public array $issuesPersistent,
    ) {
    }
}
