<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Domain\Model\AuditedPage;

/**
 * The health score of an audited page: 0–100, derived from the set of
 * recorded issues and their rule weights. Always a function of the
 * current issues — never stored independently — so it cannot drift out
 * of sync with the findings (the invariant that binds Issue inside the
 * AuditedPage aggregate).
 */
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
