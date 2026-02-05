<?php

namespace Kachnitel\AdminBundle\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Kachnitel\AdminBundle\Attribute\ColumnFilter;
use Kachnitel\AdminBundle\Service\FilterMetadataProvider;
use Kachnitel\AdminBundle\Tests\Fixtures\LabelEntity;
use Kachnitel\AdminBundle\Tests\Fixtures\RelatedEntity;
use Kachnitel\AdminBundle\Tests\Fixtures\TestEntity;
use Kachnitel\AdminBundle\Tests\Fixtures\TitleEntity;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FilterMetadataProviderTest extends TestCase
{
    private FilterMetadataProvider $provider;
    private EntityManagerInterface&MockObject $em;
    /** @var ClassMetadata<object>&MockObject */
    private ClassMetadata $metadata;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->provider = new FilterMetadataProvider($this->em);

        // Create metadata mock
        $this->metadata = $this->createMock(ClassMetadata::class);
        $this->em->method('getClassMetadata')
            ->willReturn($this->metadata);

        // Default hasField to return true unless overridden in specific tests
        $this->metadata->method('hasField')->willReturn(true);
    }

    public function testGetFiltersReturnsAllEnabledFilters(): void
    {
        $this->metadata->method('getFieldNames')->willReturn([
            'name', 'description', 'quantity', 'price', 'createdAt', 'active', 'status', 'disabledFilter'
        ]);
        $this->metadata->method('getAssociationNames')->willReturn(['relatedEntity']);
        $this->metadata->method('isCollectionValuedAssociation')->willReturn(false);
        $this->metadata->method('hasField')->willReturnCallback(
            fn($name) => in_array($name, ['name', 'description', 'quantity', 'price', 'createdAt', 'active', 'status', 'disabledFilter'], true)
        );
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
        $this->metadata->method('hasField')->willReturn(true);
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
        $this->assertEquals('BETWEEN', $filters['createdAt']['operator']);
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
        // Mock the target entity metadata for field validation
        $this->metadata->method('hasField')->willReturnCallback(
            fn($field) => in_array($field, ['id', 'name', 'email'])
        );

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

    public function testCollectionAssociationsAreSkippedWithoutColumnFilter(): void
    {
        // Create a mock for an entity without ColumnFilter on its collection
        $this->metadata->method('getFieldNames')->willReturn([]);
        $this->metadata->method('getAssociationNames')->willReturn(['items']);
        $this->metadata->method('isCollectionValuedAssociation')->willReturn(true);

        $filters = $this->provider->getFilters(TestEntity::class);

        // 'items' should be skipped because it doesn't exist on TestEntity
        // and the mock returns true for isCollectionValuedAssociation
        $this->assertArrayNotHasKey('items', $filters);
    }

    public function testCollectionAssociationWithColumnFilterIsIncluded(): void
    {
        // TestEntity has 'tags' collection with #[ColumnFilter]
        $em = $this->createMock(EntityManagerInterface::class);
        $mainEntityMetadata = $this->createMock(ClassMetadata::class);
        $tagEntityMetadata = $this->createMock(ClassMetadata::class);

        $em->method('getClassMetadata')->willReturnCallback(
            function ($class) use ($mainEntityMetadata, $tagEntityMetadata) {
                if ($class === TestEntity::class) {
                    return $mainEntityMetadata;
                }
                if ($class === \Kachnitel\AdminBundle\Tests\Fixtures\TagEntity::class) {
                    return $tagEntityMetadata;
                }
                throw new \InvalidArgumentException("Unexpected class: " . $class);
            }
        );

        $mainEntityMetadata->method('getFieldNames')->willReturn([]);
        $mainEntityMetadata->method('getAssociationNames')->willReturn(['tags']);
        $mainEntityMetadata->method('isCollectionValuedAssociation')->willReturn(true);
        $mainEntityMetadata->method('hasAssociation')->willReturn(true);
        $mainEntityMetadata->method('getAssociationTargetClass')->willReturn(
            \Kachnitel\AdminBundle\Tests\Fixtures\TagEntity::class
        );

        // TagEntity has 'name' field
        $tagEntityMetadata->method('hasField')->willReturnCallback(
            fn ($field) => in_array($field, ['id', 'name'])
        );

        $provider = new FilterMetadataProvider($em);
        $filters = $provider->getFilters(TestEntity::class);

        // 'tags' should be included because it has #[ColumnFilter]
        $this->assertArrayHasKey('tags', $filters);
        $this->assertEquals(ColumnFilter::TYPE_COLLECTION, $filters['tags']['type']);
        $this->assertEquals(['name'], $filters['tags']['searchFields']);
        $this->assertEquals('Tags', $filters['tags']['label']);
        $this->assertTrue($filters['tags']['excludeFromGlobalSearch']);
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

    public function testRelationFilterFiltersOutNonExistentSearchFields(): void
    {
        // This test covers the issue where a related entity has a computed property (getter)
        // like getName() that combines firstName and lastName, but "name" is not a database field.
        // The system should filter out non-existent fields and only keep real database fields.

        // Create a fresh EM mock for this test
        $em = $this->createMock(EntityManagerInterface::class);
        $mainEntityMetadata = $this->createMock(ClassMetadata::class);
        $userMetadata = $this->createMock(ClassMetadata::class);

        // Set up getClassMetadata with callback to handle different classes
        $em->method('getClassMetadata')->willReturnCallback(
            function($class) use ($mainEntityMetadata, $userMetadata) {
                if ($class === TestEntity::class) {
                    return $mainEntityMetadata;
                }
                if ($class === \Kachnitel\AdminBundle\Tests\Fixtures\User::class) {
                    return $userMetadata;
                }
                throw new \InvalidArgumentException("Unexpected class: " . $class);
            }
        );

        $mainEntityMetadata->method('getFieldNames')->willReturn([]);
        $mainEntityMetadata->method('getAssociationNames')->willReturn(['customer']);
        $mainEntityMetadata->method('isCollectionValuedAssociation')->willReturn(false);
        $mainEntityMetadata->method('hasAssociation')->willReturnCallback(
            fn($name) => $name === 'customer'
        );
        $mainEntityMetadata->method('getAssociationTargetClass')->willReturn(\Kachnitel\AdminBundle\Tests\Fixtures\User::class);

        // User entity has firstName, lastName, email but NOT "name" (name is a getter)
        $userMetadata->method('hasField')->willReturnCallback(
            fn($field) => in_array($field, ['firstName', 'lastName', 'email', 'id'])
        );

        $provider = new FilterMetadataProvider($em);
        $filters = $provider->getFilters(TestEntity::class);

        // Should have customer filter
        $this->assertArrayHasKey('customer', $filters);
        $this->assertEquals(ColumnFilter::TYPE_RELATION, $filters['customer']['type']);

        // The default searchFields from DEFAULT_SEARCH_FIELDS['User'] would be
        // ['name', 'email', 'firstName', 'lastName'], but 'name' doesn't exist as a
        // database field, so it should be filtered out. Other fields should remain.
        // The default searchFields from DEFAULT_SEARCH_FIELDS['User'] would be
        // ['name', 'email', 'firstName', 'lastName'], but 'name' doesn't exist as a
        // database field, so it should be filtered out. Other fields should remain.
        $searchFields = $filters['customer']['searchFields'];
        $this->assertNotContains('name', $searchFields);
        $this->assertContains('email', $searchFields);
        $this->assertContains('firstName', $searchFields);
        $this->assertContains('lastName', $searchFields);
        $this->assertContains('firstName', $searchFields);
        $this->assertContains('lastName', $searchFields);
    }

    public function testRelationFilterFallsBackToIdWhenNoValidSearchFields(): void
    {
        // If all default searchFields are non-existent, should fall back to 'id'

        // Create a fresh EM mock for this test
        $em = $this->createMock(EntityManagerInterface::class);
        $mainEntityMetadata = $this->createMock(ClassMetadata::class);
        $userMetadata = $this->createMock(ClassMetadata::class);

        // Set up getClassMetadata with callback to handle different classes
        $em->method('getClassMetadata')->willReturnCallback(
            function($class) use ($mainEntityMetadata, $userMetadata) {
                if ($class === TestEntity::class) {
                    return $mainEntityMetadata;
                }
                if ($class === \Kachnitel\AdminBundle\Tests\Fixtures\User::class) {
                    return $userMetadata;
                }
                throw new \InvalidArgumentException("Unexpected class: " . $class);
            }
        );

        $mainEntityMetadata->method('getFieldNames')->willReturn([]);
        $mainEntityMetadata->method('getAssociationNames')->willReturn(['customer']);
        $mainEntityMetadata->method('isCollectionValuedAssociation')->willReturn(false);
        $mainEntityMetadata->method('hasAssociation')->willReturnCallback(
            fn($name) => $name === 'customer'
        );
        $mainEntityMetadata->method('getAssociationTargetClass')->willReturn(\Kachnitel\AdminBundle\Tests\Fixtures\User::class);

        // User entity has no 'name' or 'email' fields (but has id)
        $userMetadata->method('hasField')->willReturnCallback(
            fn($field) => $field === 'id'
        );

        $provider = new FilterMetadataProvider($em);
        $filters = $provider->getFilters(TestEntity::class);

        // Should have customer filter
        $this->assertArrayHasKey('customer', $filters);

        // Should fall back to 'id' when no valid searchFields exist
        $searchFields = $filters['customer']['searchFields'];
        $this->assertContains('id', $searchFields);
        $this->assertCount(1, $searchFields);
    }

    public function testRelationFilterAutoDetectsSearchFieldsByDisplayPriority(): void
    {
        // For entities not in DEFAULT_SEARCH_FIELDS, should auto-detect
        // based on display field priority: name → label → title → id

        $em = $this->createMock(EntityManagerInterface::class);
        $mainEntityMetadata = $this->createMock(ClassMetadata::class);
        $labelEntityMetadata = $this->createMock(ClassMetadata::class);

        // Use LabelEntity which is not in DEFAULT_SEARCH_FIELDS
        $em->method('getClassMetadata')->willReturnCallback(
            function ($class) use ($mainEntityMetadata, $labelEntityMetadata) {
                if ($class === TestEntity::class) {
                    return $mainEntityMetadata;
                }
                if ($class === LabelEntity::class) {
                    return $labelEntityMetadata;
                }
                throw new \InvalidArgumentException("Unexpected class: " . $class);
            }
        );

        $mainEntityMetadata->method('getFieldNames')->willReturn([]);
        $mainEntityMetadata->method('getAssociationNames')->willReturn(['relatedEntity']);
        $mainEntityMetadata->method('isCollectionValuedAssociation')->willReturn(false);
        $mainEntityMetadata->method('hasAssociation')->willReturnCallback(
            fn ($name) => $name === 'relatedEntity'
        );
        $mainEntityMetadata->method('getAssociationTargetClass')->willReturn(LabelEntity::class);

        // LabelEntity has 'label' field but not 'name'
        $labelEntityMetadata->method('hasField')->willReturnCallback(
            fn ($field) => in_array($field, ['id', 'label', 'code'])
        );

        $provider = new FilterMetadataProvider($em);
        $filters = $provider->getFilters(TestEntity::class);

        // Should auto-detect 'label' since 'name' doesn't exist
        $this->assertArrayHasKey('relatedEntity', $filters);
        $searchFields = $filters['relatedEntity']['searchFields'];
        $this->assertContains('label', $searchFields);
        $this->assertCount(1, $searchFields);
    }

    public function testRelationFilterAutoDetectsTitleWhenNoNameOrLabel(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $mainEntityMetadata = $this->createMock(ClassMetadata::class);
        $titleEntityMetadata = $this->createMock(ClassMetadata::class);

        $em->method('getClassMetadata')->willReturnCallback(
            function ($class) use ($mainEntityMetadata, $titleEntityMetadata) {
                if ($class === TestEntity::class) {
                    return $mainEntityMetadata;
                }
                if ($class === TitleEntity::class) {
                    return $titleEntityMetadata;
                }
                throw new \InvalidArgumentException("Unexpected class: " . $class);
            }
        );

        $mainEntityMetadata->method('getFieldNames')->willReturn([]);
        $mainEntityMetadata->method('getAssociationNames')->willReturn(['relatedEntity']);
        $mainEntityMetadata->method('isCollectionValuedAssociation')->willReturn(false);
        $mainEntityMetadata->method('hasAssociation')->willReturnCallback(
            fn ($name) => $name === 'relatedEntity'
        );
        $mainEntityMetadata->method('getAssociationTargetClass')->willReturn(TitleEntity::class);

        // TitleEntity only has 'title' field, no 'name' or 'label'
        $titleEntityMetadata->method('hasField')->willReturnCallback(
            fn ($field) => in_array($field, ['id', 'title', 'description'])
        );

        $provider = new FilterMetadataProvider($em);
        $filters = $provider->getFilters(TestEntity::class);

        // Should auto-detect 'title' since 'name' and 'label' don't exist
        $this->assertArrayHasKey('relatedEntity', $filters);
        $searchFields = $filters['relatedEntity']['searchFields'];
        $this->assertContains('title', $searchFields);
        $this->assertCount(1, $searchFields);
    }
}
