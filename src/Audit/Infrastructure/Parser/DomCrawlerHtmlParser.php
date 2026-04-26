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
use SeoSpider\Audit\Domain\Model\Page\ParsedPage;
use SeoSpider\Audit\Domain\Model\Url;
use Symfony\Component\DomCrawler\Crawler;

final class DomCrawlerHtmlParser implements HtmlParser
{
    public function parse(string $html, Url $baseUrl): ParsedPage
    {
        $crawler = new Crawler($html);
        $effectiveBase = $this->resolveBase($crawler, $baseUrl);

        // Read-only passes first — the order among them does not matter.
        $links = $this->collectLinks($crawler, $baseUrl, $effectiveBase);
        $hreflangs = $this->collectHreflangs($crawler, $effectiveBase);
        $directive = $this->collectDirectives($crawler, $effectiveBase);
        $metadata = $this->collectMetadata($crawler);

        // DOM-mutating pass last so the removed nodes (script/style/nav/...) do
        // not hide links, directives or metadata from the earlier passes.
        $cleanContent = $this->collectCleanContent($crawler);

        return new ParsedPage(
            metadata: $metadata->withWordCount($this->countWords($cleanContent)),
            links: $links,
            hreflangs: $hreflangs,
            directive: $directive,
            cleanContent: $cleanContent,
        );
    }

    private function collectMetadata(Crawler $crawler): PageMetadata
    {
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
            wordCount: 0, // replaced by parse() once clean content is available
            lang: $this->extractLang($crawler),
            twitterCard: $this->extractTwitter($crawler, 'twitter:card'),
            twitterTitle: $this->extractTwitter($crawler, 'twitter:title'),
            twitterDescription: $this->extractTwitter($crawler, 'twitter:description'),
            twitterImage: $this->extractTwitter($crawler, 'twitter:image'),
            jsonLdTypes: $this->extractJsonLdTypes($crawler),
            hasMicrodata: $crawler->filter('[itemscope]')->count() > 0,
        );
    }

    private function collectDirectives(Crawler $crawler, Url $effectiveBase): Directive
    {
        $robotsContent = $this->extractMetaContent($crawler, 'robots')
            ?? $this->extractMetaContent($crawler, 'googlebot')
            ?? '';

        $lower = strtolower($robotsContent);

        return new Directive(
            noindex: str_contains($lower, 'noindex'),
            nofollow: str_contains($lower, 'nofollow'),
            noarchive: str_contains($lower, 'noarchive'),
            nosnippet: str_contains($lower, 'nosnippet'),
            noimageindex: str_contains($lower, 'noimageindex'),
            maxSnippet: $this->extractMaxDirective($lower, 'max-snippet'),
            maxImagePreview: $this->extractStringDirective($lower, 'max-image-preview'),
            maxVideoPreview: $this->extractMaxDirective($lower, 'max-video-preview'),
            canonical: $this->extractCanonical($crawler, $effectiveBase),
            source: DirectiveSource::META_TAG,
        );
    }

    /** @return Link[] */
    private function collectLinks(Crawler $crawler, Url $baseUrl, Url $effectiveBase): array
    {
        $links = [];

        $crawler->filter('a[href]')->each(function (Crawler $node) use ($effectiveBase, $baseUrl, &$links) {
            $href = trim($node->attr('href') ?? '');

            if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, 'javascript:') || str_starts_with($href, 'mailto:')) {
                return;
            }

            $resolved = Url::tryFromString($href) ?? $this->tryResolve($effectiveBase, $href);
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

        $crawler->filter('img[src]')->each(function (Crawler $node) use ($effectiveBase, $baseUrl, &$links) {
            $src = trim($node->attr('src') ?? '');
            if ($src === '' || str_starts_with($src, 'data:')) {
                return;
            }

            $resolved = Url::tryFromString($src) ?? $this->tryResolve($effectiveBase, $src);
            if ($resolved === null) {
                return;
            }

            $links[] = new Link(
                targetUrl: $resolved,
                type: LinkType::IMAGE,
                anchorText: $node->attr('alt') ?: null,
                relation: LinkRelation::FOLLOW,
                isInternal: $resolved->isInternalTo($baseUrl),
                width: $this->parseDimension($node->attr('width')),
                height: $this->parseDimension($node->attr('height')),
            );
        });

        $crawler->filter('script[src]')->each(function (Crawler $node) use ($effectiveBase, $baseUrl, &$links) {
            $src = trim($node->attr('src') ?? '');
            if ($src === '') {
                return;
            }

            $resolved = Url::tryFromString($src) ?? $this->tryResolve($effectiveBase, $src);
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

        $crawler->filter('link[rel="stylesheet"][href]')->each(function (Crawler $node) use ($effectiveBase, $baseUrl, &$links) {
            $href = trim($node->attr('href') ?? '');
            if ($href === '') {
                return;
            }

            $resolved = Url::tryFromString($href) ?? $this->tryResolve($effectiveBase, $href);
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

        $crawler->filter('iframe[src]')->each(function (Crawler $node) use ($effectiveBase, $baseUrl, &$links) {
            $src = trim($node->attr('src') ?? '');
            if ($src === '' || str_starts_with($src, 'about:')) {
                return;
            }

            $resolved = Url::tryFromString($src) ?? $this->tryResolve($effectiveBase, $src);
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

        $crawler->filter('link[rel="preload"][href]')->each(function (Crawler $node) use ($effectiveBase, $baseUrl, &$links) {
            $href = trim($node->attr('href') ?? '');
            if ($href === '') {
                return;
            }

            $resolved = Url::tryFromString($href) ?? $this->tryResolve($effectiveBase, $href);
            if ($resolved === null) {
                return;
            }

            $as = strtolower(trim($node->attr('as') ?? ''));
            $type = match ($as) {
                'style' => LinkType::STYLESHEET,
                'script' => LinkType::SCRIPT,
                'font' => LinkType::FONT,
                'image' => LinkType::IMAGE,
                default => LinkType::PRELOAD,
            };

            $links[] = new Link(
                targetUrl: $resolved,
                type: $type,
                anchorText: null,
                relation: LinkRelation::FOLLOW,
                isInternal: $resolved->isInternalTo($baseUrl),
            );
        });

        $crawler->filter('link[rel="modulepreload"][href]')->each(function (Crawler $node) use ($effectiveBase, $baseUrl, &$links) {
            $href = trim($node->attr('href') ?? '');
            if ($href === '') {
                return;
            }

            $resolved = Url::tryFromString($href) ?? $this->tryResolve($effectiveBase, $href);
            if ($resolved === null) {
                return;
            }

            $links[] = new Link(
                targetUrl: $resolved,
                type: LinkType::MODULEPRELOAD,
                anchorText: null,
                relation: LinkRelation::FOLLOW,
                isInternal: $resolved->isInternalTo($baseUrl),
            );
        });

        $crawler->filter('link[rel="prefetch"][href]')->each(function (Crawler $node) use ($effectiveBase, $baseUrl, &$links) {
            $href = trim($node->attr('href') ?? '');
            if ($href === '') {
                return;
            }

            $resolved = Url::tryFromString($href) ?? $this->tryResolve($effectiveBase, $href);
            if ($resolved === null) {
                return;
            }

            $links[] = new Link(
                targetUrl: $resolved,
                type: LinkType::PREFETCH,
                anchorText: null,
                relation: LinkRelation::FOLLOW,
                isInternal: $resolved->isInternalTo($baseUrl),
            );
        });

        $crawler->filter('img[srcset], source[srcset]')->each(function (Crawler $node) use ($effectiveBase, $baseUrl, &$links) {
            $srcset = trim($node->attr('srcset') ?? '');
            if ($srcset === '') {
                return;
            }

            foreach ($this->parseSrcset($srcset) as $src) {
                $resolved = Url::tryFromString($src) ?? $this->tryResolve($effectiveBase, $src);
                if ($resolved === null) {
                    continue;
                }

                $links[] = new Link(
                    targetUrl: $resolved,
                    type: LinkType::IMAGE,
                    anchorText: $node->attr('alt') ?: null,
                    relation: LinkRelation::FOLLOW,
                    isInternal: $resolved->isInternalTo($baseUrl),
                );
            }
        });

        $crawler->filter('picture > source[src]')->each(function (Crawler $node) use ($effectiveBase, $baseUrl, &$links) {
            $src = trim($node->attr('src') ?? '');
            if ($src === '' || str_starts_with($src, 'data:')) {
                return;
            }

            $resolved = Url::tryFromString($src) ?? $this->tryResolve($effectiveBase, $src);
            if ($resolved === null) {
                return;
            }

            $links[] = new Link(
                targetUrl: $resolved,
                type: LinkType::IMAGE,
                anchorText: null,
                relation: LinkRelation::FOLLOW,
                isInternal: $resolved->isInternalTo($baseUrl),
            );
        });

        $crawler->filter('video[src], video > source[src]')->each(function (Crawler $node) use ($effectiveBase, $baseUrl, &$links) {
            $src = trim($node->attr('src') ?? '');
            if ($src === '') {
                return;
            }

            $resolved = Url::tryFromString($src) ?? $this->tryResolve($effectiveBase, $src);
            if ($resolved === null) {
                return;
            }

            $links[] = new Link(
                targetUrl: $resolved,
                type: LinkType::VIDEO,
                anchorText: null,
                relation: LinkRelation::FOLLOW,
                isInternal: $resolved->isInternalTo($baseUrl),
            );
        });

        $crawler->filter('video[poster]')->each(function (Crawler $node) use ($effectiveBase, $baseUrl, &$links) {
            $poster = trim($node->attr('poster') ?? '');
            if ($poster === '' || str_starts_with($poster, 'data:')) {
                return;
            }

            $resolved = Url::tryFromString($poster) ?? $this->tryResolve($effectiveBase, $poster);
            if ($resolved === null) {
                return;
            }

            $links[] = new Link(
                targetUrl: $resolved,
                type: LinkType::IMAGE,
                anchorText: null,
                relation: LinkRelation::FOLLOW,
                isInternal: $resolved->isInternalTo($baseUrl),
            );
        });

        $crawler->filter('audio[src], audio > source[src]')->each(function (Crawler $node) use ($effectiveBase, $baseUrl, &$links) {
            $src = trim($node->attr('src') ?? '');
            if ($src === '') {
                return;
            }

            $resolved = Url::tryFromString($src) ?? $this->tryResolve($effectiveBase, $src);
            if ($resolved === null) {
                return;
            }

            $links[] = new Link(
                targetUrl: $resolved,
                type: LinkType::AUDIO,
                anchorText: null,
                relation: LinkRelation::FOLLOW,
                isInternal: $resolved->isInternalTo($baseUrl),
            );
        });

        return $this->deduplicateLinks($links);
    }

    /**
     * @param Link[] $links
     * @return Link[]
     */
    private function deduplicateLinks(array $links): array
    {
        $seen = [];
        $unique = [];

        foreach ($links as $link) {
            $key = $link->targetUrl()->toString() . '|' . $link->type()->value;
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $link;
            }
        }

        return $unique;
    }

    /** @return string[] */
    private function parseSrcset(string $srcset): array
    {
        $urls = [];
        $candidates = preg_split('/\s*,\s*/', $srcset, -1, PREG_SPLIT_NO_EMPTY);

        if (empty($candidates)) {
            return [];
        }

        foreach ($candidates as $candidate) {
            $parts = preg_split('/\s+/', trim($candidate), 2);
            if ($parts !== false && count($parts) >= 1 && $parts[0] !== '') {
                $urls[] = $parts[0];
            }
        }

        return array_unique($urls);
    }

    /** @return Hreflang[] */
    private function collectHreflangs(Crawler $crawler, Url $effectiveBase): array
    {
        $hreflangs = [];

        $crawler->filter('link[rel="alternate"][hreflang]')->each(function (Crawler $node) use ($effectiveBase, &$hreflangs) {
            $hreflang = trim($node->attr('hreflang') ?? '');
            $href = trim($node->attr('href') ?? '');

            if ($hreflang === '' || $href === '') {
                return;
            }

            $url = Url::tryFromString($href) ?? $this->tryResolve($effectiveBase, $href);
            if ($url === null) {
                return;
            }

            // x-default is the whole token per spec; don't split it on '-'
            // or language/region validation will treat "default" as a region.
            if (strtolower($hreflang) === 'x-default') {
                $language = $hreflang;
                $region = null;
            } else {
                $parts = explode('-', $hreflang, 2);
                $language = $parts[0];
                $region = $parts[1] ?? null;
            }

            $hreflangs[] = new Hreflang(
                language: $language,
                region: $region,
                href: $url,
                source: HreflangSource::HTML_HEAD,
            );
        });

        return $hreflangs;
    }

    private function collectCleanContent(Crawler $crawler): string
    {
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

    private function extractTwitter(Crawler $crawler, string $name): ?string
    {
        // Twitter spec uses name="twitter:..." but property="twitter:..." is
        // common in the wild (Open Graph parsers leak the syntax). Accept both.
        $node = $crawler->filter(sprintf('meta[name="%s"], meta[property="%s"]', $name, $name));
        if ($node->count() === 0) {
            return null;
        }

        $content = $node->first()->attr('content');

        return $content !== null ? trim($content) ?: null : null;
    }

    /** @return string[] */
    private function extractJsonLdTypes(Crawler $crawler): array
    {
        $types = [];

        $crawler->filter('script[type="application/ld+json"]')->each(function (Crawler $node) use (&$types): void {
            $raw = trim($node->text('', false));
            if ($raw === '') {
                return;
            }

            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                return;
            }

            foreach ($this->collectJsonLdTypes($decoded) as $type) {
                $types[] = $type;
            }
        });

        return array_values(array_unique($types));
    }

    /**
     * @param mixed $node
     * @return string[]
     */
    private function collectJsonLdTypes(mixed $node): array
    {
        if (!is_array($node)) {
            return [];
        }

        $found = [];

        if (isset($node['@type'])) {
            $type = $node['@type'];
            if (is_string($type)) {
                $found[] = $type;
            } elseif (is_array($type)) {
                foreach ($type as $t) {
                    if (is_string($t)) {
                        $found[] = $t;
                    }
                }
            }
        }

        // Recurse into @graph and any nested arrays/objects so we catch
        // nested entities that schema.org tooling commonly emits.
        foreach ($node as $value) {
            if (is_array($value)) {
                $found = array_merge($found, $this->collectJsonLdTypes($value));
            }
        }

        return $found;
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

    private function extractCanonical(Crawler $crawler, Url $effectiveBase): ?Url
    {
        $node = $crawler->filter('link[rel="canonical"]');
        if ($node->count() === 0) {
            return null;
        }

        $href = $node->first()->attr('href');
        if ($href === null || trim($href) === '') {
            return null;
        }

        $trimmed = trim($href);

        return Url::tryFromString($trimmed) ?? $this->tryResolve($effectiveBase, $trimmed);
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

    private function parseDimension(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '' || !ctype_digit($trimmed)) {
            return null;
        }

        $int = (int) $trimmed;

        return $int > 0 ? $int : null;
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

    private function resolveBase(Crawler $crawler, Url $fallback): Url
    {
        $node = $crawler->filter('base[href]');
        if ($node->count() === 0) {
            return $fallback;
        }

        $href = trim($node->first()->attr('href') ?? '');
        if ($href === '') {
            return $fallback;
        }

        $resolved = Url::tryFromString($href) ?? $this->tryResolve($fallback, $href);

        return $resolved ?? $fallback;
    }
}
