<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Infrastructure\InMemory;

use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\ExternalLinkRepository;
use SeoSpider\Audit\Domain\Model\Page\PageId;
use SeoSpider\Audit\Domain\Model\Url;

final class InMemoryExternalLinkRepository implements ExternalLinkRepository
{
    /** @var array<string, array> */
    private array $checks = [];

    public function exists(AuditId $auditId, Url $url): bool
    {
        $key = $auditId->value() . '::' . $url->toString();
        return isset($this->checks[$key]);
    }

    public function save(
        AuditId $auditId,
        Url $url,
        int $statusCode,
        float $responseTime,
        ?string $error,
        PageId $sourcePageId,
        ?string $anchorText,
    ): void {
        $key = $auditId->value() . '::' . $url->toString();
        $this->checks[$key] = compact('statusCode', 'responseTime', 'error', 'anchorText');
    }
}