<?php

declare(strict_types=1);

namespace SeoSpider\Auditing\Application\Reporting\GetPageDetail;

final readonly class GetPageDetailQuery
{
    public function __construct(public string $pageId)
    {
    }
}
