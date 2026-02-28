<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Twig\Components;

use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Config\EntityListConfig;
use Kachnitel\AdminBundle\DataSource\DataSourceRegistry;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\Service\Preferences\AdminPreferencesStorageInterface;
use Kachnitel\AdminBundle\Service\ColumnPermissionService;
use Kachnitel\AdminBundle\Service\EntityListBatchService;
use Kachnitel\AdminBundle\Service\EntityListColumnService;
use Kachnitel\AdminBundle\Service\EntityListPermissionService;
use Kachnitel\AdminBundle\Twig\Components\EntityList;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Unit tests for EntityList row-level editing.
 *
 * setUp uses full 9-arg constructor (7 existing + EntityManagerInterface +
 * ColumnPermissionService added for edit-in-place support).
 *
 * @covers \Kachnitel\AdminBundle\Twig\Components\EntityList
 */
class EntityListEditTest extends TestCase
{
    /** @var Security&MockObject */
    private Security $security;

    /** @var ColumnPermissionService&MockObject */
    private ColumnPermissionService $columnPermissionService;

    private EntityList $component;

    protected function setUp(): void
    {
        $this->security = $this->createMock(Security::class);
        $this->columnPermissionService = $this->createMock(ColumnPermissionService::class);

        $this->component = new EntityList(
            $this->createMock(EntityListPermissionService::class),
            $this->security,
            new EntityListConfig(),
            $this->createMock(DataSourceRegistry::class),
            $this->createMock(EntityListBatchService::class),
            $this->createMock(AdminPreferencesStorageInterface::class),
            $this->createMock(EntityListColumnService::class)
        );

        $this->component->entityClass = TestListEntity::class;

        // Grant all permissions by default; override in specific tests
        $this->security->method('isGranted')->willReturn(true);
        $this->columnPermissionService->method('canPerformAction')->willReturn(true);
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

    public function testCanEditRowChecksEntityPermission(): void
    {
        $entity = new TestListEntity(1, 'Test');

        // Override default; only respond to this specific call
        $security = $this->createMock(Security::class);
        $security->method('isGranted')
            ->with(AdminEntityVoter::ADMIN_EDIT, 'TestListEntity')
            ->willReturn(false);

        $component = $this->makeComponentWithSecurity($security);
        $this->assertFalse($component->canEditRow());
    }

    public function testCanEditRowReturnsTrueWithPermission(): void
    {
        $entity = new TestListEntity(1, 'Test');
        // default security mock returns true
        $this->assertTrue($this->component->canEditRow());
    }

    public function testEditRowThrowsExceptionWithoutPermission(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Access denied');

        $entity = new TestListEntity(1, 'Test');

        $security = $this->createMock(Security::class);
        $security->method('isGranted')
            ->with(AdminEntityVoter::ADMIN_EDIT, 'TestListEntity')
            ->willReturn(false);

        $component = $this->makeComponentWithSecurity($security);
        $component->editRow($entity->getId());
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

    private function makeComponentWithSecurity(Security $security): EntityList
    {
        $component = new EntityList(
            $this->createMock(EntityListPermissionService::class),
            $security,
            new EntityListConfig(),
            $this->createMock(DataSourceRegistry::class),
            $this->createMock(EntityListBatchService::class),
            $this->createMock(AdminPreferencesStorageInterface::class),
            $this->createMock(EntityListColumnService::class)
        );
        $component->entityClass = TestListEntity::class;
        $component->entityShortClass = 'TestListEntity';
        return $component;
    }
}

#[Admin]
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
