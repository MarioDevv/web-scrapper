<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Domain\Model\Analysis\Signal;

final readonly class Hreflang
{
    public function __construct(
        private string $language,
        private ?string $region,
        private string $href,
        private string $source,
    ) {
    }

    public function language(): string
    {
        return $this->language;
    }

    public function region(): ?string
    {
        return $this->region;
    }

    public function href(): string
    {
        return $this->href;
    }

    public function source(): string
    {
        return $this->source;
    }

    public function isXDefault(): bool
    {
        return strtolower($this->language) === 'x-default';
    }

    public function languageRegionCode(): string
    {
        if ($this->isXDefault()) {
            return 'x-default';
        }
        return $this->region !== null
            ? $this->language . '-' . $this->region
            : $this->language;
    }

    public function isValidLanguageCode(): bool
    {
        if ($this->isXDefault()) {
            return true;
        }
        return preg_match('/^[a-zA-Z]{2,3}$/', $this->language) === 1;
    }

    public function isValidRegionCode(): bool
    {
        if ($this->region === null) {
            return true;
        }
        return preg_match('/^[a-zA-Z]{2}$/', $this->region) === 1;
    }

    public function pointsTo(string $url): bool
    {
        return $this->href === $url;
    }
}
