<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Infrastructure\Persistence\Support;

use PDO;
use SeoSpider\Auditing\Domain\Model\Audit\AuditId;

/**
 * Builds an in-memory SQLite PDO with a *subset* of the production schema —
 * just the tables and columns referenced by the repositories under test
 * (audits, pages, issues, site_issues). Excludes folders, frontier,
 * external_url_checks, audit_snapshots, and most non-load-bearing indexes
 * because no current test exercises them.
 *
 * If a new test needs a column or table that's missing here, add it. If
 * the production schema gains a column on a table this factory mirrors,
 * mirror it here too. Source of truth: `database/migrations/`.
 */
final class SqliteSchemaFactory
{
    public static function inMemoryWithFullSchema(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');

        $pdo->exec(<<<'SQL'
            CREATE TABLE audits (
                id TEXT PRIMARY KEY,
                seed_url TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'pending',
                max_pages INTEGER NOT NULL DEFAULT 500,
                max_depth INTEGER NOT NULL DEFAULT 10,
                concurrency INTEGER NOT NULL DEFAULT 5,
                request_delay REAL NOT NULL DEFAULT 0.25,
                respect_robots_txt INTEGER NOT NULL DEFAULT 1,
                custom_user_agent TEXT NULL,
                exclude_patterns TEXT NOT NULL DEFAULT '[]',
                include_patterns TEXT NOT NULL DEFAULT '[]',
                follow_external_links INTEGER NOT NULL DEFAULT 0,
                crawl_subdomains INTEGER NOT NULL DEFAULT 0,
                pages_discovered INTEGER NOT NULL DEFAULT 0,
                pages_crawled INTEGER NOT NULL DEFAULT 0,
                pages_failed INTEGER NOT NULL DEFAULT 0,
                issues_found INTEGER NOT NULL DEFAULT 0,
                errors_found INTEGER NOT NULL DEFAULT 0,
                warnings_found INTEGER NOT NULL DEFAULT 0,
                started_at TEXT NULL,
                completed_at TEXT NULL,
                created_at TEXT NOT NULL
            );
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE pages (
                id TEXT PRIMARY KEY,
                audit_id TEXT NOT NULL,
                url TEXT NOT NULL,
                status_code INTEGER NOT NULL,
                content_type TEXT NULL,
                body_size INTEGER DEFAULT 0,
                response_time REAL DEFAULT 0,
                final_url TEXT NULL,
                headers TEXT DEFAULT '{}',
                crawl_depth INTEGER DEFAULT 0,
                error_count INTEGER DEFAULT 0,
                warning_count INTEGER DEFAULT 0,
                internal_link_count INTEGER DEFAULT 0,
                external_link_count INTEGER DEFAULT 0,
                image_count INTEGER DEFAULT 0,
                canonical_status TEXT DEFAULT 'missing',
                is_html INTEGER DEFAULT 0,
                title TEXT NULL,
                meta_description TEXT NULL,
                h1s TEXT DEFAULT '[]',
                h2s TEXT DEFAULT '[]',
                heading_hierarchy TEXT DEFAULT '[]',
                charset TEXT NULL,
                viewport TEXT NULL,
                og_title TEXT NULL,
                og_description TEXT NULL,
                og_image TEXT NULL,
                word_count INTEGER DEFAULT 0,
                lang TEXT NULL,
                noindex INTEGER DEFAULT 0,
                nofollow INTEGER DEFAULT 0,
                noarchive INTEGER DEFAULT 0,
                nosnippet INTEGER DEFAULT 0,
                noimageindex INTEGER DEFAULT 0,
                max_snippet INTEGER NULL,
                max_image_preview TEXT NULL,
                max_video_preview INTEGER NULL,
                canonical TEXT NULL,
                directive_source TEXT NULL,
                exact_hash TEXT NULL,
                sim_hash INTEGER NULL,
                redirect_chain TEXT DEFAULT '[]',
                links TEXT DEFAULT '[]',
                hreflangs TEXT DEFAULT '[]',
                crawled_at TEXT NOT NULL,
                FOREIGN KEY (audit_id) REFERENCES audits(id) ON DELETE CASCADE
            );
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE issues (
                id TEXT PRIMARY KEY,
                page_id TEXT NOT NULL,
                category TEXT NOT NULL,
                severity TEXT NOT NULL,
                code TEXT NOT NULL,
                catalog_version TEXT NULL,
                message TEXT NOT NULL,
                context TEXT NULL,
                FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE
            );
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE site_issues (
                id TEXT PRIMARY KEY,
                audit_id TEXT NOT NULL,
                category TEXT NOT NULL,
                severity TEXT NOT NULL,
                code TEXT NOT NULL,
                catalog_version TEXT NULL,
                message TEXT NOT NULL,
                context TEXT NULL,
                FOREIGN KEY (audit_id) REFERENCES audits(id) ON DELETE CASCADE
            );
        SQL);

        return $pdo;
    }

    public static function insertAuditRow(
        PDO $pdo,
        AuditId $id,
        string $seedUrl,
        ?string $completedAt = null,
    ): void {
        $stmt = $pdo->prepare(
            'INSERT INTO audits (id, seed_url, status, started_at, completed_at, created_at)
             VALUES (:id, :seed, :status, :started, :completed, :created_at)',
        );
        $stmt->execute([
            'id' => $id->value(),
            'seed' => $seedUrl,
            'status' => $completedAt === null ? 'running' : 'completed',
            'started' => $completedAt,
            'completed' => $completedAt,
            'created_at' => (new \DateTimeImmutable())->format('c'),
        ]);
    }
}
