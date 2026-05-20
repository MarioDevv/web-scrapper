<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Domain\Model\Analysis\Signal;

final readonly class RedirectChain
{
    /** @param RedirectHop[] $hops */
    public function __construct(private array $hops)
    {
    }

    public static function none(): self
    {
        return new self([]);
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

    public function finalUrl(): ?string
    {
        if ($this->hops === []) {
            return null;
        }
        return $this->hops[array_key_last($this->hops)]->to();
    }

    public function hasLoop(): bool
    {
        $seen = [];
        foreach ($this->hops as $hop) {
            if (isset($seen[$hop->from()])) {
                return true;
            }
            $seen[$hop->from()] = true;
        }
        return false;
    }

    public function hasMixedProtocols(): bool
    {
        $protocols = [];
        foreach ($this->hops as $hop) {
            $protocols[parse_url($hop->from(), PHP_URL_SCHEME) ?: ''] = true;
            $protocols[parse_url($hop->to(), PHP_URL_SCHEME) ?: ''] = true;
        }
        unset($protocols['']);
        return count($protocols) > 1;
    }

    public function isAllPermanent(): bool
    {
        foreach ($this->hops as $hop) {
            if (!$hop->isPermanent()) {
                return false;
            }
        }
        return true;
    }
}
