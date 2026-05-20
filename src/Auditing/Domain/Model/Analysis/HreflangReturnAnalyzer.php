<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Domain\Model\Analysis;

use SeoSpider\Auditing\Domain\Model\Analysis\Signal\Hreflang;
use SeoSpider\Auditing\Domain\Model\Issue\Issue;
use SeoSpider\Auditing\Domain\Model\Issue\IssueCategory;
use SeoSpider\Auditing\Domain\Model\Issue\IssueId;
use SeoSpider\Auditing\Domain\Model\Issue\IssueSeverity;

final class HreflangReturnAnalyzer implements SiteAnalyzer
{
    public function analyze(SiteContext $context): void
    {
        foreach ($context->pages() as $sourcePage) {
            $missing = $this->collectMissingReturns($sourcePage, $context);

            if ($missing === []) {
                continue;
            }

            $context->addPageIssue($sourcePage->url(), new Issue(
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
    private function collectMissingReturns(PageSignals $sourcePage, SiteContext $context): array
    {
        $missing = [];

        foreach ($sourcePage->hreflangs() as $hreflang) {
            if ($hreflang->isXDefault()) {
                continue;
            }

            $targetPage = $context->pageByUrl($hreflang->href());
            if ($targetPage === null) {
                continue;
            }

            if (!$this->targetReturnsTo($targetPage->hreflangs(), $sourcePage)) {
                $missing[] = sprintf(
                    '%s → %s (%s)',
                    $sourcePage->url(),
                    $hreflang->href(),
                    $hreflang->languageRegionCode(),
                );
            }
        }

        return $missing;
    }

    /** @param Hreflang[] $targetHreflangs */
    private function targetReturnsTo(array $targetHreflangs, PageSignals $sourcePage): bool
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
