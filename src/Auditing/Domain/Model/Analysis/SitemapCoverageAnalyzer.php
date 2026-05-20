<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Domain\Model\Analysis;

use SeoSpider\Auditing\Domain\Model\Issue\Issue;
use SeoSpider\Auditing\Domain\Model\Issue\IssueCategory;
use SeoSpider\Auditing\Domain\Model\Issue\IssueId;
use SeoSpider\Auditing\Domain\Model\Issue\IssueSeverity;
use SeoSpider\Auditing\Domain\Model\Issue\SiteIssue;

final readonly class SitemapCoverageAnalyzer implements SiteAnalyzer
{
    public function __construct(private SitemapIndex $sitemap)
    {
    }

    public function analyze(SiteContext $context): void
    {
        $sitemapUrls = $this->sitemap->urlsFor($context->auditId());
        if ($sitemapUrls === []) {
            return;
        }

        $sitemapIndex = [];
        foreach ($sitemapUrls as $url) {
            $sitemapIndex[$this->normalize($url)] = $url;
        }

        $crawledIndex = [];
        foreach ($context->pages() as $page) {
            $crawledIndex[$this->normalize($page->url())] = true;
            $finalUrl = $page->response()->finalUrl();
            if ($finalUrl !== null) {
                $crawledIndex[$this->normalize($finalUrl)] = true;
            }
        }

        foreach ($context->pages() as $page) {
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

            $context->addPageIssue($page->url(), new Issue(
                id: IssueId::generate(),
                category: IssueCategory::LINKS,
                severity: IssueSeverity::NOTICE,
                code: 'sitemap_missing',
                message: 'Page was crawled but is not declared in the sitemap.',
                context: $page->url(),
            ));
        }

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
                context: $url,
            ));
        }
    }

    public function category(): IssueCategory
    {
        return IssueCategory::LINKS;
    }

    private function normalize(string $url): string
    {
        $hashAt = strpos($url, '#');
        if ($hashAt !== false) {
            $url = substr($url, 0, $hashAt);
        }
        return rtrim($url, '/');
    }
}
