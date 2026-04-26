<?php

declare(strict_types=1);

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use SeoSpider\Audit\Application\StartAudit\StartAuditCommand;
use SeoSpider\Audit\Application\StartAudit\StartAuditHandler;
use SeoSpider\Audit\Application\GetAuditStatus\GetAuditStatusQuery;
use SeoSpider\Audit\Application\GetAuditStatus\GetAuditStatusHandler;
use SeoSpider\Audit\Application\GetAuditPages\GetAuditPagesQuery;
use SeoSpider\Audit\Application\GetAuditPages\GetAuditPagesHandler;
use SeoSpider\Audit\Application\GetPageDetail\GetPageDetailQuery;
use SeoSpider\Audit\Application\GetPageDetail\GetPageDetailHandler;
use SeoSpider\Audit\Application\GetAuditIssueReport\GetAuditIssueReportQuery;
use SeoSpider\Audit\Application\GetAuditIssueReport\GetAuditIssueReportHandler;
use SeoSpider\Audit\Application\CancelAudit\CancelAuditCommand;
use SeoSpider\Audit\Application\CancelAudit\CancelAuditHandler;
use SeoSpider\Audit\Application\PauseAudit\PauseAuditCommand;
use SeoSpider\Audit\Application\PauseAudit\PauseAuditHandler;
use SeoSpider\Audit\Application\ResumeAudit\ResumeAuditCommand;
use SeoSpider\Audit\Application\ResumeAudit\ResumeAuditHandler;
use App\Jobs\RunCrawlJob;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Uid\Uuid;

class SpiderDashboard extends Component
{
    // ── Form
    public string $url = '';
    public int $maxPages = 500;
    public int $maxDepth = 10;

    // ── Advanced options
    public bool $crawlResources = false;
    public bool $crawlSubdomains = false;
    public bool $followExternalLinks = false;

    // ── Audit state
    public ?string $auditId = null;

    /** @var array<string, mixed>|null */
    public ?array $status = null;

    /** @var array<int, array<string, mixed>> */
    public array $pages = [];

    public bool $crawling = false;
    public bool $paused = false;

    // ── Page detail
    public ?string $selectedPageId = null;

    /** @var array<string, mixed>|null */
    public ?array $selectedPage = null;

    public bool $detailOpen = false;
    public string $detailTab = 'seo';

    // ── Filtering / Search / Sort
    public string $activeTab = 'all';
    public string $searchQuery = '';
    public string $sortField = 'crawlDepth';
    public string $sortDir = 'asc';

    // ── Pagination (server-side; only kicks in when the audit is no
    // longer crawling so live audits keep showing every new row in
    // real time)
    public int $currentPage = 0;
    public int $pageSize = 100;
    public int $pagesTotal = 0;

    /**
     * Raw counts straight from the SQL reader (keys: pages, internal,
     * html, redirects, errors, issues, noindex). The Blade view consumes
     * the post-mapped getTabCountsProperty(); this property exists so
     * the dashboard can cache them between renders without re-querying.
     *
     * @var array<string, int>
     */
    public array $rawTabCounts = [
        'pages' => 0, 'internal' => 0, 'html' => 0,
        'redirects' => 0, 'errors' => 0, 'issues' => 0, 'noindex' => 0,
    ];

    // ── Sidebar
    public bool $sidebarCollapsed = false;

    /** @var array<int, array<string, mixed>> */
    public array $folders = [];

    /** @var array<int, array<string, mixed>> */
    public array $auditHistory = [];

    // ── Folder editing
    public ?string $editingFolderId = null;
    public string $editingFolderName = '';
    public string $newFolderName = '';
    public bool $showNewFolder = false;

    // ── Page discovery tracking
    /** @var array<int, string> */
    public array $knownPageIds = [];

    /** @var array<int, string> */
    public array $newPageIds = [];

    // ── External links
    /** @var array<int, array<string, mixed>> */
    public array $externalLinks = [];

    // ── Progress tracking
    public int $prevPagesCrawled = 0;

    public function mount(): void
    {
        $this->loadFolders();
        $this->loadAuditHistory();
    }

    // ═══════════════════════════════════════
    //  Crawl actions
    // ═══════════════════════════════════════

    public function startCrawl(): void
    {
        $this->validate([
            'url' => 'required|url',
            'maxPages' => 'integer|min:1|max:10000',
            'maxDepth' => 'integer|min:1|max:50',
        ]);

        $handler = app(StartAuditHandler::class);
        $response = $handler(new StartAuditCommand(
            seedUrl: $this->url,
            maxPages: $this->maxPages,
            maxDepth: $this->maxDepth,
            crawlResources: $this->crawlResources,
            crawlSubdomains: $this->crawlSubdomains,
            followExternalLinks: $this->followExternalLinks,
        ));

        $this->auditId = $response->auditId;
        $this->crawling = true;
        $this->reset([
            'selectedPageId', 'selectedPage', 'detailOpen', 'detailTab',
            'pages', 'status', 'knownPageIds', 'newPageIds',
            'searchQuery', 'activeTab', 'prevPagesCrawled',
        ]);

        RunCrawlJob::dispatch($this->auditId);
        $this->refreshStatus();
        $this->loadAuditHistory();
    }

    public function cancelCrawl(): void
    {
        if ($this->auditId === null) {
            return;
        }

        app(CancelAuditHandler::class)(new CancelAuditCommand($this->auditId));
        $this->crawling = false;
        $this->paused = false;
        $this->refreshStatus();
    }

    public function pauseCrawl(): void
    {
        if ($this->auditId === null || !$this->crawling) {
            return;
        }

        app(PauseAuditHandler::class)(new PauseAuditCommand($this->auditId));
        $this->paused = true;
        $this->crawling = false;
        $this->refreshStatus();
    }

    public function resumeCrawl(): void
    {
        if ($this->auditId === null || !$this->paused) {
            return;
        }

        app(ResumeAuditHandler::class)(new ResumeAuditCommand($this->auditId));
        $this->paused = false;
        $this->crawling = true;
        RunCrawlJob::dispatch($this->auditId);
        $this->refreshStatus();
    }

    public function loadAudit(string $auditId): void
    {
        $this->auditId = $auditId;
        $this->reset([
            'selectedPageId', 'selectedPage', 'detailOpen', 'detailTab',
            'searchQuery', 'knownPageIds', 'newPageIds',
        ]);
        $this->refreshStatus();
        $this->refreshPages();
        $this->loadExternalLinks();

        if ($this->status !== null) {
            $this->url = (string) ($this->status['seedUrl'] ?? '');
            $this->crawling = $this->status['status'] === 'running';
            $this->paused = $this->status['status'] === 'paused';
        }
    }

    public function selectPage(string $pageId): void
    {
        $this->selectedPageId = $pageId;
        $this->detailOpen = true;
        $this->detailTab = 'seo';

        $r = app(GetPageDetailHandler::class)(new GetPageDetailQuery($pageId));

        $this->selectedPage = [
            'pageId' => $r->pageId,
            'url' => $r->url,
            'statusCode' => $r->statusCode,
            'contentType' => $r->contentType,
            'bodySize' => $r->bodySize,
            'responseTime' => $r->responseTime,
            'crawlDepth' => $r->crawlDepth,
            'isIndexable' => $r->isIndexable,
            'title' => $r->title,
            'titleLength' => $r->titleLength,
            'metaDescription' => $r->metaDescription,
            'metaDescriptionLength' => $r->metaDescriptionLength,
            'h1s' => $r->h1s,
            'wordCount' => $r->wordCount,
            'canonical' => $r->canonical,
            'canonicalStatus' => $r->canonicalStatus,
            'noindex' => $r->noindex,
            'nofollow' => $r->nofollow,
            'redirectChain' => $r->redirectChain,
            'hreflangs' => $r->hreflangs,
            'internalLinkCount' => $r->internalLinkCount,
            'externalLinkCount' => $r->externalLinkCount,
            'links' => $r->links,
            'issues' => array_map(fn(object $i): array => [
                'id' => $i->id,
                'category' => $i->category,
                'severity' => $i->severity,
                'code' => $i->code,
                'message' => $i->message,
                'context' => $i->context,
            ], $r->issues),
        ];
    }

    public function closeDetail(): void
    {
        $this->detailOpen = false;
    }

    public function setDetailTab(string $tab): void
    {
        $this->detailTab = $tab;
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->currentPage = 0;
        // Tab moves the SQL filter; refetch from the new view.
        if ($this->auditId !== null && !$this->crawling) {
            $this->refreshPages();
        }
    }

    public function toggleSidebar(): void
    {
        $this->sidebarCollapsed = !$this->sidebarCollapsed;
    }

    public function toggleSort(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDir = 'asc';
        }
        $this->currentPage = 0;
        if ($this->auditId !== null && !$this->crawling) {
            $this->refreshPages();
        }
    }

    public function nextPage(): void
    {
        if (($this->currentPage + 1) * $this->pageSize >= $this->pagesTotal) {
            return;
        }
        $this->currentPage++;
        $this->refreshPages();
    }

    public function prevPage(): void
    {
        if ($this->currentPage === 0) {
            return;
        }
        $this->currentPage--;
        $this->refreshPages();
    }

    public function updatedSearchQuery(): void
    {
        $this->currentPage = 0;
        if ($this->auditId !== null && !$this->crawling) {
            $this->refreshPages();
        }
    }

    public function poll(): void
    {
        if ($this->auditId === null || !$this->crawling) {
            return;
        }

        $this->prevPagesCrawled = (int) ($this->status['pagesCrawled'] ?? 0);
        $this->refreshStatus();
        $this->refreshPages();
        $this->loadExternalLinks();

        if ($this->status !== null && in_array($this->status['status'], ['completed', 'failed', 'cancelled'], true)) {
            $this->crawling = false;
            $this->loadAuditHistory();
        }
    }

    // ═══════════════════════════════════════
    //  Folder CRUD
    // ═══════════════════════════════════════

    public function createFolder(): void
    {
        $name = trim($this->newFolderName);
        if ($name === '') {
            return;
        }

        $pdo = app(\PDO::class);
        $id = Uuid::v7()->toRfc4122();

        $stmt = $pdo->query('SELECT COALESCE(MAX(sort_order), 0) FROM folders');
        $maxOrder = $stmt !== false ? (int) $stmt->fetchColumn() : 0;

        $pdo->prepare('INSERT INTO folders (id, name, sort_order) VALUES (?, ?, ?)')
            ->execute([$id, $name, $maxOrder + 1]);

        $this->newFolderName = '';
        $this->showNewFolder = false;
        $this->loadFolders();
    }

    public function startEditFolder(string $folderId): void
    {
        $this->editingFolderId = $folderId;
        foreach ($this->folders as $f) {
            if (($f['id'] ?? null) === $folderId) {
                $this->editingFolderName = (string) ($f['name'] ?? '');
                break;
            }
        }
    }

    public function saveFolder(): void
    {
        if ($this->editingFolderId === null) {
            return;
        }

        $name = trim($this->editingFolderName);
        if ($name === '') {
            return;
        }

        $pdo = app(\PDO::class);
        $pdo->prepare('UPDATE folders SET name = ? WHERE id = ?')
            ->execute([$name, $this->editingFolderId]);

        $this->editingFolderId = null;
        $this->editingFolderName = '';
        $this->loadFolders();
    }

    public function cancelEditFolder(): void
    {
        $this->editingFolderId = null;
        $this->editingFolderName = '';
    }

    public function deleteFolder(string $folderId): void
    {
        $pdo = app(\PDO::class);
        $pdo->prepare('UPDATE audits SET folder_id = NULL WHERE folder_id = ?')->execute([$folderId]);
        $pdo->prepare('DELETE FROM folders WHERE id = ?')->execute([$folderId]);

        $this->loadFolders();
        $this->loadAuditHistory();
    }

    public function moveAuditToFolder(string $auditId, ?string $folderId): void
    {
        $pdo = app(\PDO::class);
        $pdo->prepare('UPDATE audits SET folder_id = ? WHERE id = ?')
            ->execute([$folderId ?: null, $auditId]);

        $this->loadAuditHistory();
    }

    public function deleteAudit(string $auditId): void
    {
        if ($this->auditId === $auditId && $this->crawling) {
            return;
        }

        $pdo = app(\PDO::class);
        $pdo->prepare('DELETE FROM external_url_checks WHERE audit_id = ?')->execute([$auditId]);
        $pdo->prepare('DELETE FROM issues WHERE page_id IN (SELECT id FROM pages WHERE audit_id = ?)')->execute([$auditId]);
        $pdo->prepare('DELETE FROM pages WHERE audit_id = ?')->execute([$auditId]);
        $pdo->prepare('DELETE FROM frontier WHERE audit_id = ?')->execute([$auditId]);
        $pdo->prepare('DELETE FROM audits WHERE id = ?')->execute([$auditId]);

        if ($this->auditId === $auditId) {
            $this->auditId = null;
            $this->status = null;
            $this->pages = [];
            $this->reset(['selectedPageId', 'selectedPage', 'detailOpen']);
        }

        $this->loadAuditHistory();
    }

    // ═══════════════════════════════════════
    //  Data refresh
    // ═══════════════════════════════════════

    public function refreshStatus(): void
    {
        if ($this->auditId === null) {
            return;
        }

        $r = app(GetAuditStatusHandler::class)(new GetAuditStatusQuery($this->auditId));
        $this->status = [
            'seedUrl' => $r->seedUrl,
            'status' => $r->status,
            'pagesCrawled' => $r->pagesCrawled,
            'pagesFailed' => $r->pagesFailed,
            'pagesDiscovered' => $r->pagesDiscovered,
            'pendingUrls' => $r->pendingUrls,
            'issuesFound' => $r->issuesFound,
            'errorsFound' => $r->errorsFound,
            'warningsFound' => $r->warningsFound,
            'maxPages' => $r->maxPages,
            'duration' => $r->duration,
            'startedAt' => $r->startedAt,
            'completedAt' => $r->completedAt,
        ];
    }

    public function refreshPages(): void
    {
        if ($this->auditId === null) {
            return;
        }

        // During a live crawl with pages already in memory, only fetch
        // the rows added since the latest crawledAt we know about.
        // The first poll of a session and any non-crawl refresh fall
        // back to the full snapshot — needed because previously-seen
        // pages can pick up new issues from site-wide analyzers when
        // the audit completes.
        $useDelta = $this->crawling && $this->pages !== [];
        $since = $useDelta ? $this->latestCrawledAt() : null;

        $r = app(GetAuditPagesHandler::class)(new GetAuditPagesQuery(
            auditId: $this->auditId,
            since: $since,
            tab: $this->mapActiveTabToQuery(),
            search: $this->searchQuery !== '' ? $this->searchQuery : null,
            sortField: $this->sortField,
            sortDir: $this->sortDir,
            // Live crawls show every newly arrived row in real time, so we
            // skip pagination there. Once the audit settles we cap each
            // load to one page so a 1000+ row audit never blows up the
            // Livewire payload.
            limit: $this->crawling ? null : $this->pageSize,
            offset: $this->crawling ? 0 : $this->currentPage * $this->pageSize,
        ));

        $this->pagesTotal = $r->total;
        $this->rawTabCounts = app(\SeoSpider\Audit\Application\GetAuditPages\PageSummaryReader::class)
            ->tabCounts(new \SeoSpider\Audit\Domain\Model\Audit\AuditId($this->auditId));

        $previousIds = $this->knownPageIds;

        $incoming = array_map(fn(object $p): array => [
            'pageId' => $p->pageId,
            'url' => $p->url,
            'statusCode' => $p->statusCode,
            'contentType' => $p->contentType ?? '',
            'title' => $p->title,
            'responseTime' => $p->responseTime,
            'bodySize' => $p->bodySize,
            'crawlDepth' => $p->crawlDepth,
            'errorCount' => $p->errorCount,
            'warningCount' => $p->warningCount,
            'isIndexable' => $p->isIndexable,
            'wordCount' => $p->wordCount,
            'internalLinkCount' => $p->internalLinkCount,
            'externalLinkCount' => $p->externalLinkCount,
            'imageCount' => $p->imageCount,
            'canonicalStatus' => $p->canonicalStatus,
            'h1Count' => $p->h1Count,
            'crawledAt' => $p->crawledAt,
        ], $r->pages);

        if ($useDelta) {
            $merged = array_column($this->pages, null, 'pageId');
            foreach ($incoming as $row) {
                $merged[$row['pageId']] = $row;
            }
            $this->pages = array_values($merged);
        } else {
            $this->pages = $incoming;
        }

        /** @var array<int, string> $currentIds */
        $currentIds = array_map(fn(array $p): string => (string) $p['pageId'], $this->pages);
        $this->newPageIds = array_values(array_diff($currentIds, $previousIds));
        $this->knownPageIds = $currentIds;
    }

    private function mapActiveTabToQuery(): ?string
    {
        return match ($this->activeTab) {
            'internal' => \SeoSpider\Audit\Application\GetAuditPages\GetAuditPagesQuery::TAB_INTERNAL,
            'html' => \SeoSpider\Audit\Application\GetAuditPages\GetAuditPagesQuery::TAB_HTML,
            'redirects' => \SeoSpider\Audit\Application\GetAuditPages\GetAuditPagesQuery::TAB_REDIRECTS,
            'errors' => \SeoSpider\Audit\Application\GetAuditPages\GetAuditPagesQuery::TAB_ERRORS,
            'issues' => \SeoSpider\Audit\Application\GetAuditPages\GetAuditPagesQuery::TAB_ISSUES,
            'noindex' => \SeoSpider\Audit\Application\GetAuditPages\GetAuditPagesQuery::TAB_NOINDEX,
            // 'all', 'overview', 'audit', 'external' fall through to the
            // unfiltered result set.
            default => null,
        };
    }

    private function latestCrawledAt(): ?string
    {
        $timestamps = array_column($this->pages, 'crawledAt');
        if ($timestamps === []) {
            return null;
        }

        $max = max($timestamps);

        return is_string($max) && $max !== '' ? $max : null;
    }

    public function loadExternalLinks(): void
    {
        if ($this->auditId === null) {
            $this->externalLinks = [];
            return;
        }

        $pdo = app(\PDO::class);
        $stmt = $pdo->prepare(
            'SELECT e.url, e.status_code, e.response_time, e.error, e.anchor_text, p.url as source_url
             FROM external_url_checks e
             LEFT JOIN pages p ON p.id = e.source_page_id
             WHERE e.audit_id = ?
             ORDER BY e.status_code DESC, e.url ASC'
        );
        $stmt->execute([$this->auditId]);
        $this->externalLinks = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function exportCsv(): StreamedResponse
    {
        $pages = $this->getFilteredPagesProperty();
        $tab = $this->activeTab;

        return response()->streamDownload(function () use ($pages): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fputcsv($out, [
                'URL', 'Status', 'Title', 'Words', 'H1', 'Int. Links', 'Ext. Links',
                'Images', 'Canonical', 'Size (B)', 'Time (ms)', 'Depth',
                'Errors', 'Warnings', 'Indexable',
            ]);

            foreach ($pages as $page) {
                fputcsv($out, [
                    $page['url'],
                    $page['statusCode'],
                    $page['title'] ?? '',
                    $page['wordCount'],
                    $page['h1Count'],
                    $page['internalLinkCount'],
                    $page['externalLinkCount'],
                    $page['imageCount'],
                    $page['canonicalStatus'],
                    $page['bodySize'],
                    number_format((float) $page['responseTime'], 0),
                    $page['crawlDepth'],
                    $page['errorCount'],
                    $page['warningCount'],
                    ($page['isIndexable'] ?? false) ? 'Yes' : 'No',
                ]);
            }

            fclose($out);
        }, sprintf('seo-audit-%s-%s.csv', $tab, date('Y-m-d')), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportExternalCsv(): StreamedResponse
    {
        $links = $this->externalLinks;

        return response()->streamDownload(function () use ($links): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fputcsv($out, ['External URL', 'Status', 'Time (ms)', 'Error', 'Anchor Text', 'Source page']);

            foreach ($links as $link) {
                fputcsv($out, [
                    $link['url'],
                    $link['status_code'] ?? 'Error',
                    number_format((float) ($link['response_time'] ?? 0), 0),
                    $link['error'] ?? '',
                    $link['anchor_text'] ?? '',
                    $link['source_url'] ?? '',
                ]);
            }

            fclose($out);
        }, sprintf('external-links-%s.csv', date('Y-m-d')), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    // ═══════════════════════════════════════
    //  Computed properties
    // ═══════════════════════════════════════

    /**
     * Tab/search/sort/paginate semantics now live in the SQL reader, so
     * this property no longer filters — it just hands the already-loaded
     * page out, optionally tacking external link checks onto the rare
     * tabs that mixed both.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getFilteredPagesProperty(): array
    {
        if (in_array($this->activeTab, ['external'], true)) {
            return [];
        }

        $rows = $this->pages;

        if (in_array($this->activeTab, ['all', 'redirects', 'errors'], true)) {
            $extAsPages = $this->externalLinksAsPages();
            if ($this->activeTab === 'redirects') {
                $extAsPages = array_filter(
                    $extAsPages,
                    static fn(array $p) => $p['statusCode'] >= 300 && $p['statusCode'] < 400,
                );
            } elseif ($this->activeTab === 'errors') {
                $extAsPages = array_filter(
                    $extAsPages,
                    static fn(array $p) => $p['statusCode'] >= 400 || ((int) $p['statusCode'] === 0 && !empty($p['isExternalCheck'])),
                );
            }
            $rows = array_merge($rows, array_values($extAsPages));
        }

        return $rows;
    }

    /**
     * @return array<string, int>
     */
    public function getTabCountsProperty(): array
    {
        $extCount = count($this->externalLinks);
        $tc = $this->rawTabCounts;

        return [
            'overview'  => $tc['pages'],
            'all'       => $tc['pages'] + $extCount,
            'internal'  => $tc['internal'],
            'external'  => $extCount,
            'html'      => $tc['html'],
            'redirects' => $tc['redirects'],
            'errors'    => $tc['errors'],
            'issues'    => $tc['issues'],
            'noindex'   => $tc['noindex'],
        ];
    }

    /**
     * Site-wide issue report grouped by rule code. Read model backing the
     * "audit" tab, computed on-demand (Livewire caches per render cycle).
     *
     * @return array<string, mixed>
     */
    public function getAuditReportProperty(): array
    {
        if ($this->auditId === null) {
            return [
                'totalIssues' => 0,
                'affectedPages' => 0,
                'severityTotals' => [],
                'categoryTotals' => [],
                'groups' => [],
                'siteScore' => 100,
            ];
        }

        $r = app(GetAuditIssueReportHandler::class)(new GetAuditIssueReportQuery($this->auditId));

        return [
            'totalIssues' => $r->totalIssues,
            'affectedPages' => $r->affectedPages,
            'severityTotals' => $r->severityTotals,
            'categoryTotals' => $r->categoryTotals,
            'siteScore' => $r->siteScore,
            'groups' => array_map(static fn($g): array => [
                'code' => $g->code,
                'category' => $g->category,
                'severity' => $g->severity,
                'title' => $g->title,
                'summary' => $g->summary,
                'why' => $g->why,
                'how' => $g->how,
                'source' => $g->source,
                'count' => $g->count,
                'affectedPageCount' => $g->affectedPageCount,
                'weight' => $g->weight,
                'affectedPages' => array_map(static fn($p): array => [
                    'pageId' => $p->pageId,
                    'url' => $p->url,
                    'context' => $p->context,
                ], $g->affectedPages),
            ], $r->groups),
        ];
    }

    /**
     * @return array{pct: float, label: string, rate: float}
     */
    public function getProgressProperty(): array
    {
        if ($this->status === null) {
            return ['pct' => 0.0, 'label' => '', 'rate' => 0.0];
        }

        $crawled = (int) $this->status['pagesCrawled'];
        $discovered = (int) $this->status['pagesDiscovered'];
        $duration = (float) ($this->status['duration'] ?? 0);

        $total = $this->crawling ? max($discovered, 1) : max($crawled, 1);
        $pct = min(($crawled / $total) * 100, 100);

        return [
            'pct' => round($pct, 1),
            'label' => "{$crawled} / {$discovered}",
            'rate' => $duration > 0 ? round($crawled / $duration, 1) : 0.0,
        ];
    }

    /**
     * Completed audits read a frozen snapshot built once when the
     * crawl finished — a single SELECT instead of recomputing the
     * aggregations on every render. Live audits (still crawling) fall
     * back to running the SQL builder against the current state.
     *
     * @return array<string, mixed>
     */
    public function getOverviewProperty(): array
    {
        if ($this->auditId === null) {
            return [];
        }

        $auditId = new \SeoSpider\Audit\Domain\Model\Audit\AuditId($this->auditId);

        $snapshot = app(\SeoSpider\Audit\Domain\Model\Audit\AuditSnapshotRepository::class)
            ->findByAudit($auditId);

        if ($snapshot !== null) {
            return $snapshot->overview + ['totalExternal' => count($this->externalLinks)];
        }

        if ($this->pagesTotal === 0) {
            return [];
        }

        return app(\SeoSpider\Audit\Application\AuditOverview\AuditOverviewBuilder::class)
            ->build($auditId)
            + ['totalExternal' => count($this->externalLinks)];
    }

    // ═══════════════════════════════════════
    //  Private
    // ═══════════════════════════════════════

    private function loadFolders(): void
    {
        $pdo = app(\PDO::class);
        $stmt = $pdo->query('SELECT * FROM folders ORDER BY sort_order ASC, name ASC');
        $this->folders = $stmt !== false ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
    }

    private function loadAuditHistory(): void
    {
        $pdo = app(\PDO::class);
        $stmt = $pdo->query(
            'SELECT id, folder_id, seed_url, status, pages_crawled, errors_found, warnings_found, created_at
             FROM audits ORDER BY created_at DESC LIMIT 100'
        );
        $this->auditHistory = $stmt !== false ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function externalLinksAsPages(): array
    {
        // external_url_checks stores one row per (url, source_page_id) by
        // design, so the broken-link report can show which pages link to a
        // given url. Tabs that merge externals into the page list want one
        // entry per url — dedupe by url keeping the first occurrence.
        $deduped = [];
        foreach ($this->externalLinks as $ext) {
            $deduped[(string) ($ext['url'] ?? '')] ??= $ext;
        }

        return array_values(array_map(static fn(array $ext): array => [
            'pageId' => null,
            'url' => $ext['url'],
            'statusCode' => (int) ($ext['status_code'] ?? 0),
            'contentType' => 'external',
            'title' => $ext['anchor_text'] ?? null,
            'responseTime' => (float) ($ext['response_time'] ?? 0),
            'bodySize' => 0,
            'crawlDepth' => 0,
            'errorCount' => ((int) ($ext['status_code'] ?? 0) >= 400 || (int) ($ext['status_code'] ?? 0) === 0) ? 1 : 0,
            'warningCount' => ($ext['error'] ?? null) !== null ? 1 : 0,
            'isIndexable' => false,
            'wordCount' => 0,
            'internalLinkCount' => 0,
            'externalLinkCount' => 0,
            'imageCount' => 0,
            'canonicalStatus' => '-',
            'h1Count' => 0,
            'isExternalCheck' => true,
        ], $deduped));
    }

    public function render(): View
    {
        return view('livewire.spider-dashboard')
            ->layout('components.layout'); // @phpstan-ignore method.notFound
    }
}