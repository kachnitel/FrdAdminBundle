<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\DataSource;

use Kachnitel\AdminBundle\DataSource\ColumnMetadata;
use PHPUnit\Framework\TestCase;

class ColumnMetadataTest extends TestCase
{
    public function testConstructorSetsAllProperties(): void
    {
        $column = new ColumnMetadata(
            name: 'testColumn',
            label: 'Test Column',
            type: 'datetime',
            sortable: false,
            template: '@App/custom.html.twig'
        );

        $this->assertSame('testColumn', $column->name);
        $this->assertSame('Test Column', $column->label);
        $this->assertSame('datetime', $column->type);
        $this->assertFalse($column->sortable);
        $this->assertSame('@App/custom.html.twig', $column->template);
    }

    public function testConstructorDefaults(): void
    {
        $column = new ColumnMetadata(
            name: 'testColumn',
            label: 'Test Column'
        );

        $this->assertSame('string', $column->type);
        $this->assertTrue($column->sortable);
        $this->assertNull($column->template);
    }

    public function testCreateWithAllParameters(): void
    {
        $column = ColumnMetadata::create(
            name: 'createdAt',
            label: 'Created At',
            type: 'datetime',
            sortable: true,
            template: '@App/datetime.html.twig'
        );

        $this->assertSame('createdAt', $column->name);
        $this->assertSame('Created At', $column->label);
        $this->assertSame('datetime', $column->type);
        $this->assertTrue($column->sortable);
        $this->assertSame('@App/datetime.html.twig', $column->template);
    }

    public function testCreateGeneratesLabelFromName(): void
    {
        $column = ColumnMetadata::create(name: 'createdAt');

        $this->assertSame('Created at', $column->label);
    }

    public function testCreateGeneratesLabelFromCamelCaseName(): void
    {
        $column = ColumnMetadata::create(name: 'updatedByUser');

        $this->assertSame('Updated by user', $column->label);
    }

    public function testCreateGeneratesLabelFromSimpleName(): void
    {
        $column = ColumnMetadata::create(name: 'name');

        $this->assertSame('Name', $column->label);
    }

    public function testCreateWithDefaults(): void
    {
        $column = ColumnMetadata::create(name: 'status');

        $this->assertSame('status', $column->name);
        $this->assertSame('Status', $column->label);
        $this->assertSame('string', $column->type);
        $this->assertTrue($column->sortable);
        $this->assertNull($column->template);
    }

    public function testCreateNonSortableColumn(): void
    {
        $column = ColumnMetadata::create(
            name: 'actions',
            sortable: false
        );

        $this->assertFalse($column->sortable);
    }

    public function testDifferentTypes(): void
    {
        $types = ['string', 'integer', 'boolean', 'datetime', 'date', 'json', 'text', 'collection', 'relation'];

        foreach ($types as $type) {
            $column = ColumnMetadata::create(name: 'field', type: $type);
            $this->assertSame($type, $column->type);
        }
    }

    public function testReadonlyClass(): void
    {
        $column = new ColumnMetadata(name: 'test', label: 'Test');

        // Verify it's a readonly class by checking the reflection
        $reflection = new \ReflectionClass($column);
        $this->assertTrue($reflection->isReadOnly());
    }
}
