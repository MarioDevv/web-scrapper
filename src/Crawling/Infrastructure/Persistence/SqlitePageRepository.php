<?php

declare(strict_types=1);

namespace SeoSpider\Crawling\Infrastructure\Persistence;

use DateTimeImmutable;
use PDO;
use SeoSpider\Crawling\Domain\Model\HttpStatusCode;
use SeoSpider\Crawling\Domain\Model\Page\Directive;
use SeoSpider\Crawling\Domain\Model\Page\DirectiveSource;
use SeoSpider\Crawling\Domain\Model\Page\Fingerprint;
use SeoSpider\Crawling\Domain\Model\Page\Hreflang;
use SeoSpider\Crawling\Domain\Model\Page\HreflangSource;
use SeoSpider\Crawling\Domain\Model\Page\Link;
use SeoSpider\Crawling\Domain\Model\Page\LinkRelation;
use SeoSpider\Crawling\Domain\Model\Page\LinkType;
use SeoSpider\Crawling\Domain\Model\Page\Page;
use SeoSpider\Crawling\Domain\Model\Page\PageId;
use SeoSpider\Crawling\Domain\Model\Page\PageMetadata;
use SeoSpider\Crawling\Domain\Model\Page\PageRepository;
use SeoSpider\Crawling\Domain\Model\Page\PageResponse;
use SeoSpider\Crawling\Domain\Model\Page\RedirectChain;
use SeoSpider\Crawling\Domain\Model\Page\RedirectHop;
use SeoSpider\Crawling\Domain\Model\Url;

final readonly class SqlitePageRepository implements PageRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function save(Page $page): void
    {
        $this->pdo->beginTransaction();

        try {
            $this->upsertPage($page);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function findById(PageId $id): ?Page
    {
        $stmt = $this->pdo->prepare('SELECT * FROM pages WHERE id = :id');
        $stmt->execute(['id' => $id->value()]);

        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function findByAuditAndUrl(string $auditId, Url $url): ?Page
    {
        $stmt = $this->pdo->prepare('SELECT * FROM pages WHERE audit_id = :audit_id AND url = :url');
        $stmt->execute([
            'audit_id' => $auditId,
            'url' => $url->toString(),
        ]);

        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        return $this->hydrate($row);
    }

    /** @return Page[] */
    public function findByAudit(string $auditId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM pages WHERE audit_id = :audit_id ORDER BY crawled_at ASC');
        $stmt->execute(['audit_id' => $auditId]);

        return array_map(fn (array $row) => $this->hydrate($row), $stmt->fetchAll());
    }

    public function findByAuditSince(string $auditId, ?string $sinceIso): array
    {
        if ($sinceIso === null || $sinceIso === '') {
            return $this->findByAudit($auditId);
        }

        $stmt = $this->pdo->prepare(
            'SELECT * FROM pages WHERE audit_id = :audit_id AND crawled_at > :since ORDER BY crawled_at ASC',
        );
        $stmt->execute([
            'audit_id' => $auditId,
            'since' => $sinceIso,
        ]);

        return array_map(fn (array $row) => $this->hydrate($row), $stmt->fetchAll());
    }

    public function countByAudit(string $auditId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM pages WHERE audit_id = :audit_id');
        $stmt->execute(['audit_id' => $auditId]);

        return (int) $stmt->fetchColumn();
    }

    public function nextId(): PageId
    {
        return PageId::generate();
    }

    /** @return array<string, Fingerprint> */
    public function fingerprintsByAudit(string $auditId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT url, exact_hash, sim_hash FROM pages WHERE audit_id = :audit_id AND exact_hash IS NOT NULL',
        );
        $stmt->execute(['audit_id' => $auditId]);

        $fingerprints = [];
        foreach ($stmt->fetchAll() as $row) {
            $fingerprints[$row['url']] = new Fingerprint($row['exact_hash'], (int) $row['sim_hash']);
        }

        return $fingerprints;
    }

    private function upsertPage(Page $page): void
    {
        $metadata = $page->metadata();
        $directives = $page->directives();
        $fingerprint = $page->fingerprint();
        $linkSummary = $this->summariseLinks($page);
        $canonicalStatus = $this->canonicalStatus($page);

        $stmt = $this->pdo->prepare('
            INSERT INTO pages (
                id, audit_id, url, status_code, content_type, body_size, response_time,
                final_url, headers, crawl_depth, error_count, warning_count,
                internal_link_count, external_link_count, image_count, canonical_status,
                is_html,
                title, meta_description, h1s, h2s, heading_hierarchy,
                charset, viewport, og_title, og_description, og_image, word_count, lang,
                noindex, nofollow, noarchive, nosnippet, noimageindex,
                max_snippet, max_image_preview, max_video_preview,
                canonical, directive_source,
                exact_hash, sim_hash,
                redirect_chain, links, hreflangs, crawled_at
            ) VALUES (
                :id, :audit_id, :url, :status_code, :content_type, :body_size, :response_time,
                :final_url, :headers, :crawl_depth, :error_count, :warning_count,
                :internal_link_count, :external_link_count, :image_count, :canonical_status,
                :is_html,
                :title, :meta_description, :h1s, :h2s, :heading_hierarchy,
                :charset, :viewport, :og_title, :og_description, :og_image, :word_count, :lang,
                :noindex, :nofollow, :noarchive, :nosnippet, :noimageindex,
                :max_snippet, :max_image_preview, :max_video_preview,
                :canonical, :directive_source,
                :exact_hash, :sim_hash,
                :redirect_chain, :links, :hreflangs, :crawled_at
            ) ON CONFLICT(id) DO UPDATE SET
                status_code = :status_code,
                error_count = :error_count,
                warning_count = :warning_count,
                internal_link_count = :internal_link_count,
                external_link_count = :external_link_count,
                image_count = :image_count,
                canonical_status = :canonical_status,
                links = :links,
                hreflangs = :hreflangs
        ');

        $stmt->execute([
            'id' => $page->id()->value(),
            'audit_id' => $page->auditId(),
            'url' => $page->url()->toString(),
            'status_code' => $page->response()->statusCode()->code(),
            'content_type' => $page->response()->contentType(),
            'body_size' => $page->response()->bodySize(),
            'response_time' => $page->response()->responseTime(),
            'final_url' => $page->response()->finalUrl()?->toString(),
            'headers' => json_encode($page->response()->headers()),
            'crawl_depth' => $page->crawlDepth(),
            'error_count' => 0,
            'warning_count' => 0,
            'internal_link_count' => $linkSummary['internal'],
            'external_link_count' => $linkSummary['external'],
            'image_count' => $linkSummary['images'],
            'canonical_status' => $canonicalStatus,
            'is_html' => $page->isHtml() ? 1 : 0,
            'title' => $metadata?->title(),
            'meta_description' => $metadata?->metaDescription(),
            'h1s' => json_encode($metadata?->h1s() ?? []),
            'h2s' => json_encode($metadata?->h2s() ?? []),
            'heading_hierarchy' => json_encode($metadata?->headingHierarchy() ?? []),
            'charset' => $metadata?->charset(),
            'viewport' => $metadata?->viewport(),
            'og_title' => $metadata?->ogTitle(),
            'og_description' => $metadata?->ogDescription(),
            'og_image' => $metadata?->ogImage(),
            'word_count' => $metadata?->wordCount() ?? 0,
            'lang' => $metadata?->lang(),
            'noindex' => $directives?->noindex() ? 1 : 0,
            'nofollow' => $directives?->nofollow() ? 1 : 0,
            'noarchive' => $directives?->noarchive() ? 1 : 0,
            'nosnippet' => $directives?->nosnippet() ? 1 : 0,
            'noimageindex' => $directives?->noimageindex() ? 1 : 0,
            'max_snippet' => $directives?->maxSnippet(),
            'max_image_preview' => $directives?->maxImagePreview(),
            'max_video_preview' => $directives?->maxVideoPreview(),
            'canonical' => $directives?->canonical()?->toString(),
            'directive_source' => $directives?->source()?->value,
            'exact_hash' => $fingerprint?->exactHash(),
            'sim_hash' => $fingerprint?->simHash(),
            'redirect_chain' => json_encode($this->serializeRedirectChain($page->redirectChain())),
            'links' => json_encode($this->serializeLinks($page->links())),
            'hreflangs' => json_encode($this->serializeHreflangs($page->hreflangs())),
            'crawled_at' => $page->crawledAt()->format('c'),
        ]);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Page
    {
        return Page::reconstitute(
            id: new PageId($row['id']),
            auditId: $row['audit_id'],
            url: Url::fromString($row['url']),
            response: new PageResponse(
                statusCode: new HttpStatusCode((int) $row['status_code']),
                headers: json_decode($row['headers'], true),
                body: null,
                contentType: $row['content_type'],
                bodySize: (int) $row['body_size'],
                responseTime: (float) $row['response_time'],
                finalUrl: $row['final_url'] !== null ? Url::fromString($row['final_url']) : null,
            ),
            redirectChain: $this->hydrateRedirectChain(json_decode($row['redirect_chain'], true)),
            crawlDepth: (int) $row['crawl_depth'],
            metadata: $row['title'] !== null || (int) $row['word_count'] > 0
                ? new PageMetadata(
                    title: $row['title'],
                    metaDescription: $row['meta_description'],
                    h1s: json_decode($row['h1s'], true),
                    h2s: json_decode($row['h2s'], true),
                    headingHierarchy: json_decode($row['heading_hierarchy'], true),
                    charset: $row['charset'],
                    viewport: $row['viewport'],
                    ogTitle: $row['og_title'],
                    ogDescription: $row['og_description'],
                    ogImage: $row['og_image'],
                    wordCount: (int) $row['word_count'],
                    lang: $row['lang'],
                )
                : null,
            directives: new Directive(
                noindex: (bool) $row['noindex'],
                nofollow: (bool) $row['nofollow'],
                noarchive: (bool) $row['noarchive'],
                nosnippet: (bool) $row['nosnippet'],
                noimageindex: (bool) $row['noimageindex'],
                maxSnippet: $row['max_snippet'] !== null ? (int) $row['max_snippet'] : null,
                maxImagePreview: $row['max_image_preview'],
                maxVideoPreview: $row['max_video_preview'] !== null ? (int) $row['max_video_preview'] : null,
                canonical: $row['canonical'] !== null ? Url::fromString($row['canonical']) : null,
                source: $row['directive_source'] !== null ? DirectiveSource::from($row['directive_source']) : null,
            ),
            fingerprint: $row['exact_hash'] !== null
                ? new Fingerprint($row['exact_hash'], (int) $row['sim_hash'])
                : null,
            links: $this->hydrateLinks(json_decode($row['links'], true)),
            hreflangs: $this->hydrateHreflangs(json_decode($row['hreflangs'], true)),
            crawledAt: new DateTimeImmutable($row['crawled_at']),
        );
    }

    /** @return array<array{from: string, to: string, statusCode: int}> */
    private function serializeRedirectChain(RedirectChain $chain): array
    {
        return array_map(
            static fn(RedirectHop $hop) => [
                'from' => $hop->from()->toString(),
                'to' => $hop->to()->toString(),
                'statusCode' => $hop->statusCode()->code(),
            ],
            $chain->hops(),
        );
    }

    /** @param array<array{from: string, to: string, statusCode: int}> $data */
    private function hydrateRedirectChain(array $data): RedirectChain
    {
        if ($data === []) {
            return RedirectChain::none();
        }

        return RedirectChain::fromHops(array_map(
            static fn(array $hop) => new RedirectHop(
                Url::fromString($hop['from']),
                Url::fromString($hop['to']),
                new HttpStatusCode($hop['statusCode']),
            ),
            $data,
        ));
    }

    /**
     * @param Link[] $links
     * @return array<array<string, mixed>>
     */
    private function serializeLinks(array $links): array
    {
        return array_map(
            static fn(Link $link) => [
                'url' => $link->targetUrl()->toString(),
                'type' => $link->type()->value,
                'anchor' => $link->anchorText(),
                'rel' => $link->relation()->value,
                'internal' => $link->isInternal(),
                'width' => $link->width(),
                'height' => $link->height(),
            ],
            $links,
        );
    }

    /**
     * @param array<array<string, mixed>> $data
     * @return Link[]
     */
    private function hydrateLinks(array $data): array
    {
        return array_map(
            static fn(array $item) => new Link(
                Url::fromString($item['url']),
                LinkType::from($item['type']),
                $item['anchor'],
                LinkRelation::from($item['rel']),
                $item['internal'],
                isset($item['width']) ? (int) $item['width'] : null,
                isset($item['height']) ? (int) $item['height'] : null,
            ),
            $data,
        );
    }

    /**
     * @param Hreflang[] $hreflangs
     * @return array<array<string, mixed>>
     */
    private function serializeHreflangs(array $hreflangs): array
    {
        return array_map(
            static fn(Hreflang $h) => [
                'lang' => $h->language(),
                'region' => $h->region(),
                'href' => $h->href()->toString(),
                'source' => $h->source()->value,
            ],
            $hreflangs,
        );
    }

    /**
     * @param array<array<string, mixed>> $data
     * @return Hreflang[]
     */
    private function hydrateHreflangs(array $data): array
    {
        return array_map(
            static fn(array $item) => new Hreflang(
                $item['lang'],
                $item['region'],
                Url::fromString($item['href']),
                HreflangSource::from($item['source']),
            ),
            $data,
        );
    }

    /** @return array{internal: int, external: int, images: int} */
    private function summariseLinks(Page $page): array
    {
        $internal = 0;
        $external = 0;
        $images = 0;

        foreach ($page->links() as $link) {
            if ($link->type() === LinkType::IMAGE) {
                $images++;
                continue;
            }

            if ($link->type() === LinkType::ANCHOR) {
                if ($link->isInternal()) {
                    $internal++;
                } else {
                    $external++;
                }
            }
        }

        return ['internal' => $internal, 'external' => $external, 'images' => $images];
    }

    private function canonicalStatus(Page $page): string
    {
        $directives = $page->directives();
        if ($directives === null || !$directives->hasCanonical()) {
            return 'missing';
        }

        return $directives->isSelfCanonical($page->url()) ? 'self' : 'other';
    }
}