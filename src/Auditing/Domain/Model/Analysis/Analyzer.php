<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Domain\Model\Analysis;

use SeoSpider\Auditing\Domain\Model\Issue\IssueCategory;

interface Analyzer
{
    public function analyze(PageSignals $signals, IssueCollector $issues): void;

    public function category(): IssueCategory;
}
