<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional;

use Kachnitel\AdminBundle\DataSource\DataSourceRegistry;
use Kachnitel\AdminBundle\Tests\Fixtures\TestEntity;

/**
 * Functional tests for EntityList component in dataSourceId mode.
 */
class EntityListDataSourceTest extends ComponentTestCase
{
    public function testComponentWorksWithDataSourceId(): void
    {
        // Create test data
        for ($i = 1; $i <= 5; $i++) {
            $entity = new TestEntity();
            $entity->setName('Entity ' . $i);
            $this->em->persist($entity);
        }
        $this->em->flush();

        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: ['dataSourceId' => 'TestEntity'],
        );

        $rendered = (string) $testComponent->render();

        $this->assertStringContainsString('<table', $rendered);
        $this->assertStringContainsString('Entity 1', $rendered);
        $this->assertStringContainsString('Entity 5', $rendered);
    }

    public function testDataSourceIdResolvesFromRegistry(): void
    {
        $container = static::getContainer();
        /** @var DataSourceRegistry $registry */
        $registry = $container->get(DataSourceRegistry::class);

        $dataSource = $registry->get('TestEntity');

        $this->assertNotNull($dataSource);
        $this->assertSame('TestEntity', $dataSource->getIdentifier());
    }

    public function testComponentIsUsingDataSourceReturnsTrue(): void
    {
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: ['dataSourceId' => 'TestEntity'],
        );

        $component = $testComponent->component();

        $this->assertTrue($component->isUsingDataSource());
        $this->assertNotNull($component->getDataSource());
    }

    public function testComponentIsUsingDataSourceReturnsFalseInLegacyMode(): void
    {
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'entityClass' => TestEntity::class,
                'entityShortClass' => 'TestEntity',
            ],
        );

        $component = $testComponent->component();

        // In legacy mode with entityShortClass, it should resolve via registry
        $this->assertTrue($component->isUsingDataSource());
    }

    public function testDataSourceModeShowsColumns(): void
    {
        $entity = new TestEntity();
        $entity->setName('Test Name');
        $this->em->persist($entity);
        $this->em->flush();

        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: ['dataSourceId' => 'TestEntity'],
        );

        $rendered = (string) $testComponent->render();

        // Should show column headers
        $this->assertStringContainsString('Name', $rendered);
        $this->assertStringContainsString('Test Name', $rendered);
    }

    public function testDataSourceModeSorting(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $entity = new TestEntity();
            $entity->setName('Entity ' . str_pad((string) $i, 2, '0', STR_PAD_LEFT));
            $this->em->persist($entity);
        }
        $this->em->flush();

        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'dataSourceId' => 'TestEntity',
                'sortBy' => 'name',
                'sortDirection' => 'ASC',
            ],
        );

        $component = $testComponent->component();
        $entities = $component->getEntities();

        $this->assertSame('Entity 01', $entities[0]->getName());
        $this->assertSame('Entity 05', $entities[4]->getName());
    }

    public function testDataSourceModePagination(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            $entity = new TestEntity();
            $entity->setName('Entity ' . $i);
            $this->em->persist($entity);
        }
        $this->em->flush();

        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'dataSourceId' => 'TestEntity',
                'itemsPerPage' => 10,
            ],
        );

        $component = $testComponent->component();
        $paginationInfo = $component->getPaginationInfo();

        $this->assertSame(25, $paginationInfo->totalItems);
        $this->assertSame(3, $paginationInfo->getTotalPages());
        $this->assertCount(10, $component->getEntities());
    }

    public function testDataSourceModeSearch(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $entity = new TestEntity();
            $entity->setName($i % 2 === 0 ? 'Special ' . $i : 'Regular ' . $i);
            $this->em->persist($entity);
        }
        $this->em->flush();

        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'dataSourceId' => 'TestEntity',
                'search' => 'Special',
            ],
        );

        $component = $testComponent->component();
        $entities = $component->getEntities();

        $this->assertCount(5, $entities);
        foreach ($entities as $entity) {
            $this->assertStringContainsString('Special', $entity->getName());
        }
    }

    public function testDataSourceModeColumnFilters(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $entity = new TestEntity();
            $entity->setName($i <= 5 ? 'Alpha' . $i : 'Beta' . $i);
            $this->em->persist($entity);
        }
        $this->em->flush();

        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'dataSourceId' => 'TestEntity',
                'columnFilters' => ['name' => 'Alpha'],
            ],
        );

        $component = $testComponent->component();
        $entities = $component->getEntities();

        $this->assertCount(5, $entities);
        foreach ($entities as $entity) {
            $this->assertStringContainsString('Alpha', $entity->getName());
        }
    }

    public function testGetEntityIdWorksInDataSourceMode(): void
    {
        $entity = new TestEntity();
        $entity->setName('Test');
        $this->em->persist($entity);
        $this->em->flush();

        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: ['dataSourceId' => 'TestEntity'],
        );

        $component = $testComponent->component();
        $entities = $component->getEntities();

        $entityId = $component->getEntityId($entities[0]);

        $this->assertNotNull($entityId);
        $this->assertIsInt($entityId);
    }

    public function testGetEntityValueWorksInDataSourceMode(): void
    {
        $entity = new TestEntity();
        $entity->setName('Test Value');
        $this->em->persist($entity);
        $this->em->flush();

        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: ['dataSourceId' => 'TestEntity'],
        );

        $component = $testComponent->component();
        $entities = $component->getEntities();

        $value = $component->getEntityValue($entities[0], 'name');

        $this->assertSame('Test Value', $value);
    }

    public function testGetColumnsWorksInDataSourceMode(): void
    {
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: ['dataSourceId' => 'TestEntity'],
        );

        $component = $testComponent->component();
        $columns = $component->getColumns();

        $this->assertIsArray($columns);
        $this->assertNotEmpty($columns);
        $this->assertContains('name', $columns);
    }

    public function testGetFilterMetadataWorksInDataSourceMode(): void
    {
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: ['dataSourceId' => 'TestEntity'],
        );

        $component = $testComponent->component();
        $filters = $component->getFilterMetadata();

        $this->assertIsArray($filters);
        // Should have filters for TestEntity fields
        $this->assertArrayHasKey('name', $filters);
    }

    public function testBatchActionsNotSupportedInDataSourceModeByDefault(): void
    {
        // EntityWithoutBatchActions has #[Admin] without enableBatchActions
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: ['dataSourceId' => 'EntityWithoutBatchActions'],
        );

        $component = $testComponent->component();

        // EntityWithoutBatchActions has #[Admin] without enableBatchActions, so batch is disabled
        $this->assertFalse($component->supportsBatchActions());

        $rendered = (string) $testComponent->render();

        // Batch delete button should not be present
        $this->assertStringNotContainsString('batchDelete', $rendered);
        // Batch select checkboxes should not be present
        $this->assertStringNotContainsString('selectedIds[]', $rendered);
    }

    public function testEmptyDataSourceShowsNoResultsMessage(): void
    {
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: ['dataSourceId' => 'TestEntity'],
        );

        $rendered = (string) $testComponent->render();

        // Empty table shows "No X found" message
        $this->assertStringContainsString('No ', $rendered);
        $this->assertStringContainsString(' found', $rendered);
    }

    public function testDataSourceModePreservesStateAcrossRerenders(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $entity = new TestEntity();
            $entity->setName('Entity ' . $i);
            $this->em->persist($entity);
        }
        $this->em->flush();

        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: ['dataSourceId' => 'TestEntity'],
        );

        // Set some state
        $testComponent->set('search', 'Entity 1');
        $testComponent->set('sortBy', 'name');

        // Re-render and check state preserved
        $rendered = (string) $testComponent->render();

        $component = $testComponent->component();
        $this->assertSame('Entity 1', $component->search);
        $this->assertSame('name', $component->sortBy);
    }
}
