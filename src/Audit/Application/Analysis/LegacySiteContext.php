<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Application\Analysis;

use SeoSpider\Audit\Domain\Model\Page\Page as LegacyPage;
use SeoSpider\Auditing\Domain\Model\Analysis\PageSignals;
use SeoSpider\Auditing\Domain\Model\Analysis\SiteContext;
use SeoSpider\Auditing\Domain\Model\Issue\Issue;
use SeoSpider\Auditing\Domain\Model\Issue\SiteIssue;

final class LegacySiteContext implements SiteContext
{
    /** @var PageSignals[] */
    private array $signals;

    /** @var array<string, LegacyPage> */
    private array $byUrl;

    /** @var SiteIssue[] */
    private array $siteIssues = [];

    /** @var array<string, Issue[]> */
    private array $bufferedIssuesByUrl = [];

    /** @param LegacyPage[] $pages */
    public function __construct(
        private readonly string $auditId,
        private readonly string $seedUrl,
        array $pages,
    ) {
        $this->signals = [];
        $this->byUrl = [];
        foreach ($pages as $page) {
            $this->signals[] = new LegacyPageToPageSignals($page);
            $this->byUrl[$page->url()->toString()] = $page;
            $finalUrl = $page->response()->finalUrl();
            if ($finalUrl !== null) {
                $this->byUrl[$finalUrl->toString()] = $page;
            }
        }
    }

    public function auditId(): string
    {
        return $this->auditId;
    }

    public function seedUrl(): string
    {
        return $this->seedUrl;
    }

    /** @return PageSignals[] */
    public function pages(): array
    {
        return $this->signals;
    }

    public function pageByUrl(string $url): ?PageSignals
    {
        $page = $this->byUrl[$url] ?? null;
        return $page === null ? null : new LegacyPageToPageSignals($page);
    }

    public function addPageIssue(string $pageUrl, Issue $issue): void
    {
        $page = $this->byUrl[$pageUrl] ?? null;
        if ($page === null) {
            return;
        }
        $this->bufferedIssuesByUrl[$page->url()->toString()][] = $issue;
    }

    public function addSiteIssue(SiteIssue $issue): void
    {
        $this->siteIssues[] = $issue;
    }

    /** @return SiteIssue[] */
    public function siteIssues(): array
    {
        return $this->siteIssues;
    }

    /** @return array<string, Issue[]> */
    public function bufferedPageIssues(): array
    {
        return $this->bufferedIssuesByUrl;
    }
}
