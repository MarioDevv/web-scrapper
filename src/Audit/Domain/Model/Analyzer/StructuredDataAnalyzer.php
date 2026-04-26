<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Analyzer;

use SeoSpider\Audit\Domain\Model\Page\Issue;
use SeoSpider\Audit\Domain\Model\Page\IssueCategory;
use SeoSpider\Audit\Domain\Model\Page\IssueId;
use SeoSpider\Audit\Domain\Model\Page\IssueSeverity;
use SeoSpider\Audit\Domain\Model\Page\Page;

final class StructuredDataAnalyzer implements Analyzer
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

        if ($metadata->hasStructuredData()) {
            return;
        }

        $page->addIssue(new Issue(
            id: IssueId::generate(),
            category: IssueCategory::METADATA,
            severity: IssueSeverity::INFO,
            code: 'schema_org_missing',
            message: 'Page declares no schema.org JSON-LD or Microdata. Rich results require structured data.',
        ));
    }

    public function category(): IssueCategory
    {
        return IssueCategory::METADATA;
    }
}
