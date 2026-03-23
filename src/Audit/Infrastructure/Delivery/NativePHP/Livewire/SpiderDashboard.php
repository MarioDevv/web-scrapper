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
use SeoSpider\Audit\Application\Engine\CrawlerEngine;

class SpiderDashboard extends Component
{
    public string $url = '';
    public int $maxPages = 500;
    public int $maxDepth = 10;

    public ?string $auditId = null;
    public ?array $status = null;
    public array $pages = [];
    public bool $crawling = false;

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

        $this->runCrawl();
    }

    public function runCrawl(): void
    {
        if ($this->auditId === null) {
            return;
        }

        $engine = app(CrawlerEngine::class);
        $engine->run($this->auditId);

        $this->crawling = false;
        $this->refreshStatus();
        $this->refreshPages();
    }

    public function refreshStatus(): void
    {
        if ($this->auditId === null) {
            return;
        }

        $handler = app(GetAuditStatusHandler::class);
        $response = $handler(new GetAuditStatusQuery($this->auditId));

        $this->status = [
            'status' => $response->status,
            'pagesCrawled' => $response->pagesCrawled,
            'pagesFailed' => $response->pagesFailed,
            'pagesDiscovered' => $response->pagesDiscovered,
            'issuesFound' => $response->issuesFound,
            'errorsFound' => $response->errorsFound,
            'warningsFound' => $response->warningsFound,
            'duration' => $response->duration,
        ];
    }

    public function refreshPages(): void
    {
        if ($this->auditId === null) {
            return;
        }

        $handler = app(GetAuditPagesHandler::class);
        $response = $handler(new GetAuditPagesQuery($this->auditId));

        $this->pages = array_map(fn($p) => [
            'pageId' => $p->pageId,
            'url' => $p->url,
            'statusCode' => $p->statusCode,
            'title' => $p->title,
            'responseTime' => $p->responseTime,
            'errorCount' => $p->errorCount,
            'warningCount' => $p->warningCount,
            'isIndexable' => $p->isIndexable,
        ], $response->pages);
    }

    public function render()
    {
        return view('livewire.spider-dashboard')
            ->layout('components.layout');
    }
}
