<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Analyzer;

use SeoSpider\Audit\Domain\Model\Page\IssueCategory;

interface Analyzer
{
    public function analyze(AnalyzablePage $page): void;

    public function category(): IssueCategory;
}
