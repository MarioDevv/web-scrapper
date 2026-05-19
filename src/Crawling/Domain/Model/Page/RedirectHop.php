<?php

declare(strict_types=1);

namespace SeoSpider\Crawling\Domain\Model\Page;

use SeoSpider\Crawling\Domain\Model\HttpStatusCode;
use SeoSpider\Crawling\Domain\Model\Url;

final readonly class RedirectHop
{
    public function __construct(
        private Url $from,
        private Url $to,
        private HttpStatusCode $statusCode,
    ) {
    }

    public function from(): Url
    {
        return $this->from;
    }

    public function to(): Url
    {
        return $this->to;
    }

    public function statusCode(): HttpStatusCode
    {
        return $this->statusCode;
    }

    public function isPermanent(): bool
    {
        return $this->statusCode->isPermanentRedirect();
    }
}
