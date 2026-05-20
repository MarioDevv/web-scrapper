<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Domain\Model\Analysis;

interface SitemapIndex
{
    /** @return string[] */
    public function urlsFor(string $auditId): array;
}
