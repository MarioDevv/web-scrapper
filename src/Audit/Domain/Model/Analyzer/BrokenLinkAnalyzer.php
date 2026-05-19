<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Analyzer;

use SeoSpider\Auditing\Domain\Model\Issue\Issue;
use SeoSpider\Auditing\Domain\Model\Issue\IssueCategory;
use SeoSpider\Auditing\Domain\Model\Issue\IssueId;
use SeoSpider\Auditing\Domain\Model\Issue\IssueSeverity;
use SeoSpider\Crawling\Domain\Model\Page\LinkRelation;

final class BrokenLinkAnalyzer implements Analyzer
{
    public function analyze(AnalyzablePage $page): void
    {
        $this->checkStatusCode($page);
        $this->checkRedirectChain($page);
        $this->checkRedirectLoop($page);
        $this->checkMixedProtocols($page);
        $this->checkRedirectNotPermanent($page);
        $this->checkInternalNofollow($page);
    }

    public function category(): IssueCategory
    {
        return IssueCategory::LINKS;
    }

    private function checkStatusCode(AnalyzablePage $page): void
    {
        $status = $page->response()->statusCode();

        if ($status->isClientError()) {
            $page->addIssue(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::LINKS,
                severity: IssueSeverity::ERROR,
                code: 'client_error',
                message: sprintf('Page returned HTTP %d.', $status->code()),
            ));
        }

        if ($status->isServerError()) {
            $page->addIssue(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::LINKS,
                severity: IssueSeverity::ERROR,
                code: 'server_error',
                message: sprintf('Page returned HTTP %d.', $status->code()),
            ));
        }
    }

    private function checkRedirectChain(AnalyzablePage $page): void
    {
        $chain = $page->redirectChain();

        if ($chain->length() <= 1) {
            return;
        }

        $page->addIssue(new Issue(
            id: IssueId::generate(),
            category: IssueCategory::LINKS,
            severity: IssueSeverity::WARNING,
            code: 'redirect_chain',
            message: sprintf('Redirect chain with %d hops detected.', $chain->length()),
            context: implode(' → ', array_map(
                static fn($hop) => $hop->from()->toString(),
                $chain->hops(),
            )) . ' → ' . $chain->finalUrl()?->toString(),
        ));
    }

    private function checkRedirectLoop(AnalyzablePage $page): void
    {
        if (!$page->redirectChain()->hasLoop()) {
            return;
        }

        $page->addIssue(new Issue(
            id: IssueId::generate(),
            category: IssueCategory::LINKS,
            severity: IssueSeverity::ERROR,
            code: 'redirect_loop',
            message: 'Redirect loop detected.',
        ));
    }

    private function checkMixedProtocols(AnalyzablePage $page): void
    {
        if (!$page->redirectChain()->hasMixedProtocols()) {
            return;
        }

        $page->addIssue(new Issue(
            id: IssueId::generate(),
            category: IssueCategory::LINKS,
            severity: IssueSeverity::WARNING,
            code: 'mixed_protocol_redirect',
            message: 'Redirect chain mixes HTTP and HTTPS.',
        ));
    }

    private function checkRedirectNotPermanent(AnalyzablePage $page): void
    {
        $chain = $page->redirectChain();

        if ($chain->isEmpty() || $chain->isAllPermanent()) {
            return;
        }

        $codes = array_map(
            static fn($hop) => (string) $hop->statusCode()->code(),
            $chain->hops(),
        );

        $page->addIssue(new Issue(
            id: IssueId::generate(),
            category: IssueCategory::LINKS,
            severity: IssueSeverity::NOTICE,
            code: 'redirect_not_permanent',
            message: 'Redirect chain uses non-permanent codes (302/303/307). Use 301/308 for long-lived moves so signals consolidate to the destination.',
            context: 'Status codes in chain: ' . implode(' → ', $codes),
        ));
    }

    private function checkInternalNofollow(AnalyzablePage $page): void
    {
        if (!$page->isHtml()) {
            return;
        }

        $nofollowInternal = array_filter(
            $page->internalLinks(),
            static fn($link) => $link->isAnchor() && $link->relation() === LinkRelation::NOFOLLOW,
        );

        $count = count($nofollowInternal);

        if ($count === 0) {
            return;
        }

        $urls = array_map(
            static fn($link) => $link->targetUrl()->toString(),
            array_slice($nofollowInternal, 0, 5),
        );

        $page->addIssue(new Issue(
            id: IssueId::generate(),
            category: IssueCategory::LINKS,
            severity: IssueSeverity::NOTICE,
            code: 'internal_nofollow',
            message: sprintf('%d internal link(s) with rel="nofollow". Since 2019 Google treats nofollow as a hint, but it can still discourage crawling and pass weaker internal signals.', $count),
            context: implode(', ', $urls) . ($count > 5 ? sprintf(' (+%d more)', $count - 5) : ''),
        ));
    }
}
