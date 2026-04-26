<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Analyzer;

use SeoSpider\Audit\Domain\Model\DiscoverySource;
use SeoSpider\Audit\Domain\Model\Frontier;
use SeoSpider\Audit\Domain\Model\Page\Issue;
use SeoSpider\Audit\Domain\Model\Page\IssueCategory;
use SeoSpider\Audit\Domain\Model\Page\IssueId;
use SeoSpider\Audit\Domain\Model\Page\IssueSeverity;
use SeoSpider\Audit\Domain\Model\Url;

final class SitemapCoverageAnalyzer implements SiteAnalyzer
{
    public function __construct(private readonly Frontier $frontier)
    {
    }

    public function analyze(SiteAuditContext $context): void
    {
        $sitemapUrls = $this->frontier->urlsBySource($context->auditId, DiscoverySource::SITEMAP);

        if ($sitemapUrls === []) {
            // No sitemap was ingested — flagging every crawled page would
            // be noise. The absence of a sitemap is a separate concern
            // (sitemap_unreachable) tracked outside phase A.3.1.
            return;
        }

        $sitemapIndex = [];
        foreach ($sitemapUrls as $url) {
            $sitemapIndex[$this->normalize($url)] = true;
        }

        foreach ($context->pages as $page) {
            if (!$page->response()->statusCode()->isSuccessful()) {
                continue;
            }
            if (!$page->isIndexable()) {
                continue;
            }

            $candidates = [$this->normalize($page->url())];
            $finalUrl = $page->response()->finalUrl();
            if ($finalUrl !== null) {
                $candidates[] = $this->normalize($finalUrl);
            }

            foreach ($candidates as $candidate) {
                if (isset($sitemapIndex[$candidate])) {
                    continue 2;
                }
            }

            $page->addIssue(new Issue(
                id: IssueId::generate(),
                category: IssueCategory::LINKS,
                severity: IssueSeverity::NOTICE,
                code: 'sitemap_missing',
                message: 'Page was crawled but is not declared in the sitemap.',
                context: $page->url()->toString(),
            ));
        }
    }

    public function category(): IssueCategory
    {
        return IssueCategory::LINKS;
    }

    private function normalize(Url $url): string
    {
        return rtrim($url->withoutFragment()->toString(), '/');
    }
}
