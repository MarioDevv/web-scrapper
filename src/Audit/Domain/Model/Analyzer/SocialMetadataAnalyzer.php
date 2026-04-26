<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Analyzer;

use SeoSpider\Audit\Domain\Model\Page\Issue;
use SeoSpider\Audit\Domain\Model\Page\IssueCategory;
use SeoSpider\Audit\Domain\Model\Page\IssueId;
use SeoSpider\Audit\Domain\Model\Page\IssueSeverity;
use SeoSpider\Audit\Domain\Model\Page\Page;

final class SocialMetadataAnalyzer implements Analyzer
{
    public function analyze(Page $page): void
    {
        if (!$page->isHtml() || !$page->response()->statusCode()->isSuccessful()) {
            return;
        }

        $metadata = $page->metadata();
        if ($metadata === null) {
            return;
        }

        $missing = [];
        if (!self::isPresent($metadata->ogTitle())) {
            $missing[] = 'og:title';
        }
        if (!self::isPresent($metadata->ogDescription())) {
            $missing[] = 'og:description';
        }
        if (!self::isPresent($metadata->ogImage())) {
            $missing[] = 'og:image';
        }

        if ($missing === []) {
            return;
        }

        $page->addIssue(new Issue(
            id: IssueId::generate(),
            category: IssueCategory::METADATA,
            severity: IssueSeverity::NOTICE,
            code: 'open_graph_incomplete',
            message: sprintf('Open Graph metadata is incomplete: missing %s.', implode(', ', $missing)),
        ));
    }

    public function category(): IssueCategory
    {
        return IssueCategory::METADATA;
    }

    private static function isPresent(?string $value): bool
    {
        return $value !== null && trim($value) !== '';
    }
}
