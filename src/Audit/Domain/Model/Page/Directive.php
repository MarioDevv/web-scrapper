<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Page;

use SeoSpider\Audit\Domain\Model\Url;

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
        private ?Url $canonical = null,
        private ?DirectiveSource $source = null,
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

    public function canonical(): ?Url
    {
        return $this->canonical;
    }

    public function hasCanonical(): bool
    {
        return $this->canonical !== null;
    }

    public function isSelfCanonical(Url $pageUrl): bool
    {
        return $this->canonical !== null && $this->canonical->equals($pageUrl);
    }

    public function source(): ?DirectiveSource
    {
        return $this->source;
    }

    public static function merge(self ...$directives): self
    {
        $noindex = false;
        $nofollow = false;
        $noarchive = false;
        $nosnippet = false;
        $noimageindex = false;
        $maxSnippet = null;
        $maxImagePreview = null;
        $maxVideoPreview = null;
        $canonical = null;

        foreach ($directives as $directive) {
            $noindex = $noindex || $directive->noindex;
            $nofollow = $nofollow || $directive->nofollow;
            $noarchive = $noarchive || $directive->noarchive;
            $nosnippet = $nosnippet || $directive->nosnippet;
            $noimageindex = $noimageindex || $directive->noimageindex;

            if ($directive->maxSnippet !== null) {
                $maxSnippet = $maxSnippet === null
                    ? $directive->maxSnippet
                    : min($maxSnippet, $directive->maxSnippet);
            }

            if ($directive->maxImagePreview !== null) {
                $maxImagePreview ??= $directive->maxImagePreview;
            }

            if ($directive->maxVideoPreview !== null) {
                $maxVideoPreview = $maxVideoPreview === null
                    ? $directive->maxVideoPreview
                    : min($maxVideoPreview, $directive->maxVideoPreview);
            }

            if ($directive->canonical !== null) {
                $canonical ??= $directive->canonical;
            }
        }

        return new self(
            noindex: $noindex,
            nofollow: $nofollow,
            noarchive: $noarchive,
            nosnippet: $nosnippet,
            noimageindex: $noimageindex,
            maxSnippet: $maxSnippet,
            maxImagePreview: $maxImagePreview,
            maxVideoPreview: $maxVideoPreview,
            canonical: $canonical,
            source: null,
        );
    }
}
