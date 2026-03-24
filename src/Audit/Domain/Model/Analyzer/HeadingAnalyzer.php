<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Analyzer;

use SeoSpider\Audit\Domain\Model\Page\Issue;
use SeoSpider\Audit\Domain\Model\Page\IssueCategory;
use SeoSpider\Audit\Domain\Model\Page\IssueId;
use SeoSpider\Audit\Domain\Model\Page\IssueSeverity;
use SeoSpider\Audit\Domain\Model\Page\Page;

final class HeadingAnalyzer implements Analyzer
{
    public function analyze(Page $page): void
    {
        if (!$page->isHtml() || $page->metadata() === null) {
            return;
        }

        $metadata = $page->metadata();
        $this->checkH2($page, $metadata);
        $this->checkHeadingOrder($page, $metadata);
    }

    public function category(): IssueCategory
    {
        return IssueCategory::METADATA;
    }

    private function checkH2(Page $page, \SeoSpider\Audit\Domain\Model\Page\PageMetadata $metadata): void
    {
        $h2s = $metadata->h2s();

        if (count($h2s) === 0) {
            $page->addIssue(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::METADATA,
                severity: IssueSeverity::NOTICE,
                code: 'h2_missing',
                message: 'La página no tiene encabezados H2.',
            ));
        }

        if (count($h2s) > 5) {
            $page->addIssue(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::METADATA,
                severity: IssueSeverity::NOTICE,
                code: 'h2_excessive',
                message: sprintf('La página tiene %d encabezados H2.', count($h2s)),
            ));
        }
    }

    private function checkHeadingOrder(Page $page, \SeoSpider\Audit\Domain\Model\Page\PageMetadata $metadata): void
    {
        $hierarchy = $metadata->headingHierarchy();
        if (count($hierarchy) === 0) {
            return;
        }

        $first = $hierarchy[0];
        if ($first['level'] !== 1) {
            $page->addIssue(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::METADATA,
                severity: IssueSeverity::WARNING,
                code: 'h1_not_first',
                message: sprintf('El primer encabezado es un H%d en lugar de H1.', $first['level']),
                context: $first['text'],
            ));
        }

        $prevLevel = 0;
        foreach ($hierarchy as $heading) {
            if ($heading['level'] > $prevLevel + 1 && $prevLevel > 0) {
                $page->addIssue(new Issue(
                    id: IssueId::generate(),
                    category: IssueCategory::METADATA,
                    severity: IssueSeverity::NOTICE,
                    code: 'heading_skip',
                    message: sprintf('Salto en jerarquía de encabezados: H%d → H%d.', $prevLevel, $heading['level']),
                    context: $heading['text'],
                ));
                break;
            }
            $prevLevel = $heading['level'];
        }
    }
}
