<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Analyzer;

use SeoSpider\Audit\Domain\Model\Page\Issue;
use SeoSpider\Audit\Domain\Model\Page\IssueCategory;
use SeoSpider\Audit\Domain\Model\Page\IssueId;
use SeoSpider\Audit\Domain\Model\Page\IssueSeverity;
use SeoSpider\Audit\Domain\Model\Page\Page;

final class MetaDataAnalyzer implements Analyzer
{
    public function analyze(Page $page): void
    {
        if (!$page->isHtml() || $page->metadata() === null) {
            return;
        }

        $metadata = $page->metadata();

        $this->checkTitle($page, $metadata);
        $this->checkMetaDescription($page, $metadata);
        $this->checkH1($page, $metadata);
        $this->checkViewport($page, $metadata);
    }

    public function category(): IssueCategory
    {
        return IssueCategory::METADATA;
    }

    private function checkTitle(Page $page, \SeoSpider\Audit\Domain\Model\Page\PageMetadata $metadata): void
    {
        if (!$metadata->hasTitle()) {
            $page->addIssue(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::METADATA,
                severity: IssueSeverity::ERROR,
                code: 'title_missing',
                message: 'Page has no title tag.',
            ));

            return;
        }

        if ($metadata->isTitleTooLong()) {
            $page->addIssue(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::METADATA,
                severity: IssueSeverity::WARNING,
                code: 'title_too_long',
                message: sprintf('Title is %d characters (recommended: max 60).', $metadata->titleLength()),
                context: $metadata->title(),
            ));
        }

        if ($metadata->isTitleTooShort()) {
            $page->addIssue(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::METADATA,
                severity: IssueSeverity::WARNING,
                code: 'title_too_short',
                message: sprintf('Title is %d characters (recommended: min 30).', $metadata->titleLength()),
                context: $metadata->title(),
            ));
        }
    }

    private function checkMetaDescription(Page $page, \SeoSpider\Audit\Domain\Model\Page\PageMetadata $metadata): void
    {
        if (!$metadata->hasMetaDescription()) {
            $page->addIssue(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::METADATA,
                severity: IssueSeverity::WARNING,
                code: 'meta_description_missing',
                message: 'Page has no meta description.',
            ));

            return;
        }

        if ($metadata->isMetaDescriptionTooLong()) {
            $page->addIssue(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::METADATA,
                severity: IssueSeverity::NOTICE,
                code: 'meta_description_too_long',
                message: sprintf('Meta description is %d characters (recommended: max 160).', $metadata->metaDescriptionLength()),
            ));
        }

        if ($metadata->isMetaDescriptionTooShort()) {
            $page->addIssue(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::METADATA,
                severity: IssueSeverity::NOTICE,
                code: 'meta_description_too_short',
                message: sprintf('Meta description is %d characters (recommended: min 70).', $metadata->metaDescriptionLength()),
            ));
        }
    }

    private function checkH1(Page $page, \SeoSpider\Audit\Domain\Model\Page\PageMetadata $metadata): void
    {
        if ($metadata->hasNoH1()) {
            $page->addIssue(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::METADATA,
                severity: IssueSeverity::WARNING,
                code: 'h1_missing',
                message: 'Page has no H1 heading.',
            ));
        }

        if ($metadata->hasMultipleH1s()) {
            $page->addIssue(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::METADATA,
                severity: IssueSeverity::WARNING,
                code: 'h1_multiple',
                message: sprintf('Page has %d H1 headings (recommended: exactly 1).', $metadata->h1Count()),
            ));
        }
    }

    private function checkViewport(Page $page, \SeoSpider\Audit\Domain\Model\Page\PageMetadata $metadata): void
    {
        if (!$metadata->hasViewport()) {
            $page->addIssue(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::METADATA,
                severity: IssueSeverity::WARNING,
                code: 'viewport_missing',
                message: 'Page has no viewport meta tag (required for mobile-friendly rendering).',
            ));
        }
    }
}
