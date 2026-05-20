<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Infrastructure\InMemory;

use SeoSpider\Audit\Domain\Model\Page\Page;
use SeoSpider\Auditing\Application\Reporting\GetAuditPages\GetAuditPagesQuery;
use SeoSpider\Auditing\Application\Reporting\GetAuditPages\PageSummary;
use SeoSpider\Auditing\Application\Reporting\GetAuditPages\PageSummaryReader;
use SeoSpider\Auditing\Domain\Model\Audit\AuditId;
use SeoSpider\Crawling\Domain\Model\Page\LinkType;
use SeoSpider\Tests\Auditing\Infrastructure\InMemory\InMemoryAuditedPageRepository;

final readonly class InMemoryPageSummaryReader implements PageSummaryReader
{
    public function __construct(
        private InMemoryPageRepository $pages,
        private InMemoryAuditedPageRepository $auditedPages,
    ) {
    }

    /** @return PageSummary[] */
    public function read(GetAuditPagesQuery $query): array
    {
        $matches = $this->matchingPages($query);

        usort($matches, $this->comparator(new AuditId($query->auditId), $query->sortField, $query->sortDir));

        if ($query->limit !== null) {
            $matches = array_slice($matches, max(0, $query->offset), $query->limit);
        }

        $auditId = new AuditId($query->auditId);
        return array_map(fn (Page $p) => $this->toSummary($p, $auditId), $matches);
    }

    public function count(GetAuditPagesQuery $query): int
    {
        return count($this->matchingPages($query));
    }

    public function totalForAudit(AuditId $auditId): int
    {
        return count($this->pages->findByAudit($auditId));
    }

    public function tabCounts(AuditId $auditId): array
    {
        $pages = $this->pages->findByAudit($auditId);

        $internal = 0;
        $html = 0;
        $redirects = 0;
        $errors = 0;
        $issues = 0;
        $noindex = 0;

        foreach ($pages as $page) {
            $status = $page->response()->statusCode()->code();
            $isHtml = $page->isHtml();
            if ($isHtml) {
                $html++;
                if ($status < 300) {
                    $internal++;
                }
            }
            if ($status >= 300 && $status < 400) {
                $redirects++;
            }
            if ($status >= 400) {
                $errors++;
            }
            if ($this->countsFor($auditId, $page)['issues'] > 0) {
                $issues++;
            }
            if (!$page->isIndexable()) {
                $noindex++;
            }
        }

        return [
            'pages' => count($pages),
            'internal' => $internal,
            'html' => $html,
            'redirects' => $redirects,
            'errors' => $errors,
            'issues' => $issues,
            'noindex' => $noindex,
        ];
    }

    /** @return Page[] */
    private function matchingPages(GetAuditPagesQuery $query): array
    {
        $auditId = new AuditId($query->auditId);
        $pages = $query->since !== null
            ? $this->pages->findByAuditSince($auditId, $query->since)
            : $this->pages->findByAudit($auditId);

        $tab = $query->tab;
        $search = $query->search !== null ? mb_strtolower($query->search) : null;

        return array_values(array_filter($pages, function (Page $p) use ($auditId, $tab, $search): bool {
            $status = $p->response()->statusCode()->code();
            $contentType = $p->response()->contentType() ?? '';
            $isHtml = str_contains(strtolower($contentType), 'html');

            $matchesTab = match ($tab) {
                GetAuditPagesQuery::TAB_INTERNAL => $isHtml && $status < 300,
                GetAuditPagesQuery::TAB_HTML => $isHtml,
                GetAuditPagesQuery::TAB_REDIRECTS => $status >= 300 && $status < 400,
                GetAuditPagesQuery::TAB_ERRORS => $status >= 400,
                GetAuditPagesQuery::TAB_ISSUES => $this->countsFor($auditId, $p)['issues'] > 0,
                GetAuditPagesQuery::TAB_NOINDEX => !$p->isIndexable(),
                default => true,
            };

            if (!$matchesTab) {
                return false;
            }

            if ($search === null) {
                return true;
            }

            $url = mb_strtolower($p->url()->toString());
            $title = mb_strtolower((string) ($p->metadata()?->title() ?? ''));

            return str_contains($url, $search) || str_contains($title, $search);
        }));
    }

    private function comparator(AuditId $auditId, ?string $sortField, string $sortDir): callable
    {
        $dir = strtolower($sortDir) === 'desc' ? -1 : 1;

        return function (Page $a, Page $b) use ($auditId, $sortField, $dir): int {
            $extract = function (Page $p) use ($auditId, $sortField): mixed {
                $counts = $this->countsFor($auditId, $p);
                return match ($sortField) {
                    'url' => $p->url()->toString(),
                    'statusCode' => $p->response()->statusCode()->code(),
                    'bodySize' => $p->response()->bodySize(),
                    'responseTime' => $p->response()->responseTime(),
                    'crawlDepth' => $p->crawlDepth(),
                    'errorCount' => $counts['errors'],
                    'warningCount' => $counts['warnings'],
                    'title' => (string) ($p->metadata()?->title() ?? ''),
                    'wordCount' => $p->metadata()?->wordCount() ?? 0,
                    'h1Count' => $p->metadata()?->h1Count() ?? 0,
                    default => $p->crawledAt()->format('c'),
                };
            };

            $va = $extract($a);
            $vb = $extract($b);
            $cmp = is_numeric($va) && is_numeric($vb)
                ? ((float) $va <=> (float) $vb)
                : strcasecmp((string) $va, (string) $vb);

            return $dir * $cmp;
        };
    }

    private function toSummary(Page $page, AuditId $auditId): PageSummary
    {
        $links = $page->links();
        $internal = 0;
        $external = 0;
        $images = 0;
        foreach ($links as $link) {
            if ($link->type() === LinkType::IMAGE) {
                $images++;
                continue;
            }
            if ($link->type() === LinkType::ANCHOR) {
                $link->isInternal() ? $internal++ : $external++;
            }
        }

        $directives = $page->directives();
        $canonicalStatus = match (true) {
            $directives === null || !$directives->hasCanonical() => 'missing',
            $directives->isSelfCanonical($page->url()) => 'self',
            default => 'other',
        };

        $counts = $this->countsFor($auditId, $page);

        return new PageSummary(
            pageId: $page->id()->value(),
            url: $page->url()->toString(),
            statusCode: $page->response()->statusCode()->code(),
            contentType: $page->response()->contentType() ?? '',
            bodySize: $page->response()->bodySize(),
            responseTime: $page->response()->responseTime(),
            crawlDepth: $page->crawlDepth(),
            errorCount: $counts['errors'],
            warningCount: $counts['warnings'],
            isIndexable: $page->isIndexable(),
            title: $page->metadata()?->title(),
            wordCount: $page->metadata()?->wordCount() ?? 0,
            internalLinkCount: $internal,
            externalLinkCount: $external,
            imageCount: $images,
            canonicalStatus: $canonicalStatus,
            h1Count: $page->metadata()?->h1Count() ?? 0,
            crawledAt: $page->crawledAt()->format('c'),
        );
    }

    /** @return array{errors: int, warnings: int, issues: int} */
    private function countsFor(AuditId $auditId, Page $page): array
    {
        $audited = $this->auditedPages->findByAuditAndUrl(
            $auditId->value(),
            $page->url()->toString(),
        );
        if ($audited === null) {
            return ['errors' => 0, 'warnings' => 0, 'issues' => 0];
        }
        return [
            'errors' => $audited->errorCount(),
            'warnings' => $audited->warningCount(),
            'issues' => count($audited->issues()),
        ];
    }
}
