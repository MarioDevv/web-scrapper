<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Analyzer;

use SeoSpider\Auditing\Domain\Model\Issue\Issue;
use SeoSpider\Auditing\Domain\Model\Issue\IssueCategory;
use SeoSpider\Auditing\Domain\Model\Issue\IssueId;
use SeoSpider\Auditing\Domain\Model\Issue\IssueSeverity;

final class StructuredDataAnalyzer implements Analyzer
{
    public function analyze(AnalyzablePage $page): void
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
