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
use App\Jobs\RunCrawlJob;

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
    public array $auditHistory = [];

    // ── Page discovery tracking
    public array $knownPageIds = [];
    public array $newPageIds = [];

    // ── Progress tracking
    public int $prevPagesCrawled = 0;

    public function mount(): void
    {
        $this->loadAuditHistory();
    }

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
        if ($this->status) {
            $this->url = $this->status['seedUrl'] ?? '';
            $this->crawling = $this->status['status'] === 'running';
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
            'noindex' => $r->noindex,
            'nofollow' => $r->nofollow,
            'redirectChain' => $r->redirectChain,
            'hreflangs' => $r->hreflangs,
            'internalLinkCount' => $r->internalLinkCount,
            'externalLinkCount' => $r->externalLinkCount,
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

        if ($this->status && in_array($this->status['status'], ['completed', 'failed', 'cancelled'])) {
            $this->crawling = false;
            $this->loadAuditHistory();
        }
    }

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
        ], $r->pages);

        $currentIds = array_map(fn($p) => $p['pageId'], $this->pages);
        $this->newPageIds = array_values(array_diff($currentIds, $previousIds));
        $this->knownPageIds = $currentIds;
    }

    // ── Computed properties ──

    public function getFilteredPagesProperty(): array
    {
        $pages = match ($this->activeTab) {
            'html'      => array_values(array_filter($this->pages, fn($p) => str_contains($p['contentType'], 'html'))),
            'errors'    => array_values(array_filter($this->pages, fn($p) => $p['statusCode'] >= 400)),
            'redirects' => array_values(array_filter($this->pages, fn($p) => $p['statusCode'] >= 300 && $p['statusCode'] < 400)),
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
        return [
            'all'       => count($this->pages),
            'html'      => count(array_filter($this->pages, fn($p) => str_contains($p['contentType'] ?? '', 'html'))),
            'redirects' => count(array_filter($this->pages, fn($p) => $p['statusCode'] >= 300 && $p['statusCode'] < 400)),
            'errors'    => count(array_filter($this->pages, fn($p) => $p['statusCode'] >= 400)),
            'issues'    => count(array_filter($this->pages, fn($p) => $p['errorCount'] > 0 || $p['warningCount'] > 0)),
            'noindex'   => count(array_filter($this->pages, fn($p) => !$p['isIndexable'])),
        ];
    }

    public function getProgressProperty(): array
    {
        if (!$this->status) return ['pct' => 0, 'label' => ''];

        $crawled = $this->status['pagesCrawled'];
        $discovered = $this->status['pagesDiscovered'];
        $max = $this->status['maxPages'];

        // Use discovered as denominator when crawling, max when done
        $total = $this->crawling ? max($discovered, 1) : max($crawled, 1);
        $pct = min(($crawled / $total) * 100, 100);

        return [
            'pct' => round($pct, 1),
            'label' => "{$crawled} / {$discovered}",
            'rate' => $this->status['duration'] > 0
                ? round($crawled / $this->status['duration'], 1)
                : 0,
        ];
    }

    public function getAuditScoreProperty(): ?int
    {
        if (empty($this->pages) || !$this->status) return null;
        $total = count($this->pages);
        if ($total === 0) return 100;

        $errors = $this->status['errorsFound'] ?? 0;
        $warnings = $this->status['warningsFound'] ?? 0;
        $penalty = ($errors * 3) + ($warnings * 1);
        $maxPenalty = $total * 5;

        return min(100, max(0, 100 - (int)(($penalty / max($maxPenalty, 1)) * 100)));
    }

    /** Group history by domain for sidebar folders */
    public function getGroupedHistoryProperty(): array
    {
        $groups = [];
        foreach ($this->auditHistory as $audit) {
            $host = parse_url($audit['seed_url'], PHP_URL_HOST) ?: 'unknown';
            $groups[$host][] = $audit;
        }
        ksort($groups);
        return $groups;
    }

    private function loadAuditHistory(): void
    {
        $pdo = app(\PDO::class);
        $stmt = $pdo->query('SELECT id, seed_url, status, pages_crawled, errors_found, warnings_found, created_at FROM audits ORDER BY created_at DESC LIMIT 50');
        $this->auditHistory = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function render()
    {
        return view('livewire.spider-dashboard')->layout('components.layout');
    }
}
