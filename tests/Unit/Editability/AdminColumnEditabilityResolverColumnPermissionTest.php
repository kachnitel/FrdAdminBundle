<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Editability;

use Kachnitel\AdminBundle\Attribute\AdminColumn;
use Kachnitel\AdminBundle\Attribute\ColumnPermission;
use Kachnitel\AdminBundle\Editability\AdminColumnEditabilityResolver;
use Kachnitel\AdminBundle\RowAction\RowActionExpressionLanguage;
use Kachnitel\AdminBundle\Service\AttributeHelper;
use Kachnitel\AdminBundle\Service\ColumnPermissionService;
use Kachnitel\AdminBundle\Tests\Fixtures\ColumnPermissionResolverEntity;
use Kachnitel\AdminBundle\Utils\ObjectHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * #[ColumnPermission] enforcement on the generated form goes through the resolver
 * contract of kachnitel/dynamic-form-bundle:
 *
 *   ADMIN_SHOW denied                 -> canEdit() false (field not on the form)
 *   ADMIN_SHOW ok, ADMIN_EDIT denied  -> canEdit() true + isReadOnly() true (disabled)
 */
#[CoversClass(AdminColumnEditabilityResolver::class)]
#[UsesClass(AdminColumn::class)]
#[UsesClass(ColumnPermission::class)]
#[UsesClass(ColumnPermissionService::class)]
#[UsesClass(AttributeHelper::class)]
#[UsesClass(ObjectHelper::class)]
#[Group('column-permission-form')]
#[Group('dynamic-form')]
final class AdminColumnEditabilityResolverColumnPermissionTest extends TestCase
{
    private const E = ColumnPermissionResolverEntity::class;

    /** @param list<string> $roles */
    private function resolverGranting(array $roles, bool $expressionResult = true): AdminColumnEditabilityResolver
    {
        $expression = $this->createStub(RowActionExpressionLanguage::class);
        $expression->method('evaluate')->willReturn($expressionResult);

        $auth = $this->createStub(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturnCallback(
            static fn (mixed $attribute): bool => \in_array($attribute, $roles, true),
        );

        return new AdminColumnEditabilityResolver(
            new AttributeHelper(),
            $expression,
            $auth,
            new ColumnPermissionService($auth),
        );
    }

    #[Test]
    public function unrestrictedPropertyIsEditableAndNotReadOnly(): void
    {
        $resolver = $this->resolverGranting([]);

        self::assertTrue($resolver->canEdit(self::E, 'plain'));
        self::assertFalse($resolver->isReadOnly(self::E, 'plain'));
    }

    #[Test]
    public function showDeniedExcludesTheField(): void
    {
        $resolver = $this->resolverGranting([]);

        self::assertFalse($resolver->canEdit(self::E, 'salary'));
        self::assertFalse($resolver->canEdit(self::E, 'ssn'));
    }

    #[Test]
    public function showAllowedButEditDeniedKeepsTheFieldAsReadOnly(): void
    {
        $resolver = $this->resolverGranting(['ROLE_HR']);

        self::assertTrue($resolver->canEdit(self::E, 'salary'));
        self::assertTrue($resolver->isReadOnly(self::E, 'salary'));
    }

    #[Test]
    public function bothAllowedIsEditableAndNotReadOnly(): void
    {
        $resolver = $this->resolverGranting(['ROLE_HR', 'ROLE_HR_EDIT']);

        self::assertTrue($resolver->canEdit(self::E, 'salary'));
        self::assertFalse($resolver->isReadOnly(self::E, 'salary'));
    }

    #[Test]
    public function showOnlyRestrictionDoesNotMakeTheFieldReadOnly(): void
    {
        $resolver = $this->resolverGranting(['ROLE_HR']);

        self::assertTrue($resolver->canEdit(self::E, 'ssn'));
        self::assertFalse($resolver->isReadOnly(self::E, 'ssn'));
    }

    #[Test]
    public function showDeniedPropertyIsNotAnExplicitOverrideEvenWithEditableTrue(): void
    {
        $resolver = $this->resolverGranting([]);

        self::assertFalse($resolver->canEdit(self::E, 'forced'));
        self::assertFalse($resolver->isExplicitOverride(self::E, 'forced'));
    }

    #[Test]
    public function showAllowedEditableTruePropertyStaysAnExplicitOverride(): void
    {
        $resolver = $this->resolverGranting(['ROLE_HR']);

        self::assertTrue($resolver->canEdit(self::E, 'forced'));
        self::assertTrue($resolver->isExplicitOverride(self::E, 'forced'));
    }

    #[Test]
    public function editableFalseStillWinsOverColumnPermissions(): void
    {
        $resolver = $this->resolverGranting(['ROLE_HR_EDIT']);

        self::assertFalse($resolver->canEdit(self::E, 'blocked'));
    }

    #[Test]
    public function expressionAllowingEditStillLetsEditPermissionMakeTheFieldReadOnly(): void
    {
        $resolver = $this->resolverGranting(['ROLE_HR'], expressionResult: true);

        self::assertTrue($resolver->canEdit(self::E, 'conditional', new ColumnPermissionResolverEntity()));
        self::assertTrue($resolver->isReadOnly(self::E, 'conditional'));
    }

    #[Test]
    public function expressionAllowingEditAndEditPermissionGrantedIsEditable(): void
    {
        $resolver = $this->resolverGranting(['ROLE_HR_EDIT'], expressionResult: true);

        self::assertTrue($resolver->canEdit(self::E, 'conditional', new ColumnPermissionResolverEntity()));
        self::assertFalse($resolver->isReadOnly(self::E, 'conditional'));
    }

    #[Test]
    public function expressionDenyingEditExcludesTheFieldEvenWithEditPermission(): void
    {
        $resolver = $this->resolverGranting(['ROLE_HR_EDIT'], expressionResult: false);

        self::assertFalse($resolver->canEdit(self::E, 'conditional', new ColumnPermissionResolverEntity()));
    }

    #[Test]
    public function unknownEntityClassFailsClosedInsteadOfBeingTreatedAsUnrestricted(): void
    {
        $resolver = $this->resolverGranting([]);

        $this->expectException(\ReflectionException::class);

        $resolver->canEdit('App\\Entity\\ThisClassDoesNotExist', 'whatever');
    }

    #[Test]
    public function readOnlyIsFalseForAPropertyThatDoesNotExist(): void
    {
        $resolver = $this->resolverGranting([]);

        self::assertFalse($resolver->isReadOnly(self::E, 'nope'));
    }
}
