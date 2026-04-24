<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Domain\Model;

/**
 * How a URL made it into the crawl frontier. Tracked so that reports can
 * surface orphaned pages (present in the sitemap but never reached by a
 * link) and undeclared pages (reached by a link but missing from the
 * sitemap).
 */
enum DiscoverySource: string
{
    case SEED = 'seed';
    case LINK = 'link';
    case SITEMAP = 'sitemap';
}
