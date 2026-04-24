<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model;

use SeoSpider\Audit\Domain\Model\Page\InvalidUrl;
use Stringable;
use Uri\Rfc3986\Uri as PhpUri;
use Uri\UriComparisonMode;

final readonly class Url implements Stringable
{
    private function __construct(private PhpUri $uri)
    {
    }

    public static function fromString(string $url): self
    {
        $trimmed = trim($url);
        $parsed = PhpUri::parse($trimmed);

        if ($parsed === null) {
            throw InvalidUrl::becauseItCannotBeParsed($trimmed);
        }

        if ($parsed->getScheme() === null) {
            throw InvalidUrl::becauseSchemeIsMissing($trimmed);
        }

        return new self($parsed);
    }

    public static function tryFromString(string $url): ?self
    {
        try {
            return self::fromString($url);
        } catch (InvalidUrl) {
            return null;
        }
    }

    public function scheme(): string
    {
        return $this->uri->getScheme() ?? '';
    }

    public function host(): string
    {
        return $this->uri->getHost() ?? '';
    }

    public function port(): ?int
    {
        return $this->uri->getPort();
    }

    public function path(): string
    {
        return $this->uri->getPath();
    }

    public function query(): ?string
    {
        return $this->uri->getQuery();
    }

    public function fragment(): ?string
    {
        return $this->uri->getFragment();
    }

    public function origin(): string
    {
        $origin = $this->scheme() . '://' . $this->host();

        if ($this->port() !== null) {
            $origin .= ':' . $this->port();
        }

        return $origin;
    }

    public function equals(self $other): bool
    {
        return $this->uri->equals($other->uri, UriComparisonMode::ExcludeFragment);
    }

    public function isInternalTo(self $baseUrl): bool
    {
        return $this->origin() === $baseUrl->origin();
    }

    #[\NoDiscard('Url is immutable — use the returned instance')]
    public function resolve(string $relativeUrl): self
    {
        $resolved = PhpUri::parse($relativeUrl, $this->uri);

        if ($resolved === null) {
            throw InvalidUrl::becauseItCannotBeParsed($relativeUrl);
        }

        return new self($resolved);
    }

    #[\NoDiscard('Url is immutable — use the returned instance')]
    public function withoutFragment(): self
    {
        return new self($this->uri->withFragment(null));
    }

    #[\NoDiscard('Url is immutable — use the returned instance')]
    public function withPath(string $path): self
    {
        return new self($this->uri->withPath($path));
    }

    #[\NoDiscard('Url is immutable — use the returned instance')]
    public function withQuery(?string $query): self
    {
        return new self($this->uri->withQuery($query));
    }

    #[\NoDiscard('Url is immutable — use the returned instance')]
    public function withScheme(string $scheme): self
    {
        return new self($this->uri->withScheme($scheme));
    }

    #[\NoDiscard('Url is immutable — use the returned instance')]
    public function withPort(?int $port): self
    {
        return new self($this->uri->withPort($port));
    }

    public function toString(): string
    {
        return $this->uri->toString();
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
