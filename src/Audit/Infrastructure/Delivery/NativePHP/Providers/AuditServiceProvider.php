<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use PDO;
use SeoSpider\Audit\Domain\Model\Audit\AuditRepository;
use SeoSpider\Audit\Domain\Model\ExternalLinkRepository;
use SeoSpider\Audit\Domain\Model\ExternalLinkVerifier;
use SeoSpider\Audit\Domain\Model\Page\PageRepository;
use SeoSpider\Audit\Domain\Model\Frontier;
use SeoSpider\Audit\Domain\Model\HttpClient;
use SeoSpider\Audit\Domain\Model\HtmlParser;
use SeoSpider\Audit\Domain\Model\PageFetcher;
use SeoSpider\Audit\Domain\Model\FrontierUrlDiscoverer;
use SeoSpider\Audit\Domain\Model\Page\PageFetched;
use SeoSpider\Audit\Domain\Model\RobotsPolicy;
use SeoSpider\Audit\Domain\Model\Sitemap\SitemapIngester;
use SeoSpider\Audit\Domain\Model\UrlCanonicalizer;
use SeoSpider\Audit\Domain\Model\UrlDiscoverer;
use SeoSpider\Audit\Infrastructure\Persistence\SqliteExternalLinkRepository;
use SeoSpider\Audit\Infrastructure\Robots\RobotsTxtPolicy;
use SeoSpider\Shared\Domain\Bus\EventBus;
use SeoSpider\Audit\Infrastructure\Persistence\SqliteAuditRepository;
use SeoSpider\Audit\Infrastructure\Persistence\SqlitePageRepository;
use SeoSpider\Audit\Infrastructure\Frontier\SqliteFrontier;
use SeoSpider\Audit\Infrastructure\ExternalLinks\HttpExternalLinkVerifier;
use SeoSpider\Audit\Infrastructure\Http\ConcurrentPageFetcher;
use SeoSpider\Audit\Infrastructure\Sitemap\XmlSitemapIngester;
use SeoSpider\Audit\Infrastructure\Http\SymfonyHttpClient;
use SeoSpider\Audit\Infrastructure\Parser\DomCrawlerHtmlParser;
use SeoSpider\Audit\Infrastructure\Bus\SyncEventBus;
use SeoSpider\Audit\Domain\Model\Analyzer\BrokenLinkAnalyzer;
use SeoSpider\Audit\Domain\Model\Analyzer\MetaDataAnalyzer;
use SeoSpider\Audit\Domain\Model\Analyzer\DirectiveAnalyzer;
use SeoSpider\Audit\Domain\Model\Analyzer\HeadingAnalyzer;
use SeoSpider\Audit\Domain\Model\Analyzer\ContentAnalyzer;
use SeoSpider\Audit\Domain\Model\Analyzer\ImageAnalyzer;
use SeoSpider\Audit\Domain\Model\Analyzer\PerformanceAnalyzer;
use SeoSpider\Audit\Domain\Model\Analyzer\SecurityHeaderAnalyzer;
use SeoSpider\Audit\Domain\Model\Analyzer\HreflangAnalyzer;
use SeoSpider\Audit\Domain\Model\Analyzer\DuplicateAnalyzer;
use SeoSpider\Audit\Domain\Model\Analyzer\TransportSecurityAnalyzer;
use SeoSpider\Audit\Domain\Model\Analyzer\SocialMetadataAnalyzer;
use SeoSpider\Audit\Domain\Model\Analyzer\StructuredDataAnalyzer;
use SeoSpider\Audit\Domain\Model\Analyzer\HreflangReturnAnalyzer;
use SeoSpider\Audit\Domain\Model\Analyzer\CanonicalTargetAnalyzer;
use SeoSpider\Audit\Domain\Model\Analyzer\RobotsIndexableAnalyzer;
use SeoSpider\Audit\Domain\Model\Analyzer\SitemapCoverageAnalyzer;
use SeoSpider\Audit\Application\AnalyzeSite\AnalyzeSiteOnAuditCompleted;
use SeoSpider\Audit\Application\GetAuditIssueReport\IssueReportReader;
use SeoSpider\Audit\Infrastructure\Persistence\SqliteIssueReportReader;
use SeoSpider\Audit\Domain\Model\Page\SiteIssueRepository;
use SeoSpider\Audit\Infrastructure\Persistence\SqliteSiteIssueRepository;
use SeoSpider\Audit\Domain\Model\Audit\AuditCompleted;
use SeoSpider\Audit\Application\StartAudit\StartAuditHandler;
use SeoSpider\Audit\Application\AnalyzePage\AnalyzePageOnPageFetched;
use SeoSpider\Audit\Application\CrawlPage\CrawlPageHandler;
use SeoSpider\Audit\Application\PauseAudit\PauseAuditHandler;
use SeoSpider\Audit\Application\ResumeAudit\ResumeAuditHandler;
use SeoSpider\Audit\Application\CancelAudit\CancelAuditHandler;
use SeoSpider\Audit\Application\GetAuditStatus\GetAuditStatusHandler;
use SeoSpider\Audit\Application\GetAuditPages\GetAuditPagesHandler;
use SeoSpider\Audit\Application\GetPageDetail\GetPageDetailHandler;
use SeoSpider\Audit\Application\Engine\CrawlerEngine;

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

        $this->app->singleton(ExternalLinkRepository::class, fn($app) => new SqliteExternalLinkRepository($app->make(PDO::class)));

        $this->app->singleton(IssueReportReader::class, fn($app) => new SqliteIssueReportReader($app->make(PDO::class)));

        $this->app->singleton(SiteIssueRepository::class, fn($app) => new SqliteSiteIssueRepository($app->make(PDO::class)));

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

        $this->app->tag([
            BrokenLinkAnalyzer::class,
            MetaDataAnalyzer::class,
            DirectiveAnalyzer::class,
            HeadingAnalyzer::class,
            ContentAnalyzer::class,
            ImageAnalyzer::class,
            PerformanceAnalyzer::class,
            SecurityHeaderAnalyzer::class,
            TransportSecurityAnalyzer::class,
            SocialMetadataAnalyzer::class,
            StructuredDataAnalyzer::class,
            HreflangAnalyzer::class,
            DuplicateAnalyzer::class,
        ], 'analyzers');

        $this->app->singleton(HreflangReturnAnalyzer::class, fn() => new HreflangReturnAnalyzer());
        $this->app->singleton(CanonicalTargetAnalyzer::class, fn() => new CanonicalTargetAnalyzer());
        $this->app->singleton(RobotsIndexableAnalyzer::class, fn($app) => new RobotsIndexableAnalyzer(
            robotsPolicy: $app->make(RobotsPolicy::class),
        ));
        $this->app->singleton(SitemapCoverageAnalyzer::class, fn($app) => new SitemapCoverageAnalyzer(
            frontier: $app->make(Frontier::class),
        ));

        $this->app->tag([
            HreflangReturnAnalyzer::class,
            CanonicalTargetAnalyzer::class,
            RobotsIndexableAnalyzer::class,
            SitemapCoverageAnalyzer::class,
        ], 'site-analyzers');

        $this->app->singleton(AnalyzeSiteOnAuditCompleted::class, fn($app) => new AnalyzeSiteOnAuditCompleted(
            pageRepository: $app->make(PageRepository::class),
            auditRepository: $app->make(AuditRepository::class),
            siteIssueRepository: $app->make(SiteIssueRepository::class),
            siteAnalyzers: iterator_to_array($app->tagged('site-analyzers')),
        ));

        $this->app->singleton(UrlDiscoverer::class, fn($app) => new FrontierUrlDiscoverer(
            $app->make(Frontier::class),
        ));

        $this->app->singleton(CrawlPageHandler::class, fn($app) => new CrawlPageHandler(
            auditRepository: $app->make(AuditRepository::class),
            pageRepository: $app->make(PageRepository::class),
            httpClient: $app->make(HttpClient::class),
            htmlParser: $app->make(HtmlParser::class),
            urlDiscoverer: $app->make(UrlDiscoverer::class),
            eventBus: $app->make(EventBus::class),
        ));

        $this->app->singleton(AnalyzePageOnPageFetched::class, fn($app) => new AnalyzePageOnPageFetched(
            pageRepository: $app->make(PageRepository::class),
            auditRepository: $app->make(AuditRepository::class),
            eventBus: $app->make(EventBus::class),
            analyzers: iterator_to_array($app->tagged('analyzers')),
        ));

        $this->app->singleton(ExternalLinkVerifier::class, fn($app) => new HttpExternalLinkVerifier(
            pageRepository: $app->make(PageRepository::class),
            externalLinkRepository: $app->make(ExternalLinkRepository::class),
            httpClient: $app->make(HttpClient::class),
        ));


        $this->app->singleton(StartAuditHandler::class, fn($app) => new StartAuditHandler(
            auditRepository: $app->make(AuditRepository::class),
            frontier: $app->make(Frontier::class),
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
            frontier: $app->make(Frontier::class),
            eventBus: $app->make(EventBus::class),
        ));

        $this->app->singleton(GetAuditStatusHandler::class, fn($app) => new GetAuditStatusHandler(
            auditRepository: $app->make(AuditRepository::class),
            frontier: $app->make(Frontier::class),
        ));

        $this->app->singleton(GetAuditPagesHandler::class, fn($app) => new GetAuditPagesHandler(
            auditRepository: $app->make(AuditRepository::class),
            pageRepository: $app->make(PageRepository::class),
        ));

        $this->app->singleton(GetPageDetailHandler::class, fn($app) => new GetPageDetailHandler(
            pageRepository: $app->make(PageRepository::class),
        ));

        $this->app->singleton(PageFetcher::class, fn() => new ConcurrentPageFetcher());

        $this->app->singleton(CrawlerEngine::class, fn($app) => new CrawlerEngine(
            auditRepository: $app->make(AuditRepository::class),
            frontier: $app->make(Frontier::class),
            crawlPageHandler: $app->make(CrawlPageHandler::class),
            robotsPolicy: $app->make(RobotsPolicy::class),
            sitemapIngester: $app->make(SitemapIngester::class),
            externalLinkVerifier: $app->make(ExternalLinkVerifier::class),
            pageFetcher: $app->make(PageFetcher::class),
        ));
    }

    public function boot(): void
    {
        // Lazy: resolving the reactor drags PageRepository → PDO, which opens
        // the sqlite connection. We cannot do that here because NativePHP
        // rewrites the database path later, during native:migrate's handle().
        // The closure defers the container lookup until a PageFetched is
        // actually published, by which time the DB connection is valid.
        $app = $this->app;

        /** @var EventBus $bus */
        $bus = $app->make(EventBus::class);

        $bus->subscribe(PageFetched::class, static function (\SeoSpider\Shared\Domain\DomainEvent $event) use ($app): void {
            if ($event instanceof PageFetched) {
                ($app->make(AnalyzePageOnPageFetched::class))($event);
            }
        });

        $bus->subscribe(AuditCompleted::class, static function (\SeoSpider\Shared\Domain\DomainEvent $event) use ($app): void {
            if ($event instanceof AuditCompleted) {
                ($app->make(AnalyzeSiteOnAuditCompleted::class))($event);
            }
        });
    }
}
