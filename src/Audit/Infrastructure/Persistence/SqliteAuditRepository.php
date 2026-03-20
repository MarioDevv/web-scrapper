<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Infrastructure\Persistence;

use DateTimeImmutable;
use PDO;
use SeoSpider\Audit\Domain\Model\Audit\Audit;
use SeoSpider\Audit\Domain\Model\Audit\AuditConfiguration;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\Audit\AuditRepository;
use SeoSpider\Audit\Domain\Model\Audit\AuditStatistics;
use SeoSpider\Audit\Domain\Model\Audit\AuditStatus;
use SeoSpider\Audit\Domain\Model\Url;

final readonly class SqliteAuditRepository implements AuditRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function save(Audit $audit): void
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO audits (
                id, seed_url, status,
                max_pages, max_depth, concurrency, request_delay,
                respect_robots_txt, custom_user_agent,
                exclude_patterns, include_patterns,
                follow_external_links, crawl_subdomains,
                pages_discovered, pages_crawled, pages_failed,
                issues_found, errors_found, warnings_found,
                started_at, completed_at, created_at
            ) VALUES (
                :id, :seed_url, :status,
                :max_pages, :max_depth, :concurrency, :request_delay,
                :respect_robots_txt, :custom_user_agent,
                :exclude_patterns, :include_patterns,
                :follow_external_links, :crawl_subdomains,
                :pages_discovered, :pages_crawled, :pages_failed,
                :issues_found, :errors_found, :warnings_found,
                :started_at, :completed_at, :created_at
            ) ON CONFLICT(id) DO UPDATE SET
                status = :status,
                pages_discovered = :pages_discovered,
                pages_crawled = :pages_crawled,
                pages_failed = :pages_failed,
                issues_found = :issues_found,
                errors_found = :errors_found,
                warnings_found = :warnings_found,
                started_at = :started_at,
                completed_at = :completed_at
        ');

        $config = $audit->configuration();
        $stats = $audit->statistics();

        $stmt->execute([
            'id' => $audit->id()->value(),
            'seed_url' => $config->seedUrl->toString(),
            'status' => $audit->status()->value,
            'max_pages' => $config->maxPages,
            'max_depth' => $config->maxDepth,
            'concurrency' => $config->concurrency,
            'request_delay' => $config->requestDelay,
            'respect_robots_txt' => $config->respectRobotsTxt ? 1 : 0,
            'custom_user_agent' => $config->customUserAgent,
            'exclude_patterns' => json_encode($config->excludePatterns),
            'include_patterns' => json_encode($config->includePatterns),
            'follow_external_links' => $config->followExternalLinks ? 1 : 0,
            'crawl_subdomains' => $config->crawlSubdomains ? 1 : 0,
            'pages_discovered' => $stats->pagesDiscovered,
            'pages_crawled' => $stats->pagesCrawled,
            'pages_failed' => $stats->pagesFailed,
            'issues_found' => $stats->issuesFound,
            'errors_found' => $stats->errorsFound,
            'warnings_found' => $stats->warningsFound,
            'started_at' => $stats->startedAt?->format('c'),
            'completed_at' => $stats->completedAt?->format('c'),
            'created_at' => $audit->createdAt()->format('c'),
        ]);
    }

    public function findById(AuditId $id): ?Audit
    {
        $stmt = $this->pdo->prepare('SELECT * FROM audits WHERE id = :id');
        $stmt->execute(['id' => $id->value()]);

        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function nextId(): AuditId
    {
        return AuditId::generate();
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Audit
    {
        return Audit::reconstitute(
            id: new AuditId($row['id']),
            configuration: new AuditConfiguration(
                seedUrl: Url::fromString($row['seed_url']),
                maxPages: (int) $row['max_pages'],
                maxDepth: (int) $row['max_depth'],
                concurrency: (int) $row['concurrency'],
                requestDelay: (float) $row['request_delay'],
                respectRobotsTxt: (bool) $row['respect_robots_txt'],
                customUserAgent: $row['custom_user_agent'],
                excludePatterns: json_decode($row['exclude_patterns'], true),
                includePatterns: json_decode($row['include_patterns'], true),
                followExternalLinks: (bool) $row['follow_external_links'],
                crawlSubdomains: (bool) $row['crawl_subdomains'],
            ),
            status: AuditStatus::from($row['status']),
            statistics: new AuditStatistics(
                pagesDiscovered: (int) $row['pages_discovered'],
                pagesCrawled: (int) $row['pages_crawled'],
                pagesFailed: (int) $row['pages_failed'],
                issuesFound: (int) $row['issues_found'],
                errorsFound: (int) $row['errors_found'],
                warningsFound: (int) $row['warnings_found'],
                startedAt: $row['started_at'] !== null ? new DateTimeImmutable($row['started_at']) : null,
                completedAt: $row['completed_at'] !== null ? new DateTimeImmutable($row['completed_at']) : null,
            ),
            createdAt: new DateTimeImmutable($row['created_at']),
        );
    }
}