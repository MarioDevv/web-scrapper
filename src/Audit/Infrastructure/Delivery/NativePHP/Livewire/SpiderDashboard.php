<?php

declare(strict_types=1);

namespace App\Livewire;

use Livewire\Component;
use SeoSpider\Audit\Application\StartAudit\StartAuditCommand;
use SeoSpider\Audit\Application\StartAudit\StartAuditHandler;
use SeoSpider\Audit\Application\GetAuditStatus\GetAuditStatusQuery;
use SeoSpider\Audit\Application\GetAuditStatus\GetAuditStatusHandler;
use SeoSpider\Audit\Application\GetAuditPages\GetAuditPagesQuery;
use SeoSpider\Audit\Application\GetAuditPages\GetAuditPagesHandler;
use SeoSpider\Audit\Application\GetPageDetail\GetPageDetailQuery;
use SeoSpider\Audit\Application\GetPageDetail\GetPageDetailHandler;
use SeoSpider\Audit\Application\CancelAudit\CancelAuditCommand;
use SeoSpider\Audit\Application\CancelAudit\CancelAuditHandler;
use SeoSpider\Audit\Application\PauseAudit\PauseAuditCommand;
use SeoSpider\Audit\Application\PauseAudit\PauseAuditHandler;
use SeoSpider\Audit\Application\ResumeAudit\ResumeAuditCommand;
use SeoSpider\Audit\Application\ResumeAudit\ResumeAuditHandler;
use App\Jobs\RunCrawlJob;
use Symfony\Component\Uid\Uuid;

class SpiderDashboard extends Component
{
    // ── Form
    public string $url = '';
    public int $maxPages = 500;
    public int $maxDepth = 10;

    // ── Audit state
    public ?string $auditId = null;
    public ?array $status = null;
    public array $pages = [];
    public bool $crawling = false;
    public bool $paused = false;

    // ── Page detail
    public ?string $selectedPageId = null;
    public ?array $selectedPage = null;
    public bool $detailOpen = false;
    public string $detailTab = 'seo';

    // ── Filtering / Search / Sort
    public string $activeTab = 'all';
    public string $searchQuery = '';
    public string $sortField = 'crawlDepth';
    public string $sortDir = 'asc';

    // ── Sidebar
    public bool $sidebarCollapsed = false;
    public array $folders = [];
    public array $auditHistory = [];

    // ── Folder editing
    public ?string $editingFolderId = null;
    public string $editingFolderName = '';
    public string $newFolderName = '';
    public bool $showNewFolder = false;

    // ── Page discovery tracking
    public array $knownPageIds = [];
    public array $newPageIds = [];

    // ── External links
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
    }

    public function cancelCrawl(): void
    {
        if (!$this->auditId) return;
        app(CancelAuditHandler::class)(new CancelAuditCommand($this->auditId));
        $this->crawling = false;
        $this->paused = false;
        $this->refreshStatus();
    }

    public function pauseCrawl(): void
    {
        if (!$this->auditId || !$this->crawling) return;
        app(PauseAuditHandler::class)(new PauseAuditCommand($this->auditId));
        $this->paused = true;
        $this->crawling = false;
        $this->refreshStatus();
    }

    public function resumeCrawl(): void
    {
        if (!$this->auditId || !$this->paused) return;
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
        if ($this->status) {
            $this->url = $this->status['seedUrl'] ?? '';
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
            'issues' => array_map(fn($i) => [
                'id' => $i->id,
                'category' => $i->category,
                'severity' => $i->severity,
                'code' => $i->code,
                'message' => $i->message,
                'context' => $i->context,
            ], $r->issues),
        ];
    }

    public function closeDetail(): void { $this->detailOpen = false; }
    public function setDetailTab(string $tab): void { $this->detailTab = $tab; }
    public function setTab(string $tab): void { $this->activeTab = $tab; }
    public function toggleSidebar(): void { $this->sidebarCollapsed = !$this->sidebarCollapsed; }

    public function toggleSort(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDir = 'asc';
        }
    }

    public function poll(): void
    {
        if (!$this->auditId || !$this->crawling) return;

        $this->prevPagesCrawled = $this->status['pagesCrawled'] ?? 0;
        $this->refreshStatus();
        $this->refreshPages();
        $this->loadExternalLinks();

        if ($this->status && in_array($this->status['status'], ['completed', 'failed', 'cancelled'])) {
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
        if ($name === '') return;

        $pdo = app(\PDO::class);
        $id = Uuid::v7()->toRfc4122();
        $maxOrder = (int) $pdo->query("SELECT COALESCE(MAX(sort_order), 0) FROM folders")->fetchColumn();

        $pdo->prepare("INSERT INTO folders (id, name, sort_order) VALUES (?, ?, ?)")
            ->execute([$id, $name, $maxOrder + 1]);

        $this->newFolderName = '';
        $this->showNewFolder = false;
        $this->loadFolders();
    }

    public function startEditFolder(string $folderId): void
    {
        $this->editingFolderId = $folderId;
        foreach ($this->folders as $f) {
            if ($f['id'] === $folderId) {
                $this->editingFolderName = $f['name'];
                break;
            }
        }
    }

    public function saveFolder(): void
    {
        if (!$this->editingFolderId) return;
        $name = trim($this->editingFolderName);
        if ($name === '') return;

        $pdo = app(\PDO::class);
        $pdo->prepare("UPDATE folders SET name = ? WHERE id = ?")
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
        // Move audits out of folder first
        $pdo->prepare("UPDATE audits SET folder_id = NULL WHERE folder_id = ?")->execute([$folderId]);
        $pdo->prepare("DELETE FROM folders WHERE id = ?")->execute([$folderId]);

        $this->loadFolders();
        $this->loadAuditHistory();
    }

    public function moveAuditToFolder(string $auditId, ?string $folderId): void
    {
        $pdo = app(\PDO::class);
        $pdo->prepare("UPDATE audits SET folder_id = ? WHERE id = ?")
            ->execute([$folderId ?: null, $auditId]);

        $this->loadAuditHistory();
    }

    public function deleteAudit(string $auditId): void
    {
        if ($this->auditId === $auditId && $this->crawling) return;

        $pdo = app(\PDO::class);
        // Delete in correct order for foreign keys
        $pdo->prepare("DELETE FROM external_url_checks WHERE audit_id = ?")->execute([$auditId]);
        $pdo->prepare("DELETE FROM issues WHERE page_id IN (SELECT id FROM pages WHERE audit_id = ?)")->execute([$auditId]);
        $pdo->prepare("DELETE FROM pages WHERE audit_id = ?")->execute([$auditId]);
        $pdo->prepare("DELETE FROM frontier WHERE audit_id = ?")->execute([$auditId]);
        $pdo->prepare("DELETE FROM audits WHERE id = ?")->execute([$auditId]);

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
        if (!$this->auditId) return;
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
        if (!$this->auditId) return;
        $r = app(GetAuditPagesHandler::class)(new GetAuditPagesQuery($this->auditId));

        $previousIds = $this->knownPageIds;

        $this->pages = array_map(fn($p) => [
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
        ], $r->pages);

        $currentIds = array_map(fn($p) => $p['pageId'], $this->pages);
        $this->newPageIds = array_values(array_diff($currentIds, $previousIds));
        $this->knownPageIds = $currentIds;
    }

    public function loadExternalLinks(): void
    {
        if (!$this->auditId) {
            $this->externalLinks = [];
            return;
        }

        $pdo = app(\PDO::class);
        $stmt = $pdo->prepare(
            "SELECT e.url, e.status_code, e.response_time, e.error, e.anchor_text, p.url as source_url
             FROM external_url_checks e
             LEFT JOIN pages p ON p.id = e.source_page_id
             WHERE e.audit_id = ?
             ORDER BY e.status_code DESC, e.url ASC"
        );
        $stmt->execute([$this->auditId]);
        $this->externalLinks = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function exportCsv(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $pages = $this->filteredPages;
        $tab = $this->activeTab;

        return response()->streamDownload(function () use ($pages) {
            $out = fopen('php://output', 'w');

            // Header row
            fputcsv($out, [
                'URL', 'Estado', 'Título', 'Palabras', 'H1', 'Int. Links', 'Ext. Links',
                'Imágenes', 'Canonical', 'Tamaño (B)', 'Tiempo (ms)', 'Profundidad',
                'Errores', 'Avisos', 'Indexable',
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
                    number_format($page['responseTime'], 0),
                    $page['crawlDepth'],
                    $page['errorCount'],
                    $page['warningCount'],
                    $page['isIndexable'] ? 'Sí' : 'No',
                ]);
            }

            fclose($out);
        }, sprintf('seo-audit-%s-%s.csv', $tab, date('Y-m-d')), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportExternalCsv(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $links = $this->externalLinks;

        return response()->streamDownload(function () use ($links) {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['URL Externa', 'Estado', 'Tiempo (ms)', 'Error', 'Anchor Text', 'Página origen']);

            foreach ($links as $link) {
                fputcsv($out, [
                    $link['url'],
                    $link['status_code'] ?? 'Error',
                    number_format($link['response_time'] ?? 0, 0),
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

    public function getFilteredPagesProperty(): array
    {
        $extAsPages = $this->externalLinksAsPages();

        $pages = match ($this->activeTab) {
            'all'       => array_merge($this->pages, $extAsPages),
            'overview'  => [],  // handled in blade via $this->overview
            'internal'  => array_values(array_filter($this->pages, fn($p) => str_contains($p['contentType'] ?? '', 'html') && $p['statusCode'] < 300)),
            'external'  => [],  // handled separately in blade
            'html'      => array_values(array_filter($this->pages, fn($p) => str_contains($p['contentType'] ?? '', 'html'))),
            'redirects' => array_values(array_filter(
                array_merge($this->pages, $extAsPages),
                fn($p) => $p['statusCode'] >= 300 && $p['statusCode'] < 400
            )),
            'errors'    => array_values(array_filter(
                array_merge($this->pages, $extAsPages),
                fn($p) => $p['statusCode'] >= 400 || ($p['statusCode'] === 0 && !empty($p['isExternalCheck']))
            )),
            'issues'    => array_values(array_filter($this->pages, fn($p) => $p['errorCount'] > 0 || $p['warningCount'] > 0)),
            'noindex'   => array_values(array_filter($this->pages, fn($p) => !$p['isIndexable'])),
            default     => $this->pages,
        };

        if ($this->searchQuery !== '') {
            $q = mb_strtolower($this->searchQuery);
            $pages = array_values(array_filter($pages, fn($p) =>
                str_contains(mb_strtolower($p['url']), $q) ||
                str_contains(mb_strtolower($p['title'] ?? ''), $q)
            ));
        }

        usort($pages, function ($a, $b) {
            $va = $a[$this->sortField] ?? '';
            $vb = $b[$this->sortField] ?? '';
            $cmp = is_numeric($va) && is_numeric($vb) ? ($va <=> $vb) : strcasecmp((string) $va, (string) $vb);
            return $this->sortDir === 'asc' ? $cmp : -$cmp;
        });

        return $pages;
    }

    public function getTabCountsProperty(): array
    {
        $extAsPages = $this->externalLinksAsPages();

        return [
            'overview'  => count($this->pages),
            'all'       => count($this->pages) + count($extAsPages),
            'internal'  => count(array_filter($this->pages, fn($p) => str_contains($p['contentType'] ?? '', 'html') && $p['statusCode'] < 300)),
            'external'  => count($this->externalLinks),
            'html'      => count(array_filter($this->pages, fn($p) => str_contains($p['contentType'] ?? '', 'html'))),
            'redirects' => count(array_filter(
                array_merge($this->pages, $extAsPages),
                fn($p) => $p['statusCode'] >= 300 && $p['statusCode'] < 400
            )),
            'errors'    => count(array_filter(
                array_merge($this->pages, $extAsPages),
                fn($p) => $p['statusCode'] >= 400 || ($p['statusCode'] === 0 && !empty($p['isExternalCheck']))
            )),
            'issues'    => count(array_filter($this->pages, fn($p) => $p['errorCount'] > 0 || $p['warningCount'] > 0)),
            'noindex'   => count(array_filter($this->pages, fn($p) => !$p['isIndexable'])),
        ];
    }

    public function getProgressProperty(): array
    {
        if (!$this->status) return ['pct' => 0, 'label' => '', 'rate' => 0];

        $crawled = $this->status['pagesCrawled'];
        $discovered = $this->status['pagesDiscovered'];

        $total = $this->crawling ? max($discovered, 1) : max($crawled, 1);
        $pct = min(($crawled / $total) * 100, 100);

        return [
            'pct' => round($pct, 1),
            'label' => "{$crawled} / {$discovered}",
            'rate' => $this->status['duration'] > 0
                ? round($crawled / $this->status['duration'], 1) : 0,
        ];
    }

    public function getOverviewProperty(): array
    {
        if (count($this->pages) === 0) {
            return [];
        }

        // Status code distribution
        $statusGroups = ['2xx' => 0, '3xx' => 0, '4xx' => 0, '5xx' => 0];
        $depthDist = [];
        $responseTimeBuckets = ['<200ms' => 0, '200-500ms' => 0, '500ms-1s' => 0, '1-3s' => 0, '>3s' => 0];
        $issuesByCategory = [];
        $issuesBySeverity = ['error' => 0, 'warning' => 0, 'notice' => 0, 'info' => 0];
        $totalWords = 0;
        $totalImages = 0;
        $pagesWithoutTitle = 0;
        $pagesWithoutDesc = 0;
        $pagesWithoutH1 = 0;

        foreach ($this->pages as $p) {
            $sc = $p['statusCode'];
            if ($sc >= 200 && $sc < 300) $statusGroups['2xx']++;
            elseif ($sc >= 300 && $sc < 400) $statusGroups['3xx']++;
            elseif ($sc >= 400 && $sc < 500) $statusGroups['4xx']++;
            elseif ($sc >= 500) $statusGroups['5xx']++;

            $d = $p['crawlDepth'];
            $depthDist[$d] = ($depthDist[$d] ?? 0) + 1;

            $rt = $p['responseTime'];
            if ($rt < 200) $responseTimeBuckets['<200ms']++;
            elseif ($rt < 500) $responseTimeBuckets['200-500ms']++;
            elseif ($rt < 1000) $responseTimeBuckets['500ms-1s']++;
            elseif ($rt < 3000) $responseTimeBuckets['1-3s']++;
            else $responseTimeBuckets['>3s']++;

            $totalWords += $p['wordCount'];
            $totalImages += $p['imageCount'];
            if ($p['h1Count'] === 0 && str_contains($p['contentType'] ?? '', 'html')) $pagesWithoutH1++;
        }

        // Collect all issues from all pages via DB
        $pdo = app(\PDO::class);
        if ($this->auditId) {
            $stmt = $pdo->prepare(
                "SELECT i.category, i.severity, COUNT(*) as cnt
                 FROM issues i
                 JOIN pages p ON p.id = i.page_id
                 WHERE p.audit_id = ?
                 GROUP BY i.category, i.severity"
            );
            $stmt->execute([$this->auditId]);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $cat = $row['category'];
                $sev = $row['severity'];
                $issuesByCategory[$cat] = ($issuesByCategory[$cat] ?? 0) + (int)$row['cnt'];
                $issuesBySeverity[$sev] = ($issuesBySeverity[$sev] ?? 0) + (int)$row['cnt'];
            }

            // Pages without title/desc
            $stmt2 = $pdo->prepare(
                "SELECT
                    SUM(CASE WHEN title IS NULL OR title = '' THEN 1 ELSE 0 END) as no_title,
                    SUM(CASE WHEN meta_description IS NULL OR meta_description = '' THEN 1 ELSE 0 END) as no_desc
                 FROM pages WHERE audit_id = ? AND is_html = 1"
            );
            $stmt2->execute([$this->auditId]);
            $meta = $stmt2->fetch(\PDO::FETCH_ASSOC);
            $pagesWithoutTitle = (int)($meta['no_title'] ?? 0);
            $pagesWithoutDesc = (int)($meta['no_desc'] ?? 0);
        }

        ksort($depthDist);

        return [
            'statusGroups' => $statusGroups,
            'depthDistribution' => $depthDist,
            'responseTimeBuckets' => $responseTimeBuckets,
            'issuesByCategory' => $issuesByCategory,
            'issuesBySeverity' => $issuesBySeverity,
            'totalIssues' => array_sum($issuesBySeverity),
            'totalPages' => count($this->pages),
            'totalExternal' => count($this->externalLinks),
            'avgResponseTime' => count($this->pages) > 0
                ? round(array_sum(array_column($this->pages, 'responseTime')) / count($this->pages), 0) : 0,
            'totalWords' => $totalWords,
            'totalImages' => $totalImages,
            'pagesWithoutTitle' => $pagesWithoutTitle,
            'pagesWithoutDesc' => $pagesWithoutDesc,
            'pagesWithoutH1' => $pagesWithoutH1,
        ];
    }

    // ═══════════════════════════════════════
    //  Private
    // ═══════════════════════════════════════

    private function loadFolders(): void
    {
        $pdo = app(\PDO::class);
        $this->folders = $pdo->query("SELECT * FROM folders ORDER BY sort_order ASC, name ASC")
            ->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function loadAuditHistory(): void
    {
        $pdo = app(\PDO::class);
        $this->auditHistory = $pdo->query(
            "SELECT id, folder_id, seed_url, status, pages_crawled, errors_found, warnings_found, created_at
             FROM audits ORDER BY created_at DESC LIMIT 100"
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Map external link checks to page-like arrays for unified table rendering.
     * @return array<int, array<string, mixed>>
     */
    private function externalLinksAsPages(): array
    {
        return array_map(fn($ext) => [
            'pageId' => null,
            'url' => $ext['url'],
            'statusCode' => (int) ($ext['status_code'] ?? 0),
            'contentType' => 'external',
            'title' => $ext['anchor_text'],
            'responseTime' => (float) ($ext['response_time'] ?? 0),
            'bodySize' => 0,
            'crawlDepth' => 0,
            'errorCount' => (($ext['status_code'] ?? 0) >= 400 || ($ext['status_code'] ?? 0) === 0) ? 1 : 0,
            'warningCount' => ($ext['error'] ?? null) ? 1 : 0,
            'isIndexable' => false,
            'wordCount' => 0,
            'internalLinkCount' => 0,
            'externalLinkCount' => 0,
            'imageCount' => 0,
            'canonicalStatus' => '-',
            'h1Count' => 0,
            'isExternalCheck' => true,
        ], $this->externalLinks);
    }

    public function render()
    {
        return view('livewire.spider-dashboard')->layout('components.layout');
    }
}
