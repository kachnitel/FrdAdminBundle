<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Twig\Components;

use Kachnitel\AdminBundle\Archive\ArchiveService;
use Kachnitel\AdminBundle\Config\EntityListConfig;
use Kachnitel\AdminBundle\DataSource\DataSourceRegistry;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\Security\ObjectAuthorizationChecker;
use Kachnitel\AdminBundle\Service\EntityListBatchService;
use Kachnitel\AdminBundle\Service\EntityListColumnService;
use Kachnitel\AdminBundle\Service\EntityListPermissionService;
use Kachnitel\AdminBundle\Service\Preferences\AdminPreferencesStorageInterface;
use Kachnitel\AdminBundle\Twig\Components\EntityList;
use Kachnitel\DataSourceContracts\DataSourceInterface;
use PHPUnit\Framework\Attributes as PHPUnit;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Covers EntityList::editRow()'s object-level authorization check.
 *
 * This is a UX gate, not the security boundary — the actual enforcement for
 * every inline-edit save is AdminEditabilityResolver::canEdit() (see
 * AdminEditabilityResolverTest's "Object-level authorization" section),
 * consulted independently by each field component regardless of
 * $editingRowId. editRow() denying here only stops a denied row from
 * entering an edit state with nothing actually editable in it.
 */
#[PHPUnit\CoversClass(EntityList::class)]
#[PHPUnit\UsesClass(EntityListConfig::class)]
#[PHPUnit\Group('entity-list')]
#[PHPUnit\Group('inline-edit')]
#[PHPUnit\Group('object-authorization')]
final class EntityListObjectAuthorizationTest extends TestCase
{
    private EntityListPermissionService $permissionService;

    /** @var DataSourceRegistry&MockObject */
    private DataSourceRegistry $dataSourceRegistry;

    /** @var DataSourceInterface&MockObject */
    private DataSourceInterface $dataSource;

    /** @var ObjectAuthorizationChecker&MockObject */
    private ObjectAuthorizationChecker $objectAuthChecker;

    protected function setUp(): void
    {
        $this->permissionService = $this->createStub(EntityListPermissionService::class);
        /** @disregard P1013 Undefined method 'method'. */
        $this->permissionService->method('canInlineEdit')->willReturn(true);

        $this->objectAuthChecker = $this->createMock(ObjectAuthorizationChecker::class);
    }

    private function makeEntityList(): EntityList
    {
        $entityList = new EntityList(
            permissionService: $this->permissionService,
            config: new EntityListConfig(),
            dataSourceRegistry: $this->dataSourceRegistry,
            batchService: $this->createStub(EntityListBatchService::class),
            preferencesStorage: $this->createStub(AdminPreferencesStorageInterface::class),
            columnService: $this->createStub(EntityListColumnService::class),
            archiveService: $this->createStub(ArchiveService::class),
            objectAuthChecker: $this->objectAuthChecker,
        );

        $entityList->entityShortClass = 'Product';
        $entityList->entityClass      = 'App\\Entity\\Product';

        return $entityList;
    }

    private function stubDataSourceFind(?object $entity): void
    {
        $this->dataSource = $this->createMock(DataSourceInterface::class);
        $this->dataSource
            ->expects($this->once())
            ->method('find')
            ->willReturn($entity);

        $this->dataSourceRegistry = $this->createMock(DataSourceRegistry::class);
        $this->dataSourceRegistry
            ->expects($this->atLeastOnce())
            ->method('resolve')
            ->willReturn($this->dataSource);
    }

    #[PHPUnit\Test]
    public function editRowSucceedsWhenObjectAuthorizationGrants(): void
    {
        $entity = new \stdClass();
        $this->stubDataSourceFind($entity);

        $this->objectAuthChecker
            ->expects($this->once())
            ->method('isGranted')
            ->with(AdminEntityVoter::ADMIN_EDIT, $this->identicalTo($entity))
            ->willReturn(true);

        $entityList = $this->makeEntityList();
        $entityList->editRow(5);

        $this->assertSame(5, $entityList->editingRowId);
    }

    #[PHPUnit\Test]
    public function editRowDeniesWhenObjectAuthorizationDenies(): void
    {
        $entity = new \stdClass();
        $this->stubDataSourceFind($entity);

        $this->objectAuthChecker
            ->expects($this->once())
            ->method('isGranted')
            ->willReturn(false);

        $entityList = $this->makeEntityList();

        $this->expectException(AccessDeniedException::class);

        try {
            $entityList->editRow(5);
        } finally {
            $this->assertNull($entityList->editingRowId, 'A denied row must not enter edit mode.');
        }
    }

    #[PHPUnit\Test]
    public function editRowSkipsObjectAuthorizationWhenEntityCannotBeFound(): void
    {
        $this->stubDataSourceFind(null);

        $this->objectAuthChecker->expects($this->never())->method('isGranted');

        $entityList = $this->makeEntityList();
        $entityList->editRow(999);

        // Matches pre-existing behaviour: an unresolvable id still activates
        // edit mode — editRow() has never validated $id against the query.
        $this->assertSame(999, $entityList->editingRowId);
    }

    #[PHPUnit\Test]
    public function editRowStillDeniesOnClassLevelPermissionRegardlessOfObjectAuthorization(): void
    {
        // Create a fresh test with permissionService denying
        $permissionService = $this->createStub(EntityListPermissionService::class);
        $permissionService->method('canInlineEdit')->willReturn(false);

        $this->objectAuthChecker->expects($this->never())->method('isGranted');

        $entityList = new EntityList(
            permissionService: $permissionService,
            config: new EntityListConfig(),
            dataSourceRegistry: $this->createStub(DataSourceRegistry::class),
            batchService: $this->createStub(EntityListBatchService::class),
            preferencesStorage: $this->createStub(AdminPreferencesStorageInterface::class),
            columnService: $this->createStub(EntityListColumnService::class),
            archiveService: $this->createStub(ArchiveService::class),
            objectAuthChecker: $this->objectAuthChecker,
        );

        $entityList->entityShortClass = 'Product';
        $entityList->entityClass      = 'App\\Entity\\Product';

        $this->expectException(AccessDeniedException::class);

        $entityList->editRow(5);
    }
}
