<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Domain\Model\Analysis;

use SeoSpider\Auditing\Domain\Model\Analysis\Signal\Link;
use SeoSpider\Auditing\Domain\Model\Analysis\Signal\RedirectHop;
use SeoSpider\Auditing\Domain\Model\Issue\Issue;
use SeoSpider\Auditing\Domain\Model\Issue\IssueCategory;
use SeoSpider\Auditing\Domain\Model\Issue\IssueId;
use SeoSpider\Auditing\Domain\Model\Issue\IssueSeverity;

final class BrokenLinkAnalyzer implements Analyzer
{
    public function analyze(PageSignals $signals, IssueCollector $issues): void
    {
        $this->checkStatusCode($signals, $issues);
        $this->checkRedirectChain($signals, $issues);
        $this->checkRedirectLoop($signals, $issues);
        $this->checkMixedProtocols($signals, $issues);
        $this->checkRedirectNotPermanent($signals, $issues);
        $this->checkInternalNofollow($signals, $issues);
    }

    public function category(): IssueCategory
    {
        return IssueCategory::LINKS;
    }

    private function checkStatusCode(PageSignals $signals, IssueCollector $issues): void
    {
        $status = $signals->response()->statusCode();

        if ($status->isClientError()) {
            $issues->add(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::LINKS,
                severity: IssueSeverity::ERROR,
                code: 'client_error',
                message: sprintf('Page returned HTTP %d.', $status->code()),
            ));
        }

        if ($status->isServerError()) {
            $issues->add(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::LINKS,
                severity: IssueSeverity::ERROR,
                code: 'server_error',
                message: sprintf('Page returned HTTP %d.', $status->code()),
            ));
        }
    }

    private function checkRedirectChain(PageSignals $signals, IssueCollector $issues): void
    {
        $chain = $signals->redirectChain();

        if ($chain->length() <= 1) {
            return;
        }

        $issues->add(new Issue(
            id: IssueId::generate(),
            category: IssueCategory::LINKS,
            severity: IssueSeverity::WARNING,
            code: 'redirect_chain',
            message: sprintf('Redirect chain with %d hops detected.', $chain->length()),
            context: implode(' → ', array_map(
                static fn (RedirectHop $hop): string => $hop->from(),
                $chain->hops(),
            )) . ' → ' . $chain->finalUrl(),
        ));
    }

    private function checkRedirectLoop(PageSignals $signals, IssueCollector $issues): void
    {
        if (!$signals->redirectChain()->hasLoop()) {
            return;
        }

        $issues->add(new Issue(
            id: IssueId::generate(),
            category: IssueCategory::LINKS,
            severity: IssueSeverity::ERROR,
            code: 'redirect_loop',
            message: 'Redirect loop detected.',
        ));
    }

    private function checkMixedProtocols(PageSignals $signals, IssueCollector $issues): void
    {
        if (!$signals->redirectChain()->hasMixedProtocols()) {
            return;
        }

        $issues->add(new Issue(
            id: IssueId::generate(),
            category: IssueCategory::LINKS,
            severity: IssueSeverity::WARNING,
            code: 'mixed_protocol_redirect',
            message: 'Redirect chain mixes HTTP and HTTPS.',
        ));
    }

    private function checkRedirectNotPermanent(PageSignals $signals, IssueCollector $issues): void
    {
        $chain = $signals->redirectChain();

        if ($chain->isEmpty() || $chain->isAllPermanent()) {
            return;
        }

        $codes = array_map(
            static fn (RedirectHop $hop): string => (string) $hop->statusCode()->code(),
            $chain->hops(),
        );

        $issues->add(new Issue(
            id: IssueId::generate(),
            category: IssueCategory::LINKS,
            severity: IssueSeverity::NOTICE,
            code: 'redirect_not_permanent',
            message: 'Redirect chain uses non-permanent codes (302/303/307). Use 301/308 for long-lived moves so signals consolidate to the destination.',
            context: 'Status codes in chain: ' . implode(' → ', $codes),
        ));
    }

    private function checkInternalNofollow(PageSignals $signals, IssueCollector $issues): void
    {
        if (!$signals->isHtml()) {
            return;
        }

        $nofollowInternal = array_values(array_filter(
            $signals->internalLinks(),
            static fn (Link $link): bool => $link->isAnchor() && $link->relation() === 'nofollow',
        ));

        $count = count($nofollowInternal);

        if ($count === 0) {
            return;
        }

        $urls = array_map(
            static fn (Link $link): string => $link->targetUrl(),
            array_slice($nofollowInternal, 0, 5),
        );

        $issues->add(new Issue(
            id: IssueId::generate(),
            category: IssueCategory::LINKS,
            severity: IssueSeverity::NOTICE,
            code: 'internal_nofollow',
            message: sprintf('%d internal link(s) with rel="nofollow". Since 2019 Google treats nofollow as a hint, but it can still discourage crawling and pass weaker internal signals.', $count),
            context: implode(', ', $urls) . ($count > 5 ? sprintf(' (+%d more)', $count - 5) : ''),
        ));
    }
}
