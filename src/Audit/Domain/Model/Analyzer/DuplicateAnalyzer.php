<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Analyzer;

use SeoSpider\Audit\Domain\Model\Page\Issue;
use SeoSpider\Audit\Domain\Model\Page\IssueCategory;
use SeoSpider\Audit\Domain\Model\Page\IssueId;
use SeoSpider\Audit\Domain\Model\Page\IssueSeverity;
use SeoSpider\Audit\Domain\Model\Page\Page;
use SeoSpider\Audit\Domain\Model\Page\PageRepository;

final class DuplicateAnalyzer implements Analyzer
{
    public function __construct(private readonly PageRepository $pageRepository)
    {
    }

    public function analyze(Page $page): void
    {
        if (!$page->isHtml() || $page->fingerprint() === null) {
            return;
        }

        if (!$page->response()->statusCode()->isSuccessful()) {
            return;
        }

        $fingerprints = $this->pageRepository->fingerprintsByAudit($page->auditId());
        $pageUrl = $page->url()->toString();
        $pageFingerprint = $page->fingerprint();

        $exactDuplicates = [];
        $nearDuplicates = [];

        foreach ($fingerprints as $url => $otherFingerprint) {
            if ($url === $pageUrl) {
                continue;
            }

            if ($pageFingerprint->isExactDuplicateOf($otherFingerprint)) {
                $exactDuplicates[] = $url;
            } elseif ($pageFingerprint->isNearDuplicateOf($otherFingerprint)) {
                $nearDuplicates[] = $url;
            }
        }

        if (count($exactDuplicates) > 0) {
            $page->addIssue(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::CONTENT,
                severity: IssueSeverity::WARNING,
                code: 'exact_duplicate',
                message: sprintf('Contenido duplicado exacto con %d página(s).', count($exactDuplicates)),
                context: implode(', ', array_slice($exactDuplicates, 0, 3))
                    . (count($exactDuplicates) > 3 ? sprintf(' (+%d más)', count($exactDuplicates) - 3) : ''),
            ));
        }

        if (count($nearDuplicates) > 0) {
            $page->addIssue(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::CONTENT,
                severity: IssueSeverity::NOTICE,
                code: 'near_duplicate',
                message: sprintf('Contenido casi duplicado con %d página(s).', count($nearDuplicates)),
                context: implode(', ', array_slice($nearDuplicates, 0, 3))
                    . (count($nearDuplicates) > 3 ? sprintf(' (+%d más)', count($nearDuplicates) - 3) : ''),
            ));
        }
    }

    public function category(): IssueCategory
    {
        return IssueCategory::CONTENT;
    }
}
