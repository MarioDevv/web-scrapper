<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Audit;
use SeoSpider\Auditing\Domain\Model\Issue\Issue;

final readonly class PageChange
{
    /**
     * @param ?string  $movedFromUrl          Previous URL of this page on the base side; non-null only when `kind` is `BY_FINGERPRINT`.
     * @param string[] $addedIssueCodes       Issue codes present only in the target audit's page.
     * @param string[] $removedIssueCodes     Issue codes present only in the base audit's page.
     * @param string[] $persistentIssueCodes  Issue codes present on both sides for this page.
     */
    public function __construct(
        public string $url,
        public PageMatchKind $kind,
        public ?string $movedFromUrl,
        public array $addedIssueCodes,
        public array $removedIssueCodes,
        public array $persistentIssueCodes,
    ) {
    }
}
