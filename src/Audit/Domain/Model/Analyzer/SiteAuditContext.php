<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Analyzer;

use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\Page\Page;
use SeoSpider\Audit\Domain\Model\Page\SiteIssue;
use SeoSpider\Audit\Domain\Model\Url;

final class SiteAuditContext
{
    /** @var SiteIssue[] */
    private array $siteIssues = [];

    /** @param Page[] $pages */
    public function __construct(
        public readonly AuditId $auditId,
        public readonly Url $seedUrl,
        public readonly array $pages,
    ) {
    }

    public function pageByUrl(Url $url): ?Page
    {
        foreach ($this->pages as $page) {
            if ($page->url()->equals($url)) {
                return $page;
            }
            $finalUrl = $page->response()->finalUrl();
            if ($finalUrl !== null && $finalUrl->equals($url)) {
                return $page;
            }
        }

        return null;
    }

    /**
     * Used by analyzers that emit findings about the audit graph as a
     * whole (e.g. orphan URLs that the crawler never reached). The
     * reactor persists these via SiteIssueRepository at the end of
     * the analysis pass.
     */
    public function addSiteIssue(SiteIssue $issue): void
    {
        $this->siteIssues[] = $issue;
    }

    /** @return SiteIssue[] */
    public function siteIssues(): array
    {
        return $this->siteIssues;
    }
}
