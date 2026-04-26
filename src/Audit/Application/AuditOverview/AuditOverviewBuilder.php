<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Application\AuditOverview;

use PDO;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;

/**
 * Builds the dashboard overview block straight from SQLite using a
 * handful of aggregation queries. Used both at request time (audits
 * still crawling, no snapshot yet) and from the BuildAuditSnapshot
 * reactor that freezes the result once the crawl completes, so the
 * post-crawl dashboard reads a single JSON blob instead of recomputing
 * the same aggregations on every render.
 */
readonly class AuditOverviewBuilder
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return array<string, mixed> */
    public function build(AuditId $auditId): array
    {
        $aggregates = $this->aggregates($auditId);
        $depthDistribution = $this->depthDistribution($auditId);
        $issueBreakdown = $this->issueBreakdown($auditId);

        return [
            'statusGroups' => [
                '2xx' => $aggregates['s2xx'],
                '3xx' => $aggregates['s3xx'],
                '4xx' => $aggregates['s4xx'],
                '5xx' => $aggregates['s5xx'],
            ],
            'depthDistribution' => $depthDistribution,
            'responseTimeBuckets' => [
                '<200ms' => $aggregates['rt_under_200'],
                '200-500ms' => $aggregates['rt_200_500'],
                '500ms-1s' => $aggregates['rt_500_1000'],
                '1-3s' => $aggregates['rt_1_3s'],
                '>3s' => $aggregates['rt_over_3s'],
            ],
            'issuesByCategory' => $issueBreakdown['byCategory'],
            'issuesBySeverity' => $issueBreakdown['bySeverity'],
            'totalIssues' => $issueBreakdown['total'],
            'totalPages' => $aggregates['total_pages'],
            'avgResponseTime' => $aggregates['avg_response_time'],
            'totalWords' => $aggregates['total_words'],
            'totalImages' => $aggregates['total_images'],
            'pagesWithoutH1' => $aggregates['no_h1'],
            'pagesWithoutTitle' => $aggregates['no_title'],
            'pagesWithoutDesc' => $aggregates['no_desc'],
        ];
    }

    /** @return array<string, int> */
    private function aggregates(AuditId $auditId): array
    {
        $stmt = $this->pdo->prepare(<<<'SQL'
            SELECT
                COUNT(*) AS total_pages,
                CAST(ROUND(COALESCE(AVG(response_time), 0)) AS INTEGER) AS avg_response_time,
                COALESCE(SUM(word_count), 0) AS total_words,
                COALESCE(SUM(image_count), 0) AS total_images,
                SUM(CASE WHEN status_code BETWEEN 200 AND 299 THEN 1 ELSE 0 END) AS s2xx,
                SUM(CASE WHEN status_code BETWEEN 300 AND 399 THEN 1 ELSE 0 END) AS s3xx,
                SUM(CASE WHEN status_code BETWEEN 400 AND 499 THEN 1 ELSE 0 END) AS s4xx,
                SUM(CASE WHEN status_code >= 500 THEN 1 ELSE 0 END) AS s5xx,
                SUM(CASE WHEN response_time < 200 THEN 1 ELSE 0 END) AS rt_under_200,
                SUM(CASE WHEN response_time >= 200 AND response_time < 500 THEN 1 ELSE 0 END) AS rt_200_500,
                SUM(CASE WHEN response_time >= 500 AND response_time < 1000 THEN 1 ELSE 0 END) AS rt_500_1000,
                SUM(CASE WHEN response_time >= 1000 AND response_time < 3000 THEN 1 ELSE 0 END) AS rt_1_3s,
                SUM(CASE WHEN response_time >= 3000 THEN 1 ELSE 0 END) AS rt_over_3s,
                SUM(CASE WHEN is_html = 1 AND (title IS NULL OR title = '') THEN 1 ELSE 0 END) AS no_title,
                SUM(CASE WHEN is_html = 1 AND (meta_description IS NULL OR meta_description = '') THEN 1 ELSE 0 END) AS no_desc,
                SUM(CASE
                    WHEN is_html = 1
                     AND (h1s IS NULL OR h1s = '[]' OR h1s = '')
                    THEN 1 ELSE 0
                END) AS no_h1
            FROM pages
            WHERE audit_id = :audit_id
        SQL);
        $stmt->execute(['audit_id' => $auditId->value()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return array_map('intval', $row);
    }

    /** @return array<int, int> */
    private function depthDistribution(AuditId $auditId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT crawl_depth, COUNT(*) AS cnt FROM pages WHERE audit_id = :audit_id GROUP BY crawl_depth ORDER BY crawl_depth',
        );
        $stmt->execute(['audit_id' => $auditId->value()]);

        $dist = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $dist[(int) $row['crawl_depth']] = (int) $row['cnt'];
        }

        return $dist;
    }

    /** @return array{byCategory: array<string, int>, bySeverity: array<string, int>, total: int} */
    private function issueBreakdown(AuditId $auditId): array
    {
        $stmt = $this->pdo->prepare(<<<'SQL'
            SELECT i.category, i.severity, COUNT(*) AS cnt
            FROM issues i
            JOIN pages p ON p.id = i.page_id
            WHERE p.audit_id = :audit_id
            GROUP BY i.category, i.severity
        SQL);
        $stmt->execute(['audit_id' => $auditId->value()]);

        $byCategory = [];
        $bySeverity = ['error' => 0, 'warning' => 0, 'notice' => 0, 'info' => 0];
        $total = 0;

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $cat = (string) $row['category'];
            $sev = (string) $row['severity'];
            $cnt = (int) $row['cnt'];
            $byCategory[$cat] = ($byCategory[$cat] ?? 0) + $cnt;
            $bySeverity[$sev] = ($bySeverity[$sev] ?? 0) + $cnt;
            $total += $cnt;
        }

        return ['byCategory' => $byCategory, 'bySeverity' => $bySeverity, 'total' => $total];
    }
}
