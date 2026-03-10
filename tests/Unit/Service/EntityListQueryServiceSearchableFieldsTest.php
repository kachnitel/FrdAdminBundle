<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Kachnitel\AdminBundle\Service\EntityListQueryService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests for EntityListQueryService::getSearchableFieldNames().
 *
 * @group global-search
 * @covers \Kachnitel\AdminBundle\Service\EntityListQueryService::getSearchableFieldNames
 */
class EntityListQueryServiceSearchableFieldsTest extends TestCase
{
    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $em;

    /** @var ClassMetadata<object>&MockObject */
    private ClassMetadata $classMetadata;

    private EntityListQueryService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);

        /** @var ClassMetadata<object>&MockObject $classMetadata */
        $classMetadata = $this->createMock(ClassMetadata::class);
        $this->classMetadata = $classMetadata;

        $this->em->method('getClassMetadata')->willReturn($this->classMetadata);

        $this->service = new EntityListQueryService($this->em);
    }

    /** @test */
    public function returnsStringAndTextFields(): void
    {
        $this->classMetadata->method('getFieldNames')
            ->willReturn(['id', 'name', 'description', 'price', 'active']);

        $this->classMetadata->method('getTypeOfField')
            ->willReturnCallback(fn (string $field) => match ($field) {
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

    /** @test */
    public function returnsEmptyArrayWhenNoStringOrTextField(): void
    {
        $this->classMetadata->method('getFieldNames')
            ->willReturn(['id', 'price', 'quantity', 'active']);

        $this->classMetadata->method('getTypeOfField')
            ->willReturnCallback(fn (string $field) => match ($field) {
                'id'       => 'integer',
                'price'    => 'decimal',
                'quantity' => 'integer',
                'active'   => 'boolean',
                default    => 'integer',
            });

        $result = $this->service->getSearchableFieldNames('App\\Entity\\Product');

        $this->assertSame([], $result);
    }

    /** @test */
    public function returnsAllStringFields(): void
    {
        $this->classMetadata->method('getFieldNames')
            ->willReturn(['firstName', 'lastName', 'email']);

        $this->classMetadata->method('getTypeOfField')->willReturn('string');

        $result = $this->service->getSearchableFieldNames('App\\Entity\\User');

        $this->assertSame(['firstName', 'lastName', 'email'], $result);
    }

    /** @test */
    public function returnsEmptyArrayForEntityWithNoFields(): void
    {
        $this->classMetadata->method('getFieldNames')->willReturn([]);

        $result = $this->service->getSearchableFieldNames('App\\Entity\\Empty');

        $this->assertSame([], $result);
    }
}
