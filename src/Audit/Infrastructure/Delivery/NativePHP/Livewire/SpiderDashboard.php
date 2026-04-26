<?php

declare(strict_types=1);

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use SeoSpider\Audit\Application\StartAudit\StartAuditCommand;
use SeoSpider\Audit\Application\StartAudit\StartAuditHandler;
use SeoSpider\Audit\Application\GetAuditStatus\GetAuditStatusQuery;
use SeoSpider\Audit\Application\GetAuditStatus\GetAuditStatusHandler;
use SeoSpider\Audit\Application\CancelAudit\CancelAuditCommand;
use SeoSpider\Audit\Application\CancelAudit\CancelAuditHandler;
use SeoSpider\Audit\Application\PauseAudit\PauseAuditCommand;
use SeoSpider\Audit\Application\PauseAudit\PauseAuditHandler;
use SeoSpider\Audit\Application\ResumeAudit\ResumeAuditCommand;
use SeoSpider\Audit\Application\ResumeAudit\ResumeAuditHandler;
use App\Jobs\RunCrawlJob;
use Symfony\Component\Uid\Uuid;

/**
 * Top-level dashboard component. Owns the audit lifecycle (start /
 * pause / cancel / load), the crawl status counters, the sidebar
 * (folders + audit history) and the form for new audits. The page
 * table, detail panel, overview and report tabs live in the
 * AuditPageTable sub-component, mounted with wire:key keyed to
 * auditId so a different audit gets a fresh instance with a clean
 * state instead of reconciling DOM that no longer applies.
 */
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

    public bool $crawling = false;
    public bool $paused = false;

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
        $this->status = null;

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
        $this->refreshStatus();

        if ($this->status !== null) {
            $this->url = (string) ($this->status['seedUrl'] ?? '');
            $this->crawling = $this->status['status'] === 'running';
            $this->paused = $this->status['status'] === 'paused';
        }
    }

    public function toggleSidebar(): void
    {
        $this->sidebarCollapsed = !$this->sidebarCollapsed;
    }

    /**
     * Lightweight poll: only the parent-owned counters travel here.
     * The page table sub-component runs its own poll for the row data
     * so a tick during a busy crawl does not have to reconcile the
     * dashboard chrome (sidebar, header, footer).
     */
    public function poll(): void
    {
        if ($this->auditId === null || !$this->crawling) {
            return;
        }

        $this->refreshStatus();

        if ($this->status !== null && in_array($this->status['status'], ['completed', 'failed', 'cancelled'], true)) {
            $this->crawling = false;
            $this->loadAuditHistory();
            // Tells AuditPageTable to drop its unbounded live buffer
            // and re-fetch through the paginated read model.
            $this->dispatch('crawl-completed');
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

    // ═══════════════════════════════════════
    //  Computed properties
    // ═══════════════════════════════════════

    /** @return array{pct: float, label: string, rate: float} */
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

    public function render(): View
    {
        return view('livewire.spider-dashboard')
            ->layout('components.layout'); // @phpstan-ignore method.notFound
    }
}
