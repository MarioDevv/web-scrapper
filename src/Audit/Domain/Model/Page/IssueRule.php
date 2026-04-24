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
    public function __construct(
        public string $code,
        public IssueCategory $category,
        public IssueSeverity $severity,
        public string $title,
        public string $summary,
        public string $why,
        public string $how,
        public ?string $source = null,
    ) {
    }
}
