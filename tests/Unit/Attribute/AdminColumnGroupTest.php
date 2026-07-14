<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Attribute;

use Attribute;
use Kachnitel\AdminBundle\Attribute\AdminColumnGroup;
use Kachnitel\DataSourceContracts\ColumnGroup;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @group composite-columns
 */
final class AdminColumnGroupTest extends TestCase
{
    #[Test]
    public function itCreatesAttributeWithIdOnly(): void
    {
        $attr = new AdminColumnGroup(id: 'name_block');

        $this->assertSame('name_block', $attr->id);
        $this->assertSame(ColumnGroup::SUB_LABELS_SHOW, $attr->subLabels);
        $this->assertSame(ColumnGroup::HEADER_TEXT, $attr->header);
    }

    #[Test]
    public function itCreatesAttributeWithCollapsibleHeader(): void
    {
        $attr = new AdminColumnGroup(
            id: 'delivery',
            subLabels: ColumnGroup::SUB_LABELS_ICON,
            header: ColumnGroup::HEADER_COLLAPSIBLE,
        );

        $this->assertSame('delivery', $attr->id);
        $this->assertSame(ColumnGroup::SUB_LABELS_ICON, $attr->subLabels);
        $this->assertSame(ColumnGroup::HEADER_COLLAPSIBLE, $attr->header);
    }

    #[Test]
    public function itCreatesAttributeWithFullHeader(): void
    {
        $attr = new AdminColumnGroup(
            id: 'contact_info',
            header: ColumnGroup::HEADER_FULL,
        );

        $this->assertSame(ColumnGroup::HEADER_FULL, $attr->header);
    }

    #[Test]
    public function itIsRepeatableClassAttribute(): void
    {
        $reflection = new \ReflectionClass(AdminColumnGroup::class);
        $attrInstance = $reflection->getAttributes(Attribute::class)[0]->newInstance();

        $this->assertNotSame(0, $attrInstance->flags & Attribute::IS_REPEATABLE);
        $this->assertNotSame(0, $attrInstance->flags & Attribute::TARGET_CLASS);
    }
}
