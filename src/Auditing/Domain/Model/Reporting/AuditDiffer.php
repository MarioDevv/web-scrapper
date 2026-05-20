<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Domain\Model\Reporting;

use SeoSpider\Auditing\Domain\Model\Audit\AuditId;
use SeoSpider\Auditing\Domain\Model\Analysis\Signal\Fingerprint;

final class AuditDiffer
{
    // Stricter than Fingerprint::DEFAULT_NEAR_DUPLICATE_THRESHOLD (3): we have
    // the URL change as additional evidence of a move, so we tolerate more
    // simhash drift before declaring "this is the same page, just reformatted."
    private const int NEAR_DUPLICATE_THRESHOLD = 5;

    /**
     * @param PageRow[]               $base
     * @param PageRow[]               $target
     * @param array<string, string[]> $baseCodesByUrl
     * @param array<string, string[]> $targetCodesByUrl
     */
    public function diff(
        AuditId $baseId,
        AuditId $targetId,
        array $base,
        array $target,
        array $baseCodesByUrl = [],
        array $targetCodesByUrl = [],
    ): AuditDiff {
        $baseByUrl = [];
        foreach ($base as $row) {
            $baseByUrl[$row->url] = $row;
        }

        $targetByUrl = [];
        foreach ($target as $row) {
            $targetByUrl[$row->url] = $row;
        }

        $unchanged = [];
        $remainingBase = $baseByUrl;
        $remainingTarget = $targetByUrl;

        foreach ($targetByUrl as $url => $row) {
            if (isset($baseByUrl[$url])) {
                $unchanged[] = $this->buildPageChange(
                    url: $url,
                    kind: PageMatchKind::BY_URL,
                    movedFromUrl: null,
                    baseCodes: $baseCodesByUrl[$url] ?? [],
                    targetCodes: $targetCodesByUrl[$url] ?? [],
                );
                unset($remainingBase[$url], $remainingTarget[$url]);
            }
        }

        $moved = [];
        foreach ($remainingTarget as $url => $row) {
            if ($row->fingerprint === null) {
                continue;
            }

            $matchUrl = $this->findFingerprintMatch($row->fingerprint, $remainingBase);
            if ($matchUrl === null) {
                continue;
            }

            $moved[] = $this->buildPageChange(
                url: $url,
                kind: PageMatchKind::BY_FINGERPRINT,
                movedFromUrl: $matchUrl,
                baseCodes: $baseCodesByUrl[$matchUrl] ?? [],
                targetCodes: $targetCodesByUrl[$url] ?? [],
            );
            unset($remainingBase[$matchUrl], $remainingTarget[$url]);
        }

        $added = [];
        foreach ($remainingTarget as $url => $row) {
            $added[] = new PageChange(
                url: $url,
                kind: PageMatchKind::ADDED,
                movedFromUrl: null,
                addedIssueCodes: $targetCodesByUrl[$url] ?? [],
                removedIssueCodes: [],
                persistentIssueCodes: [],
            );
        }

        $removed = [];
        foreach ($remainingBase as $url => $row) {
            $removed[] = new PageChange(
                url: $url,
                kind: PageMatchKind::REMOVED,
                movedFromUrl: null,
                addedIssueCodes: [],
                removedIssueCodes: $baseCodesByUrl[$url] ?? [],
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

    /**
     * @param string[] $baseCodes
     * @param string[] $targetCodes
     */
    private function buildPageChange(
        string $url,
        PageMatchKind $kind,
        ?string $movedFromUrl,
        array $baseCodes,
        array $targetCodes,
    ): PageChange {
        return new PageChange(
            url: $url,
            kind: $kind,
            movedFromUrl: $movedFromUrl,
            addedIssueCodes: array_values(array_unique(array_diff($targetCodes, $baseCodes))),
            removedIssueCodes: array_values(array_unique(array_diff($baseCodes, $targetCodes))),
            persistentIssueCodes: array_values(array_unique(array_intersect($baseCodes, $targetCodes))),
        );
    }

    /** @param array<string, PageRow> $candidates */
    private function findFingerprintMatch(Fingerprint $needle, array $candidates): ?string
    {
        $bestUrl = null;
        $bestDistance = self::NEAR_DUPLICATE_THRESHOLD + 1;

        foreach ($candidates as $url => $row) {
            if ($row->fingerprint === null) {
                continue;
            }

            $distance = $needle->hammingDistance($row->fingerprint);
            if ($distance <= self::NEAR_DUPLICATE_THRESHOLD && $distance < $bestDistance) {
                $bestDistance = $distance;
                $bestUrl = $url;
            }
        }

        return $bestUrl;
    }
}
