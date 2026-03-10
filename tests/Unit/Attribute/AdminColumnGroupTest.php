<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Attribute;

use Kachnitel\AdminBundle\Attribute\AdminColumn;
use PHPUnit\Framework\TestCase;

/**
 * @group composite-columns
 */
class AdminColumnGroupTest extends TestCase
{
    /** @test */
    public function defaultGroupIsNull(): void
    {
        $attr = new AdminColumn();

        $this->assertNull($attr->group);
    }

    /** @test */
    public function groupCanBeSetToString(): void
    {
        $attr = new AdminColumn(group: 'status_block');

        $this->assertSame('status_block', $attr->group);
    }

    /** @test */
    public function groupAndEditableCanBeSetTogether(): void
    {
        $attr = new AdminColumn(editable: true, group: 'name_block');

        $this->assertTrue($attr->editable);
        $this->assertSame('name_block', $attr->group);
    }
}
