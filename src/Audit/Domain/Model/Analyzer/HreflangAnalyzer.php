<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Analyzer;

use SeoSpider\Audit\Domain\Model\Page\Issue;
use SeoSpider\Audit\Domain\Model\Page\IssueCategory;
use SeoSpider\Audit\Domain\Model\Page\IssueId;
use SeoSpider\Audit\Domain\Model\Page\IssueSeverity;
use SeoSpider\Audit\Domain\Model\Page\Page;

final class HreflangAnalyzer implements Analyzer
{
    public function analyze(Page $page): void
    {
        if (!$page->isHtml() || count($page->hreflangs()) === 0) {
            return;
        }

        $this->checkInvalidLanguageCodes($page);
        $this->checkInvalidRegionCodes($page);
        $this->checkMissingSelfReference($page);
        $this->checkDuplicateLanguageRegion($page);
    }

    public function category(): IssueCategory
    {
        return IssueCategory::HREFLANG;
    }

    private function checkInvalidLanguageCodes(Page $page): void
    {
        foreach ($page->hreflangs() as $hreflang) {
            if (!$hreflang->isValidLanguageCode()) {
                $page->addIssue(new Issue(
                    id: IssueId::generate(),
                    category: IssueCategory::HREFLANG,
                    severity: IssueSeverity::ERROR,
                    code: 'hreflang_invalid_language',
                    message: sprintf('Invalid hreflang language code: "%s".', $hreflang->language()),
                    context: $hreflang->href()->toString(),
                ));
            }
        }
    }

    private function checkInvalidRegionCodes(Page $page): void
    {
        foreach ($page->hreflangs() as $hreflang) {
            if (!$hreflang->isValidRegionCode()) {
                $page->addIssue(new Issue(
                    id: IssueId::generate(),
                    category: IssueCategory::HREFLANG,
                    severity: IssueSeverity::ERROR,
                    code: 'hreflang_invalid_region',
                    message: sprintf('Invalid hreflang region code: "%s".', $hreflang->region()),
                    context: $hreflang->languageRegionCode() . ' → ' . $hreflang->href()->toString(),
                ));
            }
        }
    }

    private function checkMissingSelfReference(Page $page): void
    {
        $hasSelf = false;
        foreach ($page->hreflangs() as $hreflang) {
            if ($hreflang->pointsTo($page->url())) {
                $hasSelf = true;
                break;
            }
        }

        if (!$hasSelf) {
            $page->addIssue(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::HREFLANG,
                severity: IssueSeverity::WARNING,
                code: 'hreflang_missing_self',
                message: 'Missing hreflang self-reference. The page should include itself in hreflang annotations.',
            ));
        }
    }

    private function checkDuplicateLanguageRegion(Page $page): void
    {
        $seen = [];
        foreach ($page->hreflangs() as $hreflang) {
            $code = $hreflang->languageRegionCode();
            if (isset($seen[$code])) {
                $page->addIssue(new Issue(
                    id: IssueId::generate(),
                    category: IssueCategory::HREFLANG,
                    severity: IssueSeverity::WARNING,
                    code: 'hreflang_duplicate',
                    message: sprintf('Duplicate hreflang for "%s".', $code),
                    context: $hreflang->href()->toString(),
                ));
                break; // Report only once
            }
            $seen[$code] = true;
        }
    }
}
