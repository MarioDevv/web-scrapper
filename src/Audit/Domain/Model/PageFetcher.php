<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model;

use SeoSpider\Audit\Domain\Model\Page\FetchOutcome;

interface PageFetcher
{
    /**
     * Fetches every URL in the batch in parallel and returns a map keyed by
     * the original URL string. Implementations should preserve each URL's
     * redirect chain and surface transport errors as FetchOutcome::failure()
     * rather than throwing, so a single bad URL does not abort the batch.
     *
     * @param Url[] $urls
     * @return array<string, FetchOutcome>
     */
    public function fetchBatch(array $urls, ?string $userAgent = null): array;
}
