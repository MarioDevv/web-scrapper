<?php

declare(strict_types=1);

namespace SeoSpider\Crawling\Domain\Model\Page;

use DateTimeImmutable;
use SeoSpider\Crawling\Domain\Model\HttpStatusCode;
use SeoSpider\Crawling\Domain\Model\Url;
use SeoSpider\Shared\Domain\DomainEvent;

final readonly class PageCrawled implements DomainEvent
{
    public function __construct(
        public PageId $pageId,
        public string $auditId,
        public Url $url,
        public HttpStatusCode $statusCode,
        public int $issueCount,
        public DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
