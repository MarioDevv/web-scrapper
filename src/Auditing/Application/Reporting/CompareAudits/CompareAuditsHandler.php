<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Application\Reporting\CompareAudits;

use RuntimeException;
use SeoSpider\Auditing\Domain\Model\Audit\AuditRepository;
use SeoSpider\Auditing\Domain\Model\AuditedPage\AuditedPageRepository;
use SeoSpider\Auditing\Domain\Model\Issue\IssueRuleCatalog;
use SeoSpider\Auditing\Domain\Model\Reporting\AuditDiffer;
use SeoSpider\Auditing\Domain\Model\Reporting\PageChange;
use SeoSpider\Auditing\Domain\Model\Reporting\PageRowReader;

final readonly class CompareAuditsHandler
{
    public function __construct(
        private AuditRepository $audits,
        private PageRowReader $pageRows,
        private AuditDiffer $differ,
        private AuditedPageRepository $auditedPages,
    ) {
    }

    public function __invoke(CompareAuditsQuery $query): CompareAuditsResponse
    {
        $base = $this->audits->findById($query->baseAuditId)
            ?? throw new RuntimeException("Base audit {$query->baseAuditId->value()} not found.");
        $target = $this->audits->findById($query->targetAuditId)
            ?? throw new RuntimeException("Target audit {$query->targetAuditId->value()} not found.");

        $diff = $this->differ->diff(
            baseId: $query->baseAuditId,
            targetId: $query->targetAuditId,
            base: $this->pageRows->forAudit($query->baseAuditId),
            target: $this->pageRows->forAudit($query->targetAuditId),
            baseCodesByUrl: $this->auditedPages->issueCodesByUrl($query->baseAuditId->value()),
            targetCodesByUrl: $this->auditedPages->issueCodesByUrl($query->targetAuditId->value()),
        );

        $added = [];
        $removed = [];
        $persistent = [];

        $allChanges = [
            ...$diff->pagesUnchanged,
            ...$diff->pagesMoved,
            ...$diff->pagesAdded,
            ...$diff->pagesRemoved,
        ];

        foreach ($allChanges as $change) {
            foreach ($change->addedIssueCodes as $code) {
                $added[] = $this->row($change, $code);
            }
            foreach ($change->removedIssueCodes as $code) {
                $removed[] = $this->row($change, $code);
            }
            foreach ($change->persistentIssueCodes as $code) {
                $persistent[] = $this->row($change, $code);
            }
        }

        return new CompareAuditsResponse(
            baseAuditId: $query->baseAuditId->value(),
            targetAuditId: $query->targetAuditId->value(),
            host: (string) (parse_url($target->configuration()->seedUrl, PHP_URL_HOST) ?? ""),
            baseCompletedAt: $base->statistics()->completedAt
                ?? throw new RuntimeException('Base audit has no completed_at; cannot compare.'),
            targetCompletedAt: $target->statistics()->completedAt
                ?? throw new RuntimeException('Target audit has no completed_at; cannot compare.'),
            pagesAddedCount: count($diff->pagesAdded),
            pagesRemovedCount: count($diff->pagesRemoved),
            pagesMovedCount: count($diff->pagesMoved),
            pagesUnchangedCount: count($diff->pagesUnchanged),
            issuesAdded: $added,
            issuesRemoved: $removed,
            issuesPersistent: $persistent,
        );
    }

    private function row(PageChange $change, string $code): IssueChangeRow
    {
        $rule = IssueRuleCatalog::forCode($code);

        return new IssueChangeRow(
            pageUrl: $change->url,
            movedFromUrl: $change->movedFromUrl,
            code: $code,
            title: $rule === null ? $code : $rule->title,
            severity: $rule === null ? 'info' : $rule->severity->value,
        );
    }
}
