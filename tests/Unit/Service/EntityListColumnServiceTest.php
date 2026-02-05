<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Service;

use Kachnitel\AdminBundle\DataSource\ColumnMetadata;
use Kachnitel\AdminBundle\DataSource\DataSourceInterface;
use Kachnitel\AdminBundle\DataSource\FilterMetadata;
use Kachnitel\AdminBundle\Service\ColumnPermissionService;
use Kachnitel\AdminBundle\Service\EntityListColumnService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class EntityListColumnServiceTest extends TestCase
{
    /** @var ColumnPermissionService&MockObject */
    private ColumnPermissionService $columnPermissionService;

    /** @var DataSourceInterface&MockObject */
    private DataSourceInterface $dataSource;

    private EntityListColumnService $service;

    protected function setUp(): void
    {
        $this->columnPermissionService = $this->createMock(ColumnPermissionService::class);
        $this->dataSource = $this->createMock(DataSourceInterface::class);

        $this->service = new EntityListColumnService($this->columnPermissionService);
    }

    // --- getPermittedColumns ---

    /** @test */
    public function getPermittedColumnsReturnsAllColumnsWhenNoEntityClass(): void
    {
        $this->dataSource->method('getColumns')->willReturn([
            'id' => ColumnMetadata::create('col'),
            'name' => ColumnMetadata::create('col'),
            'email' => ColumnMetadata::create('col'),
        ]);

        $this->columnPermissionService->expects($this->never())->method('getDeniedColumns');

        $result = $this->service->getPermittedColumns($this->dataSource, '');

        $this->assertSame(['id', 'name', 'email'], $result);
    }

    /** @test */
    public function getPermittedColumnsFiltersDeniedColumnsForEntity(): void
    {
        $this->dataSource->method('getColumns')->willReturn([
            'id' => ColumnMetadata::create('col'),
            'name' => ColumnMetadata::create('col'),
            'email' => ColumnMetadata::create('col'),
            'secret' => ColumnMetadata::create('col'),
        ]);

        $this->columnPermissionService->method('getDeniedColumns')
            ->with('App\\Entity\\User')
            ->willReturn(['secret']);

        $result = $this->service->getPermittedColumns($this->dataSource, 'App\\Entity\\User');

        $this->assertSame(['id', 'name', 'email'], $result);
    }

    /** @test */
    public function getPermittedColumnsReturnsAllColumnsWhenNoneDenied(): void
    {
        $this->dataSource->method('getColumns')->willReturn([
            'id' => ColumnMetadata::create('col'),
            'name' => ColumnMetadata::create('col'),
        ]);

        $this->columnPermissionService->method('getDeniedColumns')
            ->with('App\\Entity\\Product')
            ->willReturn([]);

        $result = $this->service->getPermittedColumns($this->dataSource, 'App\\Entity\\Product');

        $this->assertSame(['id', 'name'], $result);
    }

    /** @test */
    public function getPermittedColumnsFiltersMultipleDeniedColumns(): void
    {
        $this->dataSource->method('getColumns')->willReturn([
            'id' => ColumnMetadata::create('col'),
            'name' => ColumnMetadata::create('col'),
            'salary' => ColumnMetadata::create('col'),
            'ssn' => ColumnMetadata::create('col'),
        ]);

        $this->columnPermissionService->method('getDeniedColumns')
            ->willReturn(['salary', 'ssn']);

        $result = $this->service->getPermittedColumns($this->dataSource, 'App\\Entity\\Employee');

        $this->assertSame(['id', 'name'], $result);
    }

    // --- getPermittedFilters ---

    /** @test */
    public function getPermittedFiltersReturnsAllFiltersWhenNoEntityClass(): void
    {
        $nameFilter = FilterMetadata::text('name');
        $emailFilter = FilterMetadata::text('email');

        $this->dataSource->method('getColumns')->willReturn([
            'id' => ColumnMetadata::create('col'),
            'name' => ColumnMetadata::create('col'),
            'email' => ColumnMetadata::create('col'),
        ]);
        $this->dataSource->method('getFilters')->willReturn([
            'name' => $nameFilter,
            'email' => $emailFilter,
        ]);

        $this->columnPermissionService->expects($this->never())->method('getDeniedColumns');

        $result = $this->service->getPermittedFilters($this->dataSource, '');

        $this->assertCount(2, $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('email', $result);
        $this->assertSame($nameFilter->toArray(), $result['name']);
    }

    /** @test */
    public function getPermittedFiltersExcludesDeniedColumnFilters(): void
    {
        $nameFilter = FilterMetadata::text('name');
        $secretFilter = FilterMetadata::text('secret');

        $this->dataSource->method('getColumns')->willReturn([
            'id' => ColumnMetadata::create('col'),
            'name' => ColumnMetadata::create('col'),
            'secret' => ColumnMetadata::create('col'),
        ]);
        $this->dataSource->method('getFilters')->willReturn([
            'name' => $nameFilter,
            'secret' => $secretFilter,
        ]);

        $this->columnPermissionService->method('getDeniedColumns')
            ->with('App\\Entity\\User')
            ->willReturn(['secret']);

        $result = $this->service->getPermittedFilters($this->dataSource, 'App\\Entity\\User');

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayNotHasKey('secret', $result);
    }

    /** @test */
    public function getPermittedFiltersReturnsEmptyWhenAllFiltersDenied(): void
    {
        $secretFilter = FilterMetadata::text('secret');

        $this->dataSource->method('getColumns')->willReturn([
            'secret' => ColumnMetadata::create('col'),
        ]);
        $this->dataSource->method('getFilters')->willReturn([
            'secret' => $secretFilter,
        ]);

        $this->columnPermissionService->method('getDeniedColumns')
            ->willReturn(['secret']);

        $result = $this->service->getPermittedFilters($this->dataSource, 'App\\Entity\\User');

        $this->assertSame([], $result);
    }

    /** @test */
    public function getPermittedFiltersConvertsFilterMetadataToArray(): void
    {
        $filter = FilterMetadata::number('price', operator: '>');

        $this->dataSource->method('getColumns')->willReturn([
            'price' => ColumnMetadata::create('col'),
        ]);
        $this->dataSource->method('getFilters')->willReturn([
            'price' => $filter,
        ]);

        $this->columnPermissionService->method('getDeniedColumns')->willReturn([]);

        $result = $this->service->getPermittedFilters($this->dataSource, 'App\\Entity\\Product');

        $this->assertSame($filter->toArray(), $result['price']);
    }
}
