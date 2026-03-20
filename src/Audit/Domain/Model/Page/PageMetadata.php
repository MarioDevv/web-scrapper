<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Page;

final readonly class PageMetadata
{
    private const int TITLE_MAX_LENGTH = 60;
    private const int TITLE_MIN_LENGTH = 30;
    private const int META_DESCRIPTION_MAX_LENGTH = 160;
    private const int META_DESCRIPTION_MIN_LENGTH = 70;

    /**
     * @param string[] $h1s
     * @param string[] $h2s
     * @param array<array{level: int, text: string}> $headingHierarchy
     */
    public function __construct(
        private ?string $title,
        private ?string $metaDescription,
        private array $h1s,
        private array $h2s,
        private array $headingHierarchy,
        private ?string $charset,
        private ?string $viewport,
        private ?string $ogTitle,
        private ?string $ogDescription,
        private ?string $ogImage,
        private int $wordCount,
        private ?string $lang,
    ) {
    }

    public function title(): ?string
    {
        return $this->title;
    }

    public function titleLength(): int
    {
        return $this->title !== null ? mb_strlen($this->title) : 0;
    }

    public function hasTitle(): bool
    {
        return $this->title !== null && trim($this->title) !== '';
    }

    public function isTitleTooLong(): bool
    {
        return $this->titleLength() > self::TITLE_MAX_LENGTH;
    }

    public function isTitleTooShort(): bool
    {
        return $this->hasTitle() && $this->titleLength() < self::TITLE_MIN_LENGTH;
    }

    public function metaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function metaDescriptionLength(): int
    {
        return $this->metaDescription !== null ? mb_strlen($this->metaDescription) : 0;
    }

    public function hasMetaDescription(): bool
    {
        return $this->metaDescription !== null && trim($this->metaDescription) !== '';
    }

    public function isMetaDescriptionTooLong(): bool
    {
        return $this->metaDescriptionLength() > self::META_DESCRIPTION_MAX_LENGTH;
    }

    public function isMetaDescriptionTooShort(): bool
    {
        return $this->hasMetaDescription() && $this->metaDescriptionLength() < self::META_DESCRIPTION_MIN_LENGTH;
    }

    /**
     * @return string[]
     */
    public function h1s(): array
    {
        return $this->h1s;
    }

    public function h1Count(): int
    {
        return count($this->h1s);
    }

    public function hasNoH1(): bool
    {
        return $this->h1Count() === 0;
    }

    public function hasMultipleH1s(): bool
    {
        return $this->h1Count() > 1;
    }

    /**
     * @return string[]
     */
    public function h2s(): array
    {
        return $this->h2s;
    }

    /**
     * @return array<array{level: int, text: string}>
     */
    public function headingHierarchy(): array
    {
        return $this->headingHierarchy;
    }

    public function charset(): ?string
    {
        return $this->charset;
    }

    public function hasViewport(): bool
    {
        return $this->viewport !== null && trim($this->viewport) !== '';
    }

    public function viewport(): ?string
    {
        return $this->viewport;
    }

    public function ogTitle(): ?string
    {
        return $this->ogTitle;
    }

    public function ogDescription(): ?string
    {
        return $this->ogDescription;
    }

    public function ogImage(): ?string
    {
        return $this->ogImage;
    }

    public function wordCount(): int
    {
        return $this->wordCount;
    }

    public function lang(): ?string
    {
        return $this->lang;
    }
}
