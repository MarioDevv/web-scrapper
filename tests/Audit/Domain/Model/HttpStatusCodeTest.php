<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Domain\Model;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Domain\Model\HttpStatusCode;

final class HttpStatusCodeTest extends TestCase
{
    public function test_rejects_code_below_100(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new HttpStatusCode(99);
    }

    public function test_rejects_code_above_599(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new HttpStatusCode(600);
    }

    public function test_200_is_successful(): void
    {
        $code = new HttpStatusCode(200);

        $this->assertTrue($code->isSuccessful());
        $this->assertFalse($code->isRedirect());
        $this->assertFalse($code->isClientError());
        $this->assertFalse($code->isServerError());
        $this->assertFalse($code->isBroken());
    }

    public function test_301_is_permanent_redirect(): void
    {
        $code = new HttpStatusCode(301);

        $this->assertTrue($code->isRedirect());
        $this->assertTrue($code->isPermanentRedirect());
        $this->assertFalse($code->isTemporaryRedirect());
    }

    public function test_302_is_temporary_redirect(): void
    {
        $code = new HttpStatusCode(302);

        $this->assertTrue($code->isRedirect());
        $this->assertTrue($code->isTemporaryRedirect());
        $this->assertFalse($code->isPermanentRedirect());
    }

    public function test_308_is_permanent_redirect(): void
    {
        $code = new HttpStatusCode(308);

        $this->assertTrue($code->isPermanentRedirect());
    }

    public function test_404_is_client_error_and_broken(): void
    {
        $code = new HttpStatusCode(404);

        $this->assertTrue($code->isClientError());
        $this->assertTrue($code->isBroken());
        $this->assertFalse($code->isServerError());
    }

    public function test_500_is_server_error_and_broken(): void
    {
        $code = new HttpStatusCode(500);

        $this->assertTrue($code->isServerError());
        $this->assertTrue($code->isBroken());
        $this->assertFalse($code->isClientError());
    }

    public function test_410_signals_intentional_removal(): void
    {
        $code = new HttpStatusCode(410);

        $this->assertTrue($code->signalsIntentionalRemoval());
        $this->assertTrue($code->isBroken());
    }

    public function test_404_does_not_signal_intentional_removal(): void
    {
        $code = new HttpStatusCode(404);

        $this->assertFalse($code->signalsIntentionalRemoval());
    }

    public function test_429_is_rate_limited(): void
    {
        $code = new HttpStatusCode(429);

        $this->assertTrue($code->isRateLimited());
    }

    public function test_equals(): void
    {
        $a = new HttpStatusCode(200);
        $b = new HttpStatusCode(200);
        $c = new HttpStatusCode(301);

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }

    public function test_to_string(): void
    {
        $code = new HttpStatusCode(404);

        $this->assertSame('404', (string) $code);
    }
}
