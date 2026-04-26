<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Infrastructure\Parser;

use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Domain\Model\Url;
use SeoSpider\Audit\Infrastructure\Parser\DomCrawlerHtmlParser;

final class DomCrawlerHtmlParserTest extends TestCase
{
    private DomCrawlerHtmlParser $parser;
    private Url $baseUrl;

    protected function setUp(): void
    {
        $this->parser = new DomCrawlerHtmlParser();
        $this->baseUrl = Url::fromString('https://example.com/es/page');
    }

    public function test_extracts_absolute_canonical_unchanged(): void
    {
        $html = '<html><head><link rel="canonical" href="https://example.com/canonical"></head></html>';

        $directive = $this->parser->parse($html, $this->baseUrl)->directive;

        $this->assertNotNull($directive->canonical());
        $this->assertSame('https://example.com/canonical', $directive->canonical()->toString());
    }

    public function test_resolves_root_relative_canonical_against_base_url(): void
    {
        $html = '<html><head><link rel="canonical" href="/es/foo"></head></html>';

        $directive = $this->parser->parse($html, $this->baseUrl)->directive;

        $this->assertNotNull($directive->canonical());
        $this->assertSame('https://example.com/es/foo', $directive->canonical()->toString());
    }

    public function test_resolves_path_relative_canonical_against_base_url(): void
    {
        $html = '<html><head><link rel="canonical" href="other"></head></html>';

        $directive = $this->parser->parse($html, $this->baseUrl)->directive;

        $this->assertNotNull($directive->canonical());
        $this->assertSame('https://example.com/es/other', $directive->canonical()->toString());
    }

    public function test_missing_canonical_returns_null(): void
    {
        $html = '<html><head><title>no canonical</title></head></html>';

        $directive = $this->parser->parse($html, $this->baseUrl)->directive;

        $this->assertNull($directive->canonical());
    }

    public function test_empty_canonical_href_returns_null(): void
    {
        $html = '<html><head><link rel="canonical" href="   "></head></html>';

        $directive = $this->parser->parse($html, $this->baseUrl)->directive;

        $this->assertNull($directive->canonical());
    }

    public function test_extracts_absolute_hreflang_unchanged(): void
    {
        $html = '<html><head>'
            . '<link rel="alternate" hreflang="en" href="https://example.com/en/page">'
            . '</head></html>';

        $hreflangs = $this->parser->parse($html, $this->baseUrl)->hreflangs;

        $this->assertCount(1, $hreflangs);
        $this->assertSame('en', $hreflangs[0]->language());
        $this->assertNull($hreflangs[0]->region());
        $this->assertSame('https://example.com/en/page', $hreflangs[0]->href()->toString());
    }

    public function test_resolves_relative_hreflang_against_base_url(): void
    {
        $html = '<html><head>'
            . '<link rel="alternate" hreflang="en-GB" href="/en/page">'
            . '<link rel="alternate" hreflang="es" href="/es/page">'
            . '</head></html>';

        $hreflangs = $this->parser->parse($html, $this->baseUrl)->hreflangs;

        $this->assertCount(2, $hreflangs);
        $this->assertSame('en', $hreflangs[0]->language());
        $this->assertSame('GB', $hreflangs[0]->region());
        $this->assertSame('https://example.com/en/page', $hreflangs[0]->href()->toString());
        $this->assertSame('https://example.com/es/page', $hreflangs[1]->href()->toString());
    }

    public function test_skips_hreflang_entries_with_empty_href_or_lang(): void
    {
        $html = '<html><head>'
            . '<link rel="alternate" hreflang="" href="/en">'
            . '<link rel="alternate" hreflang="fr" href="">'
            . '</head></html>';

        $hreflangs = $this->parser->parse($html, $this->baseUrl)->hreflangs;

        $this->assertSame([], $hreflangs);
    }

    public function test_base_href_redirects_relative_anchors_to_another_origin(): void
    {
        $html = '<html><head>'
            . '<base href="https://cdn.other.com/app/">'
            . '</head><body>'
            . '<a href="foo">link</a>'
            . '</body></html>';

        $links = $this->parser->parse($html, $this->baseUrl)->links;

        $this->assertCount(1, $links);
        $this->assertSame('https://cdn.other.com/app/foo', $links[0]->targetUrl()->toString());
        $this->assertFalse($links[0]->isInternal(), 'link resolved to a different origin should be external');
    }

    public function test_relative_base_href_resolves_against_document_url(): void
    {
        $html = '<html><head>'
            . '<base href="/app/">'
            . '</head><body>'
            . '<a href="foo">link</a>'
            . '</body></html>';

        $links = $this->parser->parse($html, $this->baseUrl)->links;

        $this->assertCount(1, $links);
        $this->assertSame('https://example.com/app/foo', $links[0]->targetUrl()->toString());
        $this->assertTrue($links[0]->isInternal());
    }

    public function test_empty_base_href_falls_back_to_document_url(): void
    {
        $html = '<html><head>'
            . '<base href="">'
            . '</head><body>'
            . '<a href="foo">link</a>'
            . '</body></html>';

        $links = $this->parser->parse($html, $this->baseUrl)->links;

        $this->assertCount(1, $links);
        $this->assertSame('https://example.com/es/foo', $links[0]->targetUrl()->toString());
    }

    public function test_base_href_affects_relative_canonical(): void
    {
        $html = '<html><head>'
            . '<base href="https://other.com/">'
            . '<link rel="canonical" href="page">'
            . '</head></html>';

        $directive = $this->parser->parse($html, $this->baseUrl)->directive;

        $this->assertNotNull($directive->canonical());
        $this->assertSame('https://other.com/page', $directive->canonical()->toString());
    }

    public function test_base_href_affects_relative_hreflang(): void
    {
        $html = '<html><head>'
            . '<base href="https://other.com/intl/">'
            . '<link rel="alternate" hreflang="en" href="en/page">'
            . '</head></html>';

        $hreflangs = $this->parser->parse($html, $this->baseUrl)->hreflangs;

        $this->assertCount(1, $hreflangs);
        $this->assertSame('https://other.com/intl/en/page', $hreflangs[0]->href()->toString());
    }

    public function test_extracts_twitter_card_metadata(): void
    {
        $html = '<html><head>'
            . '<meta name="twitter:card" content="summary_large_image">'
            . '<meta name="twitter:title" content="Twitter Title">'
            . '<meta name="twitter:description" content="A twitter description.">'
            . '<meta name="twitter:image" content="https://example.com/twitter.png">'
            . '</head></html>';

        $metadata = $this->parser->parse($html, $this->baseUrl)->metadata;

        $this->assertSame('summary_large_image', $metadata->twitterCard());
        $this->assertSame('Twitter Title', $metadata->twitterTitle());
        $this->assertSame('A twitter description.', $metadata->twitterDescription());
        $this->assertSame('https://example.com/twitter.png', $metadata->twitterImage());
    }

    public function test_accepts_twitter_meta_via_property_attribute(): void
    {
        $html = '<html><head>'
            . '<meta property="twitter:card" content="summary">'
            . '</head></html>';

        $metadata = $this->parser->parse($html, $this->baseUrl)->metadata;

        $this->assertSame('summary', $metadata->twitterCard());
    }

    public function test_extracts_json_ld_types_from_top_level_objects(): void
    {
        $html = '<html><head>'
            . '<script type="application/ld+json">{"@context":"https://schema.org","@type":"Article","headline":"x"}</script>'
            . '</head></html>';

        $metadata = $this->parser->parse($html, $this->baseUrl)->metadata;

        $this->assertSame(['Article'], $metadata->jsonLdTypes());
        $this->assertTrue($metadata->hasStructuredData());
    }

    public function test_extracts_json_ld_types_from_graph_with_nested_entities(): void
    {
        $html = '<html><head>'
            . '<script type="application/ld+json">'
            . '{"@context":"https://schema.org","@graph":['
            . '{"@type":"WebSite","name":"X"},'
            . '{"@type":"Person","name":"Y","knowsAbout":["a"]}'
            . ']}'
            . '</script>'
            . '</head></html>';

        $metadata = $this->parser->parse($html, $this->baseUrl)->metadata;

        $this->assertEqualsCanonicalizing(['WebSite', 'Person'], $metadata->jsonLdTypes());
    }

    public function test_ignores_invalid_json_ld_blocks(): void
    {
        $html = '<html><head>'
            . '<script type="application/ld+json">not-actually-json</script>'
            . '</head></html>';

        $metadata = $this->parser->parse($html, $this->baseUrl)->metadata;

        $this->assertSame([], $metadata->jsonLdTypes());
        $this->assertFalse($metadata->hasStructuredData());
    }

    public function test_detects_microdata_when_itemscope_is_present(): void
    {
        $html = '<html><body>'
            . '<div itemscope itemtype="https://schema.org/Person">'
            . '<span itemprop="name">Mario</span>'
            . '</div>'
            . '</body></html>';

        $metadata = $this->parser->parse($html, $this->baseUrl)->metadata;

        $this->assertTrue($metadata->hasMicrodata());
        $this->assertTrue($metadata->hasStructuredData());
    }

    public function test_extracts_image_dimensions_from_attributes(): void
    {
        $html = '<html><body>'
            . '<img src="https://example.com/a.png" alt="a" width="800" height="600">'
            . '<img src="https://example.com/b.png" alt="b">'
            . '</body></html>';

        $links = $this->parser->parse($html, $this->baseUrl)->links;

        $byUrl = [];
        foreach ($links as $link) {
            $byUrl[$link->targetUrl()->toString()] = $link;
        }

        $this->assertSame(800, $byUrl['https://example.com/a.png']->width());
        $this->assertSame(600, $byUrl['https://example.com/a.png']->height());
        $this->assertNull($byUrl['https://example.com/b.png']->width());
        $this->assertNull($byUrl['https://example.com/b.png']->height());
    }

    public function test_ignores_non_numeric_image_dimensions(): void
    {
        $html = '<html><body>'
            . '<img src="https://example.com/a.png" alt="a" width="100%" height="auto">'
            . '</body></html>';

        $links = $this->parser->parse($html, $this->baseUrl)->links;

        $this->assertNull($links[0]->width());
        $this->assertNull($links[0]->height());
    }
}
