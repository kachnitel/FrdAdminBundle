<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Twig\Components;

use Kachnitel\AdminBundle\Archive\ArchiveConfig;
use Kachnitel\AdminBundle\Archive\ArchiveService;
use Kachnitel\AdminBundle\Config\EntityListConfig;
use Kachnitel\AdminBundle\DataSource\DataSourceRegistry;
use Kachnitel\AdminBundle\Security\ObjectAuthorizationChecker;
use Kachnitel\AdminBundle\Service\EntityListBatchService;
use Kachnitel\AdminBundle\Service\EntityListColumnService;
use Kachnitel\AdminBundle\Service\EntityListPermissionService;
use Kachnitel\AdminBundle\Service\Preferences\AdminPreferencesStorageInterface;
use Kachnitel\AdminBundle\Twig\Components\EntityList;
use Kachnitel\DataSourceContracts\ColumnMetadata;
use Kachnitel\DataSourceContracts\DataSourceInterface;
use Kachnitel\DataSourceContracts\PaginatedResult;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[CoversClass(EntityList::class)]
#[UsesClass(EntityListConfig::class)]
#[UsesClass(ArchiveConfig::class)]
#[AllowMockObjectsWithoutExpectations]
final class EntityListEdgeCasesTest extends TestCase
{
    /** @var DataSourceInterface&MockObject */
    private DataSourceInterface $dataSource;

    private DataSourceRegistry $registry;

    /** @var EntityListPermissionService&MockObject */
    private EntityListPermissionService $permissionService;

    /** @var ArchiveService&MockObject */
    private ArchiveService $archiveService;

    /** @var AdminPreferencesStorageInterface&MockObject */
    private AdminPreferencesStorageInterface $preferencesStorage;

    /** @var EntityListColumnService&MockObject */
    private EntityListColumnService $columnService;

    protected function setUp(): void
    {
        $this->dataSource = $this->createMock(DataSourceInterface::class);
        $this->dataSource->method('getColumns')->willReturn([
            'id' => new ColumnMetadata('id', 'ID'),
            'name' => new ColumnMetadata('name', 'Name'),
        ]);
        $this->dataSource->method('supportsAction')->willReturn(true);

        $registry = $this->createStub(DataSourceRegistry::class);
        $registry->method('resolve')->willReturn($this->dataSource);
        $this->registry = $registry;

        $this->permissionService = $this->createMock(EntityListPermissionService::class);
        $this->archiveService = $this->createMock(ArchiveService::class);
        $this->preferencesStorage = $this->createMock(AdminPreferencesStorageInterface::class);
        $this->columnService = $this->createMock(EntityListColumnService::class);
        $this->columnService->method('getPermittedColumns')->willReturn([
            'id' => 'id',
            'name' => 'name',
        ]);
    }

    public function testCheckPermissionsThrowsForDeniedListUsingDataSourceIdentifier(): void
    {
        $this->permissionService->expects(self::once())
            ->method('canViewList')
            ->with('test-list')
            ->willReturn(false);

        $component = $this->makeComponent();
        $component->dataSourceId = 'test-list';

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Access denied to view test-list.');

        $component->checkPermissions();
    }

    public function testCheckPermissionsFallsBackToEntityShortClass(): void
    {
        $this->permissionService->expects(self::once())
            ->method('canViewList')
            ->with('Product')
            ->willReturn(true);

        $component = $this->makeComponent();
        $component->entityShortClass = 'Product';

        $component->checkPermissions();
    }

    public function testIsArchivedRowReturnsFalseWhenArchiveViewIsOffOrEntityIsNotDoctrine(): void
    {
        $component = $this->makeComponent();
        $component->entityClass = EdgeCaseEntity::class;

        self::assertFalse($component->isArchivedRow(new EdgeCaseEntity()));

        $component->showArchived = true;
        $component->entityClass = '';

        self::assertFalse($component->isArchivedRow(new EdgeCaseEntity()));
        $this->archiveService->expects(self::never())->method('resolveConfig');
    }

    public function testIsArchivedRowReturnsFalseWithoutArchiveConfig(): void
    {
        $this->archiveService->expects(self::once())
            ->method('resolveConfig')
            ->with(EdgeCaseEntity::class)
            ->willReturn(null);

        $component = $this->makeComponent();
        $component->entityClass = EdgeCaseEntity::class;
        $component->showArchived = true;

        self::assertFalse($component->isArchivedRow(new EdgeCaseEntity()));
    }

    public function testIsArchivedRowDelegatesConfiguredExpression(): void
    {
        $config = new ArchiveConfig('entity.archived', 'archived', 'boolean', null);
        $entity = new EdgeCaseEntity();
        $this->archiveService->method('resolveConfig')->willReturn($config);
        $this->archiveService->expects(self::once())
            ->method('isArchived')
            ->with($entity, 'entity.archived')
            ->willReturn(true);

        $component = $this->makeComponent();
        $component->entityClass = EdgeCaseEntity::class;
        $component->showArchived = true;

        self::assertTrue($component->isArchivedRow($entity));
    }

    public function testGetPaginationInfoLoadsAndReturnsTheCurrentResult(): void
    {
        $this->dataSource->expects(self::once())
            ->method('query')
            ->willReturn(new PaginatedResult([], 25, 2, 10));

        $component = $this->makeComponent();
        $component->itemsPerPage = 10;

        $pagination = $component->getPaginationInfo();

        self::assertSame(25, $pagination->totalItems);
        self::assertSame(2, $pagination->currentPage);
        self::assertSame(10, $pagination->itemsPerPage);
        self::assertSame(3, $pagination->getTotalPages());
        self::assertSame(2, $component->page);
    }

    public function testGetPaginationInfoThrowsIfOverriddenQueryDoesNotPopulateCache(): void
    {
        $component = new class(
            $this->permissionService,
            new EntityListConfig(),
            $this->registry,
            $this->createStub(EntityListBatchService::class),
            $this->preferencesStorage,
            $this->columnService,
            $this->archiveService,
            $this->createStub(ObjectAuthorizationChecker::class),
        ) extends EntityList {
            public function getEntities(): array
            {
                return [];
            }
        };

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('queryResult not populated; call getEntities() first.');

        $component->getPaginationInfo();
    }

    public function testVisibleColumnsIgnoreMalformedPreferenceValues(): void
    {
        $this->preferencesStorage->expects(self::once())
            ->method('get')
            ->with('column_visibility.test-list', [])
            ->willReturn(['name', 42, null]);

        $component = $this->makeComponent();
        $component->dataSourceId = 'test-list';

        self::assertSame(['id'], $component->getVisibleColumns());
    }

    public function testVisibleColumnsIgnoreNonArrayPreferenceValue(): void
    {
        $this->preferencesStorage->expects(self::once())
            ->method('get')
            ->with('column_visibility.test-list', [])
            ->willReturn('name');

        $component = $this->makeComponent();
        $component->dataSourceId = 'test-list';

        self::assertSame(['id' => 'id', 'name' => 'name'], $component->getVisibleColumns());
    }

    private function makeComponent(): EntityList
    {
        return new EntityList(
            $this->permissionService,
            new EntityListConfig(),
            $this->registry,
            $this->createStub(EntityListBatchService::class),
            $this->preferencesStorage,
            $this->columnService,
            $this->archiveService,
            $this->createStub(ObjectAuthorizationChecker::class),
        );
    }
}

final class EdgeCaseEntity {}
