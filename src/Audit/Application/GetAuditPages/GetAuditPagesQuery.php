<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Application\GetAuditPages;

final readonly class GetAuditPagesQuery
{
    public const string TAB_ALL_PAGES = 'pages';
    public const string TAB_INTERNAL = 'internal';
    public const string TAB_HTML = 'html';
    public const string TAB_REDIRECTS = 'redirects';
    public const string TAB_ERRORS = 'errors';
    public const string TAB_ISSUES = 'issues';
    public const string TAB_NOINDEX = 'noindex';

    /**
     * @param ?string $since      ISO-8601 timestamp; when set, the handler
     *                            only returns pages crawled strictly after
     *                            it (delta fetch for the polling UI).
     * @param ?string $tab        Predicate filter shorthand: html, internal,
     *                            redirects, errors, issues, noindex. null
     *                            returns every audit page.
     * @param ?string $search     Substring matched against url and title
     *                            (case-insensitive).
     * @param ?string $sortField  Column name in PageSummary; null falls
     *                            back to crawled_at ascending.
     * @param string  $sortDir    'asc' | 'desc'.
     * @param ?int    $limit      Page size for the list query; null returns
     *                            every match.
     * @param int     $offset     Row offset for paginated lists.
     */
    public function __construct(
        public string $auditId,
        public ?string $since = null,
        public ?string $tab = null,
        public ?string $search = null,
        public ?string $sortField = null,
        public string $sortDir = 'asc',
        public ?int $limit = null,
        public int $offset = 0,
    ) {
    }
}
