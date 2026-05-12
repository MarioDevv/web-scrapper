<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Audit;

use SeoSpider\Audit\Domain\Model\Page\Fingerprint;
use SeoSpider\Audit\Domain\Model\Page\Page;

/**
 * Compares two crawled-page graphs and emits a structured diff. Pure
 * domain function: no repository or persistence access.
 *
 * Pages are first matched by canonical URL. Pages without a URL match
 * are then matched by Fingerprint Hamming distance (≤ NEAR_DUPLICATE)
 * so a renamed/moved URL with similar content is reported as moved
 * rather than two unrelated added/removed entries.
 */
final class AuditDiffer
{
    // Stricter than Fingerprint::DEFAULT_NEAR_DUPLICATE_THRESHOLD (3): we have
    // the URL change as additional evidence of a move, so we tolerate more
    // simhash drift before declaring "this is the same page, just reformatted."
    private const int NEAR_DUPLICATE_THRESHOLD = 5;

    /**
     * @param Page[] $base
     * @param Page[] $target
     */
    public function diff(AuditId $baseId, AuditId $targetId, array $base, array $target): AuditDiff
    {
        /** @var array<string, Page> $baseByUrl */
        $baseByUrl = [];
        foreach ($base as $page) {
            $baseByUrl[$page->url()->toString()] = $page;
        }

        /** @var array<string, Page> $targetByUrl */
        $targetByUrl = [];
        foreach ($target as $page) {
            $targetByUrl[$page->url()->toString()] = $page;
        }

        $unchanged = [];
        $remainingBase = $baseByUrl;
        $remainingTarget = $targetByUrl;

        foreach ($targetByUrl as $url => $targetPage) {
            if (isset($baseByUrl[$url])) {
                $unchanged[] = $this->buildPageChange(
                    url: $url,
                    kind: PageMatchKind::BY_URL,
                    movedFromUrl: null,
                    basePage: $baseByUrl[$url],
                    targetPage: $targetPage,
                );
                unset($remainingBase[$url], $remainingTarget[$url]);
            }
        }

        $moved = [];
        foreach ($remainingTarget as $url => $targetPage) {
            $targetFp = $targetPage->fingerprint();
            if ($targetFp === null) {
                continue; // no fingerprint → falls through to pagesAdded below
            }

            $matchUrl = $this->findFingerprintMatch($targetFp, $remainingBase);
            if ($matchUrl === null) {
                continue; // no close base candidate → falls through to pagesAdded below
            }

            $moved[] = $this->buildPageChange(
                url: $url,
                kind: PageMatchKind::BY_FINGERPRINT,
                movedFromUrl: $matchUrl,
                basePage: $remainingBase[$matchUrl],
                targetPage: $targetPage,
            );
            unset($remainingBase[$matchUrl], $remainingTarget[$url]);
        }

        $added = [];
        foreach ($remainingTarget as $url => $targetPage) {
            $added[] = new PageChange(
                url: $url,
                kind: PageMatchKind::ADDED,
                movedFromUrl: null,
                addedIssueCodes: $this->codes($targetPage),
                removedIssueCodes: [],
                persistentIssueCodes: [],
            );
        }

        $removed = [];
        foreach ($remainingBase as $url => $basePage) {
            $removed[] = new PageChange(
                url: $url,
                kind: PageMatchKind::REMOVED,
                movedFromUrl: null,
                addedIssueCodes: [],
                removedIssueCodes: $this->codes($basePage),
                persistentIssueCodes: [],
            );
        }

        return new AuditDiff(
            baseAuditId: $baseId,
            targetAuditId: $targetId,
            pagesAdded: $added,
            pagesRemoved: $removed,
            pagesMoved: $moved,
            pagesUnchanged: $unchanged,
        );
    }

    private function buildPageChange(
        string $url,
        PageMatchKind $kind,
        ?string $movedFromUrl,
        Page $basePage,
        Page $targetPage,
    ): PageChange {
        $baseCodes = $this->codes($basePage);
        $targetCodes = $this->codes($targetPage);

        return new PageChange(
            url: $url,
            kind: $kind,
            movedFromUrl: $movedFromUrl,
            addedIssueCodes: array_values(array_unique(array_diff($targetCodes, $baseCodes))),
            removedIssueCodes: array_values(array_unique(array_diff($baseCodes, $targetCodes))),
            persistentIssueCodes: array_values(array_unique(array_intersect($baseCodes, $targetCodes))),
        );
    }

    /**
     * @param array<string, Page> $candidates
     */
    private function findFingerprintMatch(Fingerprint $needle, array $candidates): ?string
    {
        $bestUrl = null;
        $bestDistance = self::NEAR_DUPLICATE_THRESHOLD + 1;

        foreach ($candidates as $url => $page) {
            $candidateFp = $page->fingerprint();
            if ($candidateFp === null) {
                continue;
            }

            $distance = $needle->hammingDistance($candidateFp);
            if ($distance <= self::NEAR_DUPLICATE_THRESHOLD && $distance < $bestDistance) {
                $bestDistance = $distance;
                $bestUrl = $url;
            }
        }

        return $bestUrl;
    }

    /** @return string[] */
    private function codes(Page $page): array
    {
        return array_map(
            static fn($issue) => $issue->code(),
            $page->issues(),
        );
    }
}
