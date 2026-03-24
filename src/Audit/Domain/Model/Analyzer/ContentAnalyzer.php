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
    private const int THIN_CONTENT_THRESHOLD = 200;
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
                message: sprintf('Página con muy poco contenido (%d palabras).', $wordCount),
            ));
        } elseif ($wordCount < self::THIN_CONTENT_THRESHOLD) {
            $page->addIssue(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::CONTENT,
                severity: IssueSeverity::NOTICE,
                code: 'content_thin',
                message: sprintf('Contenido ligero: %d palabras (recomendado: mín. %d).', $wordCount, self::THIN_CONTENT_THRESHOLD),
            ));
        }
    }

    public function category(): IssueCategory
    {
        return IssueCategory::CONTENT;
    }
}
