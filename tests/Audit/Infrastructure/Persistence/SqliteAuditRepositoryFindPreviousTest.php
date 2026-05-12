<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Infrastructure\Persistence;

use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Infrastructure\Persistence\SqliteAuditRepository;
use SeoSpider\Tests\Audit\Infrastructure\Persistence\Support\SqliteSchemaFactory;

final class SqliteAuditRepositoryFindPreviousTest extends TestCase
{
    public function test_returns_most_recent_completed_audit_for_the_given_host(): void
    {
        $pdo = SqliteSchemaFactory::inMemoryWithFullSchema();
        $repo = new SqliteAuditRepository($pdo);

        $older = AuditId::generate();
        $newer = AuditId::generate();
        $current = AuditId::generate();
        $otherHost = AuditId::generate();
        $stillRunning = AuditId::generate();

        SqliteSchemaFactory::insertAuditRow($pdo, $older, 'https://example.com/', completedAt: '2026-04-01T10:00:00+00:00');
        SqliteSchemaFactory::insertAuditRow($pdo, $newer, 'https://example.com/', completedAt: '2026-04-15T10:00:00+00:00');
        SqliteSchemaFactory::insertAuditRow($pdo, $current, 'https://example.com/', completedAt: '2026-04-27T10:00:00+00:00');
        SqliteSchemaFactory::insertAuditRow($pdo, $otherHost, 'https://other.com/', completedAt: '2026-04-20T10:00:00+00:00');
        SqliteSchemaFactory::insertAuditRow($pdo, $stillRunning, 'https://example.com/');

        $found = $repo->findPreviousCompletedByHost('example.com', $current);

        self::assertNotNull($found);
        self::assertSame($newer->value(), $found->id()->value());
    }

    public function test_returns_null_when_no_other_completed_audit_for_host(): void
    {
        $pdo = SqliteSchemaFactory::inMemoryWithFullSchema();
        $repo = new SqliteAuditRepository($pdo);

        $only = AuditId::generate();
        SqliteSchemaFactory::insertAuditRow($pdo, $only, 'https://example.com/', completedAt: '2026-04-27T10:00:00+00:00');

        self::assertNull($repo->findPreviousCompletedByHost('example.com', $only));
    }
}
