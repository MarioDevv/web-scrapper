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
        $this->checkHtmlLang($page, $metadata);
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
                severity: IssueSeverity::NOTICE,
                code: 'h1_multiple',
                message: sprintf('Page has %d H1 headings. Prefer one main H1 for structural clarity; Google confirms multiple H1s are not a ranking penalty.', $metadata->h1Count()),
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

    private function checkHtmlLang(Page $page, \SeoSpider\Audit\Domain\Model\Page\PageMetadata $metadata): void
    {
        $lang = $metadata->lang();

        if ($lang !== null && trim($lang) !== '') {
            return;
        }

        $page->addIssue(new Issue(
            id: IssueId::generate(),
            category: IssueCategory::METADATA,
            severity: IssueSeverity::NOTICE,
            code: 'html_lang_missing',
            message: 'The <html> element does not declare a lang attribute.',
        ));
    }
}
