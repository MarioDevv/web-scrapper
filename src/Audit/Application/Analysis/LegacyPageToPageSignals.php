<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Application\Analysis;

use SeoSpider\Crawling\Domain\Model\Page\Page as LegacyPage;
use SeoSpider\Auditing\Domain\Model\Analysis\PageSignals;
use SeoSpider\Auditing\Domain\Model\Analysis\Signal\Directive;
use SeoSpider\Auditing\Domain\Model\Analysis\Signal\Fingerprint;
use SeoSpider\Auditing\Domain\Model\Analysis\Signal\Hreflang;
use SeoSpider\Auditing\Domain\Model\Analysis\Signal\Link;
use SeoSpider\Auditing\Domain\Model\Analysis\Signal\PageMetadata;
use SeoSpider\Auditing\Domain\Model\Analysis\Signal\PageResponseInfo;
use SeoSpider\Auditing\Domain\Model\Analysis\Signal\RedirectChain;
use SeoSpider\Auditing\Domain\Model\Analysis\Signal\RedirectHop;
use SeoSpider\Auditing\Domain\Model\Analysis\Signal\StatusCode;
use SeoSpider\Crawling\Domain\Model\Page\Directive as CrawlingDirective;
use SeoSpider\Crawling\Domain\Model\Page\Fingerprint as CrawlingFingerprint;
use SeoSpider\Crawling\Domain\Model\Page\Hreflang as CrawlingHreflang;
use SeoSpider\Crawling\Domain\Model\Page\Link as CrawlingLink;
use SeoSpider\Crawling\Domain\Model\Page\PageMetadata as CrawlingPageMetadata;

final readonly class LegacyPageToPageSignals implements PageSignals
{
    public function __construct(private LegacyPage $page)
    {
    }

    public function auditId(): string
    {
        return $this->page->auditId();
    }

    public function url(): string
    {
        return $this->page->url()->toString();
    }

    public function crawlDepth(): int
    {
        return $this->page->crawlDepth();
    }

    public function response(): PageResponseInfo
    {
        $r = $this->page->response();
        return new PageResponseInfo(
            new StatusCode($r->statusCode()->code()),
            $r->headers(),
            $r->contentType(),
            $r->bodySize(),
            $r->responseTime(),
            $r->finalUrl()?->toString(),
        );
    }

    public function metadata(): ?PageMetadata
    {
        $m = $this->page->metadata();
        return $m === null ? null : $this->translateMetadata($m);
    }

    public function directives(): ?Directive
    {
        $d = $this->page->directives();
        return $d === null ? null : $this->translateDirective($d);
    }

    public function fingerprint(): ?Fingerprint
    {
        $f = $this->page->fingerprint();
        return $f === null ? null : $this->translateFingerprint($f);
    }

    public function redirectChain(): RedirectChain
    {
        $chain = $this->page->redirectChain();
        $hops = [];
        foreach ($chain->hops() as $hop) {
            $hops[] = new RedirectHop(
                $hop->from()->toString(),
                $hop->to()->toString(),
                new StatusCode($hop->statusCode()->code()),
            );
        }
        return new RedirectChain($hops);
    }

    /** @return Link[] */
    public function links(): array
    {
        return array_map($this->translateLink(...), $this->page->links());
    }

    /** @return Link[] */
    public function internalLinks(): array
    {
        return array_map($this->translateLink(...), array_values($this->page->internalLinks()));
    }

    /** @return Link[] */
    public function externalLinks(): array
    {
        return array_map($this->translateLink(...), array_values($this->page->externalLinks()));
    }

    /** @return Hreflang[] */
    public function hreflangs(): array
    {
        return array_map($this->translateHreflang(...), $this->page->hreflangs());
    }

    public function isHtml(): bool
    {
        return $this->page->isHtml();
    }

    public function isBroken(): bool
    {
        return $this->page->isBroken();
    }

    public function isRedirect(): bool
    {
        return $this->page->isRedirect();
    }

    public function isIndexable(): bool
    {
        return $this->page->isIndexable();
    }

    private function translateMetadata(CrawlingPageMetadata $m): PageMetadata
    {
        return new PageMetadata(
            title: $m->title(),
            metaDescription: $m->metaDescription(),
            h1s: $m->h1s(),
            h2s: $m->h2s(),
            headingHierarchy: $m->headingHierarchy(),
            charset: $m->charset(),
            viewport: $m->viewport(),
            ogTitle: $m->ogTitle(),
            ogDescription: $m->ogDescription(),
            ogImage: $m->ogImage(),
            wordCount: $m->wordCount(),
            lang: $m->lang(),
            twitterCard: $m->twitterCard(),
            twitterTitle: $m->twitterTitle(),
            twitterDescription: $m->twitterDescription(),
            twitterImage: $m->twitterImage(),
            jsonLdTypes: $m->jsonLdTypes(),
            hasMicrodata: $m->hasMicrodata(),
        );
    }

    private function translateDirective(CrawlingDirective $d): Directive
    {
        return new Directive(
            noindex: $d->noindex(),
            nofollow: $d->nofollow(),
            noarchive: $d->noarchive(),
            nosnippet: $d->nosnippet(),
            noimageindex: $d->noimageindex(),
            maxSnippet: $d->maxSnippet(),
            maxImagePreview: $d->maxImagePreview(),
            maxVideoPreview: $d->maxVideoPreview(),
            canonical: $d->canonical()?->toString(),
            source: $d->source()?->value,
        );
    }

    private function translateFingerprint(CrawlingFingerprint $f): Fingerprint
    {
        return new Fingerprint($f->exactHash(), $f->simHash());
    }

    private function translateLink(CrawlingLink $l): Link
    {
        return new Link(
            targetUrl: $l->targetUrl()->toString(),
            type: $l->type()->value,
            anchorText: $l->anchorText(),
            relation: $l->relation()->value,
            isInternal: $l->isInternal(),
            width: $l->width(),
            height: $l->height(),
        );
    }

    private function translateHreflang(CrawlingHreflang $h): Hreflang
    {
        return new Hreflang(
            language: $h->language(),
            region: $h->region(),
            href: $h->href()->toString(),
            source: $h->source()->value,
        );
    }
}
