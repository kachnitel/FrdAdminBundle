<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Twig\Runtime;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\Proxy;
use Kachnitel\AdminBundle\DataSource\DoctrineItemValueResolver;
use Kachnitel\AdminBundle\Service\ColumnPermissionService;
use Kachnitel\AdminBundle\Twig\Runtime\AdminEntityDataRuntime;
use Kachnitel\AdminBundle\Utils\ObjectHelper;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(AdminEntityDataRuntime::class)]
#[UsesClass(DoctrineItemValueResolver::class)]
#[UsesClass(ObjectHelper::class)]
#[AllowMockObjectsWithoutExpectations]
final class AdminEntityDataRuntimeTest extends TestCase
{
    /** @var ClassMetadata<object>&MockObject */
    private ClassMetadata $metadata;

    private AdminEntityDataRuntime $runtime;
    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $em;

    /** @var ColumnPermissionService&MockObject */
    private ColumnPermissionService $columnPermissions;

    /** @var list<string> */
    private array $deniedColumns = [];

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->metadata = $this->createMock(ClassMetadata::class);
        $this->columnPermissions = $this->createMock(ColumnPermissionService::class);
        $this->columnPermissions->method('getDeniedColumnsForAction')
            ->willReturnCallback(fn (): array => $this->deniedColumns);

        $this->em->method('getClassMetadata')->willReturn($this->metadata);

        $this->runtime = new AdminEntityDataRuntime(
            em: $this->em,
            resolver: new DoctrineItemValueResolver(),
            columnPermissions: $this->columnPermissions,
        );
    }

    // ── isAssociation ──────────────────────────────────────────────────────────

    #[Test]
    public function isAssociationReturnsTrueForSingleValuedAssociation(): void
    {
        $entity = new \stdClass();
        $this->metadata->expects($this->once())->method('isSingleValuedAssociation')->with('category')->willReturn(true);
        $this->metadata->expects($this->never())->method('isCollectionValuedAssociation');

        $this->assertTrue($this->runtime->isAssociation($entity, 'category'));
    }

    #[Test]
    public function isAssociationReturnsTrueForCollectionValuedAssociation(): void
    {
        $entity = new \stdClass();
        $this->metadata->expects($this->once())->method('isSingleValuedAssociation')->with('tags')->willReturn(false);
        $this->metadata->expects($this->once())->method('isCollectionValuedAssociation')->with('tags')->willReturn(true);

        $this->assertTrue($this->runtime->isAssociation($entity, 'tags'));
    }

    #[Test]
    public function isAssociationReturnsFalseForRegularField(): void
    {
        $entity = new \stdClass();
        $this->metadata->expects($this->once())->method('isSingleValuedAssociation')->with('name')->willReturn(false);
        $this->metadata->expects($this->once())->method('isCollectionValuedAssociation')->with('name')->willReturn(false);

        $this->assertFalse($this->runtime->isAssociation($entity, 'name'));
    }

    // ── getAssociationType ─────────────────────────────────────────────────────

    #[Test]
    public function getAssociationTypeReturnsTargetClassForAssociation(): void
    {
        $entity = new \stdClass();
        $this->metadata->method('isSingleValuedAssociation')->willReturn(true);
        $this->metadata->method('isCollectionValuedAssociation')->willReturn(false);
        $this->metadata->expects($this->once())->method('getAssociationTargetClass')->with('category')->willReturn('App\\Entity\\Category');

        $result = $this->runtime->getAssociationType($entity, 'category');

        $this->assertSame('App\\Entity\\Category', $result);
    }

    #[Test]
    public function getAssociationTypeReturnsNullForNonAssociation(): void
    {
        $entity = new \stdClass();
        $this->metadata->method('isSingleValuedAssociation')->willReturn(false);
        $this->metadata->method('isCollectionValuedAssociation')->willReturn(false);

        $result = $this->runtime->getAssociationType($entity, 'name');

        $this->assertNull($result);
    }

    #[Test]
    public function getAssociationTypeReturnsTargetClassForCollection(): void
    {
        $entity = new \stdClass();
        $this->metadata->expects($this->once())->method('isSingleValuedAssociation')->with('tags')->willReturn(false);
        $this->metadata->expects($this->once())->method('isCollectionValuedAssociation')->with('tags')->willReturn(true);
        $this->metadata->expects($this->once())->method('getAssociationTargetClass')->with('tags')->willReturn('App\\Entity\\Tag');

        $result = $this->runtime->getAssociationType($entity, 'tags');

        $this->assertSame('App\\Entity\\Tag', $result);
    }

    // ── isCollection ──────────────────────────────────────────────────────────

    #[Test]
    public function isCollectionReturnsTrueForCollectionValuedAssociation(): void
    {
        $entity = new \stdClass();
        $this->metadata->expects($this->once())->method('isCollectionValuedAssociation')->with('items')->willReturn(true);

        $this->assertTrue($this->runtime->isCollection($entity, 'items'));
    }

    #[Test]
    public function isCollectionReturnsFalseForSingleValuedAssociation(): void
    {
        $entity = new \stdClass();
        $this->metadata->expects($this->once())->method('isCollectionValuedAssociation')->with('owner')->willReturn(false);

        $this->assertFalse($this->runtime->isCollection($entity, 'owner'));
    }

    // ── getEntityLabel ────────────────────────────────────────────────────────

    #[Test]
    public function getEntityLabelUsesGetNameMethod(): void
    {
        $entity = new class {
            public function getName(): string { return 'Test Product'; }
        };

        $this->assertSame('Test Product', $this->runtime->getEntityLabel($entity));
    }

    #[Test]
    public function getEntityLabelFallsBackToGetLabel(): void
    {
        $entity = new class {
            public function getLabel(): string { return 'My Label'; }
        };

        $this->assertSame('My Label', $this->runtime->getEntityLabel($entity));
    }

    #[Test]
    public function getEntityLabelFallsBackToGetTitle(): void
    {
        $entity = new class {
            public function getTitle(): string { return 'My Title'; }
        };

        $this->assertSame('My Title', $this->runtime->getEntityLabel($entity));
    }

    #[Test]
    public function getEntityLabelFallsBackToToString(): void
    {
        $entity = new class {
            public function __toString(): string { return 'String Rep'; }
        };

        $this->assertSame('String Rep', $this->runtime->getEntityLabel($entity));
    }

    #[Test]
    public function getEntityLabelFallsBackToGetId(): void
    {
        $entity = new class {
            public function getId(): int { return 42; }
        };

        $this->assertSame('#42', $this->runtime->getEntityLabel($entity));
    }

    #[Test]
    public function getEntityLabelFallsBackToClassName(): void
    {
        $entity = new \stdClass();

        $this->assertSame('stdClass', $this->runtime->getEntityLabel($entity));
    }

    #[Test]
    public function getEntityLabelUsesCustomGetterWhenProvided(): void
    {
        $entity = new class {
            public function getDisplayName(): string { return 'Custom Display'; }
            public function getName(): string { return 'Should Not Use'; }
        };

        $this->assertSame('Custom Display', $this->runtime->getEntityLabel($entity, 'getDisplayName'));
    }

    #[Test]
    public function getEntityLabelPrefersLabelOverNameAndTitle(): void
    {
        $entity = new class {
            public function getName(): string { return 'From name'; }
            public function getLabel(): string { return 'From label'; }
            public function getTitle(): string { return 'From title'; }
        };

        $this->assertSame('From label', $this->runtime->getEntityLabel($entity));
    }

    // ── getPropertyType ───────────────────────────────────────────────────────

    #[Test]
    public function getPropertyTypeReturnsDoctrineFieldType(): void
    {
        $entity = new \stdClass();
        $this->metadata->method('isSingleValuedAssociation')->willReturn(false);
        $this->metadata->method('isCollectionValuedAssociation')->willReturn(false);
        $this->metadata->expects($this->once())->method('getTypeOfField')->with('createdAt')->willReturn('datetime');

        $this->assertSame('datetime', $this->runtime->getPropertyType($entity, 'createdAt'));
    }

    #[Test]
    public function getPropertyTypeReturnsTargetClassForAssociation(): void
    {
        $entity = new \stdClass();
        $this->metadata->expects($this->once())->method('isSingleValuedAssociation')->with('category')->willReturn(true);
        $this->metadata->method('isCollectionValuedAssociation')->willReturn(false);
        $this->metadata->expects($this->once())->method('getAssociationTargetClass')->with('category')->willReturn('App\\Entity\\Category');

        $this->assertSame('App\\Entity\\Category', $this->runtime->getPropertyType($entity, 'category'));
    }

    #[Test]
    public function getPropertyTypeReturnsStringWhenFieldTypeIsNull(): void
    {
        $entity = new \stdClass();
        $this->metadata->method('isSingleValuedAssociation')->willReturn(false);
        $this->metadata->method('isCollectionValuedAssociation')->willReturn(false);
        $this->metadata->method('getTypeOfField')->willReturn(null);

        $this->assertSame('string', $this->runtime->getPropertyType($entity, 'unknown'));
    }

    // ── getColumns ────────────────────────────────────────────────────────────

    #[Test]
    public function getColumnsReturnsMappedFieldNamesAndSingleValuedAssociations(): void
    {
        $this->metadata->method('getFieldNames')->willReturn(['id', 'name', 'active']);
        $this->metadata->method('getAssociationNames')->willReturn(['category', 'tags']);
        $this->metadata->expects($this->atLeast(2))->method('isCollectionValuedAssociation')
            ->willReturnMap([['category', false], ['tags', true]]);

        $columns = $this->runtime->getColumns(\stdClass::class);

        $this->assertContains('id', $columns);
        $this->assertContains('name', $columns);
        $this->assertContains('category', $columns);
        $this->assertNotContains('tags', $columns, 'Collection-valued associations must be excluded');
    }

    #[Test]
    public function getColumnsOmitsDeniedFieldsAndAssociations(): void
    {
        $this->metadata->method('getFieldNames')->willReturn(['id', 'salary']);
        $this->metadata->method('getAssociationNames')->willReturn(['department']);
        $this->metadata->method('isCollectionValuedAssociation')->willReturn(false);
        $this->deniedColumns = ['salary', 'department'];

        $this->assertSame(['id'], $this->runtime->getColumns(\stdClass::class));
    }
    #[Test]
    public function getColumnsResolvesProxyClassBeforeReadingMetadata(): void
    {
        $this->em->expects($this->once())
            ->method('getClassMetadata')
            ->with(RuntimeProxyBaseEntity::class)
            ->willReturn($this->metadata);
        $this->metadata->method('getFieldNames')->willReturn(['name']);
        $this->metadata->method('getAssociationNames')->willReturn([]);
        $this->deniedColumns = [];

        $this->assertSame(['name'], $this->runtime->getColumns(RuntimeProxyEntity::class));
    }

    #[Test]
    public function getDataOmitsDeniedFieldsAndAssociations(): void
    {
        $this->metadata->method('getFieldNames')->willReturn(['visible', 'secret']);
        $this->metadata->method('getAssociationNames')->willReturn(['privateAssociation']);
        $this->deniedColumns = ['secret', 'privateAssociation'];
        $this->metadata->method('hasField')->willReturnCallback(static fn (string $field): bool => $field === 'visible');
        $this->metadata->method('getFieldValue')->willReturn('shown');

        $data = $this->runtime->getData(new \stdClass());

        $this->assertSame(['visible' => 'shown'], $data);
    }
}
class RuntimeProxyBaseEntity {}

/** @implements Proxy<object> */
class RuntimeProxyEntity extends RuntimeProxyBaseEntity implements Proxy
{
    public function __load(): void {}

    public function __isInitialized(): bool
    {
        return true;
    }
}
