<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Page;

/**
 * Static metadata for an issue code: the ubiquitous prose used to
 * communicate a finding to the user. Analyzers still emit instances
 * of {@see Issue} with interpolated numbers/URLs; this VO provides
 * the invariant human-facing copy (title, rationale, remediation)
 * and an optional link to an authoritative source.
 */
final readonly class IssueRule
{
    /**
     * Default weight per severity, used when the rule does not provide
     * an explicit override. Calibrated against the relative impact of a
     * typical issue at each level on overall search performance.
     */
    private const array DEFAULT_WEIGHT_BY_SEVERITY = [
        'error' => 10,
        'warning' => 5,
        'notice' => 2,
        'info' => 0,
    ];

    public function __construct(
        public string $code,
        public IssueCategory $category,
        public IssueSeverity $severity,
        public string $title,
        public string $summary,
        public string $why,
        public string $how,
        public ?string $source = null,
        private ?int $weightOverride = null,
    ) {
    }

    /**
     * Weight in [0, 10] used by the audit scoring formula. Returns the
     * explicit override when set, falling back to the severity default.
     */
    public function weight(): int
    {
        if ($this->weightOverride !== null) {
            return $this->weightOverride;
        }

        return self::DEFAULT_WEIGHT_BY_SEVERITY[$this->severity->value];
    }
}
