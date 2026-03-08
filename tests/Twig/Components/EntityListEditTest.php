<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Twig\Components;

use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Config\EntityListConfig;
use Kachnitel\AdminBundle\DataSource\DataSourceRegistry;
use Kachnitel\AdminBundle\Service\Preferences\AdminPreferencesStorageInterface;
use Kachnitel\AdminBundle\Service\EntityListBatchService;
use Kachnitel\AdminBundle\Service\EntityListColumnService;
use Kachnitel\AdminBundle\Service\EntityListPermissionService;
use Kachnitel\AdminBundle\Twig\Components\EntityList;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for EntityList row-level editing.
 *
 * canEditRow() and editRow() are tested by mocking EntityListPermissionService,
 * which now owns the inline-edit logic (attribute flag + voter).
 *
 * @covers \Kachnitel\AdminBundle\Twig\Components\EntityList
 */
#[UsesClass(EntityListConfig::class)]
class EntityListEditTest extends TestCase
{
    /** @var EntityListPermissionService&MockObject */
    private EntityListPermissionService $permissionService;

    private EntityList $component;

    protected function setUp(): void
    {
        $this->permissionService = $this->createMock(EntityListPermissionService::class);

        // Allow inline editing and list viewing by default; override in specific tests.
        $this->permissionService->method('canInlineEdit')->willReturn(true);
        $this->permissionService->method('canViewList')->willReturn(true);

        $this->component = $this->makeComponent($this->permissionService);
    }

    public function testEditingRowIdIsNullByDefault(): void
    {
        $this->assertNull($this->component->editingRowId);
    }

    public function testCanActivateEditModeForRow(): void
    {
        $entity = new TestListEntity(1, 'Test');
        $this->component->editRow($entity->getId());
        $this->assertSame(1, $this->component->editingRowId);
    }

    public function testIsRowEditingReturnsTrueForEditingRow(): void
    {
        $entity1 = new TestListEntity(1, 'Test 1');
        $entity2 = new TestListEntity(2, 'Test 2');

        $this->component->editRow($entity1->getId());

        $this->assertTrue($this->component->isRowEditing($entity1));
        $this->assertFalse($this->component->isRowEditing($entity2));
    }

    public function testOnlyOneRowCanBeEditedAtATime(): void
    {
        $entity1 = new TestListEntity(1, 'Test 1');
        $entity2 = new TestListEntity(2, 'Test 2');

        $this->component->editRow($entity1->getId());
        $this->assertSame(1, $this->component->editingRowId);

        $this->component->editRow($entity2->getId());
        $this->assertSame(2, $this->component->editingRowId);
        $this->assertFalse($this->component->isRowEditing($entity1));
        $this->assertTrue($this->component->isRowEditing($entity2));
    }

    public function testCanEditRowReturnsTrueWhenPermissionServiceAllows(): void
    {
        // Default setUp already configures canInlineEdit → true
        $this->assertTrue($this->component->canEditRow());
    }

    public function testCanEditRowReturnsFalseWhenPermissionServiceDenies(): void
    {
        /** @var EntityListPermissionService&MockObject $permissionService */
        $permissionService = $this->createMock(EntityListPermissionService::class);
        $permissionService->method('canInlineEdit')->willReturn(false);

        $component = $this->makeComponent($permissionService);
        $this->assertFalse($component->canEditRow());
    }

    public function testCanEditRowDelegatesToPermissionServiceWithCorrectArgs(): void
    {
        /** @var EntityListPermissionService&MockObject $permissionService */
        $permissionService = $this->createMock(EntityListPermissionService::class);
        $permissionService->expects($this->once())
            ->method('canInlineEdit')
            ->with(TestListEntity::class, 'TestListEntity')
            ->willReturn(true);

        $component = $this->makeComponent($permissionService);
        $component->canEditRow();
    }

    public function testEditRowThrowsExceptionWithoutPermission(): void
    {
        $this->expectException(\Symfony\Component\Security\Core\Exception\AccessDeniedException::class);
        $this->expectExceptionMessage('Access denied for inline editing');

        /** @var EntityListPermissionService&MockObject $permissionService */
        $permissionService = $this->createMock(EntityListPermissionService::class);
        $permissionService->method('canInlineEdit')->willReturn(false);

        $component = $this->makeComponent($permissionService);
        $component->editRow(1);
    }

    /**
     * Entity self-validation (setter throws) prevents invalid data from reaching saveRow.
     */
    public function testEntitySelfValidationPreventsInvalidData(): void
    {
        $entity = new TestListEntity(1, 'Test');
        $this->component->editRow($entity->getId());

        $this->expectException(\InvalidArgumentException::class);
        $entity->setName(''); // throws before saveRow is reached
    }

    private function makeComponent(EntityListPermissionService $permissionService): EntityList
    {
        $component = new EntityList(
            $permissionService,
            new EntityListConfig(),
            $this->createMock(DataSourceRegistry::class),
            $this->createMock(EntityListBatchService::class),
            $this->createMock(AdminPreferencesStorageInterface::class),
            $this->createMock(EntityListColumnService::class),
        );
        $component->entityClass = TestListEntity::class;
        $component->entityShortClass = 'TestListEntity';
        return $component;
    }
}

#[Admin(enableInlineEdit: true)]
class TestListEntity
{
    public function __construct(
        private int $id,
        private string $name,
        private string $email = '',
    ) {}

    public function getId(): int { return $this->id; }
    public function getName(): string { return $this->name; }

    public function setName(string $name): self
    {
        if (empty($name)) {
            throw new \InvalidArgumentException('Name cannot be empty');
        }
        $this->name = $name;
        return $this;
    }

    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): self { $this->email = $email; return $this; }
}
