<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\DataSource;

use Kachnitel\AdminBundle\DataSource\ColumnMetadata;
use PHPUnit\Framework\TestCase;

/**
 * @group composite-columns
 */
class ColumnMetadataGroupTest extends TestCase
{
    /** @test */
    public function defaultGroupIsNull(): void
    {
        $col = ColumnMetadata::create(name: 'name');

        $this->assertNull($col->group);
    }

    /** @test */
    public function groupCarriedThroughCreate(): void
    {
        $col = ColumnMetadata::create(name: 'firstName', group: 'name_block');

        $this->assertSame('name_block', $col->group);
    }

    /** @test */
    public function groupCarriedThroughConstructor(): void
    {
        $col = new ColumnMetadata(
            name: 'firstName',
            label: 'First Name',
            type: 'string',
            sortable: true,
            template: null,
            group: 'name_block',
        );

        $this->assertSame('name_block', $col->group);
    }
}
