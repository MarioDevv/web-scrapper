<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Application\Reporting\GetPageDetail;

use SeoSpider\Auditing\Domain\Exception\PageNotFound;
use SeoSpider\Auditing\Domain\Model\AuditedPage\AuditedPageRepository;
use SeoSpider\Auditing\Domain\Model\Issue\Issue;
use SeoSpider\Auditing\Domain\Model\Reporting\PageDetailData;
use SeoSpider\Auditing\Domain\Model\Reporting\PageDetailReader;

final readonly class GetPageDetailHandler
{
    public function __construct(
        private PageDetailReader $pageDetailReader,
        private AuditedPageRepository $auditedPageRepository,
    ) {
    }

    public function __invoke(GetPageDetailQuery $query): GetPageDetailResponse
    {
        $data = $this->pageDetailReader->findById($query->pageId);
        if ($data === null) {
            throw PageNotFound::withId($query->pageId);
        }

        $audited = $this->auditedPageRepository->findByAuditAndUrl($data->auditId, $data->url);

        return $this->toResponse($data, $audited?->issues() ?? []);
    }

    /** @param Issue[] $issues */
    private function toResponse(PageDetailData $data, array $issues): GetPageDetailResponse
    {
        return new GetPageDetailResponse(
            pageId: $data->pageId,
            auditId: $data->auditId,
            url: $data->url,
            statusCode: $data->statusCode,
            contentType: $data->contentType,
            bodySize: $data->bodySize,
            responseTime: $data->responseTime,
            crawlDepth: $data->crawlDepth,
            isIndexable: $data->isIndexable,
            title: $data->title,
            titleLength: $data->titleLength,
            metaDescription: $data->metaDescription,
            metaDescriptionLength: $data->metaDescriptionLength,
            h1s: $data->h1s,
            wordCount: $data->wordCount,
            canonical: $data->canonical,
            canonicalStatus: $data->canonicalStatus,
            noindex: $data->noindex,
            nofollow: $data->nofollow,
            redirectChain: $data->redirectChain,
            hreflangs: $data->hreflangs,
            internalLinkCount: $data->internalLinkCount,
            externalLinkCount: $data->externalLinkCount,
            links: $data->links,
            issues: array_map($this->toIssueSummary(...), $issues),
            crawledAt: $data->crawledAt,
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
