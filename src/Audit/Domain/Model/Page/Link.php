<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Page;

use SeoSpider\Audit\Domain\Model\Url;

final readonly class Link
{
    public function __construct(
        private Url $targetUrl,
        private LinkType $type,
        private ?string $anchorText,
        private LinkRelation $relation,
        private bool $isInternal,
    ) {
    }

    public function targetUrl(): Url
    {
        return $this->targetUrl;
    }

    public function type(): LinkType
    {
        return $this->type;
    }

    public function anchorText(): ?string
    {
        return $this->anchorText;
    }

    public function relation(): LinkRelation
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
        return $this->relation === LinkRelation::FOLLOW;
    }

    public function isAnchor(): bool
    {
        return $this->type === LinkType::ANCHOR;
    }
}
