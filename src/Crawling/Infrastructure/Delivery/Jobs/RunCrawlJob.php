<?php

declare(strict_types=1);

namespace SeoSpider\Crawling\Infrastructure\Delivery\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use SeoSpider\Crawling\Application\Engine\CrawlerEngine;

final class RunCrawlJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 3600;

    public function __construct(public readonly string $auditId) {}

    public function handle(CrawlerEngine $engine): void
    {
        $engine->run($this->auditId);
    }
}
