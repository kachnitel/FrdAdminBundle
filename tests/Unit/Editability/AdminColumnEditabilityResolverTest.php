<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Editability;

use Kachnitel\AdminBundle\Attribute\AdminColumn;
use Kachnitel\AdminBundle\Attribute\ColumnPermission;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\Service\ColumnPermissionService;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Kachnitel\AdminBundle\Editability\AdminColumnEditabilityResolver;
use Kachnitel\AdminBundle\RowAction\RowActionExpressionLanguage;
use Kachnitel\AdminBundle\Service\AttributeHelper;
use Kachnitel\AdminBundle\Utils\ObjectHelper;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Direct unit coverage for AdminColumnEditabilityResolver — the admin-bundle
 * implementation of kachnitel/dynamic-form-bundle's FieldEditabilityResolverInterface.
 *
 * Supersedes the old DynamicFormExpressionEditabilityTest, which tested the same
 * #[AdminColumn(editable: 'expression')] precedence indirectly through
 * DynamicEntityFormType::buildForm(). Now that the precedence logic lives here
 * instead of inline inside DynamicEntityFormType, it's tested directly against
 * this class — no form builder, no metadata mocking required.
 */
#[CoversClass(AdminColumnEditabilityResolver::class)]
#[UsesClass(AdminColumn::class)]
#[UsesClass(ColumnPermission::class)]
#[UsesClass(ColumnPermissionService::class)]
#[UsesClass(AttributeHelper::class)]
#[UsesClass(ObjectHelper::class)]
#[Group('dynamic-form')]
#[Group('expressions')]
#[Group('column-permission-form')]
#[AllowMockObjectsWithoutExpectations]
final class AdminColumnEditabilityResolverTest extends TestCase
{
    /** @var RowActionExpressionLanguage&MockObject */
    private RowActionExpressionLanguage $expressionLanguage;

    private AdminColumnEditabilityResolver $resolver;

    /** @var list<string> roles the simulated user holds */
    private array $grantedRoles = [];

    protected function setUp(): void
    {
        $this->grantedRoles = [];

        $this->expressionLanguage   = $this->createMock(RowActionExpressionLanguage::class);

        // AttributeHelper is a plain reflection service with no dependencies of its
        // own — using the real implementation keeps this test honest about how
        // #[AdminColumn] is actually read, rather than mocking away the one
        // collaborator whose interaction with the resolver matters most.
        $authChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authChecker->method('isGranted')
            ->willReturnCallback(fn (mixed $attribute): bool => $this->holdsRole($attribute));

        $this->resolver = new AdminColumnEditabilityResolver(
            new AttributeHelper(),
            $this->expressionLanguage,
            $authChecker,
            new ColumnPermissionService($authChecker),
        );
    }

    /**
     * Separate method so PHPStan doesn't narrow $grantedRoles to the empty array
     * assigned in setUp() when analysing the closure.
     */
    private function holdsRole(mixed $attribute): bool
    {
        return in_array($attribute, $this->grantedRoles, true);
    }

    // ── canEdit(): the key behavioural difference from AdminEditabilityResolver ──

    /**
     * ResolverTestEntity carries no #[Admin] attribute at all — enableInlineEdit
     * is unset (defaults to false). If this resolver ever fell back to it the way
     * the sibling inline-edit resolver does, every field on every entity without
     * enableInlineEdit: true would vanish from the auto-generated form. See the
     * class docblock.
     */
    #[Test]
    public function canEditNeverConsultsEnableInlineEdit(): void
    {
        $this->assertTrue($this->resolver->canEdit(ResolverTestEntity::class, 'plain'));
    }

    // ── explicit false: shown, but read-only ───────────────────────────────────

    #[Test]
    public function aFieldMarkedEditableFalseIsIncludedButReadOnly(): void
    {
        $this->assertTrue($this->resolver->canEdit(ResolverTestEntity::class, 'blocked'));
        $this->assertTrue($this->resolver->isReadOnly(ResolverTestEntity::class, 'blocked'));
    }

    #[Test]
    public function editableFalseIsReadOnlyWithAnEntityProvidedToo(): void
    {
        $entity = new ResolverTestEntity();

        $this->assertTrue($this->resolver->canEdit(ResolverTestEntity::class, 'blocked', $entity));
        $this->assertTrue($this->resolver->isReadOnly(ResolverTestEntity::class, 'blocked', $entity));
    }

    #[Test]
    public function editableFalseNeverHidesAFieldThePermissionsDeny(): void
    {
        // no ADMIN_SHOW role: permissions win, editable can only restrict further
        $this->assertFalse($this->resolver->canEdit(ResolverTestEntity::class, 'salaryLocked'));
    }

    // ── canEdit(): explicit true ───────────────────────────────────────────────

    #[Test]
    public function canEditIncludesAFieldMarkedEditableTrue(): void
    {
        $this->assertTrue($this->resolver->canEdit(ResolverTestEntity::class, 'forced'));
        $this->assertFalse($this->resolver->isReadOnly(ResolverTestEntity::class, 'forced'));
    }

    /** editable can only restrict, never permit: true must not lift a ColumnPermission restriction. */
    #[Test]
    public function editableTrueDoesNotOverrideAnEditPermissionRestriction(): void
    {
        $this->assertTrue($this->resolver->isReadOnly(ResolverTestEntity::class, 'forcedRestricted'));
    }

    #[Test]
    public function editableTrueDoesNotOverrideAShowPermissionRestriction(): void
    {
        $this->assertFalse($this->resolver->canEdit(ResolverTestEntity::class, 'forcedRestricted'));
    }

    // ── canEdit(): expression, entity available ────────────────────────────────

    #[Test]
    public function isReadOnlyEvaluatesTheExpressionWhenAnEntityIsAvailable(): void
    {
        $entity          = new ResolverTestEntity();
        $entity->enabled = true;

        $this->expressionLanguage->expects($this->once())->method('evaluate')
            ->with('entity.enabled', $entity, $this->createStub(\Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface::class))
            ->willReturn(true);

        $this->assertFalse($this->resolver->isReadOnly(ResolverTestEntity::class, 'status', $entity));
    }

    #[Test]
    public function aFalseExpressionMakesTheFieldReadOnlyNotAbsent(): void
    {
        $entity = new ResolverTestEntity();

        $this->expressionLanguage->method('evaluate')->willReturn(false);

        $this->assertTrue($this->resolver->canEdit(ResolverTestEntity::class, 'status', $entity));
        $this->assertTrue($this->resolver->isReadOnly(ResolverTestEntity::class, 'status', $entity));
    }

    // ── canEdit(): expression, no entity yet ───────────────────────────────────

    #[Test]
    public function anExpressionFieldIsEditableWhenNoEntityIsAvailableYet(): void
    {
        $this->expressionLanguage->expects($this->never())->method('evaluate');

        $this->assertTrue($this->resolver->canEdit(ResolverTestEntity::class, 'status'));
        $this->assertFalse($this->resolver->isReadOnly(ResolverTestEntity::class, 'status'));
    }

    // ── canEdit(): reflection failures fall back to permissive ────────────────

    #[Test]
    public function canEditIncludesAPropertyThatDoesNotExistOnTheClass(): void
    {
        $this->assertTrue($this->resolver->canEdit(ResolverTestEntity::class, 'thisPropertyDoesNotExist'));
    }

    #[Test]
    public function canEditIncludesWhenTheEntityClassDoesNotExist(): void
    {
        $this->assertTrue($this->resolver->canEdit('App\\Entity\\ThisClassDoesNotExist', 'whatever'));
    }

    // ── isExplicitOverride(): no attribute → not overridden ────────────────────

    #[Test]
    public function isExplicitOverrideIsFalseWithNoAdminColumnAttribute(): void
    {
        $this->assertFalse($this->resolver->isExplicitOverride(ResolverTestEntity::class, 'plain'));
    }

    // ── isExplicitOverride(): explicit false → not overridden ──────────────────

    #[Test]
    public function isExplicitOverrideIsFalseWhenEditableIsExplicitlyFalse(): void
    {
        $this->assertFalse($this->resolver->isExplicitOverride(ResolverTestEntity::class, 'blocked'));
    }

    // ── isExplicitOverride(): explicit true → overridden ────────────────────────

    #[Test]
    public function isExplicitOverrideIsTrueWhenEditableIsExplicitlyTrue(): void
    {
        $this->assertTrue($this->resolver->isExplicitOverride(ResolverTestEntity::class, 'forced'));
    }

    #[Test]
    public function isExplicitOverrideEditableTrueDoesNotNeedAnEntity(): void
    {
        $this->expressionLanguage->expects($this->never())->method('evaluate');

        $this->assertTrue($this->resolver->isExplicitOverride(ResolverTestEntity::class, 'forced'));
    }

    // ── isExplicitOverride(): expression ────────────────────────────────────────

    #[Test]
    public function isExplicitOverrideEvaluatesTheExpressionWhenAnEntityIsAvailable(): void
    {
        $entity          = new ResolverTestEntity();
        $entity->enabled = true;

        $this->expressionLanguage->expects($this->once())->method('evaluate')
            ->with('entity.enabled', $entity, $this->createStub(\Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface::class))
            ->willReturn(true);

        $this->assertTrue($this->resolver->isExplicitOverride(ResolverTestEntity::class, 'status', $entity));
    }

    /**
     * Unlike canEdit(), an unresolved expression must NOT count as an override —
     * see FieldEditabilityResolverInterface's docblock for why: falling back to
     * "yes, overridden" here would pull an auto-skipped inverse-side association
     * into a brand-new entity's form before there's ever a real instance to
     * evaluate the expression against.
     */
    #[Test]
    public function isExplicitOverrideIsFalseForAnExpressionFieldWithNoEntityYet(): void
    {
        $this->expressionLanguage->expects($this->never())->method('evaluate');

        $this->assertFalse($this->resolver->isExplicitOverride(ResolverTestEntity::class, 'status'));
    }

    // ── #[ColumnPermission]: no entry / read-only / read-write ─────────────────

    #[Test]
    public function columnWithoutShowRoleHasNoEntry(): void
    {
        $this->assertFalse($this->resolver->canEdit(ResolverTestEntity::class, 'salary'));
    }

    #[Test]
    public function columnWithShowButNotEditRoleIsReadOnly(): void
    {
        $this->grantedRoles = ['ROLE_HR'];

        $this->assertTrue($this->resolver->canEdit(ResolverTestEntity::class, 'salary'));
        $this->assertTrue($this->resolver->isReadOnly(ResolverTestEntity::class, 'salary'));
    }

    #[Test]
    public function columnWithShowAndEditRolesIsReadWrite(): void
    {
        $this->grantedRoles = ['ROLE_HR', 'ROLE_HR_EDIT'];

        $this->assertTrue($this->resolver->canEdit(ResolverTestEntity::class, 'salary'));
        $this->assertFalse($this->resolver->isReadOnly(ResolverTestEntity::class, 'salary'));
    }

    #[Test]
    public function columnRestrictedOnlyByEditRoleIsReadOnlyWithoutIt(): void
    {
        $this->assertTrue($this->resolver->canEdit(ResolverTestEntity::class, 'editRestricted'));
        $this->assertTrue($this->resolver->isReadOnly(ResolverTestEntity::class, 'editRestricted'));
    }

    #[Test]
    public function editableFalseRestrictsEvenWhenPermissionsAllowEverything(): void
    {
        $this->grantedRoles = ['ROLE_HR', 'ROLE_HR_EDIT'];

        $this->assertTrue($this->resolver->canEdit(ResolverTestEntity::class, 'salaryLocked'));
        $this->assertTrue($this->resolver->isReadOnly(ResolverTestEntity::class, 'salaryLocked'));
    }

    #[Test]
    public function unrestrictedColumnIsNeverReadOnly(): void
    {
        $this->assertFalse($this->resolver->isReadOnly(ResolverTestEntity::class, 'plain'));
        $this->assertFalse($this->resolver->isReadOnly(ResolverTestEntity::class, 'doesNotExist'));
    }

    #[Test]
    public function readOnlyColumnIsNotAnExplicitOverride(): void
    {
        $this->grantedRoles = ['ROLE_HR'];

        $this->assertFalse($this->resolver->isExplicitOverride(ResolverTestEntity::class, 'salary'));
    }
}

// ── Fixture ────────────────────────────────────────────────────────────────────

class ResolverTestEntity
{
    public string $plain = '';

    #[AdminColumn(editable: false)]
    public string $blocked = '';

    #[AdminColumn(editable: true)]
    public string $forced = '';

    #[AdminColumn(editable: 'entity.enabled')]
    public string $status = '';

    public bool $enabled = false;

    #[ColumnPermission([
        AdminEntityVoter::ADMIN_SHOW => 'ROLE_HR',
        AdminEntityVoter::ADMIN_EDIT => 'ROLE_HR_EDIT',
    ])]
    public string $salary = '';

    #[ColumnPermission([AdminEntityVoter::ADMIN_EDIT => 'ROLE_HR_EDIT'])]
    public string $editRestricted = '';

    #[AdminColumn(editable: false)]
    #[ColumnPermission([AdminEntityVoter::ADMIN_SHOW => 'ROLE_HR'])]
    public string $salaryLocked = '';

    #[AdminColumn(editable: true)]
    #[ColumnPermission([
        AdminEntityVoter::ADMIN_SHOW => 'ROLE_HR',
        AdminEntityVoter::ADMIN_EDIT => 'ROLE_HR_EDIT',
    ])]
    public string $forcedRestricted = '';
}
