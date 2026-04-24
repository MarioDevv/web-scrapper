<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Domain\Model;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Domain\Model\Url;
use SeoSpider\Audit\Domain\Model\UrlCanonicalizer;

final class UrlCanonicalizerTest extends TestCase
{
    private UrlCanonicalizer $canonicalizer;

    protected function setUp(): void
    {
        $this->canonicalizer = new UrlCanonicalizer();
    }

    /** @return iterable<string, array{string, string}> */
    public static function canonicalizationCases(): iterable
    {
        yield 'lowercases scheme and host' => [
            'HTTPS://Example.COM/Path',
            'https://example.com/Path',
        ];

        yield 'strips default https port' => [
            'https://example.com:443/path',
            'https://example.com/path',
        ];

        yield 'strips default http port' => [
            'http://example.com:80/path',
            'http://example.com/path',
        ];

        yield 'keeps non-default port' => [
            'https://example.com:8443/path',
            'https://example.com:8443/path',
        ];

        yield 'collapses empty path to /' => [
            'https://example.com',
            'https://example.com/',
        ];

        yield 'strips fragment' => [
            'https://example.com/page#section',
            'https://example.com/page',
        ];

        yield 'strips utm parameters' => [
            'https://example.com/page?utm_source=x&utm_medium=y&real=1',
            'https://example.com/page?real=1',
        ];

        yield 'strips fbclid' => [
            'https://example.com/page?fbclid=abc',
            'https://example.com/page',
        ];

        yield 'strips gclid and dclid but keeps genuine params' => [
            'https://example.com/page?gclid=x&dclid=y&q=shoes',
            'https://example.com/page?q=shoes',
        ];

        yield 'strips tracking regardless of key case' => [
            'https://example.com/page?UTM_SOURCE=x&FbClId=y',
            'https://example.com/page',
        ];

        yield 'keeps path-encoded characters as the rfc lib canonicalizes them' => [
            'https://example.com/%7Euser',
            'https://example.com/~user',
        ];

        yield 'resolves dot segments' => [
            'https://example.com/a/./b/../c',
            'https://example.com/a/c',
        ];
    }

    #[DataProvider('canonicalizationCases')]
    public function test_canonicalize(string $input, string $expected): void
    {
        $this->assertSame(
            $expected,
            $this->canonicalizer->canonicalize(Url::fromString($input))->toString(),
        );
    }

    public function test_canonicalize_is_idempotent(): void
    {
        $once = $this->canonicalizer->canonicalize(
            Url::fromString('https://Example.com:443/a?utm_source=x&b=1#frag'),
        );
        $twice = $this->canonicalizer->canonicalize($once);

        $this->assertSame($once->toString(), $twice->toString());
    }

    public function test_policy_can_be_customized_via_constructor(): void
    {
        $canonicalizer = new UrlCanonicalizer(
            trackingParams: ['ref'],
            trackingParamPrefixes: [],
        );

        $input = Url::fromString('https://example.com/page?ref=x&utm_source=y');

        $this->assertSame(
            'https://example.com/page?utm_source=y',
            $canonicalizer->canonicalize($input)->toString(),
        );
    }
}
