<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Page;

use DateTimeImmutable;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\HttpStatusCode;
use SeoSpider\Audit\Domain\Model\Url;
use SeoSpider\Shared\Domain\DomainEvent;

final readonly class PageCrawled implements DomainEvent
{
    public function __construct(
        public PageId $pageId,
        public AuditId $auditId,
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
