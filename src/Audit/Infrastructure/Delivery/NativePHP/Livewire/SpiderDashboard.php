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
    public string $url = '';
    public int $maxPages = 500;
    public int $maxDepth = 10;

    public ?string $auditId = null;
    public ?array $status = null;
    public array $pages = [];
    public bool $crawling = false;

    public ?string $selectedPageId = null;
    public ?array $selectedPage = null;

    public string $activeTab = 'all';
    public bool $detailOpen = false;
    public bool $sidebarCollapsed = false;

    public array $auditHistory = [];

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
        $this->selectedPageId = null;
        $this->selectedPage = null;
        $this->detailOpen = false;
        $this->pages = [];
        $this->status = null;

        RunCrawlJob::dispatch($this->auditId);

        $this->refreshStatus();
    }

    public function cancelCrawl(): void
    {
        if (!$this->auditId) return;
        $handler = app(CancelAuditHandler::class);
        $handler(new CancelAuditCommand($this->auditId));
        $this->crawling = false;
        $this->refreshStatus();
    }

    public function loadAudit(string $auditId): void
    {
        $this->auditId = $auditId;
        $this->selectedPageId = null;
        $this->selectedPage = null;
        $this->detailOpen = false;
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

        $handler = app(GetPageDetailHandler::class);
        $response = $handler(new GetPageDetailQuery($pageId));

        $this->selectedPage = [
            'pageId' => $response->pageId,
            'url' => $response->url,
            'statusCode' => $response->statusCode,
            'contentType' => $response->contentType,
            'bodySize' => $response->bodySize,
            'responseTime' => $response->responseTime,
            'crawlDepth' => $response->crawlDepth,
            'isIndexable' => $response->isIndexable,
            'title' => $response->title,
            'titleLength' => $response->titleLength,
            'metaDescription' => $response->metaDescription,
            'metaDescriptionLength' => $response->metaDescriptionLength,
            'h1s' => $response->h1s,
            'wordCount' => $response->wordCount,
            'canonical' => $response->canonical,
            'noindex' => $response->noindex,
            'nofollow' => $response->nofollow,
            'redirectChain' => $response->redirectChain,
            'hreflangs' => $response->hreflangs,
            'internalLinkCount' => $response->internalLinkCount,
            'externalLinkCount' => $response->externalLinkCount,
            'issues' => array_map(fn($i) => [
                'severity' => $i->severity,
                'code' => $i->code,
                'message' => $i->message,
                'context' => $i->context,
            ], $response->issues),
        ];
    }

    public function closeDetail(): void
    {
        $this->detailOpen = false;
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function toggleSidebar(): void
    {
        $this->sidebarCollapsed = !$this->sidebarCollapsed;
    }

    public function poll(): void
    {
        if (!$this->auditId || !$this->crawling) return;
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
        $handler = app(GetAuditStatusHandler::class);
        $r = $handler(new GetAuditStatusQuery($this->auditId));
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
        ];
    }

    public function refreshPages(): void
    {
        if (!$this->auditId) return;
        $handler = app(GetAuditPagesHandler::class);
        $r = $handler(new GetAuditPagesQuery($this->auditId));
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
    }

    public function getFilteredPagesProperty(): array
    {
        return match ($this->activeTab) {
            'html' => array_values(array_filter($this->pages, fn($p) => str_contains($p['contentType'], 'html'))),
            'errors' => array_values(array_filter($this->pages, fn($p) => $p['statusCode'] >= 400)),
            'redirects' => array_values(array_filter($this->pages, fn($p) => $p['statusCode'] >= 300 && $p['statusCode'] < 400)),
            'issues' => array_values(array_filter($this->pages, fn($p) => $p['errorCount'] > 0 || $p['warningCount'] > 0)),
            default => $this->pages,
        };
    }

    private function loadAuditHistory(): void
    {
        $pdo = app(\PDO::class);
        $stmt = $pdo->query('SELECT id, seed_url, status, pages_crawled, errors_found, warnings_found, created_at FROM audits ORDER BY created_at DESC LIMIT 30');
        $this->auditHistory = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function render()
    {
        return view('livewire.spider-dashboard')->layout('components.layout');
    }
}
