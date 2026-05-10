<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\BatchAction;

use Kachnitel\AdminBundle\Archive\ArchiveConfig;
use Kachnitel\AdminBundle\Archive\ArchiveService;
use Kachnitel\AdminBundle\BatchAction\ArchiveBatchActionProvider;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @group batch-actions
 * @group archive
 */
class ArchiveBatchActionProviderTest extends TestCase
{
    /** @var ArchiveService&MockObject */
    private ArchiveService $archiveService;

    private ArchiveBatchActionProvider $provider;

    protected function setUp(): void
    {
        $this->archiveService = $this->createMock(ArchiveService::class);
        $this->provider = new ArchiveBatchActionProvider($this->archiveService);
    }

    /** @test */
    public function supportsTrueWhenArchiveConfigured(): void
    {
        $config = new ArchiveConfig('item.archived', 'archived', 'boolean', null);
        $this->archiveService->method('resolveConfig')->willReturn($config);

        $this->assertTrue($this->provider->supports('App\\Entity\\Product')); // @phpstan-ignore argument.type
    }

    /** @test */
    public function supportsFalseWhenArchiveNotConfigured(): void
    {
        $this->archiveService->method('resolveConfig')->willReturn(null);

        $this->assertFalse($this->provider->supports('App\\Entity\\Product')); // @phpstan-ignore argument.type
    }

    /** @test */
    public function returnsArchiveBatchAction(): void
    {
        $config = new ArchiveConfig('item.archived', 'archived', 'boolean', null);
        $this->archiveService->method('resolveConfig')->willReturn($config);

        $actions = $this->provider->getActions('App\\Entity\\Product'); // @phpstan-ignore argument.type

        $this->assertCount(1, $actions);
        $this->assertSame('archive', $actions[0]->name);
        $this->assertSame('K:Admin:Action:Archive', $actions[0]->liveComponent);
    }

    /** @test */
    public function actionRequiresAdminArchiveVoter(): void
    {
        $config = new ArchiveConfig('item.archived', 'archived', 'boolean', null);
        $this->archiveService->method('resolveConfig')->willReturn($config);

        $actions = $this->provider->getActions('App\\Entity\\Product'); // @phpstan-ignore argument.type

        $this->assertSame(AdminEntityVoter::ADMIN_ARCHIVE, $actions[0]->voterAttribute);
    }

    /** @test */
    public function actionHasConfirmMessage(): void
    {
        $config = new ArchiveConfig('item.archived', 'archived', 'boolean', null);
        $this->archiveService->method('resolveConfig')->willReturn($config);

        $actions = $this->provider->getActions('App\\Entity\\Product'); // @phpstan-ignore argument.type

        $this->assertNotNull($actions[0]->confirmMessage);
        $this->assertStringContainsString('%count%', (string) $actions[0]->confirmMessage);
    }

    /** @test */
    public function priorityIsTwelve(): void
    {
        $this->assertSame(12, $this->provider->getPriority());
    }
}
