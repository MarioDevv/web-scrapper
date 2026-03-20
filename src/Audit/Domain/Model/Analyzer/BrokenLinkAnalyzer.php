<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Analyzer;

use SeoSpider\Audit\Domain\Model\Page\Issue;
use SeoSpider\Audit\Domain\Model\Page\IssueCategory;
use SeoSpider\Audit\Domain\Model\Page\IssueId;
use SeoSpider\Audit\Domain\Model\Page\IssueSeverity;
use SeoSpider\Audit\Domain\Model\Page\Page;

final class BrokenLinkAnalyzer implements Analyzer
{
    public function analyze(Page $page): void
    {
        $this->checkStatusCode($page);
        $this->checkRedirectChain($page);
        $this->checkRedirectLoop($page);
        $this->checkMixedProtocols($page);
    }

    public function category(): IssueCategory
    {
        return IssueCategory::LINKS;
    }

    private function checkStatusCode(Page $page): void
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

    private function checkRedirectChain(Page $page): void
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

    private function checkRedirectLoop(Page $page): void
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

    private function checkMixedProtocols(Page $page): void
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
}
