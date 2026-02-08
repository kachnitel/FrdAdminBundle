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
    public function getDataReturnsExpectedKeys(): void
    {
        $entity = new class {
            public int $id = 1;
            public string $name = 'Test';

            public function getId(): int
            {
                return $this->id;
            }

            public function getName(): string
            {
                return $this->name;
            }
        };

        $this->metadata->method('getFieldNames')->willReturn(['id', 'name']);
        $this->metadata->method('getAssociationNames')->willReturn([]);

        $data = $this->runtime->getData($entity);

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertCount(2, $data);
    }

    /**
     * @test
     */
    public function getDataReturnsFieldValues(): void
    {
        $entity = new class {
            public int $id = 42;

            public function getId(): int
            {
                return $this->id;
            }
        };

        $this->metadata->method('getFieldNames')->willReturn(['id']);
        $this->metadata->method('getAssociationNames')->willReturn([]);
        $this->normalizer->method('normalize')->willReturnArgument(0);

        $data = $this->runtime->getData($entity);

        $this->assertSame(42, $data['id']);
    }

    /**
     * @test
     */
    public function getColumnsReturnsFieldNames(): void
    {
        $this->metadata->method('getFieldNames')->willReturn(['id', 'name', 'email']);
        $this->metadata->method('getAssociationNames')->willReturn([]);

        $columns = $this->runtime->getColumns(TestEntity::class);

        $this->assertSame(['id', 'name', 'email'], $columns);
    }

    /**
     * @test
     */
    public function getColumnsIncludesAllFields(): void
    {
        $fieldNames = ['id', 'name', 'description', 'createdAt'];
        $this->metadata->method('getFieldNames')->willReturn($fieldNames);
        $this->metadata->method('getAssociationNames')->willReturn([]);

        $columns = $this->runtime->getColumns(TestEntity::class);

        $this->assertCount(4, $columns);
        $this->assertContains('id', $columns);
        $this->assertContains('name', $columns);
        $this->assertContains('description', $columns);
        $this->assertContains('createdAt', $columns);
    }

    /**
     * @test
     */
    public function getColumnsIncludesSingleValuedAssociations(): void
    {
        $this->metadata->method('getFieldNames')->willReturn(['id', 'name']);
        $this->metadata->method('getAssociationNames')->willReturn(['category', 'author']);
        $this->metadata->method('isCollectionValuedAssociation')->willReturn(false);

        $columns = $this->runtime->getColumns(TestEntity::class);

        $this->assertCount(4, $columns);
        $this->assertContains('category', $columns);
        $this->assertContains('author', $columns);
    }

    /**
     * @test
     */
    public function getColumnsReturnsEmptyArrayForEmptyEntity(): void
    {
        $this->metadata->method('getFieldNames')->willReturn([]);
        $this->metadata->method('getAssociationNames')->willReturn([]);

        $columns = $this->runtime->getColumns(TestEntity::class);

        $this->assertSame([], $columns);
    }

    /**
     * @test
     */
    public function getColumnsWithSingleStringField(): void
    {
        $this->metadata->method('getFieldNames')->willReturn(['name']);
        $this->metadata->method('getAssociationNames')->willReturn([]);

        $columns = $this->runtime->getColumns(TestEntity::class);

        $this->assertSame(['name'], $columns);
    }

    /**
     * @test
     */
    public function getColumnsWithSingleIntegerField(): void
    {
        $this->metadata->method('getFieldNames')->willReturn(['id']);
        $this->metadata->method('getAssociationNames')->willReturn([]);

        $columns = $this->runtime->getColumns(TestEntity::class);

        $this->assertSame(['id'], $columns);
    }

    /**
     * @test
     */
    public function getColumnsWithDatetimeField(): void
    {
        $this->metadata->method('getFieldNames')->willReturn(['createdAt']);
        $this->metadata->method('getAssociationNames')->willReturn([]);

        $columns = $this->runtime->getColumns(TestEntity::class);

        $this->assertSame(['createdAt'], $columns);
    }

    /**
     * @test
     */
    public function getColumnsWithBooleanField(): void
    {
        $this->metadata->method('getFieldNames')->willReturn(['isActive']);
        $this->metadata->method('getAssociationNames')->willReturn([]);

        $columns = $this->runtime->getColumns(TestEntity::class);

        $this->assertSame(['isActive'], $columns);
    }

    /**
     * @test
     */
    public function singleValuedAssociationIncludedInColumns(): void
    {
        $this->metadata->method('getFieldNames')->willReturn([]);
        $this->metadata->method('getAssociationNames')->willReturn(['relatedEntity']);
        $this->metadata->method('isCollectionValuedAssociation')->with('relatedEntity')->willReturn(false);

        $columns = $this->runtime->getColumns(TestEntity::class);

        $this->assertSame(['relatedEntity'], $columns);
    }

    /**
     * @test
     */
    public function collectionAssociationExcludedFromColumns(): void
    {
        $this->metadata->method('getFieldNames')->willReturn([]);
        $this->metadata->method('getAssociationNames')->willReturn(['items']);
        $this->metadata->method('isCollectionValuedAssociation')->with('items')->willReturn(true);

        $columns = $this->runtime->getColumns(TestEntity::class);

        $this->assertNotContains('items', $columns);
        $this->assertSame([], $columns);
    }

    /**
     * @test
     */
    public function multipleFieldsAllIncluded(): void
    {
        $this->metadata->method('getFieldNames')->willReturn(['id', 'name', 'price', 'active']);
        $this->metadata->method('getAssociationNames')->willReturn([]);

        $columns = $this->runtime->getColumns(TestEntity::class);

        $this->assertSame(['id', 'name', 'price', 'active'], $columns);
    }

    /**
     * @test
     */
    public function complexEntityExcludesCollectionAssociations(): void
    {
        $this->metadata->method('getFieldNames')->willReturn(['id', 'name', 'createdAt', 'active']);
        $this->metadata->method('getAssociationNames')->willReturn(['category', 'author', 'tags']);
        $this->metadata->method('isCollectionValuedAssociation')->willReturnCallback(fn ($a) => $a === 'tags');

        $columns = $this->runtime->getColumns(TestEntity::class);

        $this->assertContains('id', $columns);
        $this->assertContains('name', $columns);
        $this->assertContains('category', $columns);
        $this->assertContains('author', $columns);
        $this->assertNotContains('tags', $columns);
        $this->assertCount(6, $columns);
    }
}
