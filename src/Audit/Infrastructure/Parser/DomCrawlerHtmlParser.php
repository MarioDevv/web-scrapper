<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Infrastructure\Parser;

use SeoSpider\Audit\Domain\Model\HtmlParser;
use SeoSpider\Audit\Domain\Model\Page\Directive;
use SeoSpider\Audit\Domain\Model\Page\DirectiveSource;
use SeoSpider\Audit\Domain\Model\Page\Hreflang;
use SeoSpider\Audit\Domain\Model\Page\HreflangSource;
use SeoSpider\Audit\Domain\Model\Page\Link;
use SeoSpider\Audit\Domain\Model\Page\LinkRelation;
use SeoSpider\Audit\Domain\Model\Page\LinkType;
use SeoSpider\Audit\Domain\Model\Page\PageMetadata;
use SeoSpider\Audit\Domain\Model\Url;
use Symfony\Component\DomCrawler\Crawler;

final class DomCrawlerHtmlParser implements HtmlParser
{
    public function extractMetadata(string $html): PageMetadata
    {
        $crawler = new Crawler($html);

        $h1s = $crawler->filter('h1')->each(fn(Crawler $node) => trim($node->text('', false)));
        $h2s = $crawler->filter('h2')->each(fn(Crawler $node) => trim($node->text('', false)));

        $headings = [];
        $crawler->filter('h1, h2, h3, h4, h5, h6')->each(function (Crawler $node) use (&$headings) {
            $level = (int)substr($node->nodeName(), 1);
            $headings[] = ['level' => $level, 'text' => trim($node->text('', false))];
        });

        return new PageMetadata(
            title: $this->extractFirst($crawler, 'title'),
            metaDescription: $this->extractMetaContent($crawler, 'description'),
            h1s: $h1s,
            h2s: $h2s,
            headingHierarchy: $headings,
            charset: $this->extractCharset($crawler),
            viewport: $this->extractMetaContent($crawler, 'viewport'),
            ogTitle: $this->extractMetaProperty($crawler, 'og:title'),
            ogDescription: $this->extractMetaProperty($crawler, 'og:description'),
            ogImage: $this->extractMetaProperty($crawler, 'og:image'),
            wordCount: $this->countWords($this->extractCleanContent($html)),
            lang: $this->extractLang($crawler),
        );
    }

    public function extractDirectives(string $html): Directive
    {
        $crawler = new Crawler($html);

        $robotsContent = $this->extractMetaContent($crawler, 'robots')
            ?? $this->extractMetaContent($crawler, 'googlebot')
            ?? '';

        $lower = strtolower($robotsContent);
        $canonical = $this->extractCanonical($crawler);

        return new Directive(
            noindex: str_contains($lower, 'noindex'),
            nofollow: str_contains($lower, 'nofollow'),
            noarchive: str_contains($lower, 'noarchive'),
            nosnippet: str_contains($lower, 'nosnippet'),
            noimageindex: str_contains($lower, 'noimageindex'),
            maxSnippet: $this->extractMaxDirective($lower, 'max-snippet'),
            maxImagePreview: $this->extractStringDirective($lower, 'max-image-preview'),
            maxVideoPreview: $this->extractMaxDirective($lower, 'max-video-preview'),
            canonical: $canonical,
            source: DirectiveSource::META_TAG,
        );
    }

    /** @return Link[] */
    public function extractLinks(string $html, Url $baseUrl): array
    {
        $crawler = new Crawler($html);
        $links = [];

        // ── Anchors (<a href>)
        $crawler->filter('a[href]')->each(function (Crawler $node) use ($baseUrl, &$links) {
            $href = trim($node->attr('href') ?? '');

            if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, 'javascript:') || str_starts_with($href, 'mailto:')) {
                return;
            }

            $resolved = Url::tryFromString($href) ?? $this->tryResolve($baseUrl, $href);
            if ($resolved === null) {
                return;
            }

            $resolved = $resolved->withoutFragment();

            $rel = strtolower(trim($node->attr('rel') ?? ''));
            $relation = match (true) {
                str_contains($rel, 'nofollow') => LinkRelation::NOFOLLOW,
                str_contains($rel, 'sponsored') => LinkRelation::SPONSORED,
                str_contains($rel, 'ugc') => LinkRelation::UGC,
                default => LinkRelation::FOLLOW,
            };

            $links[] = new Link(
                targetUrl: $resolved,
                type: LinkType::ANCHOR,
                anchorText: trim($node->text('', false)) ?: null,
                relation: $relation,
                isInternal: $resolved->isInternalTo($baseUrl),
            );
        });

        // ── Images (<img src>)
        $crawler->filter('img[src]')->each(function (Crawler $node) use ($baseUrl, &$links) {
            $src = trim($node->attr('src') ?? '');
            if ($src === '' || str_starts_with($src, 'data:')) {
                return;
            }

            $resolved = Url::tryFromString($src) ?? $this->tryResolve($baseUrl, $src);
            if ($resolved === null) {
                return;
            }

            $links[] = new Link(
                targetUrl: $resolved,
                type: LinkType::IMAGE,
                anchorText: $node->attr('alt') ?: null,
                relation: LinkRelation::FOLLOW,
                isInternal: $resolved->isInternalTo($baseUrl),
            );
        });

        // ── Scripts (<script src>)
        $crawler->filter('script[src]')->each(function (Crawler $node) use ($baseUrl, &$links) {
            $src = trim($node->attr('src') ?? '');
            if ($src === '') {
                return;
            }

            $resolved = Url::tryFromString($src) ?? $this->tryResolve($baseUrl, $src);
            if ($resolved === null) {
                return;
            }

            $links[] = new Link(
                targetUrl: $resolved,
                type: LinkType::SCRIPT,
                anchorText: null,
                relation: LinkRelation::FOLLOW,
                isInternal: $resolved->isInternalTo($baseUrl),
            );
        });

        // ── Stylesheets (<link rel="stylesheet" href>)
        $crawler->filter('link[rel="stylesheet"][href]')->each(function (Crawler $node) use ($baseUrl, &$links) {
            $href = trim($node->attr('href') ?? '');
            if ($href === '') {
                return;
            }

            $resolved = Url::tryFromString($href) ?? $this->tryResolve($baseUrl, $href);
            if ($resolved === null) {
                return;
            }

            $links[] = new Link(
                targetUrl: $resolved,
                type: LinkType::STYLESHEET,
                anchorText: null,
                relation: LinkRelation::FOLLOW,
                isInternal: $resolved->isInternalTo($baseUrl),
            );
        });

        // ── Iframes (<iframe src>)
        $crawler->filter('iframe[src]')->each(function (Crawler $node) use ($baseUrl, &$links) {
            $src = trim($node->attr('src') ?? '');
            if ($src === '' || str_starts_with($src, 'about:')) {
                return;
            }

            $resolved = Url::tryFromString($src) ?? $this->tryResolve($baseUrl, $src);
            if ($resolved === null) {
                return;
            }

            $links[] = new Link(
                targetUrl: $resolved,
                type: LinkType::IFRAME,
                anchorText: null,
                relation: LinkRelation::FOLLOW,
                isInternal: $resolved->isInternalTo($baseUrl),
            );
        });

        return $links;
    }

    /** @return Hreflang[] */
    public function extractHreflangs(string $html): array
    {
        $crawler = new Crawler($html);
        $hreflangs = [];

        $crawler->filter('link[rel="alternate"][hreflang]')->each(function (Crawler $node) use (&$hreflangs) {
            $hreflang = trim($node->attr('hreflang') ?? '');
            $href = trim($node->attr('href') ?? '');

            if ($hreflang === '' || $href === '') {
                return;
            }

            $url = Url::tryFromString($href);
            if ($url === null) {
                return;
            }

            $parts = explode('-', $hreflang, 2);

            $hreflangs[] = new Hreflang(
                language: $parts[0],
                region: $parts[1] ?? null,
                href: $url,
                source: HreflangSource::HTML_HEAD,
            );
        });

        return $hreflangs;
    }

    public function extractCleanContent(string $html): string
    {
        $crawler = new Crawler($html);

        $crawler->filter('script, style, nav, header, footer, aside, noscript, iframe')->each(
            function (Crawler $node): void {
                $domNode = $node->getNode(0);

                if ($domNode !== null) {
                    $domNode->parentNode?->removeChild($domNode);
                }
            },
        );

        $body = $crawler->filter('body');
        if ($body->count() === 0) {
            return '';
        }

        $text = $body->text('', false);

        return preg_replace('/\s+/', ' ', trim($text)) ?? '';
    }

    private function extractFirst(Crawler $crawler, string $selector): ?string
    {
        $node = $crawler->filter($selector);

        return $node->count() > 0 ? trim($node->first()->text('', false)) ?: null : null;
    }

    private function extractMetaContent(Crawler $crawler, string $name): ?string
    {
        $node = $crawler->filter(sprintf('meta[name="%s"]', $name));
        if ($node->count() === 0) {
            return null;
        }

        $content = $node->first()->attr('content');

        return $content !== null ? trim($content) ?: null : null;
    }

    private function extractMetaProperty(Crawler $crawler, string $property): ?string
    {
        $node = $crawler->filter(sprintf('meta[property="%s"]', $property));
        if ($node->count() === 0) {
            return null;
        }

        $content = $node->first()->attr('content');

        return $content !== null ? trim($content) ?: null : null;
    }

    private function extractCharset(Crawler $crawler): ?string
    {
        $meta = $crawler->filter('meta[charset]');
        if ($meta->count() > 0) {
            return $meta->first()->attr('charset');
        }

        $httpEquiv = $crawler->filter('meta[http-equiv="Content-Type"]');
        if ($httpEquiv->count() > 0) {
            $content = $httpEquiv->first()->attr('content') ?? '';
            if (preg_match('/charset=([^\s;]+)/i', $content, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    private function extractLang(Crawler $crawler): ?string
    {
        $html = $crawler->filter('html');

        return $html->count() > 0 ? $html->first()->attr('lang') : null;
    }

    private function extractCanonical(Crawler $crawler): ?Url
    {
        $node = $crawler->filter('link[rel="canonical"]');
        if ($node->count() === 0) {
            return null;
        }

        $href = $node->first()->attr('href');
        if ($href === null || trim($href) === '') {
            return null;
        }

        return Url::tryFromString(trim($href));
    }

    private function extractMaxDirective(string $content, string $directive): ?int
    {
        if (preg_match('/' . preg_quote($directive, '/') . ':(\d+)/i', $content, $matches)) {
            return (int)$matches[1];
        }

        return null;
    }

    private function extractStringDirective(string $content, string $directive): ?string
    {
        if (preg_match('/' . preg_quote($directive, '/') . ':(\S+)/i', $content, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function countWords(string $text): int
    {
        if (trim($text) === '') {
            return 0;
        }

        return count(preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }

    private function tryResolve(Url $base, string $relative): ?Url
    {
        try {
            return $base->resolve($relative);
        } catch (\Throwable) {
            return null;
        }
    }
}