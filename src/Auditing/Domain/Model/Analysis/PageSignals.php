<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Domain\Model\Analysis;

use SeoSpider\Auditing\Domain\Model\Analysis\Signal\Directive;
use SeoSpider\Auditing\Domain\Model\Analysis\Signal\Fingerprint;
use SeoSpider\Auditing\Domain\Model\Analysis\Signal\Hreflang;
use SeoSpider\Auditing\Domain\Model\Analysis\Signal\Link;
use SeoSpider\Auditing\Domain\Model\Analysis\Signal\PageMetadata;
use SeoSpider\Auditing\Domain\Model\Analysis\Signal\PageResponseInfo;
use SeoSpider\Auditing\Domain\Model\Analysis\Signal\RedirectChain;

interface PageSignals
{
    public function auditId(): string;

    public function url(): string;

    public function crawlDepth(): int;

    public function response(): PageResponseInfo;

    public function metadata(): ?PageMetadata;

    public function directives(): ?Directive;

    public function fingerprint(): ?Fingerprint;

    public function redirectChain(): RedirectChain;

    /** @return Link[] */
    public function links(): array;

    /** @return Link[] */
    public function internalLinks(): array;

    /** @return Link[] */
    public function externalLinks(): array;

    /** @return Hreflang[] */
    public function hreflangs(): array;

    public function isHtml(): bool;

    public function isBroken(): bool;

    public function isRedirect(): bool;

    public function isIndexable(): bool;
}
