<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Domain\Model\Reporting;

interface PageDetailReader
{
    public function findById(string $pageId): ?PageDetailData;
}
