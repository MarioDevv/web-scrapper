<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Infrastructure\InMemory;

use SeoSpider\Auditing\Domain\Model\Audit\AuditId;
use SeoSpider\Crawling\Domain\Model\Page\Fingerprint;
use SeoSpider\Auditing\Domain\Model\Issue\Issue;
use SeoSpider\Audit\Domain\Model\Page\Page;
use SeoSpider\Audit\Domain\Model\Page\PageId;
use SeoSpider\Audit\Domain\Model\Page\PageRepository;
use SeoSpider\Crawling\Domain\Model\Url;

final class InMemoryPageRepository implements PageRepository
{
    /** @var array<string, Page> */
    private array $pages = [];

    public function save(Page $page): void
    {
        $this->pages[$page->id()->value()] = $page;
    }

    public function findById(PageId $id): ?Page
    {
        return $this->pages[$id->value()] ?? null;
    }

    public function findByAuditAndUrl(AuditId $auditId, Url $url): ?Page
    {
        foreach ($this->pages as $page) {
            if ($page->auditId()->equals($auditId) && $page->url()->equals($url)) {
                return $page;
            }
        }

        return null;
    }

    /** @return Page[] */
    public function findByAudit(AuditId $auditId): array
    {
        return array_values(array_filter(
            $this->pages,
            static fn(Page $page) => $page->auditId()->equals($auditId),
        ));
    }

    /** @return Page[] */
    public function findByAuditSince(AuditId $auditId, ?string $sinceIso): array
    {
        if ($sinceIso === null || $sinceIso === '') {
            return $this->findByAudit($auditId);
        }

        return array_values(array_filter(
            $this->findByAudit($auditId),
            static fn(Page $page) => $page->crawledAt()->format('c') > $sinceIso,
        ));
    }

    public function countByAudit(AuditId $auditId): int
    {
        return count($this->findByAudit($auditId));
    }

    public function nextId(): PageId
    {
        return PageId::generate();
    }

    /** @return array<string, Fingerprint> */
    public function fingerprintsByAudit(AuditId $auditId): array
    {
        $fingerprints = [];

        foreach ($this->findByAudit($auditId) as $page) {
            if ($page->fingerprint() !== null) {
                $fingerprints[$page->url()->toString()] = $page->fingerprint();
            }
        }

        return $fingerprints;
    }
}
