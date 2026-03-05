<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Attribute;

use Attribute;
use Kachnitel\AdminBundle\Attribute\AdminColumn;
use PHPUnit\Framework\TestCase;

/**
 * @group inline-edit
 */
class AdminColumnTest extends TestCase
{
    /** @test */
    public function defaultEditableIsNull(): void
    {
        $attr = new AdminColumn();

        $this->assertNull($attr->editable);
    }

    /** @test */
    public function editableCanBeSetToFalse(): void
    {
        $attr = new AdminColumn(editable: false);

        $this->assertFalse($attr->editable);
    }

    /** @test */
    public function editableCanBeSetToTrue(): void
    {
        $attr = new AdminColumn(editable: true);

        $this->assertTrue($attr->editable);
    }

    /** @test */
    public function editableCanBeSetToExpressionString(): void
    {
        $attr = new AdminColumn(editable: 'entity.status != "locked"');

        $this->assertSame('entity.status != "locked"', $attr->editable);
    }

    /** @test */
    public function editableCanCombinePropertyAndSecurityChecks(): void
    {
        $attr = new AdminColumn(editable: 'entity.active && is_granted("ROLE_EDITOR")');

        $this->assertSame('entity.active && is_granted("ROLE_EDITOR")', $attr->editable);
    }

    /** @test */
    public function isPropertyLevelAttribute(): void
    {
        $reflection = new \ReflectionClass(AdminColumn::class);
        $attrInstance = $reflection->getAttributes(Attribute::class)[0]->newInstance();

        $this->assertTrue(($attrInstance->flags & Attribute::TARGET_PROPERTY) !== 0);
    }

    /** @test */
    public function canBeReadFromPropertyWithNull(): void
    {
        $entity = new class {
            #[AdminColumn]
            public string $inheritField = '';
        };

        $reflection = new \ReflectionProperty($entity, 'inheritField');
        $attributes = $reflection->getAttributes(AdminColumn::class);

        $this->assertCount(1, $attributes);
        $attr = $attributes[0]->newInstance();
        $this->assertNull($attr->editable, 'Default should be null (inherit entity setting)');
    }

    /** @test */
    public function canBeReadFromPropertyWithFalse(): void
    {
        $entity = new class {
            #[AdminColumn(editable: false)]
            public string $computedField = '';
        };

        $reflection = new \ReflectionProperty($entity, 'computedField');
        $attributes = $reflection->getAttributes(AdminColumn::class);

        $this->assertCount(1, $attributes);
        $attr = $attributes[0]->newInstance();
        $this->assertFalse($attr->editable);
    }

    /** @test */
    public function canBeReadFromPropertyWithExpression(): void
    {
        $entity = new class {
            #[AdminColumn(editable: 'entity.status != "locked"')]
            public string $statusField = '';
        };

        $reflection = new \ReflectionProperty($entity, 'statusField');
        $attributes = $reflection->getAttributes(AdminColumn::class);

        $this->assertCount(1, $attributes);
        $attr = $attributes[0]->newInstance();
        $this->assertSame('entity.status != "locked"', $attr->editable);
    }
}
