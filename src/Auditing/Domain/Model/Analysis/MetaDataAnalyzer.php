<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Domain\Model\Analysis;

use SeoSpider\Auditing\Domain\Model\Analysis\Signal\PageMetadata;
use SeoSpider\Auditing\Domain\Model\Issue\Issue;
use SeoSpider\Auditing\Domain\Model\Issue\IssueCategory;
use SeoSpider\Auditing\Domain\Model\Issue\IssueId;
use SeoSpider\Auditing\Domain\Model\Issue\IssueSeverity;

final class MetaDataAnalyzer implements Analyzer
{
    public function analyze(PageSignals $signals, IssueCollector $issues): void
    {
        if (!$signals->isHtml() || $signals->metadata() === null) {
            return;
        }

        $metadata = $signals->metadata();
        $this->checkTitle($issues, $metadata);
        $this->checkMetaDescription($issues, $metadata);
        $this->checkH1($issues, $metadata);
        $this->checkViewport($issues, $metadata);
        $this->checkHtmlLang($issues, $metadata);
    }

    public function category(): IssueCategory
    {
        return IssueCategory::METADATA;
    }

    private function checkTitle(IssueCollector $issues, PageMetadata $metadata): void
    {
        if (!$metadata->hasTitle()) {
            $issues->add(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::METADATA,
                severity: IssueSeverity::ERROR,
                code: 'title_missing',
                message: 'Page has no title tag.',
            ));
            return;
        }

        if ($metadata->isTitleTooLong()) {
            $issues->add(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::METADATA,
                severity: IssueSeverity::WARNING,
                code: 'title_too_long',
                message: sprintf('Title is %d characters (recommended: max 60).', $metadata->titleLength()),
                context: $metadata->title(),
            ));
        }

        if ($metadata->isTitleTooShort()) {
            $issues->add(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::METADATA,
                severity: IssueSeverity::WARNING,
                code: 'title_too_short',
                message: sprintf('Title is %d characters (recommended: min 30).', $metadata->titleLength()),
                context: $metadata->title(),
            ));
        }
    }

    private function checkMetaDescription(IssueCollector $issues, PageMetadata $metadata): void
    {
        if (!$metadata->hasMetaDescription()) {
            $issues->add(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::METADATA,
                severity: IssueSeverity::WARNING,
                code: 'meta_description_missing',
                message: 'Page has no meta description.',
            ));
            return;
        }

        if ($metadata->isMetaDescriptionTooLong()) {
            $issues->add(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::METADATA,
                severity: IssueSeverity::NOTICE,
                code: 'meta_description_too_long',
                message: sprintf('Meta description is %d characters (recommended: max 160).', $metadata->metaDescriptionLength()),
            ));
        }
    }

    private function checkH1(IssueCollector $issues, PageMetadata $metadata): void
    {
        if ($metadata->hasNoH1()) {
            $issues->add(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::METADATA,
                severity: IssueSeverity::WARNING,
                code: 'h1_missing',
                message: 'Page has no H1 heading.',
            ));
        }

        if ($metadata->hasMultipleH1s()) {
            $issues->add(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::METADATA,
                severity: IssueSeverity::NOTICE,
                code: 'h1_multiple',
                message: sprintf('Page has %d H1 headings. Prefer one main H1 for structural clarity; Google confirms multiple H1s are not a ranking penalty.', $metadata->h1Count()),
            ));
        }
    }

    private function checkViewport(IssueCollector $issues, PageMetadata $metadata): void
    {
        if (!$metadata->hasViewport()) {
            $issues->add(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::METADATA,
                severity: IssueSeverity::WARNING,
                code: 'viewport_missing',
                message: 'Page has no viewport meta tag (required for mobile-friendly rendering).',
            ));
        }
    }

    private function checkHtmlLang(IssueCollector $issues, PageMetadata $metadata): void
    {
        $lang = $metadata->lang();
        if ($lang !== null && trim($lang) !== '') {
            return;
        }

        $issues->add(new Issue(
            id: IssueId::generate(),
            category: IssueCategory::METADATA,
            severity: IssueSeverity::NOTICE,
            code: 'html_lang_missing',
            message: 'The <html> element does not declare a lang attribute.',
        ));
    }
}
