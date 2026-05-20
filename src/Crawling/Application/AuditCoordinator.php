<?php

declare(strict_types=1);

namespace SeoSpider\Crawling\Application;

interface AuditCoordinator
{
    public function snapshot(string $auditId): ?AuditSnapshot;

    public function registerPageFailed(string $auditId): void;

    public function registerUrlsDiscovered(string $auditId, int $count): void;

    public function complete(string $auditId): void;
}
