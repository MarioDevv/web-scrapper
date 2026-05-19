<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model\Analyzer;

use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Crawling\Domain\Model\Page\Directive;
use SeoSpider\Crawling\Domain\Model\Page\Fingerprint;
use SeoSpider\Crawling\Domain\Model\Page\PageMetadata;
use SeoSpider\Crawling\Domain\Model\Page\PageResponse;
use SeoSpider\Crawling\Domain\Model\Page\RedirectChain;
use SeoSpider\Crawling\Domain\Model\Url;

/**
 * Read port: the parsed-page surface the per-page analyzers consume.
 * Identical accessor signatures to the legacy Page aggregate so Page
 * satisfies it directly. From 3c the Auditing context supplies its own
 * implementation via the ACL translator.
 */
interface PageSignals
{
    public function url(): Url;

    public function auditId(): AuditId;

    public function response(): PageResponse;

    public function metadata(): ?PageMetadata;

    public function directives(): ?Directive;

    public function fingerprint(): ?Fingerprint;

    public function redirectChain(): RedirectChain;

    public function crawlDepth(): int;

    /** @return \SeoSpider\Crawling\Domain\Model\Page\Link[] */
    public function links(): array;

    /** @return \SeoSpider\Crawling\Domain\Model\Page\Link[] */
    public function internalLinks(): array;

    /** @return \SeoSpider\Crawling\Domain\Model\Page\Link[] */
    public function externalLinks(): array;

    /** @return \SeoSpider\Crawling\Domain\Model\Page\Hreflang[] */
    public function hreflangs(): array;

    public function isHtml(): bool;

    public function isBroken(): bool;

    public function isRedirect(): bool;

    public function isIndexable(): bool;
}
