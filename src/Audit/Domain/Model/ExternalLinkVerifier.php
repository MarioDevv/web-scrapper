<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model;

use SeoSpider\Audit\Domain\Model\Audit\AuditId;

/**
 * Post-crawl phase: walks every external anchor link that the crawl recorded,
 * probes its HTTP status once per unique URL (regardless of how many source
 * pages reference it) and writes the result for every (page, url) pair so
 * broken-link reports can attribute each failure to the pages that caused it.
 *
 * Separated from the crawl hot path so a page with N external links no longer
 * delays the next page by N request round-trips.
 */
interface ExternalLinkVerifier
{
    /** Returns the number of unique external URLs probed. */
    public function verify(AuditId $auditId, ?string $userAgent = null): int;
}
