<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Application\CompareAudits;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Application\CompareAudits\CompareAuditsHandler;
use SeoSpider\Audit\Application\CompareAudits\CompareAuditsQuery;
use SeoSpider\Audit\Domain\Model\Audit\Audit;
use SeoSpider\Audit\Domain\Model\Audit\AuditConfiguration;
use SeoSpider\Audit\Domain\Model\Audit\AuditDiffer;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\Audit\AuditStatistics;
use SeoSpider\Audit\Domain\Model\Audit\AuditStatus;
use SeoSpider\Crawling\Domain\Model\HttpStatusCode;
use SeoSpider\Audit\Domain\Model\Page\Issue;
use SeoSpider\Audit\Domain\Model\Page\IssueCategory;
use SeoSpider\Audit\Domain\Model\Page\IssueId;
use SeoSpider\Audit\Domain\Model\Page\IssueSeverity;
use SeoSpider\Audit\Domain\Model\Page\Page;
use SeoSpider\Audit\Domain\Model\Page\PageId;
use SeoSpider\Crawling\Domain\Model\Page\PageResponse;
use SeoSpider\Crawling\Domain\Model\Page\RedirectChain;
use SeoSpider\Crawling\Domain\Model\Url;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\InMemoryAuditRepository;
use SeoSpider\Tests\Audit\Infrastructure\InMemory\InMemoryPageRepository;

final class CompareAuditsHandlerTest extends TestCase
{
    private InMemoryAuditRepository $audits;
    private InMemoryPageRepository $pages;
    private CompareAuditsHandler $handler;

    protected function setUp(): void
    {
        $this->audits = new InMemoryAuditRepository();
        $this->pages = new InMemoryPageRepository();
        $this->handler = new CompareAuditsHandler(
            $this->audits,
            $this->pages,
            new AuditDiffer(),
        );
    }

    public function test_returns_added_and_resolved_issues_with_hydrated_titles(): void
    {
        $baseId = $this->seedAudit('https://example.com/', '2026-04-15T10:00:00+00:00');
        $targetId = $this->seedAudit('https://example.com/', '2026-04-27T10:00:00+00:00');

        $this->pages->save($this->page($baseId, 'https://example.com/', ['h1_multiple']));
        $this->pages->save($this->page($targetId, 'https://example.com/', ['title_missing']));

        $response = ($this->handler)(new CompareAuditsQuery($baseId, $targetId));

        self::assertSame('example.com', $response->host);
        self::assertCount(1, $response->issuesAdded);
        self::assertSame('title_missing', $response->issuesAdded[0]->code);
        self::assertNotSame('', $response->issuesAdded[0]->title);

        self::assertCount(1, $response->issuesRemoved);
        self::assertSame('h1_multiple', $response->issuesRemoved[0]->code);
    }

    private function seedAudit(string $seedUrl, string $completedAt): AuditId
    {
        $id = AuditId::generate();
        $audit = Audit::reconstitute(
            id: $id,
            configuration: new AuditConfiguration(
                seedUrl: Url::fromString($seedUrl),
                maxPages: 100,
                maxDepth: 5,
                concurrency: 1,
                requestDelay: 0.0,
                respectRobotsTxt: false,
                customUserAgent: null,
                excludePatterns: [],
                includePatterns: [],
                followExternalLinks: false,
                crawlSubdomains: false,
            ),
            status: AuditStatus::COMPLETED,
            statistics: new AuditStatistics(
                pagesDiscovered: 1,
                pagesCrawled: 1,
                pagesFailed: 0,
                issuesFound: 1,
                errorsFound: 1,
                warningsFound: 0,
                startedAt: new DateTimeImmutable($completedAt),
                completedAt: new DateTimeImmutable($completedAt),
            ),
            createdAt: new DateTimeImmutable($completedAt),
        );

        $this->audits->save($audit);

        return $id;
    }

    /** @param string[] $codes */
    private function page(AuditId $auditId, string $url, array $codes): Page
    {
        $issues = array_map(
            static fn(string $code) => new Issue(
                id: IssueId::generate(),
                category: IssueCategory::METADATA,
                severity: IssueSeverity::ERROR,
                code: $code,
                message: $code,
            ),
            $codes,
        );

        return Page::reconstitute(
            id: PageId::generate(),
            auditId: $auditId,
            url: Url::fromString($url),
            response: new PageResponse(
                statusCode: new HttpStatusCode(200),
                headers: [],
                body: null,
                contentType: 'text/html',
                bodySize: 0,
                responseTime: 0.0,
                finalUrl: null,
            ),
            redirectChain: RedirectChain::none(),
            crawlDepth: 0,
            metadata: null,
            directives: null,
            fingerprint: null,
            links: [],
            hreflangs: [],
            issues: $issues,
            crawledAt: new DateTimeImmutable(),
        );
    }
}
