<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Page;

use DateTimeImmutable;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\Url;
use SeoSpider\Shared\Domain\AggregateRoot;

final class Page extends AggregateRoot
{
    private PageId $id;
    private AuditId $auditId;
    private Url $url;
    private PageResponse $response;
    private ?PageMetadata $metadata = null;
    private ?Directive $directives = null;
    private ?Fingerprint $fingerprint = null;
    private RedirectChain $redirectChain;
    private int $crawlDepth;

    /** @var Link[] */
    private array $links = [];

    /** @var Hreflang[] */
    private array $hreflangs = [];

    /** @var Issue[] */
    private array $issues = [];

    private DateTimeImmutable $crawledAt;

    private function __construct()
    {
    }

    public static function fromCrawl(
        PageId $id,
        AuditId $auditId,
        Url $url,
        PageResponse $response,
        ?RedirectChain $redirectChain,
        int $crawlDepth,
    ): self {
        $page = new self();
        $page->id = $id;
        $page->auditId = $auditId;
        $page->url = $url;
        $page->response = $response;
        $page->redirectChain = $redirectChain ?? RedirectChain::none();
        $page->crawlDepth = $crawlDepth;
        $page->crawledAt = new DateTimeImmutable();

        return $page;
    }

    /**
     * @param Link[] $links
     * @param Hreflang[] $hreflangs
     * @param Issue[] $issues
     */
    public static function reconstitute(
        PageId $id,
        AuditId $auditId,
        Url $url,
        PageResponse $response,
        RedirectChain $redirectChain,
        int $crawlDepth,
        ?PageMetadata $metadata,
        ?Directive $directives,
        ?Fingerprint $fingerprint,
        array $links,
        array $hreflangs,
        array $issues,
        DateTimeImmutable $crawledAt,
    ): self {
        $page = new self();
        $page->id = $id;
        $page->auditId = $auditId;
        $page->url = $url;
        $page->response = $response;
        $page->redirectChain = $redirectChain;
        $page->crawlDepth = $crawlDepth;
        $page->metadata = $metadata;
        $page->directives = $directives;
        $page->fingerprint = $fingerprint;
        $page->links = $links;
        $page->hreflangs = $hreflangs;
        $page->issues = $issues;
        $page->crawledAt = $crawledAt;

        return $page;
    }

    public function enrichWithMetadata(PageMetadata $metadata): void
    {
        $this->metadata = $metadata;
    }

    public function enrichWithDirectives(Directive $directives): void
    {
        $this->directives = $directives;
    }

    public function enrichWithFingerprint(Fingerprint $fingerprint): void
    {
        $this->fingerprint = $fingerprint;
    }

    /** @param Link[] $links */
    public function enrichWithLinks(array $links): void
    {
        $this->links = $links;
    }

    /** @param Hreflang[] $hreflangs */
    public function enrichWithHreflangs(array $hreflangs): void
    {
        $this->hreflangs = $hreflangs;
    }

    public function addIssue(Issue $issue): void
    {
        $this->issues[] = $issue;
    }

    public function markAsAnalyzed(): void
    {
        $this->recordEvent(new PageCrawled(
            $this->id,
            $this->auditId,
            $this->url,
            $this->response->statusCode(),
            count($this->issues),
            new DateTimeImmutable(),
        ));
    }

    public function id(): PageId
    {
        return $this->id;
    }

    public function auditId(): AuditId
    {
        return $this->auditId;
    }

    public function url(): Url
    {
        return $this->url;
    }

    public function response(): PageResponse
    {
        return $this->response;
    }

    public function metadata(): ?PageMetadata
    {
        return $this->metadata;
    }

    public function directives(): ?Directive
    {
        return $this->directives;
    }

    public function fingerprint(): ?Fingerprint
    {
        return $this->fingerprint;
    }

    public function redirectChain(): RedirectChain
    {
        return $this->redirectChain;
    }

    public function crawlDepth(): int
    {
        return $this->crawlDepth;
    }

    /** @return Link[] */
    public function links(): array
    {
        return $this->links;
    }

    /** @return Hreflang[] */
    public function hreflangs(): array
    {
        return $this->hreflangs;
    }

    /** @return Issue[] */
    public function issues(): array
    {
        return $this->issues;
    }

    public function crawledAt(): DateTimeImmutable
    {
        return $this->crawledAt;
    }

    public function isHtml(): bool
    {
        return $this->response->isHtml();
    }

    public function isBroken(): bool
    {
        return $this->response->statusCode()->isBroken();
    }

    public function isRedirect(): bool
    {
        return $this->response->statusCode()->isRedirect();
    }

    public function isIndexable(): bool
    {
        if (!$this->response->statusCode()->isSuccessful()) {
            return false;
        }

        if ($this->directives !== null && $this->directives->noindex()) {
            return false;
        }

        if ($this->directives !== null
            && $this->directives->hasCanonical()
            && !$this->directives->isSelfCanonical($this->url)) {
            return false;
        }

        return true;
    }

    public function errorCount(): int
    {
        return count(array_filter(
            $this->issues,
            static fn(Issue $issue) => $issue->isError(),
        ));
    }

    public function warningCount(): int
    {
        return count(array_filter(
            $this->issues,
            static fn(Issue $issue) => $issue->isWarning(),
        ));
    }

    /** @return Link[] */
    public function internalLinks(): array
    {
        return array_filter(
            $this->links,
            static fn(Link $link) => $link->isInternal(),
        );
    }

    /** @return Link[] */
    public function externalLinks(): array
    {
        return array_filter(
            $this->links,
            static fn(Link $link) => $link->isExternal(),
        );
    }
}