<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Twig\Runtime;

use Doctrine\Persistence\Proxy;
use Kachnitel\AdminBundle\Archive\ArchiveConfig;
use Kachnitel\AdminBundle\Archive\ArchiveService;
use Kachnitel\AdminBundle\Twig\Runtime\AdminArchiveRuntime;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @group archive
 * @covers \Kachnitel\AdminBundle\Twig\Runtime\AdminArchiveRuntime
 */
class AdminArchiveRuntimeTest extends TestCase
{
    /** @var ArchiveService&MockObject */
    private ArchiveService $archiveService;

    private AdminArchiveRuntime $runtime;

    protected function setUp(): void
    {
        $this->archiveService = $this->createMock(ArchiveService::class);
        $this->runtime = new AdminArchiveRuntime($this->archiveService);
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

    /** @test */
    public function isArchivedReturnsTrueWhenArchiveServiceConfirms(): void
    {
        $entity = new \stdClass();
        $config = $this->makeConfig();

        $this->archiveService->method('resolveConfig')->willReturn($config);
        $this->archiveService->method('isArchived')->with($entity, $config->expression)->willReturn(true);

        $this->assertTrue($this->runtime->isArchived($entity));
    }

    /** @test */
    public function isArchivedReturnsFalseWhenNotArchived(): void
    {
        $entity = new \stdClass();
        $config = $this->makeConfig();

        $this->archiveService->method('resolveConfig')->willReturn($config);
        $this->archiveService->method('isArchived')->willReturn(false);

        $this->assertFalse($this->runtime->isArchived($entity));
    }

    /** @test */
    public function isArchivedReturnsFalseWhenNoConfigForEntity(): void
    {
        $entity = new \stdClass();

        $this->archiveService->method('resolveConfig')->willReturn(null);
        $this->archiveService->expects($this->never())->method('isArchived');

        $this->assertFalse($this->runtime->isArchived($entity));
    }

    /** @test */
    public function isArchivedUnwrapsDoctrineProxy(): void
    {
        // Doctrine proxies extend the real entity class; resolveConfig should
        // receive the real class name, not the proxy class name.
        $proxyEntity = new class extends \stdClass implements Proxy {
            public function __load(): void {}
            public function __isInitialized(): bool { return true; }
        };

        $config = $this->makeConfig();

        $this->archiveService
            ->expects($this->once())
            ->method('resolveConfig')
            ->with($this->callback(fn (string $cls) => !str_contains($cls, 'Proxy')))
            ->willReturn($config);

        $this->archiveService->method('isArchived')->willReturn(false);

        $this->runtime->isArchived($proxyEntity);
    }

    /** @test */
    public function isArchivedPassesExpressionStringToService(): void
    {
        $entity = new \stdClass();
        $config = new ArchiveConfig(
            expression: 'entity.deletedAt != null',
            field: 'deletedAt',
            doctrineType: 'datetime',
            role: null,
        );

        $this->archiveService->method('resolveConfig')->willReturn($config);

        $this->archiveService
            ->expects($this->once())
            ->method('isArchived')
            ->with($entity, 'entity.deletedAt != null')
            ->willReturn(true);

        $this->runtime->isArchived($entity);
    }
}
