<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Domain\Model\Analysis\Signal;

final readonly class Fingerprint
{
    private const int DEFAULT_NEAR_DUPLICATE_THRESHOLD = 3;

    public function __construct(
        private string $exactHash,
        private int $simHash,
    ) {
    }

    public function exactHash(): string
    {
        return $this->exactHash;
    }

    public function simHash(): int
    {
        return $this->simHash;
    }

    public function isExactDuplicateOf(self $other): bool
    {
        return $this->exactHash === $other->exactHash;
    }

    public function isNearDuplicateOf(self $other, int $threshold = self::DEFAULT_NEAR_DUPLICATE_THRESHOLD): bool
    {
        return $this->hammingDistance($other) <= $threshold;
    }

    public function hammingDistance(self $other): int
    {
        $xor = $this->simHash ^ $other->simHash;
        $distance = 0;
        while ($xor !== 0) {
            $distance += $xor & 1;
            $xor = ($xor >> 1) & PHP_INT_MAX;
        }
        return $distance;
    }
}
