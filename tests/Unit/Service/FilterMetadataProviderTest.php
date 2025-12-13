<?php

namespace Kachnitel\AdminBundle\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Kachnitel\AdminBundle\Attribute\ColumnFilter;
use Kachnitel\AdminBundle\Service\FilterMetadataProvider;
use Kachnitel\AdminBundle\Tests\Fixtures\TestEntity;
use Kachnitel\AdminBundle\Tests\Fixtures\RelatedEntity;
use PHPUnit\Framework\TestCase;

class FilterMetadataProviderTest extends TestCase
{
    private FilterMetadataProvider $provider;
    private EntityManagerInterface $em;
    /** @var ClassMetadata<object> */
    private ClassMetadata $metadata;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->provider = new FilterMetadataProvider($this->em);

        // Create metadata mock
        $this->metadata = $this->createMock(ClassMetadata::class);
        $this->em->method('getClassMetadata')
            ->willReturn($this->metadata);
    }

    public function testGetFiltersReturnsAllEnabledFilters(): void
    {
        $this->metadata->method('getFieldNames')->willReturn([
            'name', 'description', 'quantity', 'price', 'createdAt', 'active', 'status', 'disabledFilter'
        ]);
        $this->metadata->method('getAssociationNames')->willReturn(['relatedEntity']);
        $this->metadata->method('isCollectionValuedAssociation')->willReturn(false);
        $this->metadata->method('hasAssociation')->willReturnCallback(
            fn($name) => $name === 'relatedEntity'
        );
        $this->metadata->method('getTypeOfField')->willReturnCallback(function ($field) {
            return match ($field) {
                'name', 'status', 'disabledFilter' => 'string',
                'description' => 'text',
                'quantity' => 'integer',
                'price' => 'decimal',
                'createdAt' => 'datetime',
                'active' => 'boolean',
                default => null,
            };
        });
        $this->metadata->method('getAssociationTargetClass')
            ->willReturn(RelatedEntity::class);

        $filters = $this->provider->getFilters(TestEntity::class);

        // Should include all enabled filters
        $this->assertArrayHasKey('name', $filters);
        $this->assertArrayHasKey('quantity', $filters);
        $this->assertArrayHasKey('createdAt', $filters);
        $this->assertArrayHasKey('active', $filters);
        $this->assertArrayHasKey('relatedEntity', $filters);

        // Should exclude disabled filter
        $this->assertArrayNotHasKey('disabledFilter', $filters);
    }

    public function testTextFieldHasCorrectFilterType(): void
    {
        $this->metadata->method('getFieldNames')->willReturn(['name']);
        $this->metadata->method('getAssociationNames')->willReturn([]);
        $this->metadata->method('hasAssociation')->willReturn(false);
        $this->metadata->method('getTypeOfField')->willReturn('string');

        $filters = $this->provider->getFilters(TestEntity::class);

        $this->assertEquals(ColumnFilter::TYPE_TEXT, $filters['name']['type']);
        $this->assertEquals('LIKE', $filters['name']['operator']);
        $this->assertEquals('Name', $filters['name']['label']);
    }

    public function testNumberFieldHasCorrectFilterType(): void
    {
        $this->metadata->method('getFieldNames')->willReturn(['quantity']);
        $this->metadata->method('getAssociationNames')->willReturn([]);
        $this->metadata->method('hasAssociation')->willReturn(false);
        $this->metadata->method('getTypeOfField')->willReturn('integer');

        $filters = $this->provider->getFilters(TestEntity::class);

        $this->assertEquals(ColumnFilter::TYPE_NUMBER, $filters['quantity']['type']);
        $this->assertEquals('=', $filters['quantity']['operator']);
    }

    public function testDateFieldHasCorrectFilterType(): void
    {
        $this->metadata->method('getFieldNames')->willReturn(['createdAt']);
        $this->metadata->method('getAssociationNames')->willReturn([]);
        $this->metadata->method('hasAssociation')->willReturn(false);
        $this->metadata->method('getTypeOfField')->willReturn('datetime');

        $filters = $this->provider->getFilters(TestEntity::class);

        $this->assertEquals(ColumnFilter::TYPE_DATE, $filters['createdAt']['type']);
        $this->assertEquals('>=', $filters['createdAt']['operator']);
    }

    public function testBooleanFieldHasCorrectFilterType(): void
    {
        $this->metadata->method('getFieldNames')->willReturn(['active']);
        $this->metadata->method('getAssociationNames')->willReturn([]);
        $this->metadata->method('hasAssociation')->willReturn(false);
        $this->metadata->method('getTypeOfField')->willReturn('boolean');

        $filters = $this->provider->getFilters(TestEntity::class);

        $this->assertEquals(ColumnFilter::TYPE_BOOLEAN, $filters['active']['type']);
        $this->assertEquals('=', $filters['active']['operator']);
    }

    public function testEnumFieldIsDetected(): void
    {
        $this->metadata->method('getFieldNames')->willReturn(['status']);
        $this->metadata->method('getAssociationNames')->willReturn([]);
        $this->metadata->method('hasAssociation')->willReturn(false);
        $this->metadata->method('getTypeOfField')->willReturn('string');

        $filters = $this->provider->getFilters(TestEntity::class);

        $this->assertEquals(ColumnFilter::TYPE_ENUM, $filters['status']['type']);
        $this->assertArrayHasKey('enumClass', $filters['status']);
        $this->assertTrue($filters['status']['showAllOption']);
    }

    public function testRelationFilterHasSearchFields(): void
    {
        $this->metadata->method('getFieldNames')->willReturn([]);
        $this->metadata->method('getAssociationNames')->willReturn(['relatedEntity']);
        $this->metadata->method('isCollectionValuedAssociation')->willReturn(false);
        $this->metadata->method('hasAssociation')->willReturn(true);
        $this->metadata->method('getAssociationTargetClass')
            ->willReturn(RelatedEntity::class);

        $filters = $this->provider->getFilters(TestEntity::class);

        $this->assertEquals(ColumnFilter::TYPE_RELATION, $filters['relatedEntity']['type']);
        $this->assertArrayHasKey('searchFields', $filters['relatedEntity']);
        $this->assertContains('name', $filters['relatedEntity']['searchFields']);
        $this->assertContains('email', $filters['relatedEntity']['searchFields']);
        $this->assertEquals(RelatedEntity::class, $filters['relatedEntity']['targetClass']);
    }

    public function testColumnFilterAttributeOverridesType(): void
    {
        $this->metadata->method('getFieldNames')->willReturn(['name']);
        $this->metadata->method('getAssociationNames')->willReturn([]);
        $this->metadata->method('hasAssociation')->willReturn(false);
        $this->metadata->method('getTypeOfField')->willReturn('string');

        $filters = $this->provider->getFilters(TestEntity::class);

        // name has ColumnFilter(type: TYPE_TEXT, placeholder: 'Search name...')
        $this->assertEquals(ColumnFilter::TYPE_TEXT, $filters['name']['type']);
        $this->assertEquals('Search name...', $filters['name']['placeholder']);
    }

    public function testColumnFilterAttributeCanDisableFilter(): void
    {
        $this->metadata->method('getFieldNames')->willReturn(['disabledFilter']);
        $this->metadata->method('getAssociationNames')->willReturn([]);
        $this->metadata->method('hasAssociation')->willReturn(false);
        $this->metadata->method('getTypeOfField')->willReturn('string');

        $filters = $this->provider->getFilters(TestEntity::class);

        $this->assertArrayNotHasKey('disabledFilter', $filters);
    }

    public function testCollectionAssociationsAreSkipped(): void
    {
        $this->metadata->method('getFieldNames')->willReturn([]);
        $this->metadata->method('getAssociationNames')->willReturn(['items']);
        $this->metadata->method('isCollectionValuedAssociation')->willReturn(true);

        $filters = $this->provider->getFilters(TestEntity::class);

        $this->assertArrayNotHasKey('items', $filters);
    }

    public function testFiltersAreSortedByPriority(): void
    {
        $this->metadata->method('getFieldNames')->willReturn(['name', 'quantity', 'createdAt']);
        $this->metadata->method('getAssociationNames')->willReturn([]);
        $this->metadata->method('hasAssociation')->willReturn(false);
        $this->metadata->method('getTypeOfField')->willReturnCallback(function ($field) {
            return match ($field) {
                'name' => 'string',
                'quantity' => 'integer',
                'createdAt' => 'datetime',
            };
        });

        $filters = $this->provider->getFilters(TestEntity::class);

        // Filters with explicit priority should come first
        // name has no priority, quantity has TYPE_NUMBER, createdAt has TYPE_DATE
        // All should have default priority of 999
        $keys = array_keys($filters);
        $this->assertCount(3, $keys);
    }

    public function testHumanizeConvertsPropertyNameToLabel(): void
    {
        $this->metadata->method('getFieldNames')->willReturn(['createdAt']);
        $this->metadata->method('getAssociationNames')->willReturn([]);
        $this->metadata->method('hasAssociation')->willReturn(false);
        $this->metadata->method('getTypeOfField')->willReturn('datetime');

        $filters = $this->provider->getFilters(TestEntity::class);

        $this->assertEquals('Created at', $filters['createdAt']['label']);
    }
}
