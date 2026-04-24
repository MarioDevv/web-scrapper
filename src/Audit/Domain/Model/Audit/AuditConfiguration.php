<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Audit;

use InvalidArgumentException;
use SeoSpider\Audit\Domain\Model\Url;

final readonly class AuditConfiguration
{
    /**
     * @param string[] $excludePatterns
     * @param string[] $includePatterns
     */
    public function __construct(
        public Url $seedUrl,
        public int $maxPages = 500,
        public int $maxDepth = 10,
        public int $concurrency = 5,
        public float $requestDelay = 0.25,
        public bool $respectRobotsTxt = true,
        public ?string $customUserAgent = null,
        public array $excludePatterns = [],
        public array $includePatterns = [],
        public bool $followExternalLinks = false,
        public bool $crawlSubdomains = false,
        public bool $crawlResources = false,
        public bool $ingestSitemaps = true,
    ) {
        if ($this->maxPages < 1) {
            throw new InvalidArgumentException('maxPages must be at least 1.');
        }

        if ($this->maxDepth < 1) {
            throw new InvalidArgumentException('maxDepth must be at least 1.');
        }

        if ($this->concurrency < 1 || $this->concurrency > 20) {
            throw new InvalidArgumentException('concurrency must be between 1 and 20.');
        }

        if ($this->requestDelay < 0) {
            throw new InvalidArgumentException('requestDelay cannot be negative.');
        }
    }
}
