<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Infrastructure\Persistence\Support;

use DateTimeImmutable;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\HttpStatusCode;
use SeoSpider\Audit\Domain\Model\Page\Issue;
use SeoSpider\Audit\Domain\Model\Page\Page;
use SeoSpider\Audit\Domain\Model\Page\PageId;
use SeoSpider\Audit\Domain\Model\Page\PageResponse;
use SeoSpider\Audit\Domain\Model\Page\RedirectChain;
use SeoSpider\Audit\Domain\Model\Url;

final class PageFixture
{
    public static function buildWithIssue(
        AuditId $auditId,
        Issue $issue,
        string $url = 'https://example.com/',
    ): Page {
        return Page::reconstitute(
            id: PageId::generate(),
            auditId: $auditId,
            url: Url::fromString($url),
            response: new PageResponse(
                statusCode: new HttpStatusCode(200),
                headers: [],
                body: null,
                contentType: 'text/html',
                bodySize: 100,
                responseTime: 0.1,
                finalUrl: null,
            ),
            redirectChain: RedirectChain::none(),
            crawlDepth: 0,
            metadata: null,
            directives: null,
            fingerprint: null,
            links: [],
            hreflangs: [],
            issues: [$issue],
            crawledAt: new DateTimeImmutable('2026-04-27T10:00:00+00:00'),
        );
    }
}
