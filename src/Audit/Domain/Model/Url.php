<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model;

use SeoSpider\Audit\Domain\Model\Page\InvalidUrl;
use Stringable;
use Uri\Rfc3986\Uri as PhpUri;
use Uri\UriComparisonMode;

final readonly class Url implements Stringable
{
    /**
     * Query parameter keys that identify a session/campaign rather than the
     * underlying resource. Stripped during normalization so that the same
     * canonical URL is not crawled once per tracking variant.
     */
    private const array TRACKING_PARAMS = [
        'fbclid',
        'gclid',
        'gclsrc',
        'dclid',
        'msclkid',
        'yclid',
        'mc_cid',
        'mc_eid',
        '_ga',
        '_gl',
        'igshid',
        'mkt_tok',
    ];

    private const array TRACKING_PARAM_PREFIXES = ['utm_'];

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
    public function normalized(): self
    {
        $uri = $this->uri->withFragment(null);

        $scheme = strtolower($uri->getScheme() ?? '');
        $port = $uri->getPort();
        if (($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443)) {
            $uri = $uri->withPort(null);
        }

        if ($uri->getPath() === '' && ($uri->getHost() ?? '') !== '') {
            $uri = $uri->withPath('/');
        }

        $query = $uri->getQuery();
        if ($query !== null && $query !== '') {
            $filtered = self::stripTrackingParams($query);
            $uri = $uri->withQuery($filtered === '' ? null : $filtered);
        }

        return new self($uri);
    }

    private static function stripTrackingParams(string $query): string
    {
        $kept = [];

        foreach (explode('&', $query) as $pair) {
            if ($pair === '') {
                continue;
            }

            $eq = strpos($pair, '=');
            $rawKey = $eq === false ? $pair : substr($pair, 0, $eq);
            $key = strtolower(rawurldecode($rawKey));

            if (in_array($key, self::TRACKING_PARAMS, true)) {
                continue;
            }

            foreach (self::TRACKING_PARAM_PREFIXES as $prefix) {
                if (str_starts_with($key, $prefix)) {
                    continue 2;
                }
            }

            $kept[] = $pair;
        }

        return implode('&', $kept);
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
