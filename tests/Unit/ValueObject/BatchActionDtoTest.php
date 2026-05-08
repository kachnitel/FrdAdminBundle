<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\ValueObject;

use Kachnitel\AdminBundle\ValueObject\BatchActionDto;
use PHPUnit\Framework\TestCase;

/**
 * @group batch-actions
 */
class BatchActionDtoTest extends TestCase
{
    /** @test */
    public function itStoresRequiredProperties(): void
    {
        $dto = new BatchActionDto(
            name: 'publish',
            entityIds: [1, 2, 3],
            entityClass: 'App\\Entity\\Product',
            entityShortClass: 'Product',
        );

        $this->assertSame('publish', $dto->name);
        $this->assertSame([1, 2, 3], $dto->entityIds);
        $this->assertSame('App\\Entity\\Product', $dto->entityClass);
        $this->assertSame('Product', $dto->entityShortClass);
    }

    /** @test */
    public function getCountReturnsNumberOfSelectedEntities(): void
    {
        $dto = new BatchActionDto(
            name: 'delete',
            entityIds: [1, 2, 3, 4, 5],
            entityClass: 'App\\Entity\\Product',
            entityShortClass: 'Product',
        );

        $this->assertSame(5, $dto->getCount());
    }

    /** @test */
    public function getCountReturnsZeroForEmptySelection(): void
    {
        $dto = new BatchActionDto(
            name: 'delete',
            entityIds: [],
            entityClass: 'App\\Entity\\Product',
            entityShortClass: 'Product',
        );

        $this->assertSame(0, $dto->getCount());
    }

    /** @test */
    public function isReadonly(): void
    {
        $reflection = new \ReflectionClass(BatchActionDto::class);
        $this->assertTrue($reflection->isReadOnly());
    }
}
