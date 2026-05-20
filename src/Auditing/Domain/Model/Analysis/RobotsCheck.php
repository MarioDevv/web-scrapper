<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Domain\Model\Analysis;

interface RobotsCheck
{
    public function load(string $seedUrl): void;

    public function isAllowed(string $url): bool;
}
