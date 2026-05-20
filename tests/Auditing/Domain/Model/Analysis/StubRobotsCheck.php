<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Auditing\Domain\Model\Analysis;

use SeoSpider\Auditing\Domain\Model\Analysis\RobotsCheck;

final class StubRobotsCheck implements RobotsCheck
{
    /** @var array<string, true> */
    private array $disallowed = [];

    public function disallow(string $url): void
    {
        $this->disallowed[$url] = true;
    }

    public function load(string $seedUrl): void
    {
    }

    public function isAllowed(string $url): bool
    {
        return !isset($this->disallowed[$url]);
    }
}
