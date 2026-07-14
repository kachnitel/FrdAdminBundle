<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\DataSource;

use Kachnitel\DataSourceContracts\ColumnMetadata;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @group composite-columns
 */
final class ColumnMetadataGroupTest extends TestCase
{
    #[Test]
    public function defaultGroupIsNull(): void
    {
        $col = ColumnMetadata::create(name: 'name');

        $this->assertNull($col->group);
    }

    #[Test]
    public function groupCarriedThroughCreate(): void
    {
        $col = ColumnMetadata::create(name: 'firstName', group: 'name_block');

        $this->assertSame('name_block', $col->group);
    }

    #[Test]
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
