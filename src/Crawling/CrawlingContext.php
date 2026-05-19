<?php

declare(strict_types=1);

namespace SeoSpider\Crawling;

/**
 * Marker for the Crawling bounded context (generic/supporting subdomain).
 * Anchors the SeoSpider\Crawling PSR-4 namespace and gives the
 * ContextBoundaryTest a concrete file to scan. No behaviour.
 */
interface CrawlingContext
{
}
