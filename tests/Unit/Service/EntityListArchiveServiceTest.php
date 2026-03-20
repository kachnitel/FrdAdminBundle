<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Service;

use Kachnitel\AdminBundle\Archive\ArchiveConfig;
use Kachnitel\AdminBundle\Archive\ArchiveService;
use Kachnitel\AdminBundle\Service\EntityListArchiveService;
use Kachnitel\AdminBundle\Service\EntityListPermissionService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @group archive
 */
class EntityListArchiveServiceTest extends TestCase
{
    /** @var ArchiveService&MockObject */
    private ArchiveService $archiveService;

    /** @var EntityListPermissionService&MockObject */
    private EntityListPermissionService $permissionService;

    private EntityListArchiveService $service;

    protected function setUp(): void
    {
        $this->archiveService = $this->createMock(ArchiveService::class);
        $this->permissionService = $this->createMock(EntityListPermissionService::class);

        $this->service = new EntityListArchiveService(
            $this->archiveService,
            $this->permissionService,
        );
    }

    private function makeConfig(string $field = 'archived', string $type = 'boolean', ?string $role = null): ArchiveConfig
    {
        return new ArchiveConfig('item.' . $field, $field, $type, $role);
    }

    // ── resolveConfig() ───────────────────────────────────────────────────────

    /** @test */
    public function resolveConfigDelegatesToArchiveService(): void
    {
        $config = $this->makeConfig();

        $this->archiveService
            ->expects($this->once())
            ->method('resolveConfig')
            ->with('App\\Entity\\Product')
            ->willReturn($config);

        /** @phpstan-ignore argument.type */
        $this->assertSame($config, $this->service->resolveConfig('App\\Entity\\Product'));
    }

    /** @test */
    public function resolveConfigReturnsNullWhenNotConfigured(): void
    {
        $this->archiveService->method('resolveConfig')->willReturn(null);

        /** @phpstan-ignore argument.type */
        $this->assertNull($this->service->resolveConfig('App\\Entity\\Product'));
    }

    // ── canToggle() ───────────────────────────────────────────────────────────

    /** @test */
    public function canToggleDelegatesToPermissionService(): void
    {
        $config = $this->makeConfig();

        $this->permissionService
            ->expects($this->once())
            ->method('canToggleArchive')
            ->with($config)
            ->willReturn(true);

        $this->assertTrue($this->service->canToggle($config));
    }

    /** @test */
    public function canTogglePassesNullWhenNoConfig(): void
    {
        $this->permissionService
            ->expects($this->once())
            ->method('canToggleArchive')
            ->with(null)
            ->willReturn(false);

        $this->assertFalse($this->service->canToggle(null));
    }

    // ── isArchivedRow() ───────────────────────────────────────────────────────

    /** @test */
    public function isArchivedRowPassesEntityAndExpressionToArchiveService(): void
    {
        $entity = new \stdClass();
        $config = $this->makeConfig();

        $this->archiveService
            ->expects($this->once())
            ->method('isArchived')
            ->with($entity, 'item.archived')
            ->willReturn(true);

        $this->assertTrue($this->service->isArchivedRow($entity, $config));
    }

    /** @test */
    public function isArchivedRowReturnsFalseForNonArchivedEntity(): void
    {
        $entity = new \stdClass();
        $config = $this->makeConfig();
        $this->archiveService->method('isArchived')->willReturn(false);

        $this->assertFalse($this->service->isArchivedRow($entity, $config));
    }

    // ── buildDqlCondition() ───────────────────────────────────────────────────

    /** @test */
    public function buildDqlConditionPassesEntityAliasAndFieldInHideMode(): void
    {
        $config = $this->makeConfig();

        $this->archiveService
            ->expects($this->once())
            ->method('buildDqlCondition')
            ->with('e', 'archived', 'boolean', false)
            ->willReturn('e.archived = false');

        $this->assertSame('e.archived = false', $this->service->buildDqlCondition($config, showArchived: false));
    }

    /** @test */
    public function buildDqlConditionReturnsNullInShowAllMode(): void
    {
        $config = $this->makeConfig();
        $this->archiveService->method('buildDqlCondition')->willReturn(null);

        $this->assertNull($this->service->buildDqlCondition($config, showArchived: true));
    }

    /** @test */
    public function buildDqlConditionWorksForDatetimeField(): void
    {
        $config = $this->makeConfig('deletedAt', 'datetime_immutable');

        $this->archiveService
            ->expects($this->once())
            ->method('buildDqlCondition')
            ->with('e', 'deletedAt', 'datetime_immutable', false)
            ->willReturn('e.deletedAt IS NULL');

        $this->assertSame('e.deletedAt IS NULL', $this->service->buildDqlCondition($config, showArchived: false));
    }

    /** @test */
    public function buildDqlConditionAlwaysUsesEntityAliasE(): void
    {
        $config = $this->makeConfig();

        $this->archiveService
            ->expects($this->once())
            ->method('buildDqlCondition')
            ->with(
                $this->identicalTo('e'),
                $this->anything(),
                $this->anything(),
                $this->anything(),
            )
            ->willReturn(null);

        $this->service->buildDqlCondition($config, showArchived: false);
    }
}
