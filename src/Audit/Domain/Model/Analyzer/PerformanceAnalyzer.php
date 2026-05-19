<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Analyzer;

use SeoSpider\Auditing\Domain\Model\Issue\Issue;
use SeoSpider\Auditing\Domain\Model\Issue\IssueCategory;
use SeoSpider\Auditing\Domain\Model\Issue\IssueId;
use SeoSpider\Auditing\Domain\Model\Issue\IssueSeverity;

final class PerformanceAnalyzer implements Analyzer
{
    private const float SLOW_THRESHOLD_MS = 1000.0;
    private const float VERY_SLOW_THRESHOLD_MS = 3000.0;
    private const int LARGE_PAGE_BYTES = 512 * 1024;

    public function analyze(AnalyzablePage $page): void
    {
        $this->checkResponseTime($page);
        $this->checkPageSize($page);
    }

    public function category(): IssueCategory
    {
        return IssueCategory::PERFORMANCE;
    }

    private function checkResponseTime(AnalyzablePage $page): void
    {
        $time = $page->response()->responseTime();

        if ($time >= self::VERY_SLOW_THRESHOLD_MS) {
            $page->addIssue(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::PERFORMANCE,
                severity: IssueSeverity::ERROR,
                code: 'response_very_slow',
                message: sprintf('Very slow response time: %.0fms (recommended max: %.0fms).', $time, self::SLOW_THRESHOLD_MS),
            ));
        } elseif ($time >= self::SLOW_THRESHOLD_MS) {
            $page->addIssue(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::PERFORMANCE,
                severity: IssueSeverity::WARNING,
                code: 'response_slow',
                message: sprintf('Slow response time: %.0fms (recommended max: %.0fms).', $time, self::SLOW_THRESHOLD_MS),
            ));
        }
    }

    private function checkPageSize(AnalyzablePage $page): void
    {
        if (!$page->isHtml()) {
            return;
        }

        $size = $page->response()->bodySize();

        if ($size > self::LARGE_PAGE_BYTES) {
            $page->addIssue(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::PERFORMANCE,
                severity: IssueSeverity::WARNING,
                code: 'page_too_large',
                message: sprintf('HTML page too large: %s (recommended max: 512KB).', $this->formatBytes($size)),
            ));
        }
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1) . ' MB';
        }
        return number_format($bytes / 1024, 0) . ' KB';
    }
}
