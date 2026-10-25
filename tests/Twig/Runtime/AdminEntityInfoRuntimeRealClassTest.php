<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Twig\Runtime;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Proxy;
use Kachnitel\AdminBundle\Service\AttributeHelper;
use Kachnitel\AdminBundle\Twig\Runtime\AdminEntityInfoRuntime;
use Kachnitel\AdminBundle\Utils\ObjectHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AdminEntityInfoRuntime::class)]
#[UsesClass(ObjectHelper::class)]
#[Group('collection-dialog')]
final class AdminEntityInfoRuntimeRealClassTest extends TestCase
{
    #[Test]
    public function returnsTheClassOfAPlainEntity(): void
    {
        $this->assertSame(\stdClass::class, $this->runtime()->getRealClass(new \stdClass()));
    }

    #[Test]
    public function unwrapsADoctrineProxyToItsParentClass(): void
    {
        $proxy = new class extends \ArrayObject implements Proxy {
            public function __load(): void {}

            public function __isInitialized(): bool
            {
                return true;
            }
        };

        $this->assertSame(\ArrayObject::class, $this->runtime()->getRealClass($proxy));
    }

    private function runtime(): AdminEntityInfoRuntime
    {
        return new AdminEntityInfoRuntime(
            $this->createStub(AttributeHelper::class),
            $this->createStub(EntityManagerInterface::class),
        );
    }
}
