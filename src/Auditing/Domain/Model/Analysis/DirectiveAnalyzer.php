<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Domain\Model\Analysis;

use SeoSpider\Auditing\Domain\Model\Analysis\Signal\Directive;
use SeoSpider\Auditing\Domain\Model\Issue\Issue;
use SeoSpider\Auditing\Domain\Model\Issue\IssueCategory;
use SeoSpider\Auditing\Domain\Model\Issue\IssueId;
use SeoSpider\Auditing\Domain\Model\Issue\IssueSeverity;

final class DirectiveAnalyzer implements Analyzer
{
    public function analyze(PageSignals $signals, IssueCollector $issues): void
    {
        $directives = $signals->directives();
        if ($directives === null) {
            return;
        }

        $this->checkNoindex($issues, $directives);
        $this->checkCanonical($signals, $issues, $directives);
        $this->checkNoindexWithCanonical($issues, $directives);
    }

    public function category(): IssueCategory
    {
        return IssueCategory::DIRECTIVES;
    }

    private function checkNoindex(IssueCollector $issues, Directive $directives): void
    {
        if ($directives->noindex()) {
            $issues->add(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::DIRECTIVES,
                severity: IssueSeverity::INFO,
                code: 'noindex',
                message: 'Page has noindex directive.',
            ));
        }

        if ($directives->nofollow()) {
            $issues->add(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::DIRECTIVES,
                severity: IssueSeverity::INFO,
                code: 'nofollow',
                message: 'Page has nofollow directive.',
            ));
        }
    }

    private function checkCanonical(PageSignals $signals, IssueCollector $issues, Directive $directives): void
    {
        if (!$signals->isHtml() || !$signals->response()->statusCode()->isSuccessful()) {
            return;
        }

        if (!$directives->hasCanonical()) {
            $issues->add(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::DIRECTIVES,
                severity: IssueSeverity::NOTICE,
                code: 'canonical_missing',
                message: 'Page has no canonical tag.',
            ));
            return;
        }

        if (!$directives->isSelfCanonical($signals->url())) {
            $issues->add(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::DIRECTIVES,
                severity: IssueSeverity::INFO,
                code: 'canonical_non_self',
                message: 'Canonical points to a different URL.',
                context: $directives->canonical(),
            ));
        }
    }

    private function checkNoindexWithCanonical(IssueCollector $issues, Directive $directives): void
    {
        if ($directives->noindex() && $directives->hasCanonical()) {
            $issues->add(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::DIRECTIVES,
                severity: IssueSeverity::ERROR,
                code: 'noindex_with_canonical',
                message: 'Page has both noindex and canonical — conflicting directives.',
                context: $directives->canonical(),
            ));
        }
    }
}
