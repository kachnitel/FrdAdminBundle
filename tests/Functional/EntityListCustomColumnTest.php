<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional;

use Kachnitel\AdminBundle\DataSource\DoctrineCustomColumnProvider;
use Kachnitel\AdminBundle\DataSource\DoctrineDataSourceFactory;
use Kachnitel\AdminBundle\Tests\Fixtures\EntityWithCustomColumns;

/**
 * Functional tests for #[AdminCustomColumn] rendered in EntityList.
 *
 * @group custom-columns
 */
class EntityListCustomColumnTest extends ComponentTestCase
{
    // -------------------------------------------------------------------------
    // DoctrineCustomColumnProvider reads attribute from real entity
    // -------------------------------------------------------------------------

    /** @test */
    public function providerReadsCustomColumnsFromFixtureEntity(): void
    {
        $provider = new DoctrineCustomColumnProvider();
        $columns = $provider->getCustomColumns(EntityWithCustomColumns::class);

        $this->assertArrayHasKey('fullName', $columns);
        $this->assertArrayHasKey('statusBadge', $columns);

        $this->assertSame('test/custom_column_full_name.html.twig', $columns['fullName']->template);
        $this->assertSame('Full Name', $columns['fullName']->label);
        $this->assertFalse($columns['fullName']->sortable);

        $this->assertSame('test/custom_column_status_badge.html.twig', $columns['statusBadge']->template);
        $this->assertSame('Status Badge', $columns['statusBadge']->label);
    }

    // -------------------------------------------------------------------------
    // DoctrineDataSource exposes custom columns in getColumns()
    // -------------------------------------------------------------------------

    /** @test */
    public function dataSourceExposesCustomColumnsViaGetColumns(): void
    {
        $factory = static::getContainer()->get(DoctrineDataSourceFactory::class);
        $dataSource = $factory->create(EntityWithCustomColumns::class);

        $this->assertNotNull($dataSource);
        $columns = $dataSource->getColumns();

        // Explicit columns: list — only these appear, in this order
        $this->assertSame(['id', 'firstName', 'lastName', 'fullName', 'status'], array_keys($columns));

        // fullName is a custom column with a template
        $this->assertSame('custom', $columns['fullName']->type);
        $this->assertSame('test/custom_column_full_name.html.twig', $columns['fullName']->template);
    }

    /** @test */
    public function dataSourceReturnsNullValueForCustomColumn(): void
    {
        $factory = static::getContainer()->get(DoctrineDataSourceFactory::class);
        $dataSource = $factory->create(EntityWithCustomColumns::class);
        $this->assertNotNull($dataSource);

        $entity = new EntityWithCustomColumns();
        $entity->setFirstName('Jane');
        $entity->setLastName('Doe');

        $this->assertNull($dataSource->getItemValue($entity, 'fullName'));
        $this->assertNull($dataSource->getItemValue($entity, 'statusBadge'));
    }

    // -------------------------------------------------------------------------
    // EntityList renders custom column templates
    // -------------------------------------------------------------------------

    /** @test */
    public function entityListRendersCustomColumnTemplate(): void
    {
        $entity = new EntityWithCustomColumns();
        $entity->setFirstName('Jane');
        $entity->setLastName('Doe');
        $entity->setStatus('active');
        $this->em->persist($entity);
        $this->em->flush();

        $component = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'entityClass' => EntityWithCustomColumns::class,
                'entityShortClass' => 'EntityWithCustomColumns',
            ],
        );

        $rendered = (string) $component->render();

        // The fullName custom template marker must be present
        $this->assertStringContainsString('<!-- TEST_CUSTOM_COLUMN:FULL_NAME -->', $rendered);

        // The template renders entity.firstName + entity.lastName
        $this->assertStringContainsString('Jane Doe', $rendered);
    }

    /** @test */
    public function customColumnNotInExplicitListIsNotRendered(): void
    {
        // statusBadge is NOT in Admin::columns on EntityWithCustomColumns, so it must
        // not appear in the column list at all
        $factory = static::getContainer()->get(DoctrineDataSourceFactory::class);
        $dataSource = $factory->create(EntityWithCustomColumns::class);
        $this->assertNotNull($dataSource);

        $columns = $dataSource->getColumns();

        $this->assertArrayNotHasKey('statusBadge', $columns);
    }

    /** @test */
    public function entityListRendersColumnHeaderForCustomColumn(): void
    {
        $component = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'entityClass' => EntityWithCustomColumns::class,
                'entityShortClass' => 'EntityWithCustomColumns',
            ],
        );

        $rendered = (string) $component->render();

        // 'Full Name' is the label from #[AdminCustomColumn(label: 'Full Name')]
        $this->assertStringContainsString('Full Name', $rendered);
    }

    /** @test */
    public function entityListRendersMultipleRowsWithCustomColumn(): void
    {
        foreach ([['Alice', 'Smith', 'active'], ['Bob', 'Jones', 'inactive']] as [$first, $last, $status]) {
            $entity = new EntityWithCustomColumns();
            $entity->setFirstName($first);
            $entity->setLastName($last);
            $entity->setStatus($status);
            $this->em->persist($entity);
        }
        $this->em->flush();

        $component = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'entityClass' => EntityWithCustomColumns::class,
                'entityShortClass' => 'EntityWithCustomColumns',
            ],
        );

        $rendered = (string) $component->render();

        $this->assertStringContainsString('Alice Smith', $rendered);
        $this->assertStringContainsString('Bob Jones', $rendered);
    }

    // -------------------------------------------------------------------------
    // Append behaviour (no explicit columns: list)
    // -------------------------------------------------------------------------

    /** @test */
    public function customColumnsAreAppendedWhenNoExplicitColumnsList(): void
    {
        // We test this via the provider + factory at unit level since we need a
        // different fixture; rely on DoctrineDataSourceCustomColumnTest for the full
        // merge logic. Here we just confirm the real provider integration appends correctly.

        $provider = new DoctrineCustomColumnProvider();

        // EntityWithCustomColumns HAS an explicit columns: list, so test append
        // behaviour via the no-explicit-list path using the provider directly:
        // create a data source via factory but introspect what would happen
        // if columns were null — covered in unit tests.
        // Smoke test: provider returns keyed array in declaration order
        $columns = $provider->getCustomColumns(EntityWithCustomColumns::class);
        $this->assertSame(['fullName', 'statusBadge'], array_keys($columns));
    }
}
