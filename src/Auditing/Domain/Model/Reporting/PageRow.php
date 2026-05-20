<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Domain\Model\Reporting;

use SeoSpider\Auditing\Domain\Model\Analysis\Signal\Fingerprint;

final readonly class PageRow
{
    public function __construct(
        public string $url,
        public ?Fingerprint $fingerprint,
    ) {
    }
}
