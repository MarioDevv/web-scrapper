<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Domain\Model\Reporting;

use SeoSpider\Audit\Domain\Model\Audit\AuditId;

/**
 * Result of comparing a base audit (older) to a target audit (newer).
 * The four page lists are disjoint: a given URL appears in exactly one
 * of pagesAdded, pagesRemoved, pagesMoved, or pagesUnchanged.
 */
final readonly class AuditDiff
{
    /**
     * @param PageChange[] $pagesAdded
     * @param PageChange[] $pagesRemoved
     * @param PageChange[] $pagesMoved
     * @param PageChange[] $pagesUnchanged
     */
    public function __construct(
        public AuditId $baseAuditId,
        public AuditId $targetAuditId,
        public array $pagesAdded,
        public array $pagesRemoved,
        public array $pagesMoved,
        public array $pagesUnchanged,
    ) {
    }
}
