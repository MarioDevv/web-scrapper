<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Analyzer;

use SeoSpider\Audit\Domain\Model\Page\Issue;
use SeoSpider\Audit\Domain\Model\Page\IssueCategory;
use SeoSpider\Audit\Domain\Model\Page\IssueId;
use SeoSpider\Audit\Domain\Model\Page\IssueSeverity;
use SeoSpider\Audit\Domain\Model\Page\Link;
use SeoSpider\Audit\Domain\Model\Page\LinkType;
use SeoSpider\Audit\Domain\Model\Page\Page;

final class TransportSecurityAnalyzer implements Analyzer
{
    private const array MIXED_CONTENT_TYPES = [
        LinkType::SCRIPT,
        LinkType::STYLESHEET,
        LinkType::IFRAME,
        LinkType::IMAGE,
        LinkType::VIDEO,
        LinkType::AUDIO,
        LinkType::FONT,
    ];

    public function analyze(Page $page): void
    {
        $effectiveUrl = $page->response()->finalUrl() ?? $page->url();
        $pageScheme = strtolower($effectiveUrl->scheme());

        if ($pageScheme === 'http') {
            $page->addIssue(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::SECURITY,
                severity: IssueSeverity::ERROR,
                code: 'http_insecure',
                message: 'Page is served over plain HTTP. Browsers flag it as "Not secure" and Google treats HTTPS as a ranking signal.',
                context: $effectiveUrl->toString(),
            ));

            return;
        }

        if ($pageScheme !== 'https' || !$page->isHtml()) {
            return;
        }

        $insecureResources = [];
        foreach ($page->links() as $link) {
            if (!in_array($link->type(), self::MIXED_CONTENT_TYPES, true)) {
                continue;
            }

            if (strtolower($link->targetUrl()->scheme()) === 'http') {
                $insecureResources[] = $link;
            }
        }

        if ($insecureResources === []) {
            return;
        }

        $sample = array_map(
            static fn(Link $link) => sprintf('%s (%s)', $link->targetUrl()->toString(), $link->type()->value),
            array_slice($insecureResources, 0, 5),
        );

        $count = count($insecureResources);

        $page->addIssue(new Issue(
            id: IssueId::generate(),
            category: IssueCategory::SECURITY,
            severity: IssueSeverity::WARNING,
            code: 'mixed_content',
            message: sprintf('%d resource(s) loaded over HTTP from an HTTPS page (mixed content).', $count),
            context: implode(', ', $sample) . ($count > 5 ? sprintf(' (+%d more)', $count - 5) : ''),
        ));
    }

    public function category(): IssueCategory
    {
        return IssueCategory::SECURITY;
    }
}
