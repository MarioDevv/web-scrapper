<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Domain\Model\AuditedPage;

final readonly class AuditScore
{
    private function __construct(private int $value)
    {
    }

    public static function fromPenalty(int $totalWeight): self
    {
        return new self(max(0, 100 - max(0, $totalWeight)));
    }

    public function value(): int
    {
        return $this->value;
    }
}
