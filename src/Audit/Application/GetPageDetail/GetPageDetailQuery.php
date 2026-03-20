<?php

declare(strict_types=1);

namespace SeoSpider\Audit\Application\GetPageDetail;

final readonly class GetPageDetailQuery
{
    public function __construct(public string $pageId)
    {
    }
}
