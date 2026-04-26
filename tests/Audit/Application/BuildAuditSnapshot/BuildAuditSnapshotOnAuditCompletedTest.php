<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Application\BuildAuditSnapshot;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Application\AuditOverview\AuditOverviewBuilder;
use SeoSpider\Audit\Application\BuildAuditSnapshot\BuildAuditSnapshotOnAuditCompleted;
use SeoSpider\Audit\Domain\Model\Audit\AuditCompleted;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\Audit\AuditStatistics;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\InMemoryAuditSnapshotRepository;

final class BuildAuditSnapshotOnAuditCompletedTest extends TestCase
{
    public function test_persists_overview_snapshot_on_audit_completed(): void
    {
        $auditId = AuditId::generate();
        $repo = new InMemoryAuditSnapshotRepository();

        $builder = new readonly class () extends AuditOverviewBuilder {
            public function __construct() {}

            public function build(AuditId $auditId): array
            {
                return [
                    'totalPages' => 42,
                    'statusGroups' => ['2xx' => 40, '3xx' => 1, '4xx' => 1, '5xx' => 0],
                    'totalIssues' => 7,
                ];
            }
        };

        $reactor = new BuildAuditSnapshotOnAuditCompleted($builder, $repo);

        ($reactor)(new AuditCompleted(
            auditId: $auditId,
            statistics: new AuditStatistics(),
            occurredAt: new DateTimeImmutable(),
        ));

        $stored = $repo->findByAudit($auditId);
        $this->assertNotNull($stored);
        $this->assertSame(42, $stored->overview['totalPages']);
        $this->assertSame(7, $stored->overview['totalIssues']);
    }
}
