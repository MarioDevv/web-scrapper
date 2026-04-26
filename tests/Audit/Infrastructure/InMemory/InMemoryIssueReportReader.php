<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Infrastructure\InMemory;

use SeoSpider\Audit\Application\GetAuditIssueReport\IssueReportData;
use SeoSpider\Audit\Application\GetAuditIssueReport\IssueReportReader;
use SeoSpider\Audit\Application\GetAuditIssueReport\IssueReportRow;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;

/**
 * Test double that projects an in-memory page repository into the same
 * shape the SQLite reader returns. Lets the existing handler tests keep
 * persisting Page aggregates while the production code path uses a
 * direct SQL query.
 */
final readonly class InMemoryIssueReportReader implements IssueReportReader
{
    public function __construct(private InMemoryPageRepository $pages)
    {
    }

    public function read(AuditId $auditId): IssueReportData
    {
        $pages = $this->pages->findByAudit($auditId);

        $rows = [];
        foreach ($pages as $page) {
            foreach ($page->issues() as $issue) {
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

        return new IssueReportData(
            rows: $rows,
            pageCount: count($pages),
        );
    }
}
