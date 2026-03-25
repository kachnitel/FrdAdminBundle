<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Field;

use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Attribute\AdminColumn;
use Kachnitel\AdminBundle\Field\AdminEditabilityResolver;
use Kachnitel\AdminBundle\RowAction\RowActionExpressionLanguage;
use Kachnitel\AdminBundle\Service\AttributeHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * @covers \Kachnitel\AdminBundle\Field\AdminEditabilityResolver
 * @group inline-edit
 */
class AdminEditabilityResolverTest extends TestCase
{
    /** @var AttributeHelper&MockObject */
    private AttributeHelper $attributeHelper;

    /** @var AuthorizationCheckerInterface&MockObject */
    private AuthorizationCheckerInterface $authChecker;

    /** @var PropertyAccessorInterface&MockObject */
    private PropertyAccessorInterface $propertyAccessor;

    private RowActionExpressionLanguage $expressionLanguage;
    private AdminEditabilityResolver $resolver;

    protected function setUp(): void
    {
        $this->attributeHelper    = $this->createMock(AttributeHelper::class);
        $this->authChecker        = $this->createMock(AuthorizationCheckerInterface::class);
        $this->propertyAccessor   = $this->createMock(PropertyAccessorInterface::class);
        $this->expressionLanguage = new RowActionExpressionLanguage();

        $this->resolver = new AdminEditabilityResolver(
            $this->attributeHelper,
            $this->expressionLanguage,
            $this->authChecker,
            $this->propertyAccessor,
        );
    }

    private function stubEntityAdmin(?Admin $admin): void
    {
        $this->attributeHelper
            ->method('getAttribute')
            ->willReturn($admin);
    }

    private function stubColumnAttr(?AdminColumn $attr): void
    {
        $this->attributeHelper
            ->method('getPropertyAttribute')
            ->willReturn($attr);
    }

    private function stubVoterGranted(bool $granted = true): void
    {
        $this->authChecker
            ->method('isGranted')
            ->willReturn($granted);
    }

    private function stubWritable(bool $writable = true): void
    {
        $this->propertyAccessor
            ->method('isWritable')
            ->willReturn($writable);
    }

    private function makeEntity(): object
    {
        return new class {
            public string $title = '';
        };
    }

    // ── editable: false short-circuits everything ──────────────────────────────

    /** @test */
    public function editableFalseReturnsFalseRegardlessOfEverythingElse(): void
    {
        $this->stubColumnAttr(new AdminColumn(editable: false));
        $this->stubEntityAdmin(new Admin(enableInlineEdit: true));
        $this->stubVoterGranted(true);
        $this->stubWritable(true);

        // Voter and writable should never be consulted
        $this->authChecker->expects($this->never())->method('isGranted');
        $this->propertyAccessor->expects($this->never())->method('isWritable');

        $this->assertFalse($this->resolver->canEdit($this->makeEntity(), 'title'));
    }

    // ── editable: true bypasses entity default, still needs voter + writable ──

    /** @test */
    public function editableTrueReturnsTrueWhenVoterGrantedAndWritable(): void
    {
        $this->stubColumnAttr(new AdminColumn(editable: true));
        $this->stubEntityAdmin(new Admin(enableInlineEdit: false)); // entity default OFF
        $this->stubVoterGranted(true);
        $this->stubWritable(true);

        $this->assertTrue(
            $this->resolver->canEdit($this->makeEntity(), 'title'),
            'editable:true should bypass entity default and allow editing when voter+writable pass.'
        );
    }

    /** @test */
    public function editableTrueReturnsFalseWhenPropertyHasNoSetter(): void
    {
        $this->stubColumnAttr(new AdminColumn(editable: true));
        $this->stubEntityAdmin(new Admin(enableInlineEdit: true));
        $this->stubVoterGranted(true);
        $this->stubWritable(false); // ← No setter

        $this->assertFalse(
            $this->resolver->canEdit($this->makeEntity(), 'title'),
            'editable:true must still be blocked when the property has no setter.'
        );
    }

    /** @test */
    public function editableTrueReturnsFalseWhenVoterDenies(): void
    {
        $this->stubColumnAttr(new AdminColumn(editable: true));
        $this->stubEntityAdmin(new Admin(enableInlineEdit: true));
        $this->stubVoterGranted(false); // ← Voter denies
        $this->stubWritable(true);

        $this->assertFalse(
            $this->resolver->canEdit($this->makeEntity(), 'title'),
            'editable:true must still be blocked when the ADMIN_EDIT voter denies.'
        );
    }

    // ── editable: null inherits entity-level setting ──────────────────────────

    /** @test */
    public function nullEditableWithEntityInlineEditTrueAllowsEditing(): void
    {
        $this->stubColumnAttr(new AdminColumn(editable: null));
        $this->stubEntityAdmin(new Admin(enableInlineEdit: true));
        $this->stubVoterGranted(true);
        $this->stubWritable(true);

        $this->assertTrue($this->resolver->canEdit($this->makeEntity(), 'title'));
    }

    /** @test */
    public function nullEditableWithEntityInlineEditFalseBlocksEditing(): void
    {
        $this->stubColumnAttr(new AdminColumn(editable: null));
        $this->stubEntityAdmin(new Admin(enableInlineEdit: false));

        // Voter and writable are not reached when the entity default is off
        $this->authChecker->expects($this->never())->method('isGranted');
        $this->propertyAccessor->expects($this->never())->method('isWritable');

        $this->assertFalse($this->resolver->canEdit($this->makeEntity(), 'title'));
    }

    /** @test */
    public function noAdminColumnAttrWithEntityInlineEditFalseBlocksEditing(): void
    {
        $this->stubColumnAttr(null); // No #[AdminColumn] on the property
        $this->stubEntityAdmin(new Admin(enableInlineEdit: false));

        $this->assertFalse($this->resolver->canEdit($this->makeEntity(), 'title'));
    }

    /** @test */
    public function noAdminColumnAttrWithEntityInlineEditTrueAndVoterGrantedAllows(): void
    {
        $this->stubColumnAttr(null);
        $this->stubEntityAdmin(new Admin(enableInlineEdit: true));
        $this->stubVoterGranted(true);
        $this->stubWritable(true);

        $this->assertTrue($this->resolver->canEdit($this->makeEntity(), 'title'));
    }

    /** @test */
    public function noAdminAttributeOnEntityBlocksEditing(): void
    {
        $this->stubColumnAttr(null); // No AdminColumn
        $this->stubEntityAdmin(null); // No Admin attr either

        $this->authChecker->expects($this->never())->method('isGranted');
        $this->propertyAccessor->expects($this->never())->method('isWritable');

        $this->assertFalse($this->resolver->canEdit($this->makeEntity(), 'title'));
    }

    // ── editable: expression ──────────────────────────────────────────────────

    /** @test */
    public function expressionTrueAllowsEditing(): void
    {
        $entity = new class { public string $status = 'draft'; };

        $this->stubColumnAttr(new AdminColumn(editable: 'entity.status == "draft"'));
        $this->stubEntityAdmin(new Admin(enableInlineEdit: false)); // entity default irrelevant
        $this->stubVoterGranted(true);
        $this->stubWritable(true);

        $this->assertTrue($this->resolver->canEdit($entity, 'status'));
    }

    /** @test */
    public function expressionFalseBlocksEditing(): void
    {
        $entity = new class { public string $status = 'published'; };

        $this->stubColumnAttr(new AdminColumn(editable: 'entity.status == "draft"'));
        $this->stubEntityAdmin(new Admin(enableInlineEdit: true)); // entity default irrelevant

        // When expression is false, voter and writable must not be consulted
        $this->authChecker->expects($this->never())->method('isGranted');
        $this->propertyAccessor->expects($this->never())->method('isWritable');

        $this->assertFalse($this->resolver->canEdit($entity, 'status'));
    }

    /** @test */
    public function expressionWithIsGrantedAllowsWhenRoleGranted(): void
    {
        $entity = new class { public string $title = 'Hello'; };

        $this->stubColumnAttr(new AdminColumn(editable: 'is_granted("ROLE_EDITOR")'));
        $this->stubEntityAdmin(new Admin(enableInlineEdit: false));

        // canEdit makes TWO isGranted calls:
        //   1. Expression language: is_granted("ROLE_EDITOR")   → must be true
        //   2. Voter check:         isGranted('ADMIN_EDIT', ...) → must be true
        $this->authChecker
            ->method('isGranted')
            ->willReturnCallback(function (string $attr): bool {
                return in_array($attr, ['ROLE_EDITOR', 'ADMIN_EDIT'], true);
            });

        $this->stubWritable(true);

        $this->assertTrue($this->resolver->canEdit($entity, 'title'));
    }

    /** @test */
    public function expressionWithIsGrantedBlocksWhenRoleMissing(): void
    {
        $entity = new class { public string $title = 'Hello'; };

        $this->stubColumnAttr(new AdminColumn(editable: 'is_granted("ROLE_EDITOR")'));
        $this->stubEntityAdmin(new Admin(enableInlineEdit: false));

        // Expression is_granted("ROLE_EDITOR") returns false → isEligibleByAttribute is false
        // → canEdit returns early, voter is never reached
        $this->authChecker->method('isGranted')->willReturn(false);

        $this->assertFalse($this->resolver->canEdit($entity, 'title'));
    }

    /** @test */
    public function expressionCombinedPropertyAndRoleAllowsWhenBothPass(): void
    {
        $entity = new class {
            public string $status = 'draft';
        };

        $this->stubColumnAttr(new AdminColumn(editable: 'entity.status == "draft" && is_granted("ROLE_EDITOR")'));
        $this->stubEntityAdmin(new Admin(enableInlineEdit: false));

        // Both expression's is_granted("ROLE_EDITOR") AND the voter's ADMIN_EDIT check need true
        $this->authChecker
            ->method('isGranted')
            ->willReturnCallback(fn (string $attr) => in_array($attr, ['ROLE_EDITOR', 'ADMIN_EDIT'], true));

        $this->stubWritable(true);

        $this->assertTrue($this->resolver->canEdit($entity, 'status'));
    }

    /** @test */
    public function expressionCombinedBlocksWhenPropertyConditionFails(): void
    {
        $entity = new class {
            public string $status = 'published'; // not draft
        };

        $this->stubColumnAttr(new AdminColumn(editable: 'entity.status == "draft" && is_granted("ROLE_EDITOR")'));
        $this->stubEntityAdmin(new Admin(enableInlineEdit: false));

        $this->authChecker->method('isGranted')->willReturn(true);

        $this->assertFalse($this->resolver->canEdit($entity, 'status'));
    }

    /** @test */
    public function invalidExpressionReturnsFalse(): void
    {
        $entity = new class {};

        $this->stubColumnAttr(new AdminColumn(editable: 'entity.nonExistentProp == true'));
        $this->stubEntityAdmin(new Admin(enableInlineEdit: true));

        // Expression fails silently — should not throw, should return false
        $this->assertFalse($this->resolver->canEdit($entity, 'title'));
    }

    // ── Voter is checked with correct entity short class ──────────────────────

    /** @test */
    public function voterIsCalledWithAdminEditAttributeAndShortClassName(): void
    {
        $entity = new class {};
        $shortClass = (new \ReflectionClass($entity))->getShortName();

        $this->stubColumnAttr(new AdminColumn(editable: true));
        $this->stubEntityAdmin(new Admin(enableInlineEdit: true));
        $this->stubWritable(true);

        $this->authChecker
            ->expects($this->atLeastOnce())
            ->method('isGranted')
            ->with('ADMIN_EDIT', $shortClass)
            ->willReturn(true);

        $this->resolver->canEdit($entity, 'title');
    }
}
