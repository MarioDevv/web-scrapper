<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Domain\Model\Analysis;

interface PageSignalsReader
{
    public function findById(string $pageId): ?PageSignals;
}
