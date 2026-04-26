<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Analyzer;

use SeoSpider\Audit\Domain\Model\Page\Issue;
use SeoSpider\Audit\Domain\Model\Page\IssueCategory;
use SeoSpider\Audit\Domain\Model\Page\IssueId;
use SeoSpider\Audit\Domain\Model\Page\IssueSeverity;
use SeoSpider\Audit\Domain\Model\Page\Page;

final class CanonicalTargetAnalyzer implements SiteAnalyzer
{
    public function analyze(SiteAuditContext $context): void
    {
        foreach ($context->pages as $page) {
            $this->checkCanonicalTarget($page, $context);
        }
    }

    public function category(): IssueCategory
    {
        return IssueCategory::DIRECTIVES;
    }

    private function checkCanonicalTarget(Page $page, SiteAuditContext $context): void
    {
        $directives = $page->directives();
        if ($directives === null || !$directives->hasCanonical()) {
            return;
        }

        $canonical = $directives->canonical();
        if ($canonical === null || $directives->isSelfCanonical($page->url())) {
            return;
        }

        $target = $context->pageByUrl($canonical);
        if ($target === null) {
            // Target was not crawled. It may be intentional (cross-domain
            // canonical) so we do not flag it from this analyzer; the
            // per-page DirectiveAnalyzer already surfaces non-self
            // canonicals as INFO.
            return;
        }

        $status = $target->response()->statusCode();
        $reason = null;
        $severity = null;

        if ($status->isClientError() || $status->isServerError()) {
            $reason = sprintf('target returned HTTP %d', $status->code());
            $severity = IssueSeverity::WARNING;
        } elseif ($target->redirectChain()->length() > 0) {
            $reason = 'target redirects further (' . $target->redirectChain()->length() . ' hop(s))';
            $severity = IssueSeverity::WARNING;
        } else {
            $targetDirectives = $target->directives();
            if ($targetDirectives !== null && $targetDirectives->noindex()) {
                $reason = 'target is noindexed';
                $severity = IssueSeverity::WARNING;
            }
        }

        if ($reason === null || $severity === null) {
            return;
        }

        $page->addIssue(new Issue(
            id: IssueId::generate(),
            category: IssueCategory::DIRECTIVES,
            severity: $severity,
            code: 'canonical_broken_target',
            message: sprintf('Canonical target is broken: %s.', $reason),
            context: $canonical->toString(),
        ));
    }
}
