<?php

declare(strict_types=1);

namespace SeoSpider\Crawling\Domain\Model\Page;

use DateTimeImmutable;
use SeoSpider\Crawling\Domain\Model\Url;
use SeoSpider\Shared\Domain\DomainEvent;

final readonly class PageFailed implements DomainEvent
{
    public function __construct(
        public PageId $pageId,
        public string $auditId,
        public Url $url,
        public string $reason,
        public DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
