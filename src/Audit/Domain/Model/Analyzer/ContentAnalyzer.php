<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Analyzer;

use SeoSpider\Audit\Domain\Model\Page\Issue;
use SeoSpider\Audit\Domain\Model\Page\IssueCategory;
use SeoSpider\Audit\Domain\Model\Page\IssueId;
use SeoSpider\Audit\Domain\Model\Page\IssueSeverity;
use SeoSpider\Audit\Domain\Model\Page\Page;

final class ContentAnalyzer implements Analyzer
{
    // Google has publicly stated word count is not a ranking factor: short
    // pages (definitions, product pages, landing pages) can rank well. We
    // only flag pages that are plausibly too sparse to answer intent — the
    // legacy "< 200 words" rule is a myth. "Empty" stays; "very thin" fires
    // well below typical short-content baselines.
    private const int THIN_CONTENT_THRESHOLD = 80;
    private const int EMPTY_CONTENT_THRESHOLD = 50;

    public function analyze(Page $page): void
    {
        if (!$page->isHtml() || $page->metadata() === null) {
            return;
        }
        if (!$page->response()->statusCode()->isSuccessful()) {
            return;
        }

        $wordCount = $page->metadata()->wordCount();

        if ($wordCount <= self::EMPTY_CONTENT_THRESHOLD) {
            $page->addIssue(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::CONTENT,
                severity: IssueSeverity::WARNING,
                code: 'content_empty',
                message: sprintf('Page with very little content (%d words).', $wordCount),
            ));
        } elseif ($wordCount < self::THIN_CONTENT_THRESHOLD) {
            $page->addIssue(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::CONTENT,
                severity: IssueSeverity::NOTICE,
                code: 'content_thin',
                message: sprintf('Very short content (%d words). Review whether the page covers search intent — length itself is not a ranking factor.', $wordCount),
            ));
        }
    }

    public function category(): IssueCategory
    {
        return IssueCategory::CONTENT;
    }
}
