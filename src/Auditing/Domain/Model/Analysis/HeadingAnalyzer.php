<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Domain\Model\Analysis;

use SeoSpider\Auditing\Domain\Model\Analysis\Signal\PageMetadata;
use SeoSpider\Auditing\Domain\Model\Issue\Issue;
use SeoSpider\Auditing\Domain\Model\Issue\IssueCategory;
use SeoSpider\Auditing\Domain\Model\Issue\IssueId;
use SeoSpider\Auditing\Domain\Model\Issue\IssueSeverity;

final class HeadingAnalyzer implements Analyzer
{
    public function analyze(PageSignals $signals, IssueCollector $issues): void
    {
        if (!$signals->isHtml() || $signals->metadata() === null) {
            return;
        }

        $metadata = $signals->metadata();
        $this->checkH2($issues, $metadata);
        $this->checkHeadingOrder($issues, $metadata);
    }

    public function category(): IssueCategory
    {
        return IssueCategory::METADATA;
    }

    private function checkH2(IssueCollector $issues, PageMetadata $metadata): void
    {
        if ($metadata->h2s() === []) {
            $issues->add(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::METADATA,
                severity: IssueSeverity::NOTICE,
                code: 'h2_missing',
                message: 'Page has no H2 headings.',
            ));
        }
    }

    private function checkHeadingOrder(IssueCollector $issues, PageMetadata $metadata): void
    {
        $hierarchy = $metadata->headingHierarchy();
        if ($hierarchy === []) {
            return;
        }

        $first = $hierarchy[0];
        if ($first['level'] !== 1) {
            $issues->add(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::METADATA,
                severity: IssueSeverity::WARNING,
                code: 'h1_not_first',
                message: sprintf('First heading is H%d instead of H1.', $first['level']),
                context: $first['text'],
            ));
        }

        $prevLevel = 0;
        foreach ($hierarchy as $heading) {
            if ($heading['level'] > $prevLevel + 1 && $prevLevel > 0) {
                $issues->add(new Issue(
                    id: IssueId::generate(),
                    category: IssueCategory::METADATA,
                    severity: IssueSeverity::NOTICE,
                    code: 'heading_skip',
                    message: sprintf('Heading hierarchy skip: H%d → H%d.', $prevLevel, $heading['level']),
                    context: $heading['text'],
                ));
                break;
            }
            $prevLevel = $heading['level'];
        }
    }
}
