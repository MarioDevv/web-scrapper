<?php

declare(strict_types=1);

namespace SeoSpider\Crawling\Application\Engine;

use SeoSpider\Crawling\Application\AuditCoordinator;
use SeoSpider\Crawling\Domain\Model\Audit\AuditSnapshot;
use SeoSpider\Crawling\Application\CrawlPage\CrawlPageCommand;
use SeoSpider\Crawling\Application\CrawlPage\CrawlPageHandler;
use SeoSpider\Crawling\Domain\Model\Frontier;
use SeoSpider\Crawling\Application\PageFetcher;
use SeoSpider\Crawling\Domain\Model\RobotsPolicy;
use SeoSpider\Crawling\Domain\Model\SitemapIngester;
use SeoSpider\Crawling\Domain\Model\ExternalLink\ExternalLinkVerifier;
use SeoSpider\Crawling\Domain\Model\FrontierEntry;
use SeoSpider\Crawling\Domain\Model\Url;

final readonly class CrawlerEngine
{
    public function __construct(
        private AuditCoordinator $auditCoordinator,
        private Frontier $frontier,
        private CrawlPageHandler $crawlPageHandler,
        private RobotsPolicy $robotsPolicy,
        private SitemapIngester $sitemapIngester,
        private ExternalLinkVerifier $externalLinkVerifier,
        private PageFetcher $pageFetcher,
    ) {
    }

    /** @param ?callable(CrawlProgress): void $onProgress */
    public function run(string $auditId, ?callable $onProgress = null): void
    {
        $snapshot = $this->auditCoordinator->snapshot($auditId);
        if ($snapshot === null || $snapshot->isFinished) {
            return;
        }

        $seedUrl = Url::fromString($snapshot->config->seedUrl);

        if ($snapshot->config->respectRobotsTxt) {
            $this->robotsPolicy->load($seedUrl);
        }

        if ($snapshot->config->ingestSitemaps) {
            $added = $this->sitemapIngester->ingest(
                $auditId,
                $seedUrl,
                $snapshot->config->customUserAgent,
            );
            if ($added > 0) {
                $this->auditCoordinator->registerUrlsDiscovered($auditId, $added);
            }
        }

        $delay = $this->effectiveDelay($snapshot);
        $concurrency = max(1, $snapshot->config->concurrency);

        while (true) {
            $snapshot = $this->auditCoordinator->snapshot($auditId);
            if ($snapshot === null || !$snapshot->isRunning) {
                break;
            }

            $batch = $this->frontier->dequeueBatch($auditId, $concurrency);
            if ($batch === []) {
                $this->externalLinkVerifier->verify($auditId, $snapshot->config->customUserAgent);
                $this->auditCoordinator->complete($auditId);
                break;
            }

            $toFetch = $this->filterAllowed($batch, $auditId, $snapshot->config->respectRobotsTxt);
            if ($toFetch === []) {
                continue;
            }

            if ($concurrency === 1) {
                $this->processSerial($auditId, $toFetch, $onProgress);
            } else {
                $this->processConcurrent(
                    $auditId,
                    $toFetch,
                    $snapshot->config->customUserAgent,
                    $onProgress,
                );
            }

            if ($delay > 0) {
                usleep((int) ($delay * 1_000_000));
            }
        }
    }

    /**
     * @param FrontierEntry[] $batch
     * @return FrontierEntry[]
     */
    private function filterAllowed(array $batch, string $auditId, bool $respectRobots): array
    {
        if (!$respectRobots) {
            return $batch;
        }

        $allowed = [];
        foreach ($batch as $entry) {
            if ($this->robotsPolicy->isAllowed($entry->url)) {
                $allowed[] = $entry;
            } else {
                $this->frontier->markVisited($auditId, $entry->url);
            }
        }

        return $allowed;
    }

    /**
     * @param FrontierEntry[] $batch
     * @param ?callable(CrawlProgress): void $onProgress
     */
    private function processSerial(string $auditId, array $batch, ?callable $onProgress): void
    {
        foreach ($batch as $entry) {
            $this->frontier->markVisited($auditId, $entry->url);

            ($this->crawlPageHandler)(new CrawlPageCommand(
                auditId: $auditId,
                url: $entry->url->toString(),
                depth: $entry->depth,
            ));

            $this->reportProgress($auditId, $entry->url->toString(), $onProgress);
        }
    }

    /**
     * @param FrontierEntry[] $batch
     * @param ?callable(CrawlProgress): void $onProgress
     */
    private function processConcurrent(
        string $auditId,
        array $batch,
        ?string $userAgent,
        ?callable $onProgress,
    ): void {
        foreach ($batch as $entry) {
            $this->frontier->markVisited($auditId, $entry->url);
        }

        $urls = array_map(static fn(FrontierEntry $entry) => $entry->url, $batch);
        $outcomes = $this->pageFetcher->fetchBatch($urls, $userAgent);

        foreach ($batch as $entry) {
            $key = $entry->url->toString();
            $command = new CrawlPageCommand(
                auditId: $auditId,
                url: $key,
                depth: $entry->depth,
            );

            $outcome = $outcomes[$key] ?? null;
            if ($outcome === null) {
                $this->crawlPageHandler->handleFetchFailure($command, 'fetcher returned no outcome for url');
            } elseif ($outcome->isSuccessful()) {
                $this->crawlPageHandler->processFetchedPage(
                    $command,
                    $outcome->response,
                    $outcome->chain,
                );
            } else {
                $this->crawlPageHandler->handleFetchFailure(
                    $command,
                    $outcome->error ?? 'unknown fetch failure',
                );
            }

            $this->reportProgress($auditId, $key, $onProgress);
        }
    }

    /** @param ?callable(CrawlProgress): void $onProgress */
    private function reportProgress(string $auditId, string $currentUrl, ?callable $onProgress): void
    {
        if ($onProgress === null) {
            return;
        }

        $snapshot = $this->auditCoordinator->snapshot($auditId);
        if ($snapshot !== null) {
            $onProgress($this->buildProgress($snapshot, $currentUrl));
        }
    }

    private function effectiveDelay(AuditSnapshot $snapshot): float
    {
        $configDelay = $snapshot->config->requestDelay;

        if ($snapshot->config->respectRobotsTxt) {
            $robotsDelay = $this->robotsPolicy->crawlDelay();
            if ($robotsDelay !== null) {
                return max($configDelay, $robotsDelay);
            }
        }

        return $configDelay;
    }

    private function buildProgress(AuditSnapshot $snapshot, string $currentUrl): CrawlProgress
    {
        return new CrawlProgress(
            auditId: $snapshot->auditId,
            currentUrl: $currentUrl,
            pagesCrawled: $snapshot->stats->pagesCrawled,
            pagesFailed: $snapshot->stats->pagesFailed,
            pagesDiscovered: $snapshot->stats->pagesDiscovered,
            pendingUrls: $this->frontier->pendingCount($snapshot->auditId),
            maxPages: $snapshot->config->maxPages,
            status: $snapshot->status,
        );
    }
}
