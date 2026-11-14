<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Utils;

use Kachnitel\AdminBundle\Tests\Fixtures\InheritedPrivateBase;
use Kachnitel\AdminBundle\Tests\Fixtures\InheritedPrivateChild;
use Kachnitel\AdminBundle\Utils\PropertyReflection;
use PHPUnit\Framework\Attributes as PHPUnit;
use PHPUnit\Framework\TestCase;

#[PHPUnit\CoversClass(PropertyReflection::class)]
#[PHPUnit\Group('inherited-properties')]
final class PropertyReflectionTest extends TestCase
{
    #[PHPUnit\Test]
    public function allIncludesPrivatePropertiesOfAncestors(): void
    {
        $names = array_keys(PropertyReflection::all(new \ReflectionClass(InheritedPrivateChild::class)));

        foreach (['own', 'shadowed', 'secret', 'locked'] as $expected) {
            self::assertContains($expected, $names);
        }
    }

    #[PHPUnit\Test]
    public function allPrefersTheMostDerivedDeclaration(): void
    {
        $all = PropertyReflection::all(new \ReflectionClass(InheritedPrivateChild::class));

        self::assertSame(InheritedPrivateChild::class, $all['shadowed']->getDeclaringClass()->getName());
        self::assertSame(InheritedPrivateBase::class, $all['secret']->getDeclaringClass()->getName());
    }

    #[PHPUnit\Test]
    public function findReturnsAnAncestorsPrivateProperty(): void
    {
        $property = PropertyReflection::find(new \ReflectionClass(InheritedPrivateChild::class), 'secret');

        self::assertNotNull($property);
        self::assertSame(InheritedPrivateBase::class, $property->getDeclaringClass()->getName());
    }

    #[PHPUnit\Test]
    public function findReturnsNullForAnUnknownProperty(): void
    {
        self::assertNull(PropertyReflection::find(new \ReflectionClass(InheritedPrivateChild::class), 'nope'));
    }
}
