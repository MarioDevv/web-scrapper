<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Audit\Domain\Model\Page;

use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Domain\Model\Page\IssueRuleCatalog;

final class IssueRuleCatalogTest extends TestCase
{
    public function test_exposes_a_version_in_year_month_format(): void
    {
        self::assertMatchesRegularExpression(
            '/^\d{4}-\d{2}$/',
            IssueRuleCatalog::VERSION,
        );
    }
}
