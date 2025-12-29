<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional;

use Kachnitel\AdminBundle\DataSource\DataSourceRegistry;
use Kachnitel\AdminBundle\Tests\Fixtures\CustomTemplateDataSource;
use Kachnitel\AdminBundle\Tests\Fixtures\TestDataSourceProvider;

/**
 * Tests for custom column template rendering in EntityList component.
 *
 * Verifies that ColumnMetadata.template is respected when rendering columns
 * in DataSource mode.
 */
class EntityListColumnTemplateTest extends ComponentTestCase
{
    private CustomTemplateDataSource $dataSource;

    protected function setUp(): void
    {
        parent::setUp();

        // Get the custom data source via the provider
        $container = static::getContainer();
        /** @var TestDataSourceProvider $provider */
        $provider = $container->get(TestDataSourceProvider::class);
        $this->dataSource = $provider->getCustomTemplateDataSource();
    }

    public function testCustomColumnTemplateIsUsed(): void
    {
        // Set up test data with changes
        $item = (object) [
            'id' => 1,
            'name' => 'Test Item',
            'changes' => [
                'total_changes' => 2,
                'changes' => [
                    ['field' => 'status', 'type' => 'update', 'old' => 'pending', 'new' => 'active'],
                    ['field' => 'name', 'type' => 'update', 'old' => 'Old Name', 'new' => 'New Name'],
                ],
            ],
            'status' => 'active',
        ];
        $this->dataSource->setItems([$item]);

        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: ['dataSourceId' => 'custom-template-test'],
        );

        $rendered = (string) $testComponent->render();

        // Verify custom template markers are present
        $this->assertStringContainsString('<!-- TEST_CUSTOM_TEMPLATE:CHANGES -->', $rendered);
        $this->assertStringContainsString('<!-- TEST_CUSTOM_TEMPLATE:STATUS -->', $rendered);

        // Verify changes template content
        $this->assertStringContainsString('2 change(s)', $rendered);
        $this->assertStringContainsString('pending → active', $rendered);

        // Verify status template content
        $this->assertStringContainsString('status-active', $rendered);
    }

    public function testCustomTemplateReceivesEntityAndItemVariables(): void
    {
        $item = (object) [
            'id' => 1,
            'name' => 'Entity Test',
            'changes' => ['total_changes' => 1, 'changes' => []],
            'status' => 'pending',
        ];
        $this->dataSource->setItems([$item]);

        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: ['dataSourceId' => 'custom-template-test'],
        );

        $rendered = (string) $testComponent->render();

        // Verify that both entity and item variables are available in the template
        $this->assertStringContainsString('<!-- ENTITY_AND_ITEM_AVAILABLE -->', $rendered);
    }

    public function testStatusColumnRendersCorrectBadges(): void
    {
        // Test different status values
        $items = [
            (object) ['id' => 1, 'name' => 'Active Item', 'changes' => null, 'status' => 'active'],
            (object) ['id' => 2, 'name' => 'Pending Item', 'changes' => null, 'status' => 'pending'],
            (object) ['id' => 3, 'name' => 'Inactive Item', 'changes' => null, 'status' => 'inactive'],
        ];
        $this->dataSource->setItems($items);

        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: ['dataSourceId' => 'custom-template-test'],
        );

        $rendered = (string) $testComponent->render();

        // Verify each status badge is rendered correctly
        $this->assertStringContainsString('status-active', $rendered);
        $this->assertStringContainsString('status-pending', $rendered);
        $this->assertStringContainsString('status-inactive', $rendered);
        $this->assertStringContainsString('bg-success', $rendered);
        $this->assertStringContainsString('bg-warning', $rendered);
        $this->assertStringContainsString('bg-secondary', $rendered);
    }

    public function testColumnsWithoutCustomTemplateUseDefaultRendering(): void
    {
        $item = (object) [
            'id' => 42,
            'name' => 'Default Template Item',
            'changes' => null,
            'status' => 'active',
        ];
        $this->dataSource->setItems([$item]);

        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: ['dataSourceId' => 'custom-template-test'],
        );

        $rendered = (string) $testComponent->render();

        // Verify name column uses default template (no custom marker)
        $this->assertStringContainsString('Default Template Item', $rendered);
        // The name column should use the standard _preview.html.twig from test overrides
        $this->assertStringContainsString('<!-- TEST_OVERRIDE:PREVIEW -->', $rendered);
    }

    public function testCustomTemplateHandlesNullValues(): void
    {
        $item = (object) [
            'id' => 1,
            'name' => 'Null Changes',
            'changes' => null,
            'status' => 'active',
        ];
        $this->dataSource->setItems([$item]);

        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: ['dataSourceId' => 'custom-template-test'],
        );

        $rendered = (string) $testComponent->render();

        // Verify null handling in custom template
        $this->assertStringContainsString('No changes', $rendered);
    }

    public function testDataSourceWithCustomTemplatesIsRegistered(): void
    {
        $container = static::getContainer();
        /** @var DataSourceRegistry $registry */
        $registry = $container->get(DataSourceRegistry::class);

        $dataSource = $registry->get('custom-template-test');

        $this->assertNotNull($dataSource);
        $this->assertSame('custom-template-test', $dataSource->getIdentifier());
    }

    public function testColumnMetadataHasTemplateProperty(): void
    {
        $container = static::getContainer();
        /** @var DataSourceRegistry $registry */
        $registry = $container->get(DataSourceRegistry::class);

        $dataSource = $registry->get('custom-template-test');
        $columns = $dataSource->getColumns();

        // changes column has custom template
        $this->assertArrayHasKey('changes', $columns);
        $this->assertSame('test/column_changes.html.twig', $columns['changes']->template);

        // status column has custom template
        $this->assertArrayHasKey('status', $columns);
        $this->assertSame('test/column_status.html.twig', $columns['status']->template);

        // name column has no custom template
        $this->assertArrayHasKey('name', $columns);
        $this->assertNull($columns['name']->template);
    }

    public function testCustomTemplateWithComplexChangesData(): void
    {
        $item = (object) [
            'id' => 1,
            'name' => 'Complex Changes',
            'changes' => [
                'total_changes' => 3,
                'changes' => [
                    ['field' => 'title', 'type' => 'update', 'old' => 'Old', 'new' => 'New', 'removed_count' => 0, 'added_count' => 0],
                    ['field' => 'tags', 'type' => 'add', 'added_count' => 2, 'removed_count' => 0],
                    ['field' => 'users', 'type' => 'remove', 'added_count' => 0, 'removed_count' => 1],
                ],
            ],
            'status' => 'active',
        ];
        $this->dataSource->setItems([$item]);

        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: ['dataSourceId' => 'custom-template-test'],
        );

        $rendered = (string) $testComponent->render();

        // Verify complex data is rendered
        $this->assertStringContainsString('3 change(s)', $rendered);
        $this->assertStringContainsString('Old → New', $rendered);
    }
}
