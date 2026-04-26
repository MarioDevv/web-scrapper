<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Analyzer;

use SeoSpider\Audit\Domain\Model\Page\Hreflang;
use SeoSpider\Audit\Domain\Model\Page\Issue;
use SeoSpider\Audit\Domain\Model\Page\IssueCategory;
use SeoSpider\Audit\Domain\Model\Page\IssueId;
use SeoSpider\Audit\Domain\Model\Page\IssueSeverity;
use SeoSpider\Audit\Domain\Model\Page\Page;

final class HreflangReturnAnalyzer implements SiteAnalyzer
{
    public function analyze(SiteAuditContext $context): void
    {
        foreach ($context->pages as $sourcePage) {
            $missing = $this->collectMissingReturns($sourcePage, $context);

            if ($missing === []) {
                continue;
            }

            $sourcePage->addIssue(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::HREFLANG,
                severity: IssueSeverity::WARNING,
                code: 'hreflang_no_return',
                message: sprintf(
                    '%d hreflang reference(s) without a return link. Each target must reference this URL back.',
                    count($missing),
                ),
                context: implode(', ', array_slice($missing, 0, 5))
                    . (count($missing) > 5 ? sprintf(' (+%d more)', count($missing) - 5) : ''),
            ));
        }
    }

    public function category(): IssueCategory
    {
        return IssueCategory::HREFLANG;
    }

    /** @return string[] */
    private function collectMissingReturns(Page $sourcePage, SiteAuditContext $context): array
    {
        $missing = [];

        foreach ($sourcePage->hreflangs() as $hreflang) {
            // x-default has no language partner, the spec does not require
            // a return link from x-default targets to the source page.
            if ($hreflang->isXDefault()) {
                continue;
            }

            $targetPage = $context->pageByUrl($hreflang->href());
            if ($targetPage === null) {
                // Target was not crawled — out of scope for reciprocity.
                continue;
            }

            if (!$this->targetReturnsTo($targetPage->hreflangs(), $sourcePage)) {
                $missing[] = sprintf(
                    '%s → %s (%s)',
                    $sourcePage->url()->toString(),
                    $hreflang->href()->toString(),
                    $hreflang->languageRegionCode(),
                );
            }
        }

        return $missing;
    }

    /** @param Hreflang[] $targetHreflangs */
    private function targetReturnsTo(array $targetHreflangs, Page $sourcePage): bool
    {
        foreach ($targetHreflangs as $hreflang) {
            if ($hreflang->isXDefault()) {
                continue;
            }

            if ($hreflang->pointsTo($sourcePage->url())) {
                return true;
            }

            $finalUrl = $sourcePage->response()->finalUrl();
            if ($finalUrl !== null && $hreflang->pointsTo($finalUrl)) {
                return true;
            }
        }

        return false;
    }
}
