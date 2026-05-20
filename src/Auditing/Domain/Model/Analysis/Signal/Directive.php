<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Domain\Model\Analysis\Signal;

final readonly class Directive
{
    public function __construct(
        private bool $noindex = false,
        private bool $nofollow = false,
        private bool $noarchive = false,
        private bool $nosnippet = false,
        private bool $noimageindex = false,
        private ?int $maxSnippet = null,
        private ?string $maxImagePreview = null,
        private ?int $maxVideoPreview = null,
        private ?string $canonical = null,
        private ?string $source = null,
    ) {
    }

    public function isIndexable(): bool
    {
        return !$this->noindex;
    }

    public function isFollowable(): bool
    {
        return !$this->nofollow;
    }

    public function noindex(): bool
    {
        return $this->noindex;
    }

    public function nofollow(): bool
    {
        return $this->nofollow;
    }

    public function noarchive(): bool
    {
        return $this->noarchive;
    }

    public function nosnippet(): bool
    {
        return $this->nosnippet;
    }

    public function noimageindex(): bool
    {
        return $this->noimageindex;
    }

    public function maxSnippet(): ?int
    {
        return $this->maxSnippet;
    }

    public function maxImagePreview(): ?string
    {
        return $this->maxImagePreview;
    }

    public function maxVideoPreview(): ?int
    {
        return $this->maxVideoPreview;
    }

    public function canonical(): ?string
    {
        return $this->canonical;
    }

    public function hasCanonical(): bool
    {
        return $this->canonical !== null;
    }

    public function isSelfCanonical(string $pageUrl): bool
    {
        return $this->canonical !== null && $this->canonical === $pageUrl;
    }

    public function source(): ?string
    {
        return $this->source;
    }
}
