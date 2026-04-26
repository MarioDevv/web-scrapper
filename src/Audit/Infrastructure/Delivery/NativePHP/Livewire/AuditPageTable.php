<?php

declare(strict_types=1);

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Reactive;
use Livewire\Component;
use SeoSpider\Audit\Application\GetAuditPages\GetAuditPagesHandler;
use SeoSpider\Audit\Application\GetAuditPages\GetAuditPagesQuery;
use SeoSpider\Audit\Application\GetAuditPages\PageSummaryReader;
use SeoSpider\Audit\Application\GetAuditIssueReport\GetAuditIssueReportHandler;
use SeoSpider\Audit\Application\GetAuditIssueReport\GetAuditIssueReportQuery;
use SeoSpider\Audit\Application\GetPageDetail\GetPageDetailHandler;
use SeoSpider\Audit\Application\GetPageDetail\GetPageDetailQuery;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\Audit\AuditSnapshotRepository;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Page table sub-component. Owns everything the user interacts with
 * inside the audit view (tabs, search, sort, pagination, page detail
 * panel, overview, audit report) so that mutations to its state do not
 * force the parent dashboard — sidebar, header, audit history — to
 * re-reconcile its DOM. Only the props it receives from the parent
 * (auditId, crawling) are reactive; the rest is owned locally.
 */
class AuditPageTable extends Component
{
    #[Reactive]
    public ?string $auditId = null;

    #[Reactive]
    public bool $crawling = false;

    /** @var array<int, array<string, mixed>> */
    public array $pages = [];

    public ?string $selectedPageId = null;

    /** @var array<string, mixed>|null */
    public ?array $selectedPage = null;

    public bool $detailOpen = false;
    public string $detailTab = 'seo';

    public string $activeTab = 'all';
    public string $searchQuery = '';
    public string $sortField = 'crawlDepth';
    public string $sortDir = 'asc';

    public int $currentPage = 0;
    public int $pageSize = 50;
    public int $pagesTotal = 0;

    /** @var array<string, int> */
    public array $rawTabCounts = [
        'pages' => 0, 'internal' => 0, 'html' => 0,
        'redirects' => 0, 'errors' => 0, 'issues' => 0, 'noindex' => 0,
    ];

    /** @var array<int, string> */
    public array $knownPageIds = [];

    /** @var array<int, string> */
    public array $newPageIds = [];

    /** @var array<int, array<string, mixed>> */
    public array $externalLinks = [];

    public function mount(): void
    {
        if ($this->auditId !== null) {
            $this->refreshPages();
            $this->loadExternalLinks();
        }
    }

    public function selectPage(string $pageId): void
    {
        $this->selectedPageId = $pageId;
        $this->detailOpen = true;
        $this->detailTab = 'seo';

        $r = app(GetPageDetailHandler::class)(new GetPageDetailQuery($pageId));

        $this->selectedPage = [
            'pageId' => $r->pageId,
            'url' => $r->url,
            'statusCode' => $r->statusCode,
            'contentType' => $r->contentType,
            'bodySize' => $r->bodySize,
            'responseTime' => $r->responseTime,
            'crawlDepth' => $r->crawlDepth,
            'isIndexable' => $r->isIndexable,
            'title' => $r->title,
            'titleLength' => $r->titleLength,
            'metaDescription' => $r->metaDescription,
            'metaDescriptionLength' => $r->metaDescriptionLength,
            'h1s' => $r->h1s,
            'wordCount' => $r->wordCount,
            'canonical' => $r->canonical,
            'canonicalStatus' => $r->canonicalStatus,
            'noindex' => $r->noindex,
            'nofollow' => $r->nofollow,
            'redirectChain' => $r->redirectChain,
            'hreflangs' => $r->hreflangs,
            'internalLinkCount' => $r->internalLinkCount,
            'externalLinkCount' => $r->externalLinkCount,
            'links' => $r->links,
            'issues' => array_map(fn(object $i): array => [
                'id' => $i->id,
                'category' => $i->category,
                'severity' => $i->severity,
                'code' => $i->code,
                'message' => $i->message,
                'context' => $i->context,
            ], $r->issues),
        ];
    }

    public function closeDetail(): void
    {
        $this->detailOpen = false;
    }

    public function setDetailTab(string $tab): void
    {
        $this->detailTab = $tab;
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->currentPage = 0;
        if ($this->auditId !== null && !$this->crawling) {
            $this->refreshPages();
        }
    }

    public function toggleSort(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDir = 'asc';
        }
        $this->currentPage = 0;
        if ($this->auditId !== null && !$this->crawling) {
            $this->refreshPages();
        }
    }

    public function nextPage(): void
    {
        if (($this->currentPage + 1) * $this->pageSize >= $this->pagesTotal) {
            return;
        }
        $this->currentPage++;
        $this->refreshPages();
    }

    public function prevPage(): void
    {
        if ($this->currentPage === 0) {
            return;
        }
        $this->currentPage--;
        $this->refreshPages();
    }

    public function updatedSearchQuery(): void
    {
        $this->currentPage = 0;
        if ($this->auditId !== null && !$this->crawling) {
            $this->refreshPages();
        }
    }

    /**
     * Owned poll: only fires while the parent reports crawling=true.
     * Refreshes the page list and external link checks; the parent
     * has its own poll for status counters so this one only carries
     * page-shaped data.
     */
    public function poll(): void
    {
        if ($this->auditId === null || !$this->crawling) {
            return;
        }

        $this->refreshPages();
        $this->loadExternalLinks();
    }

    public function refreshPages(): void
    {
        if ($this->auditId === null) {
            return;
        }

        $useDelta = $this->crawling && $this->pages !== [];
        $since = $useDelta ? $this->latestCrawledAt() : null;

        $r = app(GetAuditPagesHandler::class)(new GetAuditPagesQuery(
            auditId: $this->auditId,
            since: $since,
            tab: $this->mapActiveTabToQuery(),
            search: $this->searchQuery !== '' ? $this->searchQuery : null,
            sortField: $this->sortField,
            sortDir: $this->sortDir,
            limit: $this->crawling ? null : $this->pageSize,
            offset: $this->crawling ? 0 : $this->currentPage * $this->pageSize,
        ));

        $this->pagesTotal = $r->total;
        $this->rawTabCounts = app(PageSummaryReader::class)->tabCounts(new AuditId($this->auditId));

        $previousIds = $this->knownPageIds;

        $incoming = array_map(fn(object $p): array => [
            'pageId' => $p->pageId,
            'url' => $p->url,
            'statusCode' => $p->statusCode,
            'contentType' => $p->contentType ?? '',
            'title' => $p->title,
            'responseTime' => $p->responseTime,
            'bodySize' => $p->bodySize,
            'crawlDepth' => $p->crawlDepth,
            'errorCount' => $p->errorCount,
            'warningCount' => $p->warningCount,
            'isIndexable' => $p->isIndexable,
            'wordCount' => $p->wordCount,
            'internalLinkCount' => $p->internalLinkCount,
            'externalLinkCount' => $p->externalLinkCount,
            'imageCount' => $p->imageCount,
            'canonicalStatus' => $p->canonicalStatus,
            'h1Count' => $p->h1Count,
            'crawledAt' => $p->crawledAt,
        ], $r->pages);

        if ($useDelta) {
            $merged = array_column($this->pages, null, 'pageId');
            foreach ($incoming as $row) {
                $merged[$row['pageId']] = $row;
            }
            $this->pages = array_values($merged);
        } else {
            $this->pages = $incoming;
        }

        /** @var array<int, string> $currentIds */
        $currentIds = array_map(fn(array $p): string => (string) $p['pageId'], $this->pages);
        $this->newPageIds = array_values(array_diff($currentIds, $previousIds));
        $this->knownPageIds = $currentIds;
    }

    public function loadExternalLinks(): void
    {
        if ($this->auditId === null) {
            $this->externalLinks = [];
            return;
        }

        $pdo = app(\PDO::class);
        $stmt = $pdo->prepare(
            'SELECT e.url, e.status_code, e.response_time, e.error, e.anchor_text, p.url as source_url
             FROM external_url_checks e
             LEFT JOIN pages p ON p.id = e.source_page_id
             WHERE e.audit_id = ?
             ORDER BY e.status_code DESC, e.url ASC'
        );
        $stmt->execute([$this->auditId]);
        $this->externalLinks = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function exportCsv(): StreamedResponse
    {
        $pages = $this->getFilteredPagesProperty();
        $tab = $this->activeTab;

        return response()->streamDownload(function () use ($pages): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fputcsv($out, [
                'URL', 'Status', 'Title', 'Words', 'H1', 'Int. Links', 'Ext. Links',
                'Images', 'Canonical', 'Size (B)', 'Time (ms)', 'Depth',
                'Errors', 'Warnings', 'Indexable',
            ]);

            foreach ($pages as $page) {
                fputcsv($out, [
                    $page['url'],
                    $page['statusCode'],
                    $page['title'] ?? '',
                    $page['wordCount'],
                    $page['h1Count'],
                    $page['internalLinkCount'],
                    $page['externalLinkCount'],
                    $page['imageCount'],
                    $page['canonicalStatus'],
                    $page['bodySize'],
                    number_format((float) $page['responseTime'], 0),
                    $page['crawlDepth'],
                    $page['errorCount'],
                    $page['warningCount'],
                    ($page['isIndexable'] ?? false) ? 'Yes' : 'No',
                ]);
            }

            fclose($out);
        }, sprintf('seo-audit-%s-%s.csv', $tab, date('Y-m-d')), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportExternalCsv(): StreamedResponse
    {
        $links = $this->externalLinks;

        return response()->streamDownload(function () use ($links): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fputcsv($out, ['External URL', 'Status', 'Time (ms)', 'Error', 'Anchor Text', 'Source page']);

            foreach ($links as $link) {
                fputcsv($out, [
                    $link['url'],
                    $link['status_code'] ?? 'Error',
                    number_format((float) ($link['response_time'] ?? 0), 0),
                    $link['error'] ?? '',
                    $link['anchor_text'] ?? '',
                    $link['source_url'] ?? '',
                ]);
            }

            fclose($out);
        }, sprintf('external-links-%s.csv', date('Y-m-d')), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    public function getFilteredPagesProperty(): array
    {
        if (in_array($this->activeTab, ['external'], true)) {
            return [];
        }

        $rows = $this->pages;

        if (in_array($this->activeTab, ['all', 'redirects', 'errors'], true)) {
            $extAsPages = $this->externalLinksAsPages();
            if ($this->activeTab === 'redirects') {
                $extAsPages = array_filter(
                    $extAsPages,
                    static fn(array $p) => $p['statusCode'] >= 300 && $p['statusCode'] < 400,
                );
            } elseif ($this->activeTab === 'errors') {
                $extAsPages = array_filter(
                    $extAsPages,
                    static fn(array $p) => $p['statusCode'] >= 400 || ((int) $p['statusCode'] === 0 && !empty($p['isExternalCheck'])),
                );
            }
            $rows = array_merge($rows, array_values($extAsPages));
        }

        return $rows;
    }

    /** @return array<string, int> */
    public function getTabCountsProperty(): array
    {
        $extCount = count($this->externalLinks);
        $tc = $this->rawTabCounts;

        return [
            'overview'  => $tc['pages'],
            'all'       => $tc['pages'] + $extCount,
            'internal'  => $tc['internal'],
            'external'  => $extCount,
            'html'      => $tc['html'],
            'redirects' => $tc['redirects'],
            'errors'    => $tc['errors'],
            'issues'    => $tc['issues'],
            'noindex'   => $tc['noindex'],
        ];
    }

    /** @return array<string, mixed> */
    public function getAuditReportProperty(): array
    {
        if ($this->auditId === null) {
            return [
                'totalIssues' => 0,
                'affectedPages' => 0,
                'severityTotals' => [],
                'categoryTotals' => [],
                'groups' => [],
                'siteScore' => 100,
            ];
        }

        $r = app(GetAuditIssueReportHandler::class)(new GetAuditIssueReportQuery($this->auditId));

        return [
            'totalIssues' => $r->totalIssues,
            'affectedPages' => $r->affectedPages,
            'severityTotals' => $r->severityTotals,
            'categoryTotals' => $r->categoryTotals,
            'siteScore' => $r->siteScore,
            'groups' => array_map(static fn($g): array => [
                'code' => $g->code,
                'category' => $g->category,
                'severity' => $g->severity,
                'title' => $g->title,
                'summary' => $g->summary,
                'why' => $g->why,
                'how' => $g->how,
                'source' => $g->source,
                'count' => $g->count,
                'affectedPageCount' => $g->affectedPageCount,
                'weight' => $g->weight,
                'affectedPages' => array_map(static fn($p): array => [
                    'pageId' => $p->pageId,
                    'url' => $p->url,
                    'context' => $p->context,
                ], $g->affectedPages),
            ], $r->groups),
        ];
    }

    /** @return array<string, mixed> */
    public function getOverviewProperty(): array
    {
        if ($this->auditId === null) {
            return [];
        }

        $auditId = new AuditId($this->auditId);

        $snapshot = app(AuditSnapshotRepository::class)->findByAudit($auditId);

        if ($snapshot !== null) {
            return $snapshot->overview + ['totalExternal' => count($this->externalLinks)];
        }

        if ($this->pagesTotal === 0) {
            return [];
        }

        return app(\SeoSpider\Audit\Application\AuditOverview\AuditOverviewBuilder::class)
            ->build($auditId)
            + ['totalExternal' => count($this->externalLinks)];
    }

    private function mapActiveTabToQuery(): ?string
    {
        return match ($this->activeTab) {
            'internal' => GetAuditPagesQuery::TAB_INTERNAL,
            'html' => GetAuditPagesQuery::TAB_HTML,
            'redirects' => GetAuditPagesQuery::TAB_REDIRECTS,
            'errors' => GetAuditPagesQuery::TAB_ERRORS,
            'issues' => GetAuditPagesQuery::TAB_ISSUES,
            'noindex' => GetAuditPagesQuery::TAB_NOINDEX,
            default => null,
        };
    }

    private function latestCrawledAt(): ?string
    {
        $timestamps = array_column($this->pages, 'crawledAt');
        if ($timestamps === []) {
            return null;
        }

        $max = max($timestamps);

        return is_string($max) && $max !== '' ? $max : null;
    }

    /** @return array<int, array<string, mixed>> */
    private function externalLinksAsPages(): array
    {
        $deduped = [];
        foreach ($this->externalLinks as $ext) {
            $deduped[(string) ($ext['url'] ?? '')] ??= $ext;
        }

        return array_values(array_map(static fn(array $ext): array => [
            'pageId' => null,
            'url' => $ext['url'],
            'statusCode' => (int) ($ext['status_code'] ?? 0),
            'contentType' => 'external',
            'title' => $ext['anchor_text'] ?? null,
            'responseTime' => (float) ($ext['response_time'] ?? 0),
            'bodySize' => 0,
            'crawlDepth' => 0,
            'errorCount' => ((int) ($ext['status_code'] ?? 0) >= 400 || (int) ($ext['status_code'] ?? 0) === 0) ? 1 : 0,
            'warningCount' => ($ext['error'] ?? null) !== null ? 1 : 0,
            'isIndexable' => false,
            'wordCount' => 0,
            'internalLinkCount' => 0,
            'externalLinkCount' => 0,
            'imageCount' => 0,
            'canonicalStatus' => '-',
            'h1Count' => 0,
            'isExternalCheck' => true,
        ], $deduped));
    }

    public function render(): View
    {
        return view('livewire.audit-page-table');
    }
}
