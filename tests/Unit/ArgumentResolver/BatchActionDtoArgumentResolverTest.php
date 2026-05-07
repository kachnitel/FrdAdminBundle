<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\ArgumentResolver;

use Kachnitel\AdminBundle\ArgumentResolver\BatchActionDtoArgumentResolver;
use Kachnitel\AdminBundle\ValueObject\BatchActionDto;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

#[CoversClass(BatchActionDtoArgumentResolver::class)]
class BatchActionDtoArgumentResolverTest extends TestCase
{
    private BatchActionDtoArgumentResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new BatchActionDtoArgumentResolver();
    }

    /** @test */
    public function itResolvesBatchActionDtoFromPostData(): void
    {
        $request = new Request([], [
            'batchActionEntityIds' => ['1', '2', '3'],
            'batchActionName' => 'delete',
            'entityClass' => 'App\Entity\Product',
            'entityShortClass' => 'admin.product',
            'allSelected' => 'false',
        ]);

        $metadata = new ArgumentMetadata(
            'batchActionDto',
            BatchActionDto::class,
            false,
            false,
            null,
        );

        $result = iterator_to_array($this->resolver->resolve($request, $metadata));

        $this->assertCount(1, $result);
        $dto = $result[0];

        /** @phpstan-ignore method.alreadyNarrowedType */
        $this->assertInstanceOf(BatchActionDto::class, $dto);
        $this->assertSame('delete', $dto->getName());
        $this->assertSame(['1', '2', '3'], $dto->getEntityIds());
        $this->assertSame('App\Entity\Product', $dto->getEntityClass());
        $this->assertSame('admin.product', $dto->getEntityShortClass());
        $this->assertFalse($dto->isAllSelected());
    }

    /** @test */
    public function itHandlesAllSelectedTrue(): void
    {
        $request = new Request([], [
            'batchActionEntityIds' => [],
            'batchActionName' => 'delete',
            'entityClass' => 'App\Entity\Product',
            'entityShortClass' => 'admin.product',
            'allSelected' => 'true',
        ]);

        $metadata = new ArgumentMetadata(
            'batchActionDto',
            BatchActionDto::class,
            false,
            false,
            null,
        );

        $result = iterator_to_array($this->resolver->resolve($request, $metadata));

        $this->assertCount(1, $result);
        $dto = $result[0];

        $this->assertTrue($dto->isAllSelected());
    }

    /** @test */
    public function itReturnsEmptyIterableWhenActionNameMissing(): void
    {
        $request = new Request([], [
            'batchActionEntityIds' => ['1', '2'],
            'entityClass' => 'App\Entity\Product',
            'entityShortClass' => 'admin.product',
        ]);

        $metadata = new ArgumentMetadata(
            'batchActionDto',
            BatchActionDto::class,
            false,
            false,
            null,
        );

        $result = iterator_to_array($this->resolver->resolve($request, $metadata));

        $this->assertEmpty($result);
    }

    /** @test */
    public function itReturnsEmptyIterableWhenEntityClassMissing(): void
    {
        $request = new Request([], [
            'batchActionEntityIds' => ['1', '2'],
            'batchActionName' => 'delete',
            'entityShortClass' => 'admin.product',
        ]);

        $metadata = new ArgumentMetadata(
            'batchActionDto',
            BatchActionDto::class,
            false,
            false,
            null,
        );

        $result = iterator_to_array($this->resolver->resolve($request, $metadata));

        $this->assertEmpty($result);
    }

    /** @test */
    public function itReIndexesEntityIds(): void
    {
        $request = new Request([], [
            'batchActionEntityIds' => ['2' => 'a', '5' => 'b', '9' => 'c'],  // Non-sequential indices
            'batchActionName' => 'delete',
            'entityClass' => 'App\Entity\Product',
            'entityShortClass' => 'admin.product',
            'allSelected' => 'false',
        ]);

        $metadata = new ArgumentMetadata(
            'batchActionDto',
            BatchActionDto::class,
            false,
            false,
            null,
        );

        $result = iterator_to_array($this->resolver->resolve($request, $metadata));

        $dto = $result[0];

        // IDs should be re-indexed to 0, 1, 2
        $this->assertSame(['a', 'b', 'c'], $dto->getEntityIds());
    }

    /** @test */
    public function itHandlesEmptyEntityIds(): void
    {
        $request = new Request([], [
            'batchActionEntityIds' => [],
            'batchActionName' => 'delete',
            'entityClass' => 'App\Entity\Product',
            'entityShortClass' => 'admin.product',
            'allSelected' => 'false',
        ]);

        $metadata = new ArgumentMetadata(
            'batchActionDto',
            BatchActionDto::class,
            false,
            false,
            null,
        );

        $result = iterator_to_array($this->resolver->resolve($request, $metadata));

        $this->assertCount(1, $result);
        $dto = $result[0];

        $this->assertEmpty($dto->getEntityIds());
    }

    /** @test */
    public function itHandlesMissingEntityShortClass(): void
    {
        $request = new Request([], [
            'batchActionEntityIds' => ['1'],
            'batchActionName' => 'delete',
            'entityClass' => 'App\Entity\Product',
            // entityShortClass missing
            'allSelected' => 'false',
        ]);

        $metadata = new ArgumentMetadata(
            'batchActionDto',
            BatchActionDto::class,
            false,
            false,
            null,
        );

        $result = iterator_to_array($this->resolver->resolve($request, $metadata));

        $this->assertCount(1, $result);
        $dto = $result[0];

        // Should default to empty string
        $this->assertSame('', $dto->getEntityShortClass());
    }
}
