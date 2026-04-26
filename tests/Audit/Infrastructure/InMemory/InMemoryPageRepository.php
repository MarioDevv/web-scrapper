<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Infrastructure\InMemory;

use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\Page\Fingerprint;
use SeoSpider\Audit\Domain\Model\Page\Issue;
use SeoSpider\Audit\Domain\Model\Page\Page;
use SeoSpider\Audit\Domain\Model\Page\PageId;
use SeoSpider\Audit\Domain\Model\Page\PageRepository;
use SeoSpider\Audit\Domain\Model\Url;

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

    public function countByAudit(AuditId $auditId): int
    {
        return count($this->findByAudit($auditId));
    }

    public function nextId(): PageId
    {
        return PageId::generate();
    }

    /**
     * No-op: in-memory aggregates already hold the issues their analyzers
     * added via Page::addIssue(). This mirrors the SQLite implementation,
     * which simply inserts rows into the issues table without touching
     * the page aggregate that triggered the call.
     *
     * @param Issue[] $issues
     */
    public function appendIssues(PageId $pageId, array $issues): void
    {
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
