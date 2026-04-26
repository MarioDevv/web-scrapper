<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Infrastructure\Persistence;

use PDO;
use SeoSpider\Audit\Application\GetAuditPages\GetAuditPagesQuery;
use SeoSpider\Audit\Application\GetAuditPages\PageSummary;
use SeoSpider\Audit\Application\GetAuditPages\PageSummaryReader;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;

final readonly class SqlitePageSummaryReader implements PageSummaryReader
{
    /** Whitelist of sort columns that the UI can pass through. Anything
     *  else falls back to crawled_at to stop callers from injecting raw
     *  SQL via the sortField parameter. */
    private const array SORTABLE = [
        'url' => 'url',
        'statusCode' => 'status_code',
        'contentType' => 'content_type',
        'bodySize' => 'body_size',
        'responseTime' => 'response_time',
        'crawlDepth' => 'crawl_depth',
        'errorCount' => 'error_count',
        'warningCount' => 'warning_count',
        'title' => 'title',
        'wordCount' => 'word_count',
        'internalLinkCount' => 'internal_link_count',
        'externalLinkCount' => 'external_link_count',
        'imageCount' => 'image_count',
        'h1Count' => 'h1_count',
        'crawledAt' => 'crawled_at',
    ];

    private const string SELECT_LIST = '
        id, url, status_code, content_type, body_size, response_time,
        crawl_depth, error_count, warning_count, is_html,
        title, word_count, lang,
        internal_link_count, external_link_count, image_count, canonical_status,
        h1s, noindex, canonical, crawled_at
    ';

    public function __construct(private PDO $pdo)
    {
    }

    /** @return PageSummary[] */
    public function read(GetAuditPagesQuery $query): array
    {
        $where = $this->whereClause($query);
        $params = $this->whereParams($query);

        $sql = sprintf(
            'SELECT %s FROM pages WHERE %s ORDER BY %s %s',
            self::SELECT_LIST,
            $where,
            $this->sortColumn($query->sortField),
            $this->sortDirection($query->sortDir),
        );

        if ($query->limit !== null) {
            $sql .= ' LIMIT :limit OFFSET :offset';
            $params['limit'] = $query->limit;
            $params['offset'] = max(0, $query->offset);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_map($this->toSummary(...), $stmt->fetchAll() ?: []);
    }

    public function count(GetAuditPagesQuery $query): int
    {
        $stmt = $this->pdo->prepare(sprintf(
            'SELECT COUNT(*) FROM pages WHERE %s',
            $this->whereClause($query),
        ));
        $stmt->execute($this->whereParams($query));

        return (int) $stmt->fetchColumn();
    }

    public function totalForAudit(AuditId $auditId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM pages WHERE audit_id = :audit_id');
        $stmt->execute(['audit_id' => $auditId->value()]);

        return (int) $stmt->fetchColumn();
    }

    public function tabCounts(AuditId $auditId): array
    {
        $stmt = $this->pdo->prepare(<<<'SQL'
            SELECT
                COUNT(*) AS total,
                SUM(CASE
                    WHEN content_type LIKE '%html%' AND status_code < 300 THEN 1 ELSE 0
                END) AS internal,
                SUM(CASE WHEN content_type LIKE '%html%' THEN 1 ELSE 0 END) AS html,
                SUM(CASE
                    WHEN status_code >= 300 AND status_code < 400 THEN 1 ELSE 0
                END) AS redirects,
                SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) AS errors,
                SUM(CASE WHEN error_count > 0 OR warning_count > 0 THEN 1 ELSE 0 END) AS issues,
                SUM(CASE
                    WHEN noindex = 1
                      OR status_code >= 400
                      OR (canonical_status = 'other')
                    THEN 1 ELSE 0
                END) AS noindex
            FROM pages
            WHERE audit_id = :audit_id
        SQL);
        $stmt->execute(['audit_id' => $auditId->value()]);
        $row = $stmt->fetch() ?: [];

        return [
            'pages' => (int) ($row['total'] ?? 0),
            'internal' => (int) ($row['internal'] ?? 0),
            'html' => (int) ($row['html'] ?? 0),
            'redirects' => (int) ($row['redirects'] ?? 0),
            'errors' => (int) ($row['errors'] ?? 0),
            'issues' => (int) ($row['issues'] ?? 0),
            'noindex' => (int) ($row['noindex'] ?? 0),
        ];
    }

    private function whereClause(GetAuditPagesQuery $query): string
    {
        $clauses = ['audit_id = :audit_id'];

        if ($query->since !== null && $query->since !== '') {
            $clauses[] = 'crawled_at > :since';
        }

        $clauses[] = match ($query->tab) {
            GetAuditPagesQuery::TAB_INTERNAL => "(content_type LIKE '%html%' AND status_code < 300)",
            GetAuditPagesQuery::TAB_HTML => "content_type LIKE '%html%'",
            GetAuditPagesQuery::TAB_REDIRECTS => '(status_code >= 300 AND status_code < 400)',
            GetAuditPagesQuery::TAB_ERRORS => 'status_code >= 400',
            GetAuditPagesQuery::TAB_ISSUES => '(error_count > 0 OR warning_count > 0)',
            GetAuditPagesQuery::TAB_NOINDEX => "(noindex = 1 OR status_code >= 400 OR canonical_status = 'other')",
            default => '1 = 1',
        };

        if ($query->search !== null && $query->search !== '') {
            $clauses[] = '(LOWER(url) LIKE :search OR LOWER(COALESCE(title, "")) LIKE :search)';
        }

        return implode(' AND ', $clauses);
    }

    /** @return array<string, scalar> */
    private function whereParams(GetAuditPagesQuery $query): array
    {
        $params = ['audit_id' => $query->auditId];

        if ($query->since !== null && $query->since !== '') {
            $params['since'] = $query->since;
        }

        if ($query->search !== null && $query->search !== '') {
            $params['search'] = '%' . strtolower($query->search) . '%';
        }

        return $params;
    }

    private function sortColumn(?string $field): string
    {
        if ($field === null) {
            return 'crawled_at';
        }

        return self::SORTABLE[$field] ?? 'crawled_at';
    }

    private function sortDirection(string $dir): string
    {
        return strtolower($dir) === 'desc' ? 'DESC' : 'ASC';
    }

    /** @param array<string, mixed> $row */
    private function toSummary(array $row): PageSummary
    {
        $h1s = json_decode($row['h1s'] ?? '[]', true) ?: [];
        $isIndexable = (int) $row['status_code'] >= 200
            && (int) $row['status_code'] < 300
            && (int) $row['noindex'] === 0
            && $row['canonical_status'] !== 'other';

        return new PageSummary(
            pageId: $row['id'],
            url: $row['url'],
            statusCode: (int) $row['status_code'],
            contentType: $row['content_type'] ?? '',
            bodySize: (int) $row['body_size'],
            responseTime: (float) $row['response_time'],
            crawlDepth: (int) $row['crawl_depth'],
            errorCount: (int) $row['error_count'],
            warningCount: (int) $row['warning_count'],
            isIndexable: $isIndexable,
            title: $row['title'],
            wordCount: (int) $row['word_count'],
            internalLinkCount: (int) $row['internal_link_count'],
            externalLinkCount: (int) $row['external_link_count'],
            imageCount: (int) $row['image_count'],
            canonicalStatus: $row['canonical_status'],
            h1Count: count($h1s),
            crawledAt: $row['crawled_at'],
        );
    }
}
