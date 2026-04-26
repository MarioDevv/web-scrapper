<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Infrastructure\InMemory;

use SeoSpider\Audit\Application\GetAuditPages\GetAuditPagesQuery;
use SeoSpider\Audit\Application\GetAuditPages\PageSummary;
use SeoSpider\Audit\Application\GetAuditPages\PageSummaryReader;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\Page\LinkType;
use SeoSpider\Audit\Domain\Model\Page\Page;

/**
 * Test double that projects the in-memory page repository through the
 * same filter/sort/paginate semantics the SQLite reader implements.
 */
final readonly class InMemoryPageSummaryReader implements PageSummaryReader
{
    public function __construct(private InMemoryPageRepository $pages)
    {
    }

    /** @return PageSummary[] */
    public function read(GetAuditPagesQuery $query): array
    {
        $matches = $this->matchingPages($query);

        usort($matches, $this->comparator($query->sortField, $query->sortDir));

        if ($query->limit !== null) {
            $matches = array_slice($matches, max(0, $query->offset), $query->limit);
        }

        return array_map($this->toSummary(...), $matches);
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
            if ($page->errorCount() > 0 || $page->warningCount() > 0) {
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

        return array_values(array_filter($pages, static function (Page $p) use ($tab, $search): bool {
            $status = $p->response()->statusCode()->code();
            $contentType = $p->response()->contentType() ?? '';
            $isHtml = str_contains(strtolower($contentType), 'html');

            $matchesTab = match ($tab) {
                GetAuditPagesQuery::TAB_INTERNAL => $isHtml && $status < 300,
                GetAuditPagesQuery::TAB_HTML => $isHtml,
                GetAuditPagesQuery::TAB_REDIRECTS => $status >= 300 && $status < 400,
                GetAuditPagesQuery::TAB_ERRORS => $status >= 400,
                GetAuditPagesQuery::TAB_ISSUES => $p->errorCount() > 0 || $p->warningCount() > 0,
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

    private function comparator(?string $sortField, string $sortDir): callable
    {
        $dir = strtolower($sortDir) === 'desc' ? -1 : 1;

        return static function (Page $a, Page $b) use ($sortField, $dir): int {
            $extract = static function (Page $p) use ($sortField): mixed {
                return match ($sortField) {
                    'url' => $p->url()->toString(),
                    'statusCode' => $p->response()->statusCode()->code(),
                    'bodySize' => $p->response()->bodySize(),
                    'responseTime' => $p->response()->responseTime(),
                    'crawlDepth' => $p->crawlDepth(),
                    'errorCount' => $p->errorCount(),
                    'warningCount' => $p->warningCount(),
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

    private function toSummary(Page $page): PageSummary
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

        return new PageSummary(
            pageId: $page->id()->value(),
            url: $page->url()->toString(),
            statusCode: $page->response()->statusCode()->code(),
            contentType: $page->response()->contentType() ?? '',
            bodySize: $page->response()->bodySize(),
            responseTime: $page->response()->responseTime(),
            crawlDepth: $page->crawlDepth(),
            errorCount: $page->errorCount(),
            warningCount: $page->warningCount(),
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
}
