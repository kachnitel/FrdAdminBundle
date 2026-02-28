<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Integration\Service;

use Doctrine\ORM\EntityManagerInterface;
use Kachnitel\AdminBundle\Attribute\ColumnPermission;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\Service\ColumnPermissionService;
use Kachnitel\AdminBundle\Twig\Components\Field\StringField;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Integration tests for the permission system.
 *
 * Key fix vs original: willReturnMap() only matches calls whose argument list
 * exactly matches the map entry length. AuthorizationCheckerInterface::isGranted()
 * can be called with 1 arg (role check) or 2 args (entity check). Using
 * willReturnCallback() reliably covers both.
 *
 * @covers \Kachnitel\AdminBundle\Service\ColumnPermissionService
 * @covers \Kachnitel\AdminBundle\Twig\Components\Field\AbstractEditableField
 */
class PermissionIntegrationTest extends TestCase
{
    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $entityManager;

    /** @var AuthorizationCheckerInterface&MockObject */
    private AuthorizationCheckerInterface $authorizationChecker;

    private PropertyAccessor $propertyAccessor;

    private ColumnPermissionService $permissionService;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $this->propertyAccessor = new PropertyAccessor();
        $this->permissionService = new ColumnPermissionService($this->authorizationChecker);
    }

    public function testFieldComponentRespectsColumnPermissions(): void
    {
        $entity = new PermissionProductEntity();
        $entity->setCost(99.99);

        // Entity-level: granted; column-level edit: denied
        $this->authorizationChecker
            ->method('isGranted')
            ->willReturnCallback(function (mixed $attribute, mixed $subject = null) use ($entity): bool {
                if ($attribute === AdminEntityVoter::ADMIN_EDIT && $subject === $entity) {
                    return true;
                }
                if ($attribute === 'ROLE_PRODUCT_COST_EDIT') {
                    return false;
                }
                return false;
            });

        $field = $this->makeStringField($entity, 'cost', true);

        $this->assertFalse($field->canEdit());
    }

    public function testFieldComponentAllowsEditWithBothPermissions(): void
    {
        $entity = new PermissionProductEntity();
        $entity->setCost(99.99);

        $this->authorizationChecker
            ->method('isGranted')
            ->willReturnCallback(function (mixed $attribute, mixed $subject = null): bool {
                if ($attribute === AdminEntityVoter::ADMIN_EDIT && $subject === 'PermissionProductEntity') {
                    return true;
                }
                if ($attribute === 'ROLE_PRODUCT_COST_EDIT') {
                    return true;
                }
                return false;
            });

        $field = $this->makeStringField($entity, 'cost', true);

        $this->assertTrue($field->canEdit());
    }

    public function testSaveActionEnforcesEditPermissions(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Access denied');

        $entity = new PermissionProductEntity();
        $entity->setCost(99.99);

        $this->authorizationChecker
            ->method('isGranted')
            ->willReturnCallback(function (mixed $attribute, mixed $subject = null) use ($entity): bool {
                if ($attribute === AdminEntityVoter::ADMIN_EDIT && $subject === $entity) {
                    return true;
                }
                if ($attribute === 'ROLE_PRODUCT_COST_EDIT') {
                    return false;
                }
                return false;
            });

        $field = $this->makeStringField($entity, 'cost', true);
        $field->save();
    }

    public function testColumnPermissionServiceFiltersCorrectly(): void
    {
        $this->authorizationChecker
            ->method('isGranted')
            ->willReturnCallback(fn(mixed $role): bool => false); // all denied

        $permitted = $this->permissionService->getPermittedColumns(
            PermissionProductEntity::class,
            ['id', 'name', 'price', 'cost'],
            AdminEntityVoter::ADMIN_SHOW
        );

        $this->assertContains('id', $permitted);
        $this->assertContains('name', $permitted);
        $this->assertContains('price', $permitted);
        $this->assertNotContains('cost', $permitted);
    }

    public function testDifferentPermissionsForShowAndEdit(): void
    {
        $this->authorizationChecker
            ->method('isGranted')
            ->willReturnCallback(function (mixed $role): bool {
                return $role === 'ROLE_PRODUCT_COST_SHOW'; // can view, not edit
            });

        $this->assertTrue(
            $this->permissionService->canPerformAction(
                PermissionProductEntity::class,
                'cost',
                AdminEntityVoter::ADMIN_SHOW
            )
        );

        $this->assertFalse(
            $this->permissionService->canPerformAction(
                PermissionProductEntity::class,
                'cost',
                AdminEntityVoter::ADMIN_EDIT
            )
        );
    }

    public function testArrayOfRolesUsesOrLogic(): void
    {
        $this->authorizationChecker
            ->method('isGranted')
            ->willReturnCallback(fn(mixed $role): bool => $role === 'ROLE_MANAGER');

        // internalNotes requires ROLE_ADMIN or ROLE_MANAGER for ADMIN_SHOW
        $this->assertTrue(
            $this->permissionService->canPerformAction(
                PermissionProductEntity::class,
                'internalNotes',
                AdminEntityVoter::ADMIN_SHOW
            )
        );
    }

    private function makeStringField(object $entity, string $property, bool $editMode): StringField
    {
        $field = new StringField(
            $this->entityManager,
            $this->propertyAccessor,
            $this->authorizationChecker,
        );
        $field->property = $property;
        $field->editMode = $editMode;
        $field->mount($entity, $property);

        return $field;
    }
}

class PermissionProductEntity
{
    private int $id;
    private ?string $name = null;
    private ?float $price = null;

    public function __construct(int $id = 1)
    {
        $this->id = $id;
    }

    #[ColumnPermission([
        AdminEntityVoter::ADMIN_SHOW => 'ROLE_PRODUCT_COST_SHOW',
        AdminEntityVoter::ADMIN_EDIT => 'ROLE_PRODUCT_COST_EDIT',
    ])]
    private ?float $cost = null;

    #[ColumnPermission([
        AdminEntityVoter::ADMIN_SHOW => ['ROLE_ADMIN', 'ROLE_MANAGER'],
        AdminEntityVoter::ADMIN_EDIT => 'ROLE_ADMIN',
    ])]
    private ?string $internalNotes = null;

    public function getId(): ?int { return $this->id; }
    public function getName(): ?string { return $this->name; }
    public function setName(?string $v): self { $this->name = $v; return $this; }
    public function getPrice(): ?float { return $this->price; }
    public function setPrice(?float $v): self { $this->price = $v; return $this; }
    public function getCost(): ?float { return $this->cost; }
    public function setCost(?float $v): self { $this->cost = $v; return $this; }
    public function getInternalNotes(): ?string { return $this->internalNotes; }
    public function setInternalNotes(?string $v): self { $this->internalNotes = $v; return $this; }
}