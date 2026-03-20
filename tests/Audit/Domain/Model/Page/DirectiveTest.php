<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Domain\Model\Page;

use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Domain\Model\Page\Directive;
use SeoSpider\Audit\Domain\Model\Page\DirectiveSource;
use SeoSpider\Audit\Domain\Model\Url;

final class DirectiveTest extends TestCase
{
    public function test_default_is_indexable_and_followable(): void
    {
        $directive = new Directive();

        $this->assertTrue($directive->isIndexable());
        $this->assertTrue($directive->isFollowable());
        $this->assertFalse($directive->hasCanonical());
    }

    public function test_noindex_is_not_indexable(): void
    {
        $directive = new Directive(noindex: true);

        $this->assertFalse($directive->isIndexable());
    }

    public function test_nofollow_is_not_followable(): void
    {
        $directive = new Directive(nofollow: true);

        $this->assertFalse($directive->isFollowable());
    }

    public function test_self_canonical(): void
    {
        $url = Url::fromString('https://example.com/page');
        $directive = new Directive(canonical: $url);

        $this->assertTrue($directive->hasCanonical());
        $this->assertTrue($directive->isSelfCanonical($url));
    }

    public function test_non_self_canonical(): void
    {
        $directive = new Directive(
            canonical: Url::fromString('https://example.com/other'),
        );

        $this->assertFalse($directive->isSelfCanonical(
            Url::fromString('https://example.com/page'),
        ));
    }

    // ─── Merge ─────────────────────────────────────────────────

    public function test_merge_most_restrictive_wins(): void
    {
        $meta = new Directive(
            noindex: false,
            nofollow: true,
            source: DirectiveSource::META_TAG,
        );

        $header = new Directive(
            noindex: true,
            nofollow: false,
            source: DirectiveSource::HTTP_HEADER,
        );

        $merged = Directive::merge($meta, $header);

        $this->assertTrue($merged->noindex());
        $this->assertTrue($merged->nofollow());
    }

    public function test_merge_keeps_first_canonical(): void
    {
        $meta = new Directive(
            canonical: Url::fromString('https://example.com/from-meta'),
        );

        $header = new Directive(
            canonical: Url::fromString('https://example.com/from-header'),
        );

        $merged = Directive::merge($meta, $header);

        $this->assertSame(
            'https://example.com/from-meta',
            $merged->canonical()->toString(),
        );
    }

    public function test_merge_smallest_max_snippet_wins(): void
    {
        $a = new Directive(maxSnippet: 150);
        $b = new Directive(maxSnippet: 50);

        $merged = Directive::merge($a, $b);

        $this->assertSame(50, $merged->maxSnippet());
    }

    public function test_merge_with_no_directives_returns_defaults(): void
    {
        $merged = Directive::merge();

        $this->assertTrue($merged->isIndexable());
        $this->assertTrue($merged->isFollowable());
        $this->assertFalse($merged->hasCanonical());
    }
}
