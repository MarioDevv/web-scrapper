<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Application\Analysis;

use SeoSpider\Crawling\Domain\Model\Page\Page;
use SeoSpider\Crawling\Domain\Model\Page\PageId;
use SeoSpider\Crawling\Domain\Model\Page\PageRepository;
use SeoSpider\Auditing\Domain\Model\Reporting\PageDetailData;
use SeoSpider\Auditing\Domain\Model\Reporting\PageDetailReader;
use SeoSpider\Crawling\Domain\Model\Page\Hreflang;
use SeoSpider\Crawling\Domain\Model\Page\Link;
use SeoSpider\Crawling\Domain\Model\Page\RedirectHop;

final readonly class CrawlingPageDetailReader implements PageDetailReader
{
    public function __construct(private PageRepository $pages)
    {
    }

    public function findById(string $pageId): ?PageDetailData
    {
        $page = $this->pages->findById(new PageId($pageId));
        return $page === null ? null : $this->toData($page);
    }

    private function toData(Page $page): PageDetailData
    {
        $metadata = $page->metadata();
        $directives = $page->directives();
        $canonical = $directives?->canonical()?->toString();

        $canonicalStatus = match (true) {
            $directives === null || !$directives->hasCanonical() => 'missing',
            $directives->isSelfCanonical($page->url()) => 'self',
            default => 'other',
        };

        return new PageDetailData(
            pageId: $page->id()->value(),
            auditId: $page->auditId(),
            url: $page->url()->toString(),
            statusCode: $page->response()->statusCode()->code(),
            contentType: $page->response()->contentType() ?? '',
            bodySize: $page->response()->bodySize(),
            responseTime: $page->response()->responseTime(),
            crawlDepth: $page->crawlDepth(),
            isIndexable: $page->isIndexable(),
            title: $metadata?->title(),
            titleLength: $metadata?->titleLength(),
            metaDescription: $metadata?->metaDescription(),
            metaDescriptionLength: $metadata?->metaDescriptionLength(),
            h1s: $metadata?->h1s() ?? [],
            wordCount: $metadata?->wordCount() ?? 0,
            canonical: $canonical,
            canonicalStatus: $canonicalStatus,
            noindex: $directives?->noindex() ?? false,
            nofollow: $directives?->nofollow() ?? false,
            redirectChain: array_map(
                static fn (RedirectHop $hop): array => [
                    'from' => $hop->from()->toString(),
                    'to' => $hop->to()->toString(),
                    'statusCode' => $hop->statusCode()->code(),
                ],
                $page->redirectChain()->hops(),
            ),
            hreflangs: array_map(
                static fn (Hreflang $h): array => [
                    'language' => $h->language(),
                    'region' => $h->region(),
                    'href' => $h->href()->toString(),
                ],
                $page->hreflangs(),
            ),
            internalLinkCount: count(array_filter($page->internalLinks(), static fn (Link $l): bool => $l->isAnchor())),
            externalLinkCount: count(array_filter($page->externalLinks(), static fn (Link $l): bool => $l->isAnchor())),
            links: array_map(
                static fn (Link $l): array => [
                    'url' => $l->targetUrl()->toString(),
                    'type' => $l->type()->value,
                    'anchor' => $l->anchorText(),
                    'relation' => $l->relation()->value,
                    'internal' => $l->isInternal(),
                ],
                $page->links(),
            ),
            crawledAt: $page->crawledAt()->format('c'),
        );
    }
}
