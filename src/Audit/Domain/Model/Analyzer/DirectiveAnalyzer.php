<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Analyzer;

use SeoSpider\Audit\Domain\Model\Page\Issue;
use SeoSpider\Audit\Domain\Model\Page\IssueCategory;
use SeoSpider\Audit\Domain\Model\Page\IssueId;
use SeoSpider\Audit\Domain\Model\Page\IssueSeverity;
use SeoSpider\Audit\Domain\Model\Page\Page;

final class DirectiveAnalyzer implements Analyzer
{
    public function analyze(Page $page): void
    {
        if ($page->directives() === null) {
            return;
        }

        $directives = $page->directives();

        $this->checkNoindex($page, $directives);
        $this->checkCanonical($page, $directives);
        $this->checkNoindexWithCanonical($page, $directives);
    }

    public function category(): IssueCategory
    {
        return IssueCategory::DIRECTIVES;
    }

    private function checkNoindex(Page $page, \SeoSpider\Audit\Domain\Model\Page\Directive $directives): void
    {
        if ($directives->noindex()) {
            $page->addIssue(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::DIRECTIVES,
                severity: IssueSeverity::INFO,
                code: 'noindex',
                message: 'Page has noindex directive.',
            ));
        }

        if ($directives->nofollow()) {
            $page->addIssue(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::DIRECTIVES,
                severity: IssueSeverity::INFO,
                code: 'nofollow',
                message: 'Page has nofollow directive.',
            ));
        }
    }

    private function checkCanonical(Page $page, \SeoSpider\Audit\Domain\Model\Page\Directive $directives): void
    {
        if (!$page->isHtml() || !$page->response()->statusCode()->isSuccessful()) {
            return;
        }

        if (!$directives->hasCanonical()) {
            $page->addIssue(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::DIRECTIVES,
                severity: IssueSeverity::NOTICE,
                code: 'canonical_missing',
                message: 'Page has no canonical tag.',
            ));

            return;
        }

        if (!$directives->isSelfCanonical($page->url())) {
            $page->addIssue(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::DIRECTIVES,
                severity: IssueSeverity::INFO,
                code: 'canonical_non_self',
                message: 'Canonical points to a different URL.',
                context: $directives->canonical()?->toString(),
            ));
        }
    }

    private function checkNoindexWithCanonical(Page $page, \SeoSpider\Audit\Domain\Model\Page\Directive $directives): void
    {
        if ($directives->noindex() && $directives->hasCanonical()) {
            $page->addIssue(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::DIRECTIVES,
                severity: IssueSeverity::ERROR,
                code: 'noindex_with_canonical',
                message: 'Page has both noindex and canonical — conflicting directives.',
                context: $directives->canonical()?->toString(),
            ));
        }
    }
}
