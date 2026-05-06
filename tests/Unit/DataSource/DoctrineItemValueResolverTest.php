<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\DataSource;

use Doctrine\ORM\Mapping\ClassMetadata;
use Kachnitel\AdminBundle\DataSource\DoctrineItemValueResolver;
use Kachnitel\AdminBundle\Tests\Fixtures\TestStatus;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Kachnitel\AdminBundle\DataSource\DoctrineItemValueResolver
 */
class DoctrineItemValueResolverTest extends TestCase
{
    private DoctrineItemValueResolver $resolver;

    /** @var ClassMetadata<object>&MockObject */
    private ClassMetadata $metadata;

    protected function setUp(): void
    {
        $this->resolver = new DoctrineItemValueResolver();
        $this->metadata = $this->createMock(ClassMetadata::class);
    }

    // ── Doctrine field path ────────────────────────────────────────────────────

    /** @test */
    public function resolvesRegularDoctrineField(): void
    {
        $entity = new \stdClass();

        $this->metadata->method('hasField')->with('name')->willReturn(true);
        $this->metadata->method('hasAssociation')->willReturn(false);
        $this->metadata->method('getFieldValue')->with($entity, 'name')->willReturn('Test Name');

        $this->assertSame('Test Name', $this->resolver->resolve($entity, 'name', $this->metadata));
    }

    /** @test */
    public function resolvesIntegerDoctrineField(): void
    {
        $entity = new \stdClass();

        $this->metadata->method('hasField')->with('quantity')->willReturn(true);
        $this->metadata->method('hasAssociation')->willReturn(false);
        $this->metadata->method('getFieldValue')->with($entity, 'quantity')->willReturn(42);

        $this->assertSame(42, $this->resolver->resolve($entity, 'quantity', $this->metadata));
    }

    /** @test */
    public function resolvesNullableDoctrineField(): void
    {
        $entity = new \stdClass();

        $this->metadata->method('hasField')->with('deletedAt')->willReturn(true);
        $this->metadata->method('hasAssociation')->willReturn(false);
        $this->metadata->method('getFieldValue')->willReturn(null);

        $this->assertNull($this->resolver->resolve($entity, 'deletedAt', $this->metadata));
    }

    // ── BackedEnum normalisation ───────────────────────────────────────────────

    /** @test */
    public function normalisesStringBackedEnumToScalarValue(): void
    {
        $entity = new \stdClass();

        $this->metadata->method('hasField')->with('status')->willReturn(true);
        $this->metadata->method('hasAssociation')->willReturn(false);
        $this->metadata->method('getFieldValue')->willReturn(TestStatus::ACTIVE);

        $result = $this->resolver->resolve($entity, 'status', $this->metadata);

        $this->assertSame('active', $result, 'String-backed enum must be unwrapped to its value.');
    }

    /** @test */
    public function normalisesInactiveEnumCaseCorrectly(): void
    {
        $entity = new \stdClass();

        $this->metadata->method('hasField')->willReturn(true);
        $this->metadata->method('hasAssociation')->willReturn(false);
        $this->metadata->method('getFieldValue')->willReturn(TestStatus::INACTIVE);

        $result = $this->resolver->resolve($entity, 'status', $this->metadata);

        $this->assertSame('inactive', $result);
    }

    // ── Doctrine association path ──────────────────────────────────────────────

    /** @test */
    public function resolvesDoctrineAssociation(): void
    {
        $entity = new \stdClass();
        $related = new \stdClass();

        $this->metadata->method('hasField')->willReturn(false);
        $this->metadata->method('hasAssociation')->with('category')->willReturn(true);
        $this->metadata->method('getFieldValue')->with($entity, 'category')->willReturn($related);

        $this->assertSame($related, $this->resolver->resolve($entity, 'category', $this->metadata));
    }

    /** @test */
    public function resolvesNullAssociation(): void
    {
        $entity = new \stdClass();

        $this->metadata->method('hasField')->willReturn(false);
        $this->metadata->method('hasAssociation')->with('category')->willReturn(true);
        $this->metadata->method('getFieldValue')->willReturn(null);

        $this->assertNull($this->resolver->resolve($entity, 'category', $this->metadata));
    }

    // ── get{Field}() getter fallback ───────────────────────────────────────────

    /** @test */
    public function fallsBackToGetterWhenNoDoctrineMapping(): void
    {
        $entity = new class {
            public function getVirtualField(): string { return 'virtual value'; }
        };

        $this->metadata->method('hasField')->willReturn(false);
        $this->metadata->method('hasAssociation')->willReturn(false);

        $this->assertSame('virtual value', $this->resolver->resolve($entity, 'virtualField', $this->metadata));
    }

    /** @test */
    public function getterReceivesPriorityOverIsGetter(): void
    {
        $entity = new class {
            public function getActive(): string { return 'from getter'; }
            public function isActive(): bool { return true; }
        };

        $this->metadata->method('hasField')->willReturn(false);
        $this->metadata->method('hasAssociation')->willReturn(false);

        $this->assertSame(
            'from getter',
            $this->resolver->resolve($entity, 'active', $this->metadata),
            'get{Field}() must take priority over is{Field}().'
        );
    }

    // ── is{Field}() getter fallback ────────────────────────────────────────────

    /** @test */
    public function fallsBackToIsBooleanGetter(): void
    {
        $entity = new class {
            public function isEnabled(): bool { return true; }
        };

        $this->metadata->method('hasField')->willReturn(false);
        $this->metadata->method('hasAssociation')->willReturn(false);

        $this->assertTrue($this->resolver->resolve($entity, 'enabled', $this->metadata));
    }

    /** @test */
    public function isBooleanGetterReturningFalseIsPreserved(): void
    {
        $entity = new class {
            public function isDeleted(): bool { return false; }
        };

        $this->metadata->method('hasField')->willReturn(false);
        $this->metadata->method('hasAssociation')->willReturn(false);

        $this->assertFalse($this->resolver->resolve($entity, 'deleted', $this->metadata));
    }

    // ── null fallback ──────────────────────────────────────────────────────────

    /** @test */
    public function returnsNullWhenNoResolutionPathExists(): void
    {
        $entity = new \stdClass();

        $this->metadata->method('hasField')->willReturn(false);
        $this->metadata->method('hasAssociation')->willReturn(false);

        $this->assertNull($this->resolver->resolve($entity, 'nonExistentProperty', $this->metadata));
    }

    /** @test */
    public function hasFieldTakesPriorityOverGetterWithSameName(): void
    {
        $entity = new class {
            public function getName(): string { return 'from getter'; }
        };

        $this->metadata->method('hasField')->with('name')->willReturn(true);
        $this->metadata->method('hasAssociation')->willReturn(false);
        $this->metadata->method('getFieldValue')->willReturn('from doctrine');

        $this->assertSame(
            'from doctrine',
            $this->resolver->resolve($entity, 'name', $this->metadata),
            'Doctrine field resolution must take priority over getter.'
        );
    }
}
