<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Auditing\Domain\Model\Reporting;

use PHPUnit\Framework\TestCase;
use SeoSpider\Auditing\Domain\Model\Audit\AuditId;
use SeoSpider\Auditing\Domain\Model\Analysis\Signal\Fingerprint;
use SeoSpider\Auditing\Domain\Model\Reporting\AuditDiffer;
use SeoSpider\Auditing\Domain\Model\Reporting\PageMatchKind;
use SeoSpider\Auditing\Domain\Model\Reporting\PageRow;

final class AuditDifferTest extends TestCase
{
    public function test_unchanged_page_with_identical_issues_yields_no_change(): void
    {
        $diff = (new AuditDiffer())->diff(
            AuditId::generate(),
            AuditId::generate(),
            [$this->row('https://example.com/')],
            [$this->row('https://example.com/')],
            ['https://example.com/' => ['title_missing']],
            ['https://example.com/' => ['title_missing']],
        );

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
        $diff = (new AuditDiffer())->diff(
            AuditId::generate(),
            AuditId::generate(),
            [$this->row('https://example.com/')],
            [$this->row('https://example.com/')],
            ['https://example.com/' => ['title_missing', 'h1_multiple']],
            ['https://example.com/' => ['title_missing', 'thin_content']],
        );

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
            target: [$this->row('https://example.com/new')],
            baseCodesByUrl: [],
            targetCodesByUrl: ['https://example.com/new' => ['title_missing']],
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
            base: [$this->row('https://example.com/gone')],
            target: [],
            baseCodesByUrl: ['https://example.com/gone' => ['h1_multiple']],
            targetCodesByUrl: [],
        );

        self::assertCount(1, $diff->pagesRemoved);
        self::assertSame(PageMatchKind::REMOVED, $diff->pagesRemoved[0]->kind);
        self::assertSame(['h1_multiple'], $diff->pagesRemoved[0]->removedIssueCodes);
    }

    public function test_renamed_page_with_similar_content_is_marked_moved(): void
    {
        $shared = $this->fingerprintFor('the quick brown fox jumps over the lazy dog');

        $diff = (new AuditDiffer())->diff(
            AuditId::generate(),
            AuditId::generate(),
            base: [$this->row('https://example.com/old', $shared)],
            target: [$this->row('https://example.com/new', $shared)],
            baseCodesByUrl: ['https://example.com/old' => ['title_missing']],
            targetCodesByUrl: ['https://example.com/new' => ['title_missing']],
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
            base: [$this->row('https://example.com/')],
            target: [$this->row('https://example.com/')],
            baseCodesByUrl: ['https://example.com/' => ['title_missing', 'title_missing', 'h1_multiple']],
            targetCodesByUrl: ['https://example.com/' => ['title_missing']],
        );

        self::assertCount(1, $diff->pagesUnchanged);
        $change = $diff->pagesUnchanged[0];
        self::assertSame(['title_missing'], $change->persistentIssueCodes);
        self::assertSame(['h1_multiple'], $change->removedIssueCodes);
        self::assertSame([], $change->addedIssueCodes);
    }

    private function row(string $url, ?Fingerprint $fingerprint = null): PageRow
    {
        return new PageRow(url: $url, fingerprint: $fingerprint);
    }

    private function fingerprintFor(string $content): Fingerprint
    {
        $crawling = \SeoSpider\Crawling\Domain\Model\Page\Fingerprint::fromContent($content);
        return new Fingerprint($crawling->exactHash(), $crawling->simHash());
    }
}
