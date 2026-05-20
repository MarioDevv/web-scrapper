<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Application\Analysis;

use SeoSpider\Audit\Domain\Model\Page\Page;
use SeoSpider\Audit\Domain\Model\Page\PageRepository;
use SeoSpider\Auditing\Domain\Model\Analysis\Signal\Fingerprint as AuditingFingerprint;
use SeoSpider\Auditing\Domain\Model\Audit\AuditId;
use SeoSpider\Auditing\Domain\Model\Reporting\PageRow;
use SeoSpider\Auditing\Domain\Model\Reporting\PageRowReader;

final readonly class CrawlingPageRowReader implements PageRowReader
{
    public function __construct(private PageRepository $pages)
    {
    }

    public function forAudit(AuditId $auditId): array
    {
        return array_map(
            static function (Page $page): PageRow {
                $fp = $page->fingerprint();
                return new PageRow(
                    url: $page->url()->toString(),
                    fingerprint: $fp === null ? null : new AuditingFingerprint($fp->exactHash(), $fp->simHash()),
                );
            },
            $this->pages->findByAudit($auditId),
        );
    }
}
