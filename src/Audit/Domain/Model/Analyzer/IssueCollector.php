<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Analyzer;

use SeoSpider\Auditing\Domain\Model\Issue\Issue;

/**
 * Write port through which analyzers emit findings, instead of mutating
 * a Page aggregate directly. The implementation decides where issues go
 * (the legacy Page today; the AuditedPage aggregate from sub-phase 3c).
 */
interface IssueCollector
{
    public function add(Issue $issue): void;
}
