<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Domain\Model\Analysis;

use SeoSpider\Auditing\Domain\Model\Issue\Issue;
use SeoSpider\Auditing\Domain\Model\Issue\SiteIssue;

interface SiteContext
{
    public function auditId(): string;

    public function seedUrl(): string;

    /** @return PageSignals[] */
    public function pages(): array;

    public function pageByUrl(string $url): ?PageSignals;

    public function addPageIssue(string $pageUrl, Issue $issue): void;

    public function addSiteIssue(SiteIssue $issue): void;
}
