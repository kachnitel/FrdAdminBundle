<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Twig\Components;

use Kachnitel\AdminBundle\Archive\ArchiveConfig;
use Kachnitel\AdminBundle\Archive\ArchiveService;
use Kachnitel\AdminBundle\Config\EntityListConfig;
use Kachnitel\AdminBundle\DataSource\DataSourceRegistry;
use Kachnitel\AdminBundle\Service\EntityListBatchService;
use Kachnitel\AdminBundle\Service\EntityListColumnService;
use Kachnitel\AdminBundle\Service\EntityListPermissionService;
use Kachnitel\AdminBundle\Service\Preferences\AdminPreferencesStorageInterface;
use Kachnitel\AdminBundle\Twig\Components\EntityList;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Unit tests for EntityList archive toggle behaviour.
 *
 * @group archive
 * @covers \Kachnitel\AdminBundle\Twig\Components\EntityList::canToggleArchive
 * @covers \Kachnitel\AdminBundle\Twig\Components\EntityList::toggleArchive
 */
#[UsesClass(EntityListConfig::class)]
class EntityListArchiveToggleTest extends TestCase
{
    /** @var ArchiveService&MockObject */
    private ArchiveService $archiveService;

    /** @var EntityListPermissionService&MockObject */
    private EntityListPermissionService $permissionService;

    protected function setUp(): void
    {
        $this->archiveService = $this->createMock(ArchiveService::class);
        $this->permissionService = $this->createMock(EntityListPermissionService::class);
        $this->permissionService->method('canViewList')->willReturn(true);
    }

    private function makeComponent(): EntityList
    {
        $component = new EntityList(
            $this->permissionService,
            new EntityListConfig(),
            $this->createMock(DataSourceRegistry::class),
            $this->createMock(EntityListBatchService::class),
            $this->createMock(AdminPreferencesStorageInterface::class),
            $this->createMock(EntityListColumnService::class),
            $this->archiveService,
        );
        $component->entityClass = ArchiveToggleEntity::class;
        $component->entityShortClass = 'ArchiveToggleEntity';

        return $component;
    }

    private function makeConfig(): ArchiveConfig
    {
        return new ArchiveConfig(
            expression: 'entity.archived',
            field: 'archived',
            doctrineType: 'boolean',
            role: null,
        );
    }

    // ── canToggleArchive ──────────────────────────────────────────────────────

    /** @test */
    public function canToggleArchiveReturnsFalseForNonDoctrineDataSource(): void
    {
        $component = $this->makeComponent();
        $component->entityClass = '';

        $this->assertFalse($component->canToggleArchive());
    }

    /** @test */
    public function canToggleArchiveReturnsFalseWhenNoArchiveConfig(): void
    {
        $this->archiveService->method('resolveConfig')->willReturn(null);

        $this->assertFalse($this->makeComponent()->canToggleArchive());
    }

    /** @test */
    public function canToggleArchiveReturnsFalseWhenPermissionDenied(): void
    {
        $config = $this->makeConfig();
        $this->archiveService->method('resolveConfig')->willReturn($config);
        $this->permissionService->method('canToggleArchive')->with($config)->willReturn(false);

        $this->assertFalse($this->makeComponent()->canToggleArchive());
    }

    /** @test */
    public function canToggleArchiveReturnsTrueWhenConfiguredAndPermitted(): void
    {
        $config = $this->makeConfig();
        $this->archiveService->method('resolveConfig')->willReturn($config);
        $this->permissionService->method('canToggleArchive')->willReturn(true);

        $this->assertTrue($this->makeComponent()->canToggleArchive());
    }

    // ── toggleArchive ────────────────────────────────────────────────────────

    /** @test */
    public function toggleArchiveThrowsAccessDeniedWhenNotPermitted(): void
    {
        $this->archiveService->method('resolveConfig')->willReturn(null);
        $this->permissionService->method('canToggleArchive')->willReturn(false);

        $this->expectException(AccessDeniedException::class);

        $this->makeComponent()->toggleArchive();
    }

    /** @test */
    public function toggleArchiveFlipsShowArchivedFromFalseToTrue(): void
    {
        $config = $this->makeConfig();
        $this->archiveService->method('resolveConfig')->willReturn($config);
        $this->permissionService->method('canToggleArchive')->willReturn(true);

        $component = $this->makeComponent();
        $this->assertFalse($component->showArchived);

        $component->toggleArchive();

        $this->assertTrue($component->showArchived);
    }

    /** @test */
    public function toggleArchiveFlipsShowArchivedFromTrueToFalse(): void
    {
        $config = $this->makeConfig();
        $this->archiveService->method('resolveConfig')->willReturn($config);
        $this->permissionService->method('canToggleArchive')->willReturn(true);

        $component = $this->makeComponent();
        $component->showArchived = true;

        $component->toggleArchive();

        $this->assertFalse($component->showArchived);
    }

    /** @test */
    public function toggleArchiveResetsPageToOne(): void
    {
        $config = $this->makeConfig();
        $this->archiveService->method('resolveConfig')->willReturn($config);
        $this->permissionService->method('canToggleArchive')->willReturn(true);

        $component = $this->makeComponent();
        $component->page = 5;

        $component->toggleArchive();

        $this->assertSame(1, $component->page);
    }
}

/** Minimal fixture entity used by EntityListArchiveToggleTest. */
class ArchiveToggleEntity
{
    private bool $archived = false;

    public function isArchived(): bool { return $this->archived; }
}
