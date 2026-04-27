<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Domain\Model\Audit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Domain\Model\Audit\AuditDiffer;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\Audit\PageMatchKind;
use SeoSpider\Audit\Domain\Model\HttpStatusCode;
use SeoSpider\Audit\Domain\Model\Page\Fingerprint;
use SeoSpider\Audit\Domain\Model\Page\Issue;
use SeoSpider\Audit\Domain\Model\Page\IssueCategory;
use SeoSpider\Audit\Domain\Model\Page\IssueId;
use SeoSpider\Audit\Domain\Model\Page\IssueSeverity;
use SeoSpider\Audit\Domain\Model\Page\Page;
use SeoSpider\Audit\Domain\Model\Page\PageId;
use SeoSpider\Audit\Domain\Model\Page\PageResponse;
use SeoSpider\Audit\Domain\Model\Page\RedirectChain;
use SeoSpider\Audit\Domain\Model\Url;

final class AuditDifferTest extends TestCase
{
    public function test_unchanged_page_with_identical_issues_yields_no_change(): void
    {
        $baseId = AuditId::generate();
        $targetId = AuditId::generate();

        $base = [$this->page('https://example.com/', issueCodes: ['title_missing'])];
        $target = [$this->page('https://example.com/', issueCodes: ['title_missing'])];

        $diff = (new AuditDiffer())->diff($baseId, $targetId, $base, $target);

        self::assertCount(1, $diff->pagesUnchanged);
        self::assertCount(0, $diff->pagesAdded);
        self::assertCount(0, $diff->pagesRemoved);
        self::assertCount(0, $diff->pagesMoved);

        $change = $diff->pagesUnchanged[0];
        self::assertSame(PageMatchKind::BY_URL, $change->kind);
        self::assertSame([], $change->addedIssueCodes);
        self::assertSame([], $change->removedIssueCodes);
        self::assertSame(['title_missing'], $change->persistentIssueCodes);
    }

    public function test_classifies_added_removed_and_persistent_issue_codes(): void
    {
        $baseId = AuditId::generate();
        $targetId = AuditId::generate();

        $base = [$this->page('https://example.com/', ['title_missing', 'h1_multiple'])];
        $target = [$this->page('https://example.com/', ['title_missing', 'thin_content'])];

        $diff = (new AuditDiffer())->diff($baseId, $targetId, $base, $target);

        self::assertCount(1, $diff->pagesUnchanged);
        $change = $diff->pagesUnchanged[0];
        self::assertSame(['thin_content'], $change->addedIssueCodes);
        self::assertSame(['h1_multiple'], $change->removedIssueCodes);
        self::assertSame(['title_missing'], $change->persistentIssueCodes);
    }

    public function test_url_only_in_target_is_an_added_page(): void
    {
        $diff = (new AuditDiffer())->diff(
            AuditId::generate(),
            AuditId::generate(),
            base: [],
            target: [$this->page('https://example.com/new', ['title_missing'])],
        );

        self::assertCount(1, $diff->pagesAdded);
        self::assertSame(PageMatchKind::ADDED, $diff->pagesAdded[0]->kind);
        self::assertSame(['title_missing'], $diff->pagesAdded[0]->addedIssueCodes);
    }

    public function test_url_only_in_base_is_a_removed_page(): void
    {
        $diff = (new AuditDiffer())->diff(
            AuditId::generate(),
            AuditId::generate(),
            base: [$this->page('https://example.com/gone', ['h1_multiple'])],
            target: [],
        );

        self::assertCount(1, $diff->pagesRemoved);
        self::assertSame(PageMatchKind::REMOVED, $diff->pagesRemoved[0]->kind);
        self::assertSame(['h1_multiple'], $diff->pagesRemoved[0]->removedIssueCodes);
    }

    public function test_renamed_page_with_similar_content_is_marked_moved(): void
    {
        $shared = Fingerprint::fromContent('the quick brown fox jumps over the lazy dog');

        $diff = (new AuditDiffer())->diff(
            AuditId::generate(),
            AuditId::generate(),
            base: [$this->page('https://example.com/old', ['title_missing'], $shared)],
            target: [$this->page('https://example.com/new', ['title_missing'], $shared)],
        );

        self::assertCount(1, $diff->pagesMoved);
        self::assertCount(0, $diff->pagesAdded);
        self::assertCount(0, $diff->pagesRemoved);

        $moved = $diff->pagesMoved[0];
        self::assertSame('https://example.com/new', $moved->url);
        self::assertSame('https://example.com/old', $moved->movedFromUrl);
        self::assertSame(PageMatchKind::BY_FINGERPRINT, $moved->kind);
        self::assertSame(['title_missing'], $moved->persistentIssueCodes);
    }

    public function test_duplicate_issue_codes_on_one_page_are_reported_once(): void
    {
        $diff = (new AuditDiffer())->diff(
            AuditId::generate(),
            AuditId::generate(),
            base: [$this->page('https://example.com/', ['title_missing', 'title_missing', 'h1_multiple'])],
            target: [$this->page('https://example.com/', ['title_missing'])],
        );

        self::assertCount(1, $diff->pagesUnchanged);
        $change = $diff->pagesUnchanged[0];
        self::assertSame(['title_missing'], $change->persistentIssueCodes);
        self::assertSame(['h1_multiple'], $change->removedIssueCodes);
        self::assertSame([], $change->addedIssueCodes);
    }

    /** @param string[] $issueCodes */
    private function page(string $url, array $issueCodes = [], ?Fingerprint $fingerprint = null): Page
    {
        $issues = array_map(
            static fn(string $code) => new Issue(
                id: IssueId::generate(),
                category: IssueCategory::METADATA,
                severity: IssueSeverity::ERROR,
                code: $code,
                message: $code,
            ),
            $issueCodes,
        );

        return Page::reconstitute(
            id: PageId::generate(),
            auditId: AuditId::generate(),
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
            fingerprint: $fingerprint,
            links: [],
            hreflangs: [],
            issues: $issues,
            crawledAt: new DateTimeImmutable(),
        );
    }
}
