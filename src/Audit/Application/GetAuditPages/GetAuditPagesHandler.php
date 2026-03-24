<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Application\GetAuditPages;

use SeoSpider\Audit\Application\AuditNotFound;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\Audit\AuditRepository;
use SeoSpider\Audit\Domain\Model\Page\Page;
use SeoSpider\Audit\Domain\Model\Page\PageRepository;

final readonly class GetAuditPagesHandler
{
    public function __construct(
        private AuditRepository $auditRepository,
        private PageRepository $pageRepository,
    ) {
    }

    public function __invoke(GetAuditPagesQuery $query): GetAuditPagesResponse
    {
        $auditId = new AuditId($query->auditId);

        if ($this->auditRepository->findById($auditId) === null) {
            throw AuditNotFound::withId($query->auditId);
        }

        $pages = $this->pageRepository->findByAudit($auditId);

        return new GetAuditPagesResponse(
            auditId: $query->auditId,
            pages: array_map($this->toSummary(...), $pages),
            total: count($pages),
        );
    }

    private function toSummary(Page $page): PageSummary
    {
        $directives = $page->directives();
        $anchors = array_filter($page->links(), static fn($l) => $l->isAnchor());

        return new PageSummary(
            pageId: $page->id()->value(),
            url: $page->url()->toString(),
            statusCode: $page->response()->statusCode()->code(),
            contentType: $page->response()->contentType() ?? '',
            bodySize: $page->response()->bodySize(),
            responseTime: $page->response()->responseTime(),
            crawlDepth: $page->crawlDepth(),
            errorCount: $page->errorCount(),
            warningCount: $page->warningCount(),
            isIndexable: $page->isIndexable(),
            title: $page->metadata()?->title(),
            wordCount: $page->metadata()?->wordCount() ?? 0,
            internalLinkCount: count(array_filter($anchors, static fn($l) => $l->isInternal())),
            externalLinkCount: count(array_filter($anchors, static fn($l) => $l->isExternal())),
            imageCount: count(array_filter($page->links(), static fn($l) => $l->type() === \SeoSpider\Audit\Domain\Model\Page\LinkType::IMAGE)),
            canonicalStatus: match (true) {
                $directives === null || !$directives->hasCanonical() => 'missing',
                $directives->isSelfCanonical($page->url()) => 'self',
                default => 'other',
            },
            h1Count: $page->metadata()?->h1Count() ?? 0,
            crawledAt: $page->crawledAt()->format('c'),
        );
    }
}
