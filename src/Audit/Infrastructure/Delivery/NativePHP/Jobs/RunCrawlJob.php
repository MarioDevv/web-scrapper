<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use SeoSpider\Audit\Application\Engine\CrawlerEngine;

final class RunCrawlJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 3600;

    public function __construct(public readonly string $auditId) {}

    public function handle(CrawlerEngine $engine): void
    {
        Log::info('CrawlJob DB path: ' . storage_path('app/spider.db'));
        Log::info('CrawlJob audit: ' . $this->auditId);
        $engine->run($this->auditId);
        Log::info('CrawlJob finished');
    }
}