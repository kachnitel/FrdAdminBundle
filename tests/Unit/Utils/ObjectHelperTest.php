<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Utils;

use Doctrine\Persistence\Proxy;
use Kachnitel\AdminBundle\Utils\ObjectHelper;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Kachnitel\AdminBundle\Utils\ObjectHelper
 */
class ObjectHelperTest extends TestCase
{
    // ── getClassName ───────────────────────────────────────────────────────────

    /** @test */
    public function getClassNameReturnsShortNameWithoutNamespace(): void
    {
        $object = new \stdClass();
        // stdClass has no namespace, so short name is 'stdClass'
        $this->assertSame('stdClass', ObjectHelper::getClassName($object));
    }

    /** @test */
    public function getClassNameReturnsShortNameForNamespacedClass(): void
    {
        $object = new class {};
        // Anonymous class short name contains 'class@anonymous'
        $result = ObjectHelper::getClassName($object);
        $this->assertNotEmpty($result);
        $this->assertStringContainsString('class@anonymous', $result);
    }

    // ── getRealClass with string ───────────────────────────────────────────────

    /** @test */
    public function getRealClassReturnsStringUnchangedWhenNotProxy(): void
    {
        $result = ObjectHelper::getRealClass(\stdClass::class);
        $this->assertSame(\stdClass::class, $result);
    }

    /** @test */
    public function getRealClassReturnsStringClassNameForRegularClass(): void
    {
        $result = ObjectHelper::getRealClass(self::class);
        $this->assertSame(self::class, $result);
    }

    // ── getRealClass with object ───────────────────────────────────────────────

    /** @test */
    public function getRealClassReturnsObjectClassWhenNotProxy(): void
    {
        $object = new \stdClass();
        $result = ObjectHelper::getRealClass($object);
        $this->assertSame(\stdClass::class, $result);
    }

    /** @test */
    public function getRealClassUnwrapsDoctrineProxy(): void
    {
        // Create a mock proxy object that extends a real class
        $proxy = new class extends \stdClass implements Proxy {
            public function __load(): void {}
            public function __isInitialized(): bool { return true; }
        };

        $result = ObjectHelper::getRealClass($proxy);
        // The proxy's parent class is stdClass
        $this->assertSame(\stdClass::class, $result);
    }

    /** @test */
    public function getRealClassReturnsProxyClassWhenNoParentClass(): void
    {
        // A proxy that has no parent class other than itself would return its own class.
        // In practice Doctrine proxies always extend the real entity, so this is an edge case.
        $plain = new \stdClass();
        $result = ObjectHelper::getRealClass($plain);
        $this->assertSame(\stdClass::class, $result);
    }

    /** @test */
    public function getRealClassHandlesNamespacedObjectClass(): void
    {
        $object = new \DateTimeImmutable();
        $result = ObjectHelper::getRealClass($object);
        $this->assertSame(\DateTimeImmutable::class, $result);
    }

    // ── getRealClass with proxy string ────────────────────────────────────────

    /** @test */
    public function getRealClassWithStringProxyReturnsParent(): void
    {
        // Create a named proxy class at runtime
        if (!class_exists('TestProxyForObjectHelper')) {
            eval('class TestProxyParentForObjectHelper {}');
            eval('class TestProxyForObjectHelper extends TestProxyParentForObjectHelper implements ' . Proxy::class . ' {
                public function __load(): void {}
                public function __isInitialized(): bool { return true; }
            }');
        }

        $result = ObjectHelper::getRealClass('TestProxyForObjectHelper');
        /** @phpstan-ignore method.impossibleType */
        $this->assertSame('TestProxyParentForObjectHelper', $result);
    }
}
