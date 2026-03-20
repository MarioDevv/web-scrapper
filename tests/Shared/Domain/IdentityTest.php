<?php

declare(strict_types=1);

namespace SeoSpider\Tests\Shared\Domain;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SeoSpider\Audit\Domain\Model\Audit\AuditId;
use SeoSpider\Audit\Domain\Model\Page\PageId;

final class IdentityTest extends TestCase
{
    public function test_generate_creates_valid_uuid(): void
    {
        $id = AuditId::generate();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $id->value(),
        );
    }

    public function test_rejects_invalid_uuid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AuditId('not-a-uuid');
    }

    public function test_equals_same_type_same_value(): void
    {
        $value = AuditId::generate()->value();

        $a = new AuditId($value);
        $b = new AuditId($value);

        $this->assertTrue($a->equals($b));
    }

    public function test_not_equals_same_type_different_value(): void
    {
        $a = AuditId::generate();
        $b = AuditId::generate();

        $this->assertFalse($a->equals($b));
    }

    public function test_not_equals_different_type_same_value(): void
    {
        $value = AuditId::generate()->value();

        $auditId = new AuditId($value);
        $pageId = new PageId($value);

        $this->assertFalse($auditId->equals($pageId));
    }

    public function test_to_string_returns_value(): void
    {
        $id = AuditId::generate();

        $this->assertSame($id->value(), (string) $id);
    }

    public function test_two_generated_ids_are_unique(): void
    {
        $ids = [];
        for ($i = 0; $i < 100; $i++) {
            $ids[] = AuditId::generate()->value();
        }

        $this->assertCount(100, array_unique($ids));
    }
}
