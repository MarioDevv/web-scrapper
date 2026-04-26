<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Analyzer;

use SeoSpider\Audit\Domain\Model\DiscoverySource;
use SeoSpider\Audit\Domain\Model\Frontier;
use SeoSpider\Audit\Domain\Model\Page\Issue;
use SeoSpider\Audit\Domain\Model\Page\IssueCategory;
use SeoSpider\Audit\Domain\Model\Page\IssueId;
use SeoSpider\Audit\Domain\Model\Page\IssueSeverity;
use SeoSpider\Audit\Domain\Model\Page\SiteIssue;
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
            // (sitemap_unreachable) not yet tracked.
            return;
        }

        $sitemapIndex = [];
        foreach ($sitemapUrls as $url) {
            $sitemapIndex[$this->normalize($url)] = $url;
        }

        $crawledIndex = [];
        foreach ($context->pages as $page) {
            $crawledIndex[$this->normalize($page->url())] = true;
            $finalUrl = $page->response()->finalUrl();
            if ($finalUrl !== null) {
                $crawledIndex[$this->normalize($finalUrl)] = true;
            }
        }

        // sitemap_missing — page crawled but absent from the sitemap.
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

        // sitemap_orphans — URL declared in the sitemap that the
        // crawler never reached. These have no Page to attach to, so
        // they emit as SiteIssues persisted at the audit level.
        foreach ($sitemapIndex as $key => $url) {
            if (isset($crawledIndex[$key])) {
                continue;
            }

            $context->addSiteIssue(new SiteIssue(
                id: IssueId::generate(),
                category: IssueCategory::LINKS,
                severity: IssueSeverity::NOTICE,
                code: 'sitemap_orphans',
                message: 'URL declared in the sitemap but never reached by the crawler.',
                context: $url->toString(),
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
