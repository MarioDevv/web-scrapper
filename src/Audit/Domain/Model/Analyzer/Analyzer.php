<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Analyzer;

use SeoSpider\Audit\Domain\Model\Page\IssueCategory;
use SeoSpider\Audit\Domain\Model\Page\Page;

interface Analyzer
{
    public function analyze(Page $page): void;

    public function category(): IssueCategory;
}
