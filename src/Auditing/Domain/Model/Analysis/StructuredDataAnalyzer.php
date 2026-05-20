<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Domain\Model\Analysis;

use SeoSpider\Auditing\Domain\Model\Issue\Issue;
use SeoSpider\Auditing\Domain\Model\Issue\IssueCategory;
use SeoSpider\Auditing\Domain\Model\Issue\IssueId;
use SeoSpider\Auditing\Domain\Model\Issue\IssueSeverity;

final class StructuredDataAnalyzer implements Analyzer
{
    public function analyze(PageSignals $signals, IssueCollector $issues): void
    {
        if (!$signals->isHtml() || !$signals->response()->statusCode()->isSuccessful()) {
            return;
        }

        $metadata = $signals->metadata();
        if ($metadata === null || $metadata->hasStructuredData()) {
            return;
        }

        $issues->add(new Issue(
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
