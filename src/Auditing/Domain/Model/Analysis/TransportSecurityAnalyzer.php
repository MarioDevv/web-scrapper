<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Domain\Model\Analysis;

use SeoSpider\Auditing\Domain\Model\Analysis\Signal\Link;
use SeoSpider\Auditing\Domain\Model\Issue\Issue;
use SeoSpider\Auditing\Domain\Model\Issue\IssueCategory;
use SeoSpider\Auditing\Domain\Model\Issue\IssueId;
use SeoSpider\Auditing\Domain\Model\Issue\IssueSeverity;

final class TransportSecurityAnalyzer implements Analyzer
{
    private const array MIXED_CONTENT_TYPES = [
        'script',
        'stylesheet',
        'iframe',
        'image',
        'video',
        'audio',
        'font',
    ];

    public function analyze(PageSignals $signals, IssueCollector $issues): void
    {
        $effectiveUrl = $signals->response()->finalUrl() ?? $signals->url();
        $pageScheme = strtolower((string) (parse_url($effectiveUrl, PHP_URL_SCHEME) ?: ''));

        if ($pageScheme === 'http') {
            $issues->add(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::SECURITY,
                severity: IssueSeverity::ERROR,
                code: 'http_insecure',
                message: 'Page is served over plain HTTP. Browsers flag it as "Not secure" and Google treats HTTPS as a ranking signal.',
                context: $effectiveUrl,
            ));
            return;
        }

        if ($pageScheme !== 'https' || !$signals->isHtml()) {
            return;
        }

        $insecureResources = [];
        foreach ($signals->links() as $link) {
            if (!in_array($link->type(), self::MIXED_CONTENT_TYPES, true)) {
                continue;
            }
            $linkScheme = strtolower((string) (parse_url($link->targetUrl(), PHP_URL_SCHEME) ?: ''));
            if ($linkScheme === 'http') {
                $insecureResources[] = $link;
            }
        }

        if ($insecureResources === []) {
            return;
        }

        $sample = array_map(
            static fn(Link $link) => sprintf('%s (%s)', $link->targetUrl(), $link->type()),
            array_slice($insecureResources, 0, 5),
        );

        $count = count($insecureResources);

        $issues->add(new Issue(
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
