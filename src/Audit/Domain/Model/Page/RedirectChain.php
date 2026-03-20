<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Page;

use SeoSpider\Audit\Domain\Model\Url;

final readonly class RedirectChain
{
    /** @param RedirectHop[] $hops */
    private function __construct(private array $hops)
    {
    }

    public static function none(): self
    {
        return new self([]);
    }

    /** @param RedirectHop[] $hops */
    public static function fromHops(array $hops): self
    {
        return new self($hops);
    }

    /** @return RedirectHop[] */
    public function hops(): array
    {
        return $this->hops;
    }

    public function length(): int
    {
        return count($this->hops);
    }

    public function isEmpty(): bool
    {
        return $this->hops === [];
    }

    public function finalUrl(): ?Url
    {
        $last = array_last($this->hops);

        return $last?->to();
    }

    public function hasLoop(): bool
    {
        $seen = [];

        foreach ($this->hops as $hop) {
            $from = $hop->from()->toString();

            if (isset($seen[$from])) {
                return true;
            }

            $seen[$from] = true;
        }

        $last = array_last($this->hops);
        if ($last !== null && isset($seen[$last->to()->toString()])) {
            return true;
        }

        return false;
    }

    public function hasMixedProtocols(): bool
    {
        foreach ($this->hops as $hop) {
            if ($hop->from()->scheme() !== $hop->to()->scheme()) {
                return true;
            }
        }

        return false;
    }

    public function isAllPermanent(): bool
    {
        if ($this->isEmpty()) {
            return false;
        }

        foreach ($this->hops as $hop) {
            if (!$hop->isPermanent()) {
                return false;
            }
        }

        return true;
    }
}
