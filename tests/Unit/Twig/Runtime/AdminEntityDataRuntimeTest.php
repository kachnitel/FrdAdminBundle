<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Twig\Runtime;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Kachnitel\AdminBundle\DataSource\DoctrineItemValueResolver;
use Kachnitel\AdminBundle\Service\AttributeHelper;
use Kachnitel\AdminBundle\Tests\Fixtures\TestEntity;
use Kachnitel\AdminBundle\Twig\Runtime\AdminEntityDataRuntime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class AdminEntityDataRuntimeTest extends TestCase
{
    private AdminEntityDataRuntime $runtime;
    private MockObject&EntityManagerInterface $em;
    private MockObject&DoctrineItemValueResolver $resolver;
    private MockObject&NormalizerInterface $normalizer;
    /** @var ClassMetadata<TestEntity>&MockObject */
    private MockObject&ClassMetadata $metadata;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->resolver = $this->createMock(DoctrineItemValueResolver::class);
        $this->normalizer = $this->createMock(NormalizerInterface::class);
        $this->metadata = $this->createMock(ClassMetadata::class);

        $this->em->method('getClassMetadata')->willReturn($this->metadata);

        $this->runtime = new AdminEntityDataRuntime(
            $this->em,
            new AttributeHelper(),
            $this->resolver,
            $this->normalizer
        );
    }

    #[Test]
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
        $this->resolver->method('resolve')->willReturnCallback(fn ($entity, $field) => match ($field) {
            'id' => 1,
            'name' => 'Test',
            default => null,
        });

        $data = $this->runtime->getData($entity);

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertCount(2, $data);
    }

    #[Test]
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
        $this->resolver->method('resolve')->willReturn(42);
        $this->normalizer->method('normalize')->willReturnArgument(0);

        $data = $this->runtime->getData($entity);

        $this->assertSame(42, $data['id']);
    }

    #[Test]
    public function getColumnsReturnsFieldNames(): void
    {
        $this->metadata->method('getFieldNames')->willReturn(['id', 'name', 'email']);
        $this->metadata->method('getAssociationNames')->willReturn([]);

        $columns = $this->runtime->getColumns(TestEntity::class);

        $this->assertSame(['id', 'name', 'email'], $columns);
    }

    #[Test]
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

    #[Test]
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

    #[Test]
    public function getColumnsReturnsEmptyArrayForEmptyEntity(): void
    {
        $this->metadata->method('getFieldNames')->willReturn([]);
        $this->metadata->method('getAssociationNames')->willReturn([]);

        $columns = $this->runtime->getColumns(TestEntity::class);

        $this->assertSame([], $columns);
    }

    #[Test]
    public function getColumnsWithSingleStringField(): void
    {
        $this->metadata->method('getFieldNames')->willReturn(['name']);
        $this->metadata->method('getAssociationNames')->willReturn([]);

        $columns = $this->runtime->getColumns(TestEntity::class);

        $this->assertSame(['name'], $columns);
    }

    #[Test]
    public function getColumnsWithSingleIntegerField(): void
    {
        $this->metadata->method('getFieldNames')->willReturn(['id']);
        $this->metadata->method('getAssociationNames')->willReturn([]);

        $columns = $this->runtime->getColumns(TestEntity::class);

        $this->assertSame(['id'], $columns);
    }

    #[Test]
    public function getColumnsWithDatetimeField(): void
    {
        $this->metadata->method('getFieldNames')->willReturn(['createdAt']);
        $this->metadata->method('getAssociationNames')->willReturn([]);

        $columns = $this->runtime->getColumns(TestEntity::class);

        $this->assertSame(['createdAt'], $columns);
    }

    #[Test]
    public function getColumnsWithBooleanField(): void
    {
        $this->metadata->method('getFieldNames')->willReturn(['isActive']);
        $this->metadata->method('getAssociationNames')->willReturn([]);

        $columns = $this->runtime->getColumns(TestEntity::class);

        $this->assertSame(['isActive'], $columns);
    }

    #[Test]
    public function singleValuedAssociationIncludedInColumns(): void
    {
        $this->metadata->method('getFieldNames')->willReturn([]);
        $this->metadata->method('getAssociationNames')->willReturn(['relatedEntity']);
        $this->metadata->method('isCollectionValuedAssociation')->with('relatedEntity')->willReturn(false);

        $columns = $this->runtime->getColumns(TestEntity::class);

        $this->assertSame(['relatedEntity'], $columns);
    }

    #[Test]
    public function collectionAssociationExcludedFromColumns(): void
    {
        $this->metadata->method('getFieldNames')->willReturn([]);
        $this->metadata->method('getAssociationNames')->willReturn(['items']);
        $this->metadata->method('isCollectionValuedAssociation')->with('items')->willReturn(true);

        $columns = $this->runtime->getColumns(TestEntity::class);

        $this->assertNotContains('items', $columns);
        $this->assertSame([], $columns);
    }

    #[Test]
    public function multipleFieldsAllIncluded(): void
    {
        $this->metadata->method('getFieldNames')->willReturn(['id', 'name', 'price', 'active']);
        $this->metadata->method('getAssociationNames')->willReturn([]);

        $columns = $this->runtime->getColumns(TestEntity::class);

        $this->assertSame(['id', 'name', 'price', 'active'], $columns);
    }

    #[Test]
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

    // getEntityLabel - test all label resolution priorities: custom method → getLabel → getName → getTitle → __toString → #id
    #[Test]
    #[DataProvider('entityLabelProvider')]
    public function getEntityLabelReturnsCustomMethod(object $entity, string $expectedLabel, ?string $method = null): void
    {
        $label = $this->runtime->getEntityLabel($entity, $method);

        $this->assertSame($expectedLabel, $label);
    }

    public static function entityLabelProvider(): array // @phpstan-ignore missingType.iterableValue
    {
        return [
            'custom method' => [
                new class {
                    public function getCustomLabel(): string
                    {
                        return 'Custom Label';
                    }
                },
                'Custom Label',
                'getCustomLabel',
            ],
            'getLabel method' => [
                new class {
                    public function getLabel(): string
                    {
                        return 'Label Method';
                    }
                },
                'Label Method',
            ],
            'getName method' => [
                new class {
                    public function getName(): string
                    {
                        return 'Name Method';
                    }
                },
                'Name Method',
            ],
            'getTitle method' => [
                new class {
                    public function getTitle(): string
                    {
                        return 'Title Method';
                    }
                },
                'Title Method',
            ],
            '__toString method' => [
                new class {
                    public function __toString(): string
                    {
                        return 'ToString Method';
                    }
                },
                'ToString Method',
            ],
            '#id fallback' => [
                new class {
                    public int $id = 123;
                },
                '#123',
            ],
            'getId method fallback' => [
                new class {
                    public function getId(): int
                    {
                        return 456;
                    }
                },
                '#456',
            ],
        ];
    }
}
