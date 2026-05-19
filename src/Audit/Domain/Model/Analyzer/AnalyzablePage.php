<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Analyzer;

use SeoSpider\Auditing\Domain\Model\Issue\Issue;

/**
 * The page surface an analyzer operates on: the read signals it inspects
 * (via {@see PageSignals}) plus the ability to record findings. Analyzers
 * depend on this interface, not the concrete Page aggregate, so from 3c
 * the Auditing context can supply its own AuditedPage-backed implementation
 * without touching analyzer code.
 */
interface AnalyzablePage extends PageSignals
{
    public function addIssue(Issue $issue): void;
}
