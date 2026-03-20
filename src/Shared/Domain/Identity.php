<?php

declare(strict_types=1);

namespace SeoSpider\Shared\Domain;

use InvalidArgumentException;
use Stringable;
use Symfony\Component\Uid\Uuid;

abstract readonly class Identity implements Stringable
{
    final public function __construct(private string $value)
    {
        if (!Uuid::isValid($value)) {
            throw new InvalidArgumentException(
                sprintf('Invalid UUID format: "%s"', $value),
            );
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return static::class === $other::class
            && $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public static function generate(): static
    {
        return new static(Uuid::v7()->toString());
    }
}