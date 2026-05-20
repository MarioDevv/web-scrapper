<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Domain\Model\Analysis;

use SeoSpider\Auditing\Domain\Model\Issue\Issue;
use SeoSpider\Auditing\Domain\Model\Issue\IssueCategory;
use SeoSpider\Auditing\Domain\Model\Issue\IssueId;
use SeoSpider\Auditing\Domain\Model\Issue\IssueSeverity;

final class CanonicalTargetAnalyzer implements SiteAnalyzer
{
    public function analyze(SiteContext $context): void
    {
        foreach ($context->pages() as $page) {
            $this->checkCanonicalTarget($page, $context);
        }
    }

    public function category(): IssueCategory
    {
        return IssueCategory::DIRECTIVES;
    }

    private function checkCanonicalTarget(PageSignals $page, SiteContext $context): void
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

        $context->addPageIssue($page->url(), new Issue(
            id: IssueId::generate(),
            category: IssueCategory::DIRECTIVES,
            severity: $severity,
            code: 'canonical_broken_target',
            message: sprintf('Canonical target is broken: %s.', $reason),
            context: $canonical,
        ));
    }
}
