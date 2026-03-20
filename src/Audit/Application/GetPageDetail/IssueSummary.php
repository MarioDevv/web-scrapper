<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Application\GetPageDetail;

final readonly class IssueSummary
{
    public function __construct(
        public string $id,
        public string $category,
        public string $severity,
        public string $code,
        public string $message,
        public ?string $context,
    ) {
    }
}
