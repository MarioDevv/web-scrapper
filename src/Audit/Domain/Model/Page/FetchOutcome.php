<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Page;

use SeoSpider\Audit\Domain\Model\Url;

/**
 * Result of fetching a single URL. One of (response + chain, error) is set;
 * the other is null. Batched fetchers return a map of these keyed by the
 * original URL string so callers can correlate every input with its outcome.
 */
final readonly class FetchOutcome
{
    public function __construct(
        public Url $url,
        public ?PageResponse $response,
        public ?RedirectChain $chain,
        public ?string $error,
    ) {
    }

    /**
     * @phpstan-assert-if-true !null $this->response
     * @phpstan-assert-if-true !null $this->chain
     * @phpstan-assert-if-true null $this->error
     */
    public function isSuccessful(): bool
    {
        return $this->error === null && $this->response !== null && $this->chain !== null;
    }

    public static function success(Url $url, PageResponse $response, RedirectChain $chain): self
    {
        return new self($url, $response, $chain, null);
    }

    public static function failure(Url $url, string $error): self
    {
        return new self($url, null, null, $error);
    }
}
