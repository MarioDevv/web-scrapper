<?php

declare(strict_types=1);

namespace SeoSpider\Crawling\Domain\Model\ExternalLink;
use SeoSpider\Crawling\Domain\Model\Url;

use SeoSpider\Crawling\Domain\Model\Page\PageId;

interface ExternalLinkRepository
{
    public function exists(string $auditId, Url $url): bool;

    public function save(
        string $auditId,
        Url $url,
        int $statusCode,
        float $responseTime,
        ?string $error,
        PageId $sourcePageId,
        ?string $anchorText,
    ): void;
}