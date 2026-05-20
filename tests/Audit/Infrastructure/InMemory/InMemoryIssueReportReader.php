<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Infrastructure\InMemory;

use SeoSpider\Auditing\Application\Reporting\GetAuditIssueReport\IssueReportData;
use SeoSpider\Auditing\Application\Reporting\GetAuditIssueReport\IssueReportReader;
use SeoSpider\Auditing\Application\Reporting\GetAuditIssueReport\IssueReportRow;
use SeoSpider\Auditing\Domain\Model\Audit\AuditId;
use SeoSpider\Tests\Auditing\Infrastructure\InMemory\InMemoryAuditedPageRepository;

final readonly class InMemoryIssueReportReader implements IssueReportReader
{
    public function __construct(
        private InMemoryPageRepository $pages,
        private InMemoryAuditedPageRepository $auditedPages,
        private ?InMemorySiteIssueRepository $siteIssues = null,
    ) {
    }

    public function read(AuditId $auditId): IssueReportData
    {
        $pages = $this->pages->findByAudit($auditId->value());

        $rows = [];
        foreach ($pages as $page) {
            $audited = $this->auditedPages->findByAuditAndUrl(
                $auditId->value(),
                $page->url()->toString(),
            );
            if ($audited === null) {
                continue;
            }
            foreach ($audited->issues() as $issue) {
                $rows[] = new IssueReportRow(
                    pageId: $page->id()->value(),
                    pageUrl: $page->url()->toString(),
                    code: $issue->code(),
                    severity: $issue->severity()->value,
                    category: $issue->category()->value,
                    context: $issue->context(),
                );
            }
        }

        if ($this->siteIssues !== null) {
            foreach ($this->siteIssues->findByAudit($auditId) as $siteIssue) {
                $rows[] = new IssueReportRow(
                    pageId: null,
                    pageUrl: null,
                    code: $siteIssue->code,
                    severity: $siteIssue->severity->value,
                    category: $siteIssue->category->value,
                    context: $siteIssue->context,
                );
            }
        }

        return new IssueReportData(
            rows: $rows,
            pageCount: count($pages),
        );
    }
}
