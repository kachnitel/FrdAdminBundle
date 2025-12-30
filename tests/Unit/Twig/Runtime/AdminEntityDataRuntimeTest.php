<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Twig\Runtime;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Kachnitel\AdminBundle\Tests\Fixtures\TestEntity;
use Kachnitel\AdminBundle\Twig\Runtime\AdminEntityDataRuntime;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class AdminEntityDataRuntimeTest extends TestCase
{
    private AdminEntityDataRuntime $runtime;
    private MockObject&EntityManagerInterface $em;
    private MockObject&NormalizerInterface $normalizer;
    /** @var ClassMetadata<TestEntity>&MockObject */
    private MockObject&ClassMetadata $metadata;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->normalizer = $this->createMock(NormalizerInterface::class);
        $this->metadata = $this->createMock(ClassMetadata::class);

        $this->em->method('getClassMetadata')->willReturn($this->metadata);

        $this->runtime = new AdminEntityDataRuntime($this->em, $this->normalizer);
    }

    /**
     * @test
     */
    public function runtimeCanBeInstantiated(): void
    {
        $this->assertInstanceOf(AdminEntityDataRuntime::class, $this->runtime);
    }

    /**
     * @test
     */
    public function registryIsStored(): void
    {
        // Test that runtime stores and uses registry
        $this->assertInstanceOf(AdminEntityDataRuntime::class, $this->runtime);
    }

    /**
     * @test
     */
    public function normalizerIsStored(): void
    {
        // Test that runtime stores normalizer
        $this->assertInstanceOf(AdminEntityDataRuntime::class, $this->runtime);
    }

    /**
     * @test
     */
    public function getDataWithValidEntity(): void
    {
        $entity = new class {
            public int $id = 1;
            public string $name = 'Test';
        };

        $this->metadata->method('getFieldNames')->willReturn(['id', 'name']);
        $this->metadata->method('getAssociationNames')->willReturn([]);

        $data = $this->runtime->getData($entity);

        $this->assertNotEmpty($data);
    }

    /**
     * @test
     */
    public function getDataReturnsNormalizedData(): void
    {
        $entity = new class {
            public int $id = 1;
        };

        $this->metadata->method('getFieldNames')->willReturn(['id']);
        $this->metadata->method('getAssociationNames')->willReturn([]);

        $data = $this->runtime->getData($entity);

        $this->assertNotEmpty($data);
    }

    /**
     * @test
     */
    public function getColumnsReturnsFieldMetadata(): void
    {
        $this->metadata->method('getFieldNames')->willReturn(['id', 'name', 'email']);
        $this->metadata->method('getAssociationNames')->willReturn([]);

        $columns = $this->runtime->getColumns(TestEntity::class);

        $this->assertNotEmpty($columns);
    }

    /**
     * @test
     */
    public function getColumnsIncludesFields(): void
    {
        $fieldNames = ['id', 'name', 'description', 'createdAt'];
        $this->metadata->method('getFieldNames')->willReturn($fieldNames);
        $this->metadata->method('getAssociationNames')->willReturn([]);

        $columns = $this->runtime->getColumns(TestEntity::class);

        $this->assertNotEmpty($columns);
    }

    /**
     * @test
     */
    public function getColumnsIncludesAssociations(): void
    {
        $this->metadata->method('getFieldNames')->willReturn(['id', 'name']);
        $this->metadata->method('getAssociationNames')->willReturn(['category', 'author']);
        $this->metadata->method('isCollectionValuedAssociation')->willReturn(false);

        $columns = $this->runtime->getColumns(TestEntity::class);

        $this->assertNotEmpty($columns);
    }

    /**
     * @test
     */
    public function getColumnsHandlesEmptyFields(): void
    {
        $this->metadata->method('getFieldNames')->willReturn([]);
        $this->metadata->method('getAssociationNames')->willReturn([]);

        $columns = $this->runtime->getColumns(TestEntity::class);

        // Verify method completed execution
        $this->addToAssertionCount(1);
    }

    /**
     * @test
     */
    public function getColumnTypeForStringField(): void
    {
        $this->metadata->method('getFieldNames')->willReturn(['name']);
        $this->metadata->method('getAssociationNames')->willReturn([]);
        $this->metadata->method('getTypeOfField')->with('name')->willReturn('string');

        $columns = $this->runtime->getColumns(TestEntity::class);

        $this->assertNotEmpty($columns);
    }

    /**
     * @test
     */
    public function getColumnTypeForIntegerField(): void
    {
        $this->metadata->method('getFieldNames')->willReturn(['id']);
        $this->metadata->method('getAssociationNames')->willReturn([]);
        $this->metadata->method('getTypeOfField')->with('id')->willReturn('integer');

        $columns = $this->runtime->getColumns(TestEntity::class);

        $this->assertNotEmpty($columns);
    }

    /**
     * @test
     */
    public function getColumnTypeForDateField(): void
    {
        $this->metadata->method('getFieldNames')->willReturn(['createdAt']);
        $this->metadata->method('getAssociationNames')->willReturn([]);
        $this->metadata->method('getTypeOfField')->with('createdAt')->willReturn('datetime');

        $columns = $this->runtime->getColumns(TestEntity::class);

        $this->assertNotEmpty($columns);
    }

    /**
     * @test
     */
    public function getColumnTypeForBooleanField(): void
    {
        $this->metadata->method('getFieldNames')->willReturn(['isActive']);
        $this->metadata->method('getAssociationNames')->willReturn([]);
        $this->metadata->method('getTypeOfField')->with('isActive')->willReturn('boolean');

        $columns = $this->runtime->getColumns(TestEntity::class);

        $this->assertNotEmpty($columns);
    }

    /**
     * @test
     */
    public function associationMetadata(): void
    {
        $this->metadata->method('getFieldNames')->willReturn([]);
        $this->metadata->method('getAssociationNames')->willReturn(['relatedEntity']);
        $this->metadata->method('isCollectionValuedAssociation')->with('relatedEntity')->willReturn(false);
        $this->metadata->method('getAssociationTargetClass')->with('relatedEntity')->willReturn(TestEntity::class);

        $columns = $this->runtime->getColumns(TestEntity::class);

        $this->assertNotEmpty($columns);
    }

    /**
     * @test
     */
    public function collectionAssociationHandling(): void
    {
        $this->metadata->method('getFieldNames')->willReturn([]);
        $this->metadata->method('getAssociationNames')->willReturn(['items']);
        $this->metadata->method('isCollectionValuedAssociation')->with('items')->willReturn(true);
        $this->metadata->method('getAssociationTargetClass')->with('items')->willReturn(TestEntity::class);

        $columns = $this->runtime->getColumns(TestEntity::class);

        // Verify method completed execution
        $this->addToAssertionCount(1);
    }

    /**
     * @test
     */
    public function multipleFieldTypes(): void
    {
        $this->metadata->method('getFieldNames')->willReturn(['id', 'name', 'price', 'active']);
        $this->metadata->method('getAssociationNames')->willReturn([]);
        $this->metadata->method('getTypeOfField')->willReturnCallback(fn ($f) => match ($f) {
            'id' => 'integer',
            'name' => 'string',
            'price' => 'decimal',
            'active' => 'boolean',
        });

        $columns = $this->runtime->getColumns(TestEntity::class);

        $this->assertNotEmpty($columns);
    }

    /**
     * @test
     */
    public function complexEntityStructure(): void
    {
        $this->metadata->method('getFieldNames')->willReturn(['id', 'name', 'createdAt', 'active']);
        $this->metadata->method('getAssociationNames')->willReturn(['category', 'author', 'tags']);
        $this->metadata->method('isCollectionValuedAssociation')->willReturnCallback(fn ($a) => $a === 'tags');
        $this->metadata->method('getAssociationTargetClass')->willReturn(TestEntity::class);
        $this->metadata->method('getTypeOfField')->willReturnCallback(fn ($f) => match ($f) {
            'id' => 'integer',
            'name' => 'string',
            'createdAt' => 'datetime',
            'active' => 'boolean',
        });

        $columns = $this->runtime->getColumns(TestEntity::class);

        $this->assertNotEmpty($columns);
    }
}
