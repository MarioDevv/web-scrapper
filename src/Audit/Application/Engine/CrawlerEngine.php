<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Application\Engine;

use SeoSpider\Audit\Application\CrawlPage\CrawlPageCommand;
use SeoSpider\Audit\Application\CrawlPage\CrawlPageHandler;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\Audit\AuditRepository;
use SeoSpider\Audit\Domain\Model\ExternalLinkVerifier;
use SeoSpider\Audit\Domain\Model\Frontier;
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

        while (true) {
            $audit = $this->auditRepository->findById($id);
            if ($audit === null || !$audit->isRunning()) {
                break;
            }

            $entry = $this->frontier->dequeue($id);
            if ($entry === null) {
                $this->externalLinkVerifier->verify($id, $audit->configuration()->customUserAgent);
                $audit->complete();
                $this->auditRepository->save($audit);
                break;
            }

            if ($audit->configuration()->respectRobotsTxt
                && !$this->robotsPolicy->isAllowed($entry->url)) {
                $this->frontier->markVisited($id, $entry->url);
                continue;
            }

            ($this->crawlPageHandler)(new CrawlPageCommand(
                auditId: $auditId,
                url: $entry->url->toString(),
                depth: $entry->depth,
            ));

            if ($onProgress !== null) {
                $audit = $this->auditRepository->findById($id);
                if ($audit !== null) {
                    $onProgress($this->buildProgress($audit, $id, $entry->url->toString()));
                }
            }

            if ($delay > 0) {
                usleep((int) ($delay * 1_000_000));
            }
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
