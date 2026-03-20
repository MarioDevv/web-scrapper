<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Domain\Model\Page;

use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Domain\Model\Page\Fingerprint;

final class FingerprintTest extends TestCase
{
    public function test_exact_duplicates_have_same_hash(): void
    {
        $content = 'This is some page content about SEO and web crawling.';

        $a = Fingerprint::fromContent($content);
        $b = Fingerprint::fromContent($content);

        $this->assertTrue($a->isExactDuplicateOf($b));
        $this->assertSame(0, $a->hammingDistance($b));
    }

    public function test_different_content_produces_different_exact_hash(): void
    {
        $a = Fingerprint::fromContent('First page about cats.');
        $b = Fingerprint::fromContent('Second page about dogs.');

        $this->assertFalse($a->isExactDuplicateOf($b));
    }

    public function test_similar_content_has_small_hamming_distance(): void
    {
        $base = 'PHP is a popular programming language for web development and server side scripting';
        $variant = 'PHP is a popular programming language for web applications and server side coding';

        $a = Fingerprint::fromContent($base);
        $b = Fingerprint::fromContent($variant);

        $this->assertFalse($a->isExactDuplicateOf($b));
        $this->assertLessThan(20, $a->hammingDistance($b));
    }

    public function test_completely_different_content_has_large_hamming_distance(): void
    {
        $a = Fingerprint::fromContent(str_repeat('alpha beta gamma delta ', 50));
        $b = Fingerprint::fromContent(str_repeat('one two three four five ', 50));

        $this->assertGreaterThan(5, $a->hammingDistance($b));
    }

    public function test_near_duplicate_with_default_threshold(): void
    {
        $content = 'Exactly the same content for near duplicate testing purposes here';

        $a = Fingerprint::fromContent($content);
        $b = Fingerprint::fromContent($content);

        $this->assertTrue($a->isNearDuplicateOf($b));
    }

    public function test_near_duplicate_with_custom_threshold(): void
    {
        $a = Fingerprint::fromContent('Some shared words about testing and code quality');
        $b = Fingerprint::fromContent('Some shared words about testing and code review');

        $distance = $a->hammingDistance($b);

        $this->assertSame($distance <= 10, $a->isNearDuplicateOf($b, threshold: 10));
    }

    public function test_from_content_produces_sha256_exact_hash(): void
    {
        $content = 'test content';
        $fingerprint = Fingerprint::fromContent($content);

        $this->assertSame(hash('sha256', $content), $fingerprint->exactHash());
    }

    public function test_hamming_distance_is_symmetric(): void
    {
        $a = Fingerprint::fromContent('Content about apples and oranges');
        $b = Fingerprint::fromContent('Content about bananas and grapes');

        $this->assertSame($a->hammingDistance($b), $b->hammingDistance($a));
    }
}
