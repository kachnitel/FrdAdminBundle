<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Kachnitel\AdminBundle\Service\ColumnPermissionService;
use Kachnitel\AdminBundle\Service\EntityListQueryService;
use Kachnitel\AdminBundle\Service\ListQueryColumnGuard;
use PHPUnit\Framework\Attributes as PHPUnit;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests for EntityListQueryService::getSearchableFieldNames().
 */
#[PHPUnit\CoversClass(EntityListQueryService::class)]
#[PHPUnit\UsesClass(ListQueryColumnGuard::class)]
#[PHPUnit\Group('global-search')]
#[PHPUnit\AllowMockObjectsWithoutExpectations]
final class EntityListQueryServiceSearchableFieldsTest extends TestCase
{
    /** @var ClassMetadata<object>&MockObject */
    private ClassMetadata $classMetadata;

    private EntityListQueryService $service;

    protected function setUp(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);

        /** @var ClassMetadata<object>&MockObject $classMetadata */
        $classMetadata = $this->createMock(ClassMetadata::class);
        $this->classMetadata = $classMetadata;

        $em->method('getClassMetadata')->willReturn($this->classMetadata);

        $this->service = new EntityListQueryService($em, new ListQueryColumnGuard($em, $this->createStub(ColumnPermissionService::class)));
    }

    #[PHPUnit\Test]
    public function returnsStringAndTextFields(): void
    {
        $this->classMetadata->method('getFieldNames')
            ->willReturn(['id', 'name', 'description', 'price', 'active']);

        $this->classMetadata->method('getTypeOfField')
            ->willReturnCallback(fn (string $field): string => match ($field) {
                'id'          => 'integer',
                'name'        => 'string',
                'description' => 'text',
                'price'       => 'decimal',
                'active'      => 'boolean',
                default       => 'string',
            });

        $result = $this->service->getSearchableFieldNames('App\\Entity\\Product');

        $this->assertSame(['name', 'description'], $result);
    }

    #[PHPUnit\Test]
    public function returnsEmptyArrayWhenNoStringOrTextField(): void
    {
        $this->classMetadata->method('getFieldNames')
            ->willReturn(['id', 'price', 'quantity', 'active']);

        $this->classMetadata->method('getTypeOfField')
            ->willReturnCallback(fn (string $field): string => match ($field) {
                'id'       => 'integer',
                'price'    => 'decimal',
                'quantity' => 'integer',
                'active'   => 'boolean',
                default    => 'integer',
            });

        $result = $this->service->getSearchableFieldNames('App\\Entity\\Product');

        $this->assertSame([], $result);
    }

    #[PHPUnit\Test]
    public function returnsAllStringFields(): void
    {
        $this->classMetadata->method('getFieldNames')
            ->willReturn(['firstName', 'lastName', 'email']);

        $this->classMetadata->method('getTypeOfField')->willReturn('string');

        $result = $this->service->getSearchableFieldNames('App\\Entity\\User');

        $this->assertSame(['firstName', 'lastName', 'email'], $result);
    }

    #[PHPUnit\Test]
    public function returnsEmptyArrayForEntityWithNoFields(): void
    {
        $this->classMetadata->method('getFieldNames')->willReturn([]);

        $result = $this->service->getSearchableFieldNames('App\\Entity\\Empty');

        $this->assertSame([], $result);
    }
}
