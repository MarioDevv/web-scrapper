<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Infrastructure\InMemory;

use SeoSpider\Crawling\Domain\Model\ExternalLink\ExternalLinkRepository;
use SeoSpider\Crawling\Domain\Model\Page\PageId;
use SeoSpider\Crawling\Domain\Model\Url;

final class InMemoryExternalLinkRepository implements ExternalLinkRepository
{
    /** @var array<string, array> */
    private array $checks = [];

    public function exists(string $auditId, Url $url): bool
    {
        $key = $auditId . '::' . $url->toString();
        return isset($this->checks[$key]);
    }

    public function save(
        string $auditId,
        Url $url,
        int $statusCode,
        float $responseTime,
        ?string $error,
        PageId $sourcePageId,
        ?string $anchorText,
    ): void {
        $key = $auditId . '::' . $url->toString();
        $this->checks[$key] = compact('statusCode', 'responseTime', 'error', 'anchorText');
    }
}
