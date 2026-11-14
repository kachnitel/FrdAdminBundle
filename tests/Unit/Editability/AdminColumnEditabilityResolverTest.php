<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Editability;

use Kachnitel\AdminBundle\Attribute\AdminColumn;
use Kachnitel\AdminBundle\Editability\AdminColumnEditabilityResolver;
use Kachnitel\AdminBundle\RowAction\RowActionExpressionLanguage;
use Kachnitel\AdminBundle\Service\AttributeHelper;
use Kachnitel\AdminBundle\Service\ColumnPermissionService;
use Kachnitel\AdminBundle\Utils\ObjectHelper;
use Kachnitel\AdminBundle\Utils\PropertyReflection;
use PHPUnit\Framework\Attributes as PHPUnit;
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
#[PHPUnit\CoversClass(AdminColumnEditabilityResolver::class)]
#[PHPUnit\UsesClass(AdminColumn::class)]
#[PHPUnit\UsesClass(AttributeHelper::class)]
#[PHPUnit\UsesClass(ObjectHelper::class)]
#[PHPUnit\UsesClass(PropertyReflection::class)]
#[PHPUnit\Group('dynamic-form')]
#[PHPUnit\Group('expressions')]
#[PHPUnit\AllowMockObjectsWithoutExpectations]
final class AdminColumnEditabilityResolverTest extends TestCase
{
    /** @var RowActionExpressionLanguage&MockObject */
    private RowActionExpressionLanguage $expressionLanguage;

    private AdminColumnEditabilityResolver $resolver;

    protected function setUp(): void
    {
        $this->expressionLanguage   = $this->createMock(RowActionExpressionLanguage::class);

        // AttributeHelper is a plain reflection service with no dependencies of its
        // own — using the real implementation keeps this test honest about how
        // #[AdminColumn] is actually read, rather than mocking away the one
        // collaborator whose interaction with the resolver matters most.
        // Column permissions are covered in AdminColumnEditabilityResolverColumnPermissionTest;
        // here every action is allowed so only the #[AdminColumn] precedence is exercised.
        $columnPermissions = $this->createStub(ColumnPermissionService::class);
        $columnPermissions->method('canPerformAction')->willReturn(true);

        $this->resolver = new AdminColumnEditabilityResolver(
            new AttributeHelper(),
            $this->expressionLanguage,
            $this->createStub(\Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface::class),
            $columnPermissions,
        );
    }

    // ── canShow(): the key behavioural difference from AdminEditabilityResolver ──

    /**
     * ResolverTestEntity carries no #[Admin] attribute at all — enableInlineEdit
     * is unset (defaults to false). If this resolver ever fell back to it the way
     * the sibling inline-edit resolver does, every field on every entity without
     * enableInlineEdit: true would vanish from the auto-generated form. See the
     * class docblock.
     */
    #[PHPUnit\Test]
    public function canShowNeverConsultsEnableInlineEdit(): void
    {
        $this->assertTrue($this->resolver->canShow(ResolverTestEntity::class, 'plain'));
    }

    // ── canShow(): explicit false ──────────────────────────────────────────────

    #[PHPUnit\Test]
    public function editableFalseKeepsTheFieldButMakesItReadOnly(): void
    {
        $this->assertTrue($this->resolver->canShow(ResolverTestEntity::class, 'blocked'));
        $this->assertTrue($this->resolver->isReadOnly(ResolverTestEntity::class, 'blocked'));
    }

    #[PHPUnit\Test]
    public function editableFalseIsReadOnlyWithAnEntityProvidedAndNeverEvaluatesAnExpression(): void
    {
        $this->expressionLanguage->expects($this->never())->method('evaluate');
        $entity = new ResolverTestEntity();

        $this->assertTrue($this->resolver->canShow(ResolverTestEntity::class, 'blocked', $entity));
        $this->assertTrue($this->resolver->isReadOnly(ResolverTestEntity::class, 'blocked', $entity));
    }

    // ── canShow(): explicit true ───────────────────────────────────────────────

    #[PHPUnit\Test]
    public function canShowIncludesAFieldMarkedEditableTrue(): void
    {
        $this->assertTrue($this->resolver->canShow(ResolverTestEntity::class, 'forced'));
    }

    // ── expression: the field stays; isReadOnly() carries the answer ───────────

    #[PHPUnit\Test]
    public function canShowKeepsAnExpressionFieldWithoutEvaluatingIt(): void
    {
        $this->expressionLanguage->expects($this->never())->method('evaluate');

        $this->assertTrue($this->resolver->canShow(ResolverTestEntity::class, 'status', new ResolverTestEntity()));
        $this->assertTrue($this->resolver->canShow(ResolverTestEntity::class, 'status'));
    }

    #[PHPUnit\Test]
    public function isReadOnlyEvaluatesTheExpressionWhenAnEntityIsAvailable(): void
    {
        $entity          = new ResolverTestEntity();
        $entity->enabled = true;

        $this->expressionLanguage->expects($this->once())->method('evaluate')
            ->with('entity.enabled', $entity, $this->createStub(\Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface::class))
            ->willReturn(true);

        $this->assertFalse($this->resolver->isReadOnly(ResolverTestEntity::class, 'status', $entity));
    }

    #[PHPUnit\Test]
    public function isReadOnlyIsTrueWhenTheExpressionEvaluatesFalse(): void
    {
        $this->expressionLanguage->method('evaluate')->willReturn(false);

        $this->assertTrue($this->resolver->isReadOnly(ResolverTestEntity::class, 'status', new ResolverTestEntity()));
    }

    #[PHPUnit\Test]
    public function isReadOnlyDoesNotEvaluateTheExpressionWithoutAnEntity(): void
    {
        $this->expressionLanguage->expects($this->never())->method('evaluate');

        $this->assertFalse($this->resolver->isReadOnly(ResolverTestEntity::class, 'status'));
    }

    #[PHPUnit\Test]
    public function isReadOnlyIsFalseForFieldsWithoutAnExpression(): void
    {
        $this->expressionLanguage->expects($this->never())->method('evaluate');

        $this->assertFalse($this->resolver->isReadOnly(ResolverTestEntity::class, 'plain', new ResolverTestEntity()));
        $this->assertFalse($this->resolver->isReadOnly(ResolverTestEntity::class, 'forced', new ResolverTestEntity()));
    }

    // ── canShow(): reflection failures fall back to permissive ────────────────

    #[PHPUnit\Test]
    public function canShowIncludesAPropertyThatDoesNotExistOnTheClass(): void
    {
        $this->assertTrue($this->resolver->canShow(ResolverTestEntity::class, 'thisPropertyDoesNotExist'));
    }

    #[PHPUnit\Test]
    public function canShowIncludesWhenTheEntityClassDoesNotExist(): void
    {
        $this->assertTrue($this->resolver->canShow('App\\Entity\\ThisClassDoesNotExist', 'whatever'));
    }

    // ── isExplicitOverride(): no attribute → not overridden ────────────────────

    #[PHPUnit\Test]
    public function isExplicitOverrideIsFalseWithNoAdminColumnAttribute(): void
    {
        $this->assertFalse($this->resolver->isExplicitOverride(ResolverTestEntity::class, 'plain'));
    }

    // ── isExplicitOverride(): explicit false → not overridden ──────────────────

    #[PHPUnit\Test]
    public function isExplicitOverrideIsFalseWhenEditableIsExplicitlyFalse(): void
    {
        $this->assertFalse($this->resolver->isExplicitOverride(ResolverTestEntity::class, 'blocked'));
    }

    // ── isExplicitOverride(): explicit true → overridden ────────────────────────

    #[PHPUnit\Test]
    public function isExplicitOverrideIsTrueWhenEditableIsExplicitlyTrue(): void
    {
        $this->assertTrue($this->resolver->isExplicitOverride(ResolverTestEntity::class, 'forced'));
    }

    #[PHPUnit\Test]
    public function isExplicitOverrideEditableTrueDoesNotNeedAnEntity(): void
    {
        $this->expressionLanguage->expects($this->never())->method('evaluate');

        $this->assertTrue($this->resolver->isExplicitOverride(ResolverTestEntity::class, 'forced'));
    }

    // ── isExplicitOverride(): expression ────────────────────────────────────────

    #[PHPUnit\Test]
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
     * Unlike canShow(), an unresolved expression must NOT count as an override —
     * see FieldEditabilityResolverInterface's docblock for why: falling back to
     * "yes, overridden" here would pull an auto-skipped inverse-side association
     * into a brand-new entity's form before there's ever a real instance to
     * evaluate the expression against.
     */
    #[PHPUnit\Test]
    public function isExplicitOverrideIsFalseForAnExpressionFieldWithNoEntityYet(): void
    {
        $this->expressionLanguage->expects($this->never())->method('evaluate');

        $this->assertFalse($this->resolver->isExplicitOverride(ResolverTestEntity::class, 'status'));
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
}
