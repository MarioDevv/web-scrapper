<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Domain\Model\Analysis;

use SeoSpider\Auditing\Domain\Model\Issue\Issue;
use SeoSpider\Auditing\Domain\Model\Issue\IssueCategory;
use SeoSpider\Auditing\Domain\Model\Issue\IssueId;
use SeoSpider\Auditing\Domain\Model\Issue\IssueSeverity;

final readonly class DuplicateAnalyzer implements Analyzer
{
    public function __construct(private FingerprintIndex $fingerprints)
    {
    }

    public function analyze(PageSignals $signals, IssueCollector $issues): void
    {
        if (!$signals->isHtml() || $signals->fingerprint() === null) {
            return;
        }

        if (!$signals->response()->statusCode()->isSuccessful()) {
            return;
        }

        $pageFingerprint = $signals->fingerprint();
        $pageUrl = $signals->url();
        $exactDuplicates = [];
        $nearDuplicates = [];

        foreach ($this->fingerprints->forAudit($signals->auditId()) as $url => $other) {
            if ($url === $pageUrl) {
                continue;
            }
            if ($pageFingerprint->isExactDuplicateOf($other)) {
                $exactDuplicates[] = $url;
            } elseif ($pageFingerprint->isNearDuplicateOf($other)) {
                $nearDuplicates[] = $url;
            }
        }

        if ($exactDuplicates !== []) {
            $issues->add(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::CONTENT,
                severity: IssueSeverity::WARNING,
                code: 'exact_duplicate',
                message: sprintf('Exact duplicate content with %d page(s).', count($exactDuplicates)),
                context: implode(', ', array_slice($exactDuplicates, 0, 3))
                    . (count($exactDuplicates) > 3 ? sprintf(' (+%d more)', count($exactDuplicates) - 3) : ''),
            ));
        }

        if ($nearDuplicates !== []) {
            $issues->add(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::CONTENT,
                severity: IssueSeverity::NOTICE,
                code: 'near_duplicate',
                message: sprintf('Near-duplicate content with %d page(s).', count($nearDuplicates)),
                context: implode(', ', array_slice($nearDuplicates, 0, 3))
                    . (count($nearDuplicates) > 3 ? sprintf(' (+%d more)', count($nearDuplicates) - 3) : ''),
            ));
        }
    }

    public function category(): IssueCategory
    {
        return IssueCategory::CONTENT;
    }
}
