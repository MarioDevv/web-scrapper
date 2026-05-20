<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Analyzer;

use SeoSpider\Auditing\Domain\Model\Issue\Issue;

interface AnalyzablePage extends PageSignals
{
    public function addIssue(Issue $issue): void;
}
