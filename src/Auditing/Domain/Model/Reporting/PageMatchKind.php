<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Domain\Model\Reporting;

/**
 * How a target-audit page was matched to a base-audit page when
 * computing a diff.
 *
 * - BY_URL: same canonical URL on both sides.
 * - BY_FINGERPRINT: URL changed but the content fingerprint (SimHash)
 *   is close enough to treat as a moved/renamed page.
 * - ADDED: page exists only in the target audit.
 * - REMOVED: page exists only in the base audit.
 */
enum PageMatchKind: string
{
    case BY_URL = 'by_url';
    case BY_FINGERPRINT = 'by_fingerprint';
    case ADDED = 'added';
    case REMOVED = 'removed';
}
