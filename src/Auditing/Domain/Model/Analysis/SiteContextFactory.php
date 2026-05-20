<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Domain\Model\Analysis;

interface SiteContextFactory
{
    public function forAudit(string $auditId, string $seedUrl): ?SiteContext;
}
