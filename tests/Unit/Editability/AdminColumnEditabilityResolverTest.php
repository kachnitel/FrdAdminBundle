<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Editability;

use Kachnitel\AdminBundle\Attribute\AdminColumn;
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
#[UsesClass(AttributeHelper::class)]
#[UsesClass(ObjectHelper::class)]
#[Group('dynamic-form')]
#[Group('expressions')]
#[AllowMockObjectsWithoutExpectations]
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
        $this->resolver = new AdminColumnEditabilityResolver(
            new AttributeHelper(),
            $this->expressionLanguage,
            $this->createStub(\Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface::class),
        );
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

    // ── canEdit(): explicit false ──────────────────────────────────────────────

    #[Test]
    public function canEditExcludesAFieldMarkedEditableFalse(): void
    {
        $this->assertFalse($this->resolver->canEdit(ResolverTestEntity::class, 'blocked'));
    }

    #[Test]
    public function canEditExcludesEditableFalseEvenWithAnEntityProvided(): void
    {
        $entity = new ResolverTestEntity();

        $this->assertFalse($this->resolver->canEdit(ResolverTestEntity::class, 'blocked', $entity));
    }

    // ── canEdit(): explicit true ───────────────────────────────────────────────

    #[Test]
    public function canEditIncludesAFieldMarkedEditableTrue(): void
    {
        $this->assertTrue($this->resolver->canEdit(ResolverTestEntity::class, 'forced'));
    }

    // ── canEdit(): expression, entity available ────────────────────────────────

    #[Test]
    public function canEditEvaluatesTheExpressionWhenAnEntityIsAvailable(): void
    {
        $entity          = new ResolverTestEntity();
        $entity->enabled = true;

        $this->expressionLanguage->expects($this->once())->method('evaluate')
            ->with('entity.enabled', $entity, $this->createStub(\Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface::class))
            ->willReturn(true);

        $this->assertTrue($this->resolver->canEdit(ResolverTestEntity::class, 'status', $entity));
    }

    #[Test]
    public function canEditExcludesWhenTheExpressionEvaluatesFalse(): void
    {
        $entity = new ResolverTestEntity();

        $this->expressionLanguage->method('evaluate')->willReturn(false);

        $this->assertFalse($this->resolver->canEdit(ResolverTestEntity::class, 'status', $entity));
    }

    // ── canEdit(): expression, no entity yet ───────────────────────────────────

    #[Test]
    public function canEditIncludesAnExpressionFieldWhenNoEntityIsAvailableYet(): void
    {
        $this->expressionLanguage->expects($this->never())->method('evaluate');

        $this->assertTrue($this->resolver->canEdit(ResolverTestEntity::class, 'status'));
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
