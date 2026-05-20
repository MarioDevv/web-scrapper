<?php

declare(strict_types=1);

namespace SeoSpider\Crawling\Application;

use SeoSpider\Crawling\Domain\Model\Page\Page;
use SeoSpider\Shared\Integration\CrawledPagePayload;

final readonly class CrawledPagePayloadFactory
{
    public function fromPage(Page $page): CrawledPagePayload
    {
        $response = $page->response();

        return new CrawledPagePayload(
            auditId: $page->auditId(),
            url: $page->url()->toString(),
            crawlDepth: $page->crawlDepth(),
            isHtml: $page->isHtml(),
            isIndexable: $page->isIndexable(),
            statusCode: $response->statusCode()->code(),
            contentType: $response->contentType(),
            bodySize: $response->bodySize(),
            responseTime: $response->responseTime(),
        );
    }
}
