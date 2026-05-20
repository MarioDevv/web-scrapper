<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Domain\Model\Analysis;

use SeoSpider\Auditing\Domain\Model\Issue\IssueCategory;

interface SiteAnalyzer
{
    public function analyze(SiteContext $context): void;

    public function category(): IssueCategory;
}
