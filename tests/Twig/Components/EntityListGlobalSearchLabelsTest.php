<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Twig\Components;

use Kachnitel\AdminBundle\Config\EntityListConfig;
use Kachnitel\AdminBundle\DataSource\DataSourceInterface;
use Kachnitel\AdminBundle\DataSource\DataSourceRegistry;
use Kachnitel\AdminBundle\DataSource\SearchAwareDataSourceInterface;
use Kachnitel\AdminBundle\Service\EntityListBatchService;
use Kachnitel\AdminBundle\Service\EntityListColumnService;
use Kachnitel\AdminBundle\Service\EntityListPermissionService;
use Kachnitel\AdminBundle\Service\Preferences\AdminPreferencesStorageInterface;
use Kachnitel\AdminBundle\Twig\Components\EntityList;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for EntityList::getGlobalSearchColumnLabels().
 *
 * @group global-search
 * @covers \Kachnitel\AdminBundle\Twig\Components\EntityList::getGlobalSearchColumnLabels
 */
#[UsesClass(EntityListConfig::class)]
class EntityListGlobalSearchLabelsTest extends TestCase
{
    /** @var DataSourceRegistry&MockObject */
    private DataSourceRegistry $registry;

    /** @var EntityListPermissionService&MockObject */
    private EntityListPermissionService $permissionService;

    private function makeComponent(): EntityList
    {
        $component = new EntityList(
            $this->permissionService,
            new EntityListConfig(),
            $this->registry,
            $this->createMock(EntityListBatchService::class),
            $this->createMock(AdminPreferencesStorageInterface::class),
            $this->createMock(EntityListColumnService::class),
        );
        $component->entityClass = 'App\\Entity\\User';
        $component->entityShortClass = 'User';
        return $component;
    }

    protected function setUp(): void
    {
        $this->registry = $this->createMock(DataSourceRegistry::class);
        $this->permissionService = $this->createMock(EntityListPermissionService::class);
        $this->permissionService->method('canViewList')->willReturn(true);
    }

    /** @test */
    public function returnsLabelsFromSearchAwareDataSource(): void
    {
        /** @var DataSourceInterface&SearchAwareDataSourceInterface&MockObject $dataSource */
        $dataSource = $this->createMockForIntersectionOfInterfaces([
            DataSourceInterface::class,
            SearchAwareDataSourceInterface::class,
        ]);
        $dataSource->method('getGlobalSearchColumnLabels')
            ->willReturn(['Name', 'Description', 'Email']);

        $this->registry->method('resolve')->willReturn($dataSource);

        $labels = $this->makeComponent()->getGlobalSearchColumnLabels();

        $this->assertSame(['Name', 'Description', 'Email'], $labels);
    }

    /** @test */
    public function returnsEmptyArrayForNonSearchAwareDataSource(): void
    {
        /** @var DataSourceInterface&MockObject $dataSource */
        $dataSource = $this->createMock(DataSourceInterface::class);
        // Does NOT implement SearchAwareDataSourceInterface

        $this->registry->method('resolve')->willReturn($dataSource);

        $labels = $this->makeComponent()->getGlobalSearchColumnLabels();

        $this->assertSame([], $labels);
    }
}
