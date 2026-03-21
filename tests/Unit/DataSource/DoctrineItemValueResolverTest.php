<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\DataSource;

use Doctrine\ORM\Mapping\ClassMetadata;
use Kachnitel\AdminBundle\DataSource\DoctrineItemValueResolver;
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
        /** @var ClassMetadata<object>&MockObject $metadata */
        $metadata = $this->createMock(ClassMetadata::class);
        $this->metadata = $metadata;
    }

    /** @test */
    public function returnsFieldValueForDoctrineField(): void
    {
        $entity = new \stdClass();

        $this->metadata->method('hasField')->with('name')->willReturn(true);
        $this->metadata->method('hasAssociation')->willReturn(false);
        $this->metadata->method('getFieldValue')->with($entity, 'name')->willReturn('Alice');

        $this->assertSame('Alice', $this->resolver->resolve($entity, 'name', $this->metadata));
    }

    /** @test */
    public function returnsAssociationValueForDoctrineAssociation(): void
    {
        $entity  = new \stdClass();
        $related = new \stdClass();

        $this->metadata->method('hasField')->willReturn(false);
        $this->metadata->method('hasAssociation')->with('category')->willReturn(true);
        $this->metadata->method('getFieldValue')->with($entity, 'category')->willReturn($related);

        $this->assertSame($related, $this->resolver->resolve($entity, 'category', $this->metadata));
    }

    /** @test */
    public function fallsBackToGetterMethod(): void
    {
        $entity = new class {
            public function getCustomProp(): string { return 'custom'; }
        };

        $this->metadata->method('hasField')->willReturn(false);
        $this->metadata->method('hasAssociation')->willReturn(false);

        $this->assertSame('custom', $this->resolver->resolve($entity, 'customProp', $this->metadata));
    }

    /** @test */
    public function fallsBackToIsGetterForBooleanProperties(): void
    {
        $entity = new class {
            public function isActive(): bool { return true; }
        };

        $this->metadata->method('hasField')->willReturn(false);
        $this->metadata->method('hasAssociation')->willReturn(false);

        $this->assertTrue($this->resolver->resolve($entity, 'active', $this->metadata));
    }

    /** @test */
    public function returnsNullWhenNoResolutionPathFound(): void
    {
        $entity = new \stdClass();

        $this->metadata->method('hasField')->willReturn(false);
        $this->metadata->method('hasAssociation')->willReturn(false);

        $this->assertNull($this->resolver->resolve($entity, 'nonExistent', $this->metadata));
    }

    /** @test */
    public function prefersDoctrineFieldOverGetter(): void
    {
        $entity = new class {
            public function getName(): string { return 'getter-value'; }
        };

        $this->metadata->method('hasField')->with('name')->willReturn(true);
        $this->metadata->method('hasAssociation')->willReturn(false);
        $this->metadata->method('getFieldValue')->willReturn('doctrine-value');

        $this->assertSame('doctrine-value', $this->resolver->resolve($entity, 'name', $this->metadata));
    }

    /** @test */
    public function prefersAssociationOverGetter(): void
    {
        $related = new \stdClass();
        $entity  = new class ($related) {
            public function __construct(private readonly \stdClass $category) {}
            public function getCategory(): \stdClass { return $this->category; }
        };

        $this->metadata->method('hasField')->willReturn(false);
        $this->metadata->method('hasAssociation')->with('category')->willReturn(true);
        $this->metadata->method('getFieldValue')->willReturn($related);

        $this->assertSame($related, $this->resolver->resolve($entity, 'category', $this->metadata));
    }

    /** @test */
    public function prefersGetterOverIsGetter(): void
    {
        $entity = new class {
            public function getActive(): string { return 'string-active'; }
            public function isActive(): bool { return true; }
        };

        $this->metadata->method('hasField')->willReturn(false);
        $this->metadata->method('hasAssociation')->willReturn(false);

        $this->assertSame('string-active', $this->resolver->resolve($entity, 'active', $this->metadata));
    }
}
