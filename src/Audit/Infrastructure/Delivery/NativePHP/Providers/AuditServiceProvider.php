<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\ServiceProvider;
use PDO;
use SeoSpider\Audit\Domain\Model\Audit\AuditRepository;
use SeoSpider\Audit\Domain\Model\Page\PageRepository;
use SeoSpider\Audit\Domain\Model\Frontier;
use SeoSpider\Audit\Domain\Model\HttpClient;
use SeoSpider\Audit\Domain\Model\HtmlParser;
use SeoSpider\Audit\Domain\Model\RobotsPolicy;
use SeoSpider\Shared\Domain\Bus\EventBus;
use SeoSpider\Audit\Infrastructure\Persistence\SqliteConnection;
use SeoSpider\Audit\Infrastructure\Persistence\SqliteAuditRepository;
use SeoSpider\Audit\Infrastructure\Persistence\SqlitePageRepository;
use SeoSpider\Audit\Infrastructure\Frontier\SqliteFrontier;
use SeoSpider\Audit\Infrastructure\Http\SymfonyHttpClient;
use SeoSpider\Audit\Infrastructure\Parser\DomCrawlerHtmlParser;
use SeoSpider\Audit\Infrastructure\Robots\NullRobotsPolicy;
use SeoSpider\Audit\Infrastructure\Bus\SyncEventBus;
use SeoSpider\Audit\Domain\Model\Analyzer\BrokenLinkAnalyzer;
use SeoSpider\Audit\Domain\Model\Analyzer\MetaDataAnalyzer;
use SeoSpider\Audit\Domain\Model\Analyzer\DirectiveAnalyzer;
use SeoSpider\Audit\Domain\Model\Analyzer\HeadingAnalyzer;
use SeoSpider\Audit\Domain\Model\Analyzer\ContentAnalyzer;
use SeoSpider\Audit\Domain\Model\Analyzer\ImageAnalyzer;
use SeoSpider\Audit\Domain\Model\Analyzer\PerformanceAnalyzer;
use SeoSpider\Audit\Domain\Model\Analyzer\SecurityHeaderAnalyzer;
use SeoSpider\Audit\Application\StartAudit\StartAuditHandler;
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

        $this->app->singleton(Frontier::class, fn($app) => new SqliteFrontier($app->make(PDO::class)));

        $this->app->singleton(HttpClient::class, fn() => new SymfonyHttpClient());
        $this->app->singleton(HtmlParser::class, fn() => new DomCrawlerHtmlParser());
        $this->app->singleton(RobotsPolicy::class, fn() => new NullRobotsPolicy());
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
        ], 'analyzers');

        $this->app->singleton(CrawlPageHandler::class, fn($app) => new CrawlPageHandler(
            auditRepository: $app->make(AuditRepository::class),
            pageRepository: $app->make(PageRepository::class),
            httpClient: $app->make(HttpClient::class),
            htmlParser: $app->make(HtmlParser::class),
            frontier: $app->make(Frontier::class),
            eventBus: $app->make(EventBus::class),
            analyzers: iterator_to_array($app->tagged('analyzers')),
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

        $this->app->singleton(CrawlerEngine::class, fn($app) => new CrawlerEngine(
            auditRepository: $app->make(AuditRepository::class),
            frontier: $app->make(Frontier::class),
            crawlPageHandler: $app->make(CrawlPageHandler::class),
            robotsPolicy: $app->make(RobotsPolicy::class),
        ));
    }

    /**
     * @throws BindingResolutionException
     */
    public function boot(): void
    {
        $pdo = $this->app->make(PDO::class);
        $schemaPath = dirname(
                (new \ReflectionClass(SqliteConnection::class))->getFileName()
            ) . '/schema.sql';

        SqliteConnection::migrate($pdo, $schemaPath);

        // Safe migration: add folder_id to existing audits tables
        $columns = $pdo->query("PRAGMA table_info(audits)")->fetchAll();
        $columnNames = array_column($columns, 'name');
        if (!in_array('folder_id', $columnNames, true)) {
            $pdo->exec('ALTER TABLE audits ADD COLUMN folder_id TEXT REFERENCES folders(id) ON DELETE SET NULL');
        }
    }
}
