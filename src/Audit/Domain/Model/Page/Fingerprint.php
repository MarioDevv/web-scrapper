<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Page;

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
        return substr_count(
            decbin($this->simHash ^ $other->simHash),
            '1',
        );
    }

    public static function fromContent(string $cleanContent): self
    {
        return new self(
            exactHash: hash('sha256', $cleanContent),
            simHash: self::computeSimHash($cleanContent),
        );
    }

    private static function computeSimHash(string $text): int
    {
        $vector = array_fill(0, 64, 0);
        $words = preg_split('/\s+/', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($words as $word) {
            $hash = crc32($word);

            for ($i = 0; $i < 64; $i++) {
                $bit = ($hash >> ($i % 32)) & 1;
                $vector[$i] += $bit === 1 ? 1 : -1;
            }
        }

        $simHash = 0;
        for ($i = 0; $i < 64; $i++) {
            if ($vector[$i] > 0) {
                $simHash |= (1 << $i);
            }
        }

        return $simHash;
    }
}
