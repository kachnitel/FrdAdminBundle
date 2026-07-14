<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\RowAction;

use Kachnitel\AdminBundle\Archive\ArchiveConfig;
use Kachnitel\AdminBundle\Archive\ArchiveService;
use Kachnitel\AdminBundle\RowAction\ArchiveRowActionProvider;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\Tests\Fixtures\TestEntity;
use Kachnitel\AdminBundle\Tests\Fixtures\RelatedEntity;
use Kachnitel\AdminBundle\ValueObject\RowAction;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArchiveRowActionProvider::class)]
#[UsesClass(ArchiveConfig::class)]
#[UsesClass(RowAction::class)]
#[Group('archive')]
#[AllowMockObjectsWithoutExpectations]
final class ArchiveRowActionProviderTest extends TestCase
{
    /** @var ArchiveService&MockObject */
    private ArchiveService $archiveService;

    private ArchiveRowActionProvider $provider;

    protected function setUp(): void
    {
        $this->archiveService = $this->createMock(ArchiveService::class);
        $this->provider = new ArchiveRowActionProvider($this->archiveService);
    }

    private function makeConfig(string $expression = 'item.archived'): ArchiveConfig
    {
        return new ArchiveConfig($expression, 'archived', 'boolean', null);
    }

    #[Test]
    public function supportsEntityWhenArchiveIsConfigured(): void
    {
        $this->archiveService->method('resolveConfig')->willReturn($this->makeConfig());

        $this->assertTrue($this->provider->supports(TestEntity::class));
    }

    #[Test]
    public function doesNotSupportEntityWhenArchiveNotConfigured(): void
    {
        $this->archiveService->method('resolveConfig')->willReturn(null);

        $this->assertFalse($this->provider->supports(TestEntity::class));
    }

    #[Test]
    public function providesTwoActionsArchiveAndUnarchive(): void
    {
        $this->archiveService->method('resolveConfig')->willReturn($this->makeConfig());

        $actions = $this->provider->getActions(TestEntity::class);

        $this->assertCount(2, $actions);
        $names = array_map(fn ($a) => $a->name, $actions);
        $this->assertContains('archive', $names);
        $this->assertContains('unarchive', $names);
    }

    #[Test]
    public function returnsEmptyActionsWhenConfigIsNull(): void
    {
        $this->archiveService->method('resolveConfig')->willReturn(null);

        $this->assertSame([], $this->provider->getActions(TestEntity::class));
    }

    #[Test]
    public function archiveActionRequiresAdminArchiveVoterAttribute(): void
    {
        $this->archiveService->method('resolveConfig')->willReturn($this->makeConfig());

        $actions = $this->provider->getActions(TestEntity::class);
        $archive = current(array_filter($actions, fn ($a) => $a->name === 'archive'));

        $this->assertNotFalse($archive);
        $this->assertSame(AdminEntityVoter::ADMIN_ARCHIVE, $archive->voterAttribute);
    }

    #[Test]
    public function unarchiveActionRequiresAdminArchiveVoterAttribute(): void
    {
        $this->archiveService->method('resolveConfig')->willReturn($this->makeConfig());

        $actions = $this->provider->getActions(TestEntity::class);
        $unarchive = current(array_filter($actions, fn ($a) => $a->name === 'unarchive'));

        $this->assertNotFalse($unarchive);
        $this->assertSame(AdminEntityVoter::ADMIN_ARCHIVE, $unarchive->voterAttribute);
    }

    #[Test]
    public function archiveActionUsesPostMethod(): void
    {
        $this->archiveService->method('resolveConfig')->willReturn($this->makeConfig());

        $actions = $this->provider->getActions(TestEntity::class);
        $archive = current(array_filter($actions, fn ($a) => $a->name === 'archive'));

        $this->assertNotFalse($archive);
        $this->assertSame('POST', $archive->method);
    }

    #[Test]
    public function unarchiveActionUsesPostMethod(): void
    {
        $this->archiveService->method('resolveConfig')->willReturn($this->makeConfig());

        $actions = $this->provider->getActions(TestEntity::class);
        $unarchive = current(array_filter($actions, fn ($a) => $a->name === 'unarchive'));

        $this->assertNotFalse($unarchive);
        $this->assertSame('POST', $unarchive->method);
    }

    #[Test]
    public function archiveConditionIsNegationOfArchiveExpression(): void
    {
        $this->archiveService->method('resolveConfig')->willReturn($this->makeConfig('item.archived'));

        $actions = $this->provider->getActions(TestEntity::class);
        $archive = current(array_filter($actions, fn ($a) => $a->name === 'archive'));

        $this->assertNotFalse($archive);
        $this->assertIsString($archive->condition);
        $this->assertSame('!(item.archived)', $archive->condition);
    }

    #[Test]
    public function unarchiveConditionIsTheArchiveExpression(): void
    {
        $this->archiveService->method('resolveConfig')->willReturn($this->makeConfig('item.archived'));

        $actions = $this->provider->getActions(TestEntity::class);
        $unarchive = current(array_filter($actions, fn ($a) => $a->name === 'unarchive'));

        $this->assertNotFalse($unarchive);
        $this->assertIsString($unarchive->condition);
        $this->assertSame('item.archived', $unarchive->condition);
    }

    #[Test]
    public function conditionsAdaptToCustomArchiveExpression(): void
    {
        $this->archiveService->method('resolveConfig')
            ->willReturn(new ArchiveConfig('item.deletedAt', 'deletedAt', 'datetime_immutable', null));

        $actions = $this->provider->getActions(RelatedEntity::class);

        $archive   = current(array_filter($actions, fn ($a) => $a->name === 'archive'));
        $unarchive = current(array_filter($actions, fn ($a) => $a->name === 'unarchive'));

        $this->assertNotFalse($archive);
        $this->assertSame('!(item.deletedAt)', $archive->condition);

        $this->assertNotFalse($unarchive);
        $this->assertSame('item.deletedAt', $unarchive->condition);
    }

    #[Test]
    public function archiveActionHasConfirmMessage(): void
    {
        $this->archiveService->method('resolveConfig')->willReturn($this->makeConfig());

        $actions = $this->provider->getActions(TestEntity::class);
        $archive = current(array_filter($actions, fn ($a) => $a->name === 'archive'));

        $this->assertNotFalse($archive);
        $this->assertNotNull($archive->confirmMessage);
    }

    #[Test]
    public function archiveActionHasPriorityBetweenEditAndCustomActions(): void
    {
        $this->archiveService->method('resolveConfig')->willReturn($this->makeConfig());

        $actions = $this->provider->getActions(TestEntity::class);
        $archive = current(array_filter($actions, fn ($a) => $a->name === 'archive'));

        $this->assertNotFalse($archive);
        $this->assertGreaterThan(20, $archive->priority);
        $this->assertLessThan(100, $archive->priority);
    }

    #[Test]
    public function getPriorityIsGreaterThanZero(): void
    {
        $this->assertGreaterThan(0, $this->provider->getPriority());
    }
}
