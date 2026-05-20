<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Domain\Model\Analysis\Signal;

final readonly class Link
{
    private const array RESOURCE_TYPES = ['image', 'script', 'stylesheet', 'iframe'];

    public function __construct(
        private string $targetUrl,
        private string $type,
        private ?string $anchorText,
        private string $relation,
        private bool $isInternal,
        private ?int $width = null,
        private ?int $height = null,
    ) {
    }

    public function targetUrl(): string
    {
        return $this->targetUrl;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function anchorText(): ?string
    {
        return $this->anchorText;
    }

    public function relation(): string
    {
        return $this->relation;
    }

    public function isInternal(): bool
    {
        return $this->isInternal;
    }

    public function isExternal(): bool
    {
        return !$this->isInternal;
    }

    public function isFollowable(): bool
    {
        return $this->relation === 'follow';
    }

    public function isAnchor(): bool
    {
        return $this->type === 'anchor';
    }

    public function isResource(): bool
    {
        return in_array($this->type, self::RESOURCE_TYPES, true);
    }

    public function width(): ?int
    {
        return $this->width;
    }

    public function height(): ?int
    {
        return $this->height;
    }

    public function hasDimensions(): bool
    {
        return $this->width !== null && $this->height !== null;
    }
}
