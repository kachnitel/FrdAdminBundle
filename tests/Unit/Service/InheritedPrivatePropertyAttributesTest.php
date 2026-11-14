<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Service;

use Kachnitel\AdminBundle\Attribute\AdminColumn;
use Kachnitel\AdminBundle\DataSource\DoctrineColumnAttributeProvider;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\Service\AttributeHelper;
use Kachnitel\AdminBundle\Service\ColumnPermissionService;
use Kachnitel\AdminBundle\Tests\Fixtures\InheritedPrivateChild;
use PHPUnit\Framework\Attributes as PHPUnit;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Attributes on a PRIVATE property of a parent/mapped-superclass must be honoured:
 * a #[ColumnPermission] that is not seen silently makes the column unrestricted.
 */
#[PHPUnit\Group('inherited-properties')]
#[PHPUnit\Group('column-permissions')]
final class InheritedPrivatePropertyAttributesTest extends TestCase
{
    private function permissions(): ColumnPermissionService
    {
        return new ColumnPermissionService($this->createStub(AuthorizationCheckerInterface::class));
    }

    #[PHPUnit\Test]
    public function columnPermissionOnAParentsPrivatePropertyIsSeen(): void
    {
        $map = $this->permissions()->getColumnPermissionMap(InheritedPrivateChild::class);

        self::assertArrayHasKey('secret', $map);
    }

    #[PHPUnit\Test]
    public function inheritedRestrictionIsEnforced(): void
    {
        // no role granted by the stub
        self::assertFalse(
            $this->permissions()->canPerformAction(InheritedPrivateChild::class, 'secret', AdminEntityVoter::ADMIN_SHOW),
        );
        self::assertContains(
            'secret',
            $this->permissions()->getDeniedColumnsForAction(InheritedPrivateChild::class, AdminEntityVoter::ADMIN_SHOW),
        );
    }

    #[PHPUnit\Test]
    public function redeclaringThePropertyDropsTheParentsRestriction(): void
    {
        self::assertArrayNotHasKey('shadowed', $this->permissions()->getColumnPermissionMap(InheritedPrivateChild::class));
    }

    #[PHPUnit\Test]
    public function propertyAttributeOnAParentsPrivatePropertyIsFound(): void
    {
        $attr = (new AttributeHelper())->getPropertyAttribute(InheritedPrivateChild::class, 'locked', AdminColumn::class);

        self::assertInstanceOf(AdminColumn::class, $attr);
        self::assertFalse($attr->editable);
    }

    #[PHPUnit\Test]
    public function propertyAttributeStillThrowsForAnUnknownProperty(): void
    {
        $this->expectException(\ReflectionException::class);

        (new AttributeHelper())->getPropertyAttribute(InheritedPrivateChild::class, 'nope', AdminColumn::class);
    }

    #[PHPUnit\Test]
    public function columnAttributeProviderSeesInheritedPrivateProperties(): void
    {
        $attributes = (new DoctrineColumnAttributeProvider())->getColumnAttributes(InheritedPrivateChild::class);

        self::assertArrayHasKey('locked', $attributes);
    }
}
