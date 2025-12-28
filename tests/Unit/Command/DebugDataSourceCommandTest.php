<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Command;

use Kachnitel\AdminBundle\Command\DebugDataSourceCommand;
use Kachnitel\AdminBundle\DataSource\ColumnMetadata;
use Kachnitel\AdminBundle\DataSource\DataSourceInterface;
use Kachnitel\AdminBundle\DataSource\DataSourceRegistry;
use Kachnitel\AdminBundle\DataSource\DoctrineDataSource;
use Kachnitel\AdminBundle\DataSource\FilterEnumOptions;
use Kachnitel\AdminBundle\DataSource\FilterMetadata;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class DebugDataSourceCommandTest extends TestCase
{
    /** @var DataSourceRegistry&MockObject */
    private DataSourceRegistry $registry;

    private DebugDataSourceCommand $command;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(DataSourceRegistry::class);
        $this->command = new DebugDataSourceCommand($this->registry);
        $this->commandTester = new CommandTester($this->command);
    }

    public function testListDataSourcesWithNoDataSources(): void
    {
        $this->registry->method('all')->willReturn([]);

        $this->commandTester->execute([]);

        $this->assertSame(0, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('No data sources registered', $output);
    }

    public function testListDataSourcesShowsAllRegisteredSourcesAndPromptsForSelection(): void
    {
        $doctrineDs = $this->createMock(DoctrineDataSource::class);
        $doctrineDs->method('getIdentifier')->willReturn('product');
        $doctrineDs->method('getLabel')->willReturn('Products');
        $doctrineDs->method('getIcon')->willReturn(null);
        $doctrineDs->method('getIdField')->willReturn('id');
        $doctrineDs->method('getDefaultSortBy')->willReturn('name');
        $doctrineDs->method('getDefaultSortDirection')->willReturn('ASC');
        $doctrineDs->method('getDefaultItemsPerPage')->willReturn(25);
        $doctrineDs->method('supportsAction')->willReturn(true);
        $doctrineDs->method('getColumns')->willReturn([]);
        $doctrineDs->method('getFilters')->willReturn([]);

        $customDs = $this->createMock(DataSourceInterface::class);
        $customDs->method('getIdentifier')->willReturn('audit-log');
        $customDs->method('getLabel')->willReturn('Audit Log');

        $this->registry->method('all')->willReturn([
            'product' => $doctrineDs,
            'audit-log' => $customDs,
        ]);
        $this->registry->method('getIdentifiers')->willReturn(['product', 'audit-log']);
        $this->registry->method('get')->with('product')->willReturn($doctrineDs);

        $this->commandTester->setInputs(['product']);
        $this->commandTester->execute([]);

        $this->assertSame(0, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Found 2 data source(s)', $output);
        $this->assertStringContainsString('product', $output);
        $this->assertStringContainsString('Products', $output);
        $this->assertStringContainsString('Doctrine', $output);
        $this->assertStringContainsString('audit-log', $output);
        $this->assertStringContainsString('Audit Log', $output);
        $this->assertStringContainsString('Custom', $output);
        $this->assertStringContainsString('Select a data source to see details', $output);
        $this->assertStringContainsString('Data Source: Products', $output);
    }

    public function testShowDetailsForNonExistentDataSource(): void
    {
        $this->registry->method('get')->with('non-existent')->willReturn(null);
        $this->registry->method('getIdentifiers')->willReturn(['product', 'audit-log']);

        $this->commandTester->execute(['--identifier' => 'non-existent']);

        $this->assertSame(1, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Data source "non-existent" not found', $output);
        $this->assertStringContainsString('Available identifiers: product, audit-log', $output);
    }

    public function testShowDetailsForExistingDataSource(): void
    {
        $dataSource = $this->createMock(DataSourceInterface::class);
        $dataSource->method('getIdentifier')->willReturn('product');
        $dataSource->method('getLabel')->willReturn('Products');
        $dataSource->method('getIcon')->willReturn('box');
        $dataSource->method('getIdField')->willReturn('id');
        $dataSource->method('getDefaultSortBy')->willReturn('name');
        $dataSource->method('getDefaultSortDirection')->willReturn('ASC');
        $dataSource->method('getDefaultItemsPerPage')->willReturn(25);
        $dataSource->method('supportsAction')->willReturnMap([
            ['index', true],
            ['show', true],
            ['new', true],
            ['edit', true],
            ['delete', true],
            ['batch_delete', true],
        ]);
        $dataSource->method('getColumns')->willReturn([]);
        $dataSource->method('getFilters')->willReturn([]);

        $this->registry->method('get')->with('product')->willReturn($dataSource);

        $this->commandTester->execute(['--identifier' => 'product']);

        $this->assertSame(0, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Data Source: Products', $output);
        $this->assertStringContainsString('product', $output);
        $this->assertStringContainsString('box', $output);
        $this->assertStringContainsString('id', $output);
        $this->assertStringContainsString('name', $output);
        $this->assertStringContainsString('ASC', $output);
        $this->assertStringContainsString('25', $output);
    }

    public function testShowDetailsWithShortOption(): void
    {
        $dataSource = $this->createMock(DataSourceInterface::class);
        $dataSource->method('getIdentifier')->willReturn('product');
        $dataSource->method('getLabel')->willReturn('Products');
        $dataSource->method('getIcon')->willReturn(null);
        $dataSource->method('getIdField')->willReturn('id');
        $dataSource->method('getDefaultSortBy')->willReturn('name');
        $dataSource->method('getDefaultSortDirection')->willReturn('ASC');
        $dataSource->method('getDefaultItemsPerPage')->willReturn(25);
        $dataSource->method('supportsAction')->willReturn(true);
        $dataSource->method('getColumns')->willReturn([]);
        $dataSource->method('getFilters')->willReturn([]);

        $this->registry->method('get')->with('product')->willReturn($dataSource);

        $this->commandTester->execute(['-i' => 'product']);

        $this->assertSame(0, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Data Source: Products', $output);
        $this->assertStringContainsString('<none>', $output); // No icon
    }

    public function testShowDetailsWithColumnsAndFilters(): void
    {
        $columns = [
            'name' => new ColumnMetadata('name', 'Product Name', 'string', true, null),
            'price' => new ColumnMetadata('price', 'Price', 'integer', true, null),
            'active' => new ColumnMetadata('active', 'Active', 'boolean', false, 'custom_template.html.twig'),
        ];

        $filters = [
            'name' => FilterMetadata::text('name', 'Name'),
            'category' => new FilterMetadata(
                name: 'category',
                type: 'enum',
                label: 'Category',
                operator: '=',
                enumOptions: FilterEnumOptions::fromValues(['electronics', 'books', 'clothing'])
            ),
            'status' => new FilterMetadata(
                name: 'status',
                type: 'enum',
                label: 'Status',
                operator: '=',
                /** @phpstan-ignore-next-line Testing with string literal for display purposes */
                enumOptions: FilterEnumOptions::fromEnumClass('App\\Enum\\ProductStatus')
            ),
        ];

        $dataSource = $this->createMock(DataSourceInterface::class);
        $dataSource->method('getIdentifier')->willReturn('product');
        $dataSource->method('getLabel')->willReturn('Products');
        $dataSource->method('getIcon')->willReturn('box');
        $dataSource->method('getIdField')->willReturn('id');
        $dataSource->method('getDefaultSortBy')->willReturn('name');
        $dataSource->method('getDefaultSortDirection')->willReturn('ASC');
        $dataSource->method('getDefaultItemsPerPage')->willReturn(25);
        $dataSource->method('supportsAction')->willReturn(true);
        $dataSource->method('getColumns')->willReturn($columns);
        $dataSource->method('getFilters')->willReturn($filters);

        $this->registry->method('get')->with('product')->willReturn($dataSource);

        $this->commandTester->execute(['--identifier' => 'product']);

        $this->assertSame(0, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();

        // Check columns section
        $this->assertStringContainsString('Columns', $output);
        $this->assertStringContainsString('Product Name', $output);
        $this->assertStringContainsString('Price', $output);
        $this->assertStringContainsString('Active', $output);
        $this->assertStringContainsString('custom_template.html.twig', $output);

        // Check filters section
        $this->assertStringContainsString('Filters', $output);
        $this->assertStringContainsString('Name', $output);
        $this->assertStringContainsString('Category', $output);
        $this->assertStringContainsString('Status', $output);
        $this->assertStringContainsString('3', $output); // 3 enum options for category
        $this->assertStringContainsString('App\\Enum\\ProductStatus', $output);
    }

    public function testShowSupportedActions(): void
    {
        $dataSource = $this->createMock(DataSourceInterface::class);
        $dataSource->method('getIdentifier')->willReturn('audit-log');
        $dataSource->method('getLabel')->willReturn('Audit Log');
        $dataSource->method('getIcon')->willReturn(null);
        $dataSource->method('getIdField')->willReturn('id');
        $dataSource->method('getDefaultSortBy')->willReturn('createdAt');
        $dataSource->method('getDefaultSortDirection')->willReturn('DESC');
        $dataSource->method('getDefaultItemsPerPage')->willReturn(50);
        $dataSource->method('supportsAction')->willReturnMap([
            ['index', true],
            ['show', true],
            ['new', false],
            ['edit', false],
            ['delete', false],
            ['batch_delete', false],
        ]);
        $dataSource->method('getColumns')->willReturn([]);
        $dataSource->method('getFilters')->willReturn([]);

        $this->registry->method('get')->with('audit-log')->willReturn($dataSource);

        $this->commandTester->execute(['--identifier' => 'audit-log']);

        $this->assertSame(0, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();

        $this->assertStringContainsString('Supported Actions', $output);
        $this->assertStringContainsString('index: ✓', $output);
        $this->assertStringContainsString('show: ✓', $output);
        $this->assertStringContainsString('new: ✗', $output);
        $this->assertStringContainsString('edit: ✗', $output);
        $this->assertStringContainsString('delete: ✗', $output);
        $this->assertStringContainsString('batch_delete: ✗', $output);
    }

    public function testShowDetailsWithNoColumnsAndFilters(): void
    {
        $dataSource = $this->createMock(DataSourceInterface::class);
        $dataSource->method('getIdentifier')->willReturn('product');
        $dataSource->method('getLabel')->willReturn('Products');
        $dataSource->method('getIcon')->willReturn(null);
        $dataSource->method('getIdField')->willReturn('id');
        $dataSource->method('getDefaultSortBy')->willReturn('name');
        $dataSource->method('getDefaultSortDirection')->willReturn('ASC');
        $dataSource->method('getDefaultItemsPerPage')->willReturn(25);
        $dataSource->method('supportsAction')->willReturn(true);
        $dataSource->method('getColumns')->willReturn([]);
        $dataSource->method('getFilters')->willReturn([]);

        $this->registry->method('get')->with('product')->willReturn($dataSource);

        $this->commandTester->execute(['--identifier' => 'product']);

        $this->assertSame(0, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();

        $this->assertStringContainsString('Columns', $output);
        $this->assertStringContainsString('No columns defined', $output);
        $this->assertStringContainsString('Filters', $output);
        $this->assertStringContainsString('No filters defined', $output);
    }
}
