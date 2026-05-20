<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use PDO;
use SeoSpider\Auditing\Domain\Model\Audit\AuditRepository;
use SeoSpider\Crawling\Domain\Model\ExternalLink\ExternalLinkRepository;
use SeoSpider\Crawling\Domain\Model\ExternalLink\ExternalLinkVerifier;
use SeoSpider\Crawling\Domain\Model\Page\PageRepository;
use SeoSpider\Crawling\Application\Frontier;
use SeoSpider\Crawling\Application\HttpClient;
use SeoSpider\Crawling\Application\HtmlParser;
use SeoSpider\Crawling\Application\PageFetcher;
use SeoSpider\Crawling\Application\FrontierUrlDiscoverer;
use SeoSpider\Shared\Integration\PageWasCrawled;
use SeoSpider\Crawling\Application\RobotsPolicy;
use SeoSpider\Crawling\Application\SitemapIngester;
use SeoSpider\Crawling\Domain\Model\UrlCanonicalizer;
use SeoSpider\Crawling\Application\UrlDiscoverer;
use SeoSpider\Crawling\Infrastructure\Persistence\SqliteExternalLinkRepository;
use SeoSpider\Crawling\Infrastructure\Robots\RobotsTxtPolicy;
use SeoSpider\Shared\Domain\Bus\EventBus;
use SeoSpider\Auditing\Infrastructure\Persistence\SqliteAuditRepository;
use SeoSpider\Crawling\Infrastructure\Persistence\SqlitePageRepository;
use SeoSpider\Crawling\Infrastructure\Frontier\SqliteFrontier;
use SeoSpider\Crawling\Infrastructure\ExternalLinks\HttpExternalLinkVerifier;
use SeoSpider\Crawling\Infrastructure\Http\ConcurrentPageFetcher;
use SeoSpider\Crawling\Infrastructure\Sitemap\XmlSitemapIngester;
use SeoSpider\Crawling\Infrastructure\Http\SymfonyHttpClient;
use SeoSpider\Crawling\Infrastructure\Parser\DomCrawlerHtmlParser;
use SeoSpider\Shared\Infrastructure\Bus\SyncEventBus;
use SeoSpider\Auditing\Domain\Model\Analysis\BrokenLinkAnalyzer;
use SeoSpider\Auditing\Domain\Model\Analysis\MetaDataAnalyzer;
use SeoSpider\Auditing\Domain\Model\Analysis\DirectiveAnalyzer;
use SeoSpider\Auditing\Domain\Model\Analysis\HeadingAnalyzer;
use SeoSpider\Auditing\Domain\Model\Analysis\ContentAnalyzer;
use SeoSpider\Auditing\Domain\Model\Analysis\ImageAnalyzer;
use SeoSpider\Auditing\Domain\Model\Analysis\PerformanceAnalyzer;
use SeoSpider\Auditing\Domain\Model\Analysis\SecurityHeaderAnalyzer;
use SeoSpider\Auditing\Domain\Model\Analysis\HreflangAnalyzer;
use SeoSpider\Auditing\Domain\Model\Analysis\DuplicateAnalyzer;
use SeoSpider\Auditing\Domain\Model\Analysis\FingerprintIndex;
use SeoSpider\Auditing\Infrastructure\Persistence\SqliteFingerprintIndex;
use SeoSpider\Auditing\Domain\Model\Analysis\TransportSecurityAnalyzer;
use SeoSpider\Auditing\Domain\Model\Analysis\SocialMetadataAnalyzer;
use SeoSpider\Auditing\Domain\Model\Analysis\StructuredDataAnalyzer;
use SeoSpider\Auditing\Domain\Model\Analysis\HreflangReturnAnalyzer;
use SeoSpider\Auditing\Domain\Model\Analysis\CanonicalTargetAnalyzer;
use SeoSpider\Auditing\Domain\Model\Analysis\RobotsIndexableAnalyzer;
use SeoSpider\Auditing\Domain\Model\Analysis\SitemapCoverageAnalyzer;
use SeoSpider\Auditing\Domain\Model\Analysis\RobotsCheck;
use SeoSpider\Auditing\Domain\Model\Analysis\SitemapIndex;
use SeoSpider\Auditing\Infrastructure\Acl\CrawlingRobotsCheck;
use SeoSpider\Auditing\Infrastructure\Acl\FrontierBackedSitemapIndex;
use SeoSpider\Auditing\Infrastructure\Acl\FrontierBackedPendingUrlCounter;
use SeoSpider\Auditing\Infrastructure\Acl\FrontierBackedAuditFrontier;
use SeoSpider\Auditing\Domain\Model\Audit\AuditFrontier;
use SeoSpider\Auditing\Domain\Model\Reporting\PendingUrlCounter;
use SeoSpider\Auditing\Application\Reactors\AnalyzeSiteOnAuditCompleted;
use SeoSpider\Auditing\Application\Reporting\GetAuditIssueReport\IssueReportReader;
use SeoSpider\Auditing\Infrastructure\Persistence\SqliteIssueReportReader;
use SeoSpider\Auditing\Domain\Model\Reporting\SiteIssueRepository;
use SeoSpider\Auditing\Infrastructure\Persistence\SqliteSiteIssueRepository;
use SeoSpider\Auditing\Application\Reporting\GetAuditPages\PageSummaryReader;
use SeoSpider\Auditing\Infrastructure\Persistence\SqlitePageSummaryReader;
use SeoSpider\Auditing\Application\Reporting\AuditOverview\AuditOverviewBuilder;
use SeoSpider\Auditing\Application\Reporting\BuildAuditSnapshot\BuildAuditSnapshotOnAuditCompleted;
use SeoSpider\Auditing\Domain\Model\Reporting\AuditSnapshotRepository;
use SeoSpider\Auditing\Infrastructure\Persistence\SqliteAuditSnapshotRepository;
use SeoSpider\Auditing\Domain\Model\AuditedPage\AuditedPageRepository;
use SeoSpider\Auditing\Infrastructure\Persistence\SqliteAuditedPageRepository;
use SeoSpider\Auditing\Domain\Model\Audit\AuditCompleted;
use SeoSpider\Auditing\Application\Lifecycle\StartAudit\StartAuditHandler;
use SeoSpider\Auditing\Application\Reactors\AnalyzePageOnPageWasCrawled;
use SeoSpider\Auditing\Domain\Model\Analysis\PageSignalsReader;
use SeoSpider\Auditing\Domain\Model\Analysis\SiteContextFactory;
use SeoSpider\Auditing\Infrastructure\Acl\CrawlingPageSignalsReader;
use SeoSpider\Auditing\Infrastructure\Acl\CrawlingSiteContextFactory;
use SeoSpider\Crawling\Application\AuditCoordinator;
use SeoSpider\Auditing\Infrastructure\Acl\AuditingAuditCoordinator;
use SeoSpider\Crawling\Application\CrawlPage\CrawlPageHandler;
use SeoSpider\Auditing\Application\Lifecycle\PauseAudit\PauseAuditHandler;
use SeoSpider\Auditing\Application\Lifecycle\ResumeAudit\ResumeAuditHandler;
use SeoSpider\Auditing\Application\Lifecycle\CancelAudit\CancelAuditHandler;
use SeoSpider\Auditing\Application\Reporting\GetAuditStatus\GetAuditStatusHandler;
use SeoSpider\Auditing\Application\Reporting\GetAuditPages\GetAuditPagesHandler;
use SeoSpider\Auditing\Application\Reporting\GetPageDetail\GetPageDetailHandler;
use SeoSpider\Auditing\Domain\Model\Reporting\PageDetailReader;
use SeoSpider\Auditing\Domain\Model\Reporting\PageRowReader;
use SeoSpider\Auditing\Infrastructure\Acl\CrawlingPageDetailReader;
use SeoSpider\Auditing\Infrastructure\Acl\CrawlingPageRowReader;
use SeoSpider\Crawling\Application\Engine\CrawlerEngine;
use SeoSpider\Auditing\Application\Lifecycle\StartAudit\StartAuditCommand;
use SeoSpider\Auditing\Application\Lifecycle\PauseAudit\PauseAuditCommand;
use SeoSpider\Auditing\Application\Lifecycle\ResumeAudit\ResumeAuditCommand;
use SeoSpider\Auditing\Application\Lifecycle\CancelAudit\CancelAuditCommand;
use SeoSpider\Auditing\Application\Reporting\GetAuditStatus\GetAuditStatusQuery;
use SeoSpider\Auditing\Application\Reporting\GetAuditPages\GetAuditPagesQuery;
use SeoSpider\Auditing\Application\Reporting\GetPageDetail\GetPageDetailQuery;
use SeoSpider\Auditing\Application\Reporting\GetAuditIssueReport\GetAuditIssueReportQuery;
use SeoSpider\Auditing\Application\Reporting\GetAuditIssueReport\GetAuditIssueReportHandler;
use SeoSpider\Auditing\Application\Reporting\CompareAudits\CompareAuditsQuery;
use SeoSpider\Auditing\Application\Reporting\CompareAudits\CompareAuditsHandler;
use SeoSpider\Shared\Domain\Bus\CommandBus;
use SeoSpider\Shared\Domain\Bus\QueryBus;
use SeoSpider\Shared\Infrastructure\Bus\SyncCommandBus;
use SeoSpider\Shared\Infrastructure\Bus\SyncQueryBus;

final class AuditServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // IMPORTANT: Use Laravel's default DB connection instead of a custom
        // storage_path() PDO. NativePHP rewrites storage_path() differently
        // for the HTTP process vs the queue worker child process, causing them
        // to use separate SQLite databases. Laravel's DB manager handles this
        // correctly, sharing the same database across all processes.
        $this->app->singleton(PDO::class, function ($app) {
            $pdo = $app['db']->connection()->getPdo();

            $pdo->exec('PRAGMA journal_mode=WAL');
            $pdo->exec('PRAGMA busy_timeout=5000');
            $pdo->exec('PRAGMA synchronous=NORMAL');
            $pdo->exec('PRAGMA foreign_keys=ON');

            return $pdo;
        });

        $this->app->singleton(AuditRepository::class, fn($app) => new SqliteAuditRepository($app->make(PDO::class)));

        $this->app->singleton(PageRepository::class, fn($app) => new SqlitePageRepository($app->make(PDO::class)));

        $this->app->singleton(AuditedPageRepository::class, fn($app) => new SqliteAuditedPageRepository($app->make(PDO::class)));

        $this->app->singleton(ExternalLinkRepository::class, fn($app) => new SqliteExternalLinkRepository($app->make(PDO::class)));

        $this->app->singleton(IssueReportReader::class, fn($app) => new SqliteIssueReportReader($app->make(PDO::class)));

        $this->app->singleton(SiteIssueRepository::class, fn($app) => new SqliteSiteIssueRepository($app->make(PDO::class)));

        $this->app->singleton(PageSummaryReader::class, fn($app) => new SqlitePageSummaryReader($app->make(PDO::class)));

        $this->app->singleton(AuditSnapshotRepository::class, fn($app) => new SqliteAuditSnapshotRepository($app->make(PDO::class)));
        $this->app->singleton(AuditOverviewBuilder::class, fn($app) => new AuditOverviewBuilder($app->make(PDO::class)));
        $this->app->singleton(BuildAuditSnapshotOnAuditCompleted::class, fn($app) => new BuildAuditSnapshotOnAuditCompleted(
            builder: $app->make(AuditOverviewBuilder::class),
            repository: $app->make(AuditSnapshotRepository::class),
        ));

        $this->app->singleton(UrlCanonicalizer::class, fn() => new UrlCanonicalizer());

        $this->app->singleton(Frontier::class, fn($app) => new SqliteFrontier(
            $app->make(PDO::class),
            $app->make(UrlCanonicalizer::class),
        ));

        $this->app->singleton(HttpClient::class, fn() => new SymfonyHttpClient());
        $this->app->singleton(HtmlParser::class, fn() => new DomCrawlerHtmlParser());
        $this->app->singleton(RobotsPolicy::class, fn() => new RobotsTxtPolicy($this->app->make(HttpClient::class)));
        $this->app->singleton(SitemapIngester::class, fn($app) => new XmlSitemapIngester(
            $app->make(HttpClient::class),
            $app->make(Frontier::class),
        ));
        $this->app->singleton(EventBus::class, fn() => new SyncEventBus());

        $this->app->singleton(FingerprintIndex::class, fn($app) => new SqliteFingerprintIndex($app->make(PDO::class)));

        $this->app->tag([
            ContentAnalyzer::class,
            HeadingAnalyzer::class,
            MetaDataAnalyzer::class,
            DirectiveAnalyzer::class,
            StructuredDataAnalyzer::class,
            PerformanceAnalyzer::class,
            SecurityHeaderAnalyzer::class,
            TransportSecurityAnalyzer::class,
            SocialMetadataAnalyzer::class,
            ImageAnalyzer::class,
            HreflangAnalyzer::class,
            BrokenLinkAnalyzer::class,
            DuplicateAnalyzer::class,
        ], 'auditing-analyzers');

        $this->app->singleton(HreflangReturnAnalyzer::class, fn() => new HreflangReturnAnalyzer());
        $this->app->singleton(CanonicalTargetAnalyzer::class, fn() => new CanonicalTargetAnalyzer());
        $this->app->singleton(RobotsCheck::class, fn($app) => new CrawlingRobotsCheck(
            $app->make(RobotsPolicy::class),
        ));
        $this->app->singleton(SitemapIndex::class, fn($app) => new FrontierBackedSitemapIndex(
            $app->make(Frontier::class),
        ));
        $this->app->singleton(RobotsIndexableAnalyzer::class, fn($app) => new RobotsIndexableAnalyzer(
            $app->make(RobotsCheck::class),
        ));
        $this->app->singleton(SitemapCoverageAnalyzer::class, fn($app) => new SitemapCoverageAnalyzer(
            $app->make(SitemapIndex::class),
        ));

        $this->app->tag([
            HreflangReturnAnalyzer::class,
            CanonicalTargetAnalyzer::class,
            RobotsIndexableAnalyzer::class,
            SitemapCoverageAnalyzer::class,
        ], 'auditing-site-analyzers');

        $this->app->singleton(SiteContextFactory::class, fn($app) => new CrawlingSiteContextFactory(
            $app->make(PageRepository::class),
        ));

        $this->app->singleton(AnalyzeSiteOnAuditCompleted::class, fn($app) => new AnalyzeSiteOnAuditCompleted(
            siteContextFactory: $app->make(SiteContextFactory::class),
            auditRepository: $app->make(AuditRepository::class),
            siteIssueRepository: $app->make(SiteIssueRepository::class),
            auditedPageRepository: $app->make(AuditedPageRepository::class),
            siteAnalyzers: iterator_to_array($app->tagged('auditing-site-analyzers')),
        ));

        $this->app->singleton(UrlDiscoverer::class, fn($app) => new FrontierUrlDiscoverer(
            $app->make(Frontier::class),
        ));

        $this->app->singleton(AuditCoordinator::class, fn($app) => new AuditingAuditCoordinator(
            $app->make(AuditRepository::class),
            $app->make(EventBus::class),
        ));

        $this->app->singleton(CrawlPageHandler::class, fn($app) => new CrawlPageHandler(
            auditCoordinator: $app->make(AuditCoordinator::class),
            pageRepository: $app->make(PageRepository::class),
            httpClient: $app->make(HttpClient::class),
            htmlParser: $app->make(HtmlParser::class),
            urlDiscoverer: $app->make(UrlDiscoverer::class),
            eventBus: $app->make(EventBus::class),
        ));

        $this->app->singleton(PageSignalsReader::class, fn($app) => new CrawlingPageSignalsReader(
            $app->make(PageRepository::class),
        ));

        $this->app->singleton(AnalyzePageOnPageWasCrawled::class, fn($app) => new AnalyzePageOnPageWasCrawled(
            pageSignalsReader: $app->make(PageSignalsReader::class),
            auditRepository: $app->make(AuditRepository::class),
            eventBus: $app->make(EventBus::class),
            auditedPageRepository: $app->make(AuditedPageRepository::class),
            analyzers: iterator_to_array($app->tagged('auditing-analyzers')),
        ));

        $this->app->singleton(ExternalLinkVerifier::class, fn($app) => new HttpExternalLinkVerifier(
            pageRepository: $app->make(PageRepository::class),
            externalLinkRepository: $app->make(ExternalLinkRepository::class),
            httpClient: $app->make(HttpClient::class),
        ));


        $this->app->singleton(AuditFrontier::class, fn($app) => new FrontierBackedAuditFrontier(
            $app->make(Frontier::class),
        ));
        $this->app->singleton(StartAuditHandler::class, fn($app) => new StartAuditHandler(
            auditRepository: $app->make(AuditRepository::class),
            frontier: $app->make(AuditFrontier::class),
            eventBus: $app->make(EventBus::class),
        ));

        $this->app->singleton(PauseAuditHandler::class, fn($app) => new PauseAuditHandler(
            auditRepository: $app->make(AuditRepository::class),
            eventBus: $app->make(EventBus::class),
        ));

        $this->app->singleton(ResumeAuditHandler::class, fn($app) => new ResumeAuditHandler(
            auditRepository: $app->make(AuditRepository::class),
            eventBus: $app->make(EventBus::class),
        ));

        $this->app->singleton(CancelAuditHandler::class, fn($app) => new CancelAuditHandler(
            auditRepository: $app->make(AuditRepository::class),
            frontier: $app->make(AuditFrontier::class),
            eventBus: $app->make(EventBus::class),
        ));

        $this->app->singleton(PendingUrlCounter::class, fn($app) => new FrontierBackedPendingUrlCounter(
            $app->make(Frontier::class),
        ));
        $this->app->singleton(GetAuditStatusHandler::class, fn($app) => new GetAuditStatusHandler(
            auditRepository: $app->make(AuditRepository::class),
            pendingUrls: $app->make(PendingUrlCounter::class),
        ));

        $this->app->singleton(GetAuditPagesHandler::class, fn($app) => new GetAuditPagesHandler(
            auditRepository: $app->make(AuditRepository::class),
            reader: $app->make(PageSummaryReader::class),
        ));

        $this->app->singleton(PageDetailReader::class, fn($app) => new CrawlingPageDetailReader(
            $app->make(PageRepository::class),
        ));
        $this->app->singleton(PageRowReader::class, fn($app) => new CrawlingPageRowReader(
            $app->make(PageRepository::class),
        ));
        $this->app->singleton(GetPageDetailHandler::class, fn($app) => new GetPageDetailHandler(
            pageDetailReader: $app->make(PageDetailReader::class),
            auditedPageRepository: $app->make(AuditedPageRepository::class),
        ));

        $this->app->singleton(PageFetcher::class, fn() => new ConcurrentPageFetcher());

        $this->app->singleton(CrawlerEngine::class, fn($app) => new CrawlerEngine(
            auditCoordinator: $app->make(AuditCoordinator::class),
            frontier: $app->make(Frontier::class),
            crawlPageHandler: $app->make(CrawlPageHandler::class),
            robotsPolicy: $app->make(RobotsPolicy::class),
            sitemapIngester: $app->make(SitemapIngester::class),
            externalLinkVerifier: $app->make(ExternalLinkVerifier::class),
            pageFetcher: $app->make(PageFetcher::class),
        ));

        $this->app->singleton(CommandBus::class, fn($app) => new SyncCommandBus($app, [
            StartAuditCommand::class => StartAuditHandler::class,
            PauseAuditCommand::class => PauseAuditHandler::class,
            ResumeAuditCommand::class => ResumeAuditHandler::class,
            CancelAuditCommand::class => CancelAuditHandler::class,
        ]));

        $this->app->singleton(QueryBus::class, fn($app) => new SyncQueryBus($app, [
            GetAuditStatusQuery::class => GetAuditStatusHandler::class,
            GetAuditPagesQuery::class => GetAuditPagesHandler::class,
            GetPageDetailQuery::class => GetPageDetailHandler::class,
            GetAuditIssueReportQuery::class => GetAuditIssueReportHandler::class,
            CompareAuditsQuery::class => CompareAuditsHandler::class,
        ]));
    }

    public function boot(): void
    {
        // Lazy: resolving the reactor drags PageRepository → PDO, which opens
        // the sqlite connection. We cannot do that here because NativePHP
        // rewrites the database path later, during native:migrate's handle().
        // The closure defers the container lookup until a PageWasCrawled is
        // actually published, by which time the DB connection is valid.
        $app = $this->app;

        /** @var EventBus $bus */
        $bus = $app->make(EventBus::class);

        $bus->subscribe(PageWasCrawled::class, static function (\SeoSpider\Shared\Domain\DomainEvent $event) use ($app): void {
            if ($event instanceof PageWasCrawled) {
                ($app->make(AnalyzePageOnPageWasCrawled::class))($event);
            }
        });

        $bus->subscribe(AuditCompleted::class, static function (\SeoSpider\Shared\Domain\DomainEvent $event) use ($app): void {
            if ($event instanceof AuditCompleted) {
                ($app->make(AnalyzeSiteOnAuditCompleted::class))($event);
            }
        });

        // Snapshot must run AFTER AnalyzeSiteOnAuditCompleted so it picks
        // up the site-wide issues that reactor persists.
        $bus->subscribe(AuditCompleted::class, static function (\SeoSpider\Shared\Domain\DomainEvent $event) use ($app): void {
            if ($event instanceof AuditCompleted) {
                ($app->make(BuildAuditSnapshotOnAuditCompleted::class))($event);
            }
        });
    }
}
