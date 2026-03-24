<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Application\GetPageDetail;

use SeoSpider\Audit\Application\PageNotFound;
use SeoSpider\Audit\Domain\Model\Page\Issue;
use SeoSpider\Audit\Domain\Model\Page\Page;
use SeoSpider\Audit\Domain\Model\Page\PageId;
use SeoSpider\Audit\Domain\Model\Page\PageRepository;

final readonly class GetPageDetailHandler
{
    public function __construct(private PageRepository $pageRepository)
    {
    }

    public function __invoke(GetPageDetailQuery $query): GetPageDetailResponse
    {
        $page = $this->pageRepository->findById(new PageId($query->pageId));

        if ($page === null) {
            throw PageNotFound::withId($query->pageId);
        }

        return $this->toResponse($page);
    }

    private function toResponse(Page $page): GetPageDetailResponse
    {
        $metadata = $page->metadata();
        $directives = $page->directives();

        return new GetPageDetailResponse(
            pageId: $page->id()->value(),
            auditId: $page->auditId()->value(),
            url: $page->url()->toString(),
            statusCode: $page->response()->statusCode()->code(),
            contentType: $page->response()->contentType() ?? '',
            bodySize: $page->response()->bodySize(),
            responseTime: $page->response()->responseTime(),
            crawlDepth: $page->crawlDepth(),
            isIndexable: $page->isIndexable(),
            title: $metadata?->title(),
            titleLength: $metadata !== null ? $metadata->titleLength() : null,
            metaDescription: $metadata?->metaDescription(),
            metaDescriptionLength: $metadata !== null ? $metadata->metaDescriptionLength() : null,
            h1s: $metadata?->h1s() ?? [],
            wordCount: $metadata?->wordCount() ?? 0,
            canonical: $directives?->canonical()?->toString(),
            canonicalStatus: match (true) {
                $directives === null || !$directives->hasCanonical() => 'missing',
                $directives->isSelfCanonical($page->url()) => 'self',
                default => 'other',
            },
            noindex: $directives?->noindex() ?? false,
            nofollow: $directives?->nofollow() ?? false,
            redirectChain: array_map(
                static fn($hop) => [
                    'from' => $hop->from()->toString(),
                    'to' => $hop->to()->toString(),
                    'statusCode' => $hop->statusCode()->code(),
                ],
                $page->redirectChain()->hops(),
            ),
            hreflangs: array_map(
                static fn($h) => [
                    'language' => $h->language(),
                    'region' => $h->region(),
                    'href' => $h->href()->toString(),
                ],
                $page->hreflangs(),
            ),
            internalLinkCount: count(array_filter($page->internalLinks(), static fn($l) => $l->isAnchor())),
            externalLinkCount: count(array_filter($page->externalLinks(), static fn($l) => $l->isAnchor())),
            links: array_map(
                static fn($l) => [
                    'url' => $l->targetUrl()->toString(),
                    'type' => $l->type()->value,
                    'anchor' => $l->anchorText(),
                    'relation' => $l->relation()->value,
                    'internal' => $l->isInternal(),
                ],
                $page->links(),
            ),
            issues: array_map($this->toIssueSummary(...), $page->issues()),
            crawledAt: $page->crawledAt()->format('c'),
        );
    }

    private function toIssueSummary(Issue $issue): IssueSummary
    {
        return new IssueSummary(
            id: $issue->id()->value(),
            category: $issue->category()->value,
            severity: $issue->severity()->value,
            code: $issue->code(),
            message: $issue->message(),
            context: $issue->context(),
        );
    }
}
