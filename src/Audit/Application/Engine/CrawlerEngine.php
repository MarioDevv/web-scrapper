<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Application\Engine;

use SeoSpider\Audit\Application\CrawlPage\CrawlPageCommand;
use SeoSpider\Audit\Application\CrawlPage\CrawlPageHandler;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\Audit\AuditRepository;
use SeoSpider\Audit\Domain\Model\ExternalLinkVerifier;
use SeoSpider\Audit\Domain\Model\Frontier;
use SeoSpider\Audit\Domain\Model\FrontierEntry;
use SeoSpider\Audit\Domain\Model\PageFetcher;
use SeoSpider\Audit\Domain\Model\RobotsPolicy;
use SeoSpider\Audit\Domain\Model\Sitemap\SitemapIngester;

final readonly class CrawlerEngine
{
    public function __construct(
        private AuditRepository $auditRepository,
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
        $id = new AuditId($auditId);

        $audit = $this->auditRepository->findById($id);
        if ($audit === null || $audit->isFinished()) {
            return;
        }

        if ($audit->configuration()->respectRobotsTxt) {
            $this->robotsPolicy->load($audit->configuration()->seedUrl);
        }

        if ($audit->configuration()->ingestSitemaps) {
            $added = $this->sitemapIngester->ingest(
                $id,
                $audit->configuration()->seedUrl,
                $audit->configuration()->customUserAgent,
            );
            if ($added > 0) {
                $audit->registerUrlsDiscovered($added);
                $this->auditRepository->save($audit);
            }
        }

        $delay = $this->effectiveDelay($audit);
        $concurrency = max(1, $audit->configuration()->concurrency);

        while (true) {
            $audit = $this->auditRepository->findById($id);
            if ($audit === null || !$audit->isRunning()) {
                break;
            }

            $batch = $this->frontier->dequeueBatch($id, $concurrency);
            if ($batch === []) {
                $this->externalLinkVerifier->verify($id, $audit->configuration()->customUserAgent);
                $audit->complete();
                $this->auditRepository->save($audit);
                break;
            }

            $toFetch = $this->filterAllowed($batch, $id, $audit->configuration()->respectRobotsTxt);
            if ($toFetch === []) {
                continue;
            }

            if ($concurrency === 1) {
                $this->processSerial($auditId, $toFetch, $id, $onProgress);
            } else {
                $this->processConcurrent(
                    $auditId,
                    $toFetch,
                    $audit->configuration()->customUserAgent,
                    $id,
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
    private function filterAllowed(array $batch, AuditId $auditId, bool $respectRobots): array
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
    private function processSerial(string $auditId, array $batch, AuditId $id, ?callable $onProgress): void
    {
        foreach ($batch as $entry) {
            ($this->crawlPageHandler)(new CrawlPageCommand(
                auditId: $auditId,
                url: $entry->url->toString(),
                depth: $entry->depth,
            ));

            $this->reportProgress($id, $entry->url->toString(), $onProgress);
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
        AuditId $id,
        ?callable $onProgress,
    ): void {
        // Mark as visited up-front — the serial handler does this before its
        // own fetch, so we keep the invariant for parallel fetches too.
        foreach ($batch as $entry) {
            $this->frontier->markVisited($id, $entry->url);
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

            $this->reportProgress($id, $key, $onProgress);
        }
    }

    /** @param ?callable(CrawlProgress): void $onProgress */
    private function reportProgress(AuditId $id, string $currentUrl, ?callable $onProgress): void
    {
        if ($onProgress === null) {
            return;
        }

        $audit = $this->auditRepository->findById($id);
        if ($audit !== null) {
            $onProgress($this->buildProgress($audit, $id, $currentUrl));
        }
    }

    private function effectiveDelay(\SeoSpider\Audit\Domain\Model\Audit\Audit $audit): float
    {
        $configDelay = $audit->configuration()->requestDelay;

        if ($audit->configuration()->respectRobotsTxt) {
            $robotsDelay = $this->robotsPolicy->crawlDelay();
            if ($robotsDelay !== null) {
                return max($configDelay, $robotsDelay);
            }
        }

        return $configDelay;
    }

    private function buildProgress(
        \SeoSpider\Audit\Domain\Model\Audit\Audit $audit,
        AuditId $id,
        string $currentUrl,
    ): CrawlProgress {
        $stats = $audit->statistics();

        return new CrawlProgress(
            auditId: $audit->id()->value(),
            currentUrl: $currentUrl,
            pagesCrawled: $stats->pagesCrawled,
            pagesFailed: $stats->pagesFailed,
            pagesDiscovered: $stats->pagesDiscovered,
            pendingUrls: $this->frontier->pendingCount($id),
            maxPages: $audit->configuration()->maxPages,
            status: $audit->status()->value,
        );
    }
}
