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

        $directive = $this->parser->extractDirectives($html, $this->baseUrl);

        $this->assertNotNull($directive->canonical());
        $this->assertSame('https://example.com/canonical', $directive->canonical()->toString());
    }

    public function test_resolves_root_relative_canonical_against_base_url(): void
    {
        $html = '<html><head><link rel="canonical" href="/es/foo"></head></html>';

        $directive = $this->parser->extractDirectives($html, $this->baseUrl);

        $this->assertNotNull($directive->canonical());
        $this->assertSame('https://example.com/es/foo', $directive->canonical()->toString());
    }

    public function test_resolves_path_relative_canonical_against_base_url(): void
    {
        $html = '<html><head><link rel="canonical" href="other"></head></html>';

        $directive = $this->parser->extractDirectives($html, $this->baseUrl);

        $this->assertNotNull($directive->canonical());
        $this->assertSame('https://example.com/es/other', $directive->canonical()->toString());
    }

    public function test_missing_canonical_returns_null(): void
    {
        $html = '<html><head><title>no canonical</title></head></html>';

        $directive = $this->parser->extractDirectives($html, $this->baseUrl);

        $this->assertNull($directive->canonical());
    }

    public function test_empty_canonical_href_returns_null(): void
    {
        $html = '<html><head><link rel="canonical" href="   "></head></html>';

        $directive = $this->parser->extractDirectives($html, $this->baseUrl);

        $this->assertNull($directive->canonical());
    }

    public function test_extracts_absolute_hreflang_unchanged(): void
    {
        $html = '<html><head>'
            . '<link rel="alternate" hreflang="en" href="https://example.com/en/page">'
            . '</head></html>';

        $hreflangs = $this->parser->extractHreflangs($html, $this->baseUrl);

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

        $hreflangs = $this->parser->extractHreflangs($html, $this->baseUrl);

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

        $hreflangs = $this->parser->extractHreflangs($html, $this->baseUrl);

        $this->assertSame([], $hreflangs);
    }
}
