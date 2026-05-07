<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\BatchAction;

use Kachnitel\AdminBundle\BatchAction\DefaultBatchActionProvider;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DefaultBatchActionProvider::class)]
class DefaultBatchActionProviderTest extends TestCase
{
    private DefaultBatchActionProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new DefaultBatchActionProvider();
    }

    /** @test */
    public function itSupportsAllEntities(): void
    {
        $this->assertTrue($this->provider->supports('App\Entity\Product'));
        $this->assertTrue($this->provider->supports('App\Entity\Article'));
        $this->assertTrue($this->provider->supports(''));
    }

    /** @test */
    public function itProvidesBatchDeleteAction(): void
    {
        $actions = $this->provider->getActions('App\Entity\Product');

        $this->assertCount(1, $actions);
        $action = $actions[0];

        $this->assertSame('batch_delete', $action->name);
        $this->assertSame('Delete Selected', $action->label);
    }

    /** @test */
    public function itSetsDeleteActionProperties(): void
    {
        $actions = $this->provider->getActions('App\Entity\Product');
        $action = $actions[0];

        $this->assertSame('🗑️', $action->icon);
        $this->assertSame('batchDelete', $action->liveAction);
        $this->assertSame(AdminEntityVoter::ADMIN_DELETE, $action->voterAttribute);
        $this->assertNotNull($action->confirmMessage);
        $this->assertStringContainsString('%count%', $action->confirmMessage);
        $this->assertSame(0, $action->priority);
    }

    /** @test */
    public function itHasConfirmationMessage(): void
    {
        $actions = $this->provider->getActions('App\Entity\Product');
        $action = $actions[0];

        $this->assertTrue($action->requiresConfirmation());
    }

    /** @test */
    public function itReturnsLowestPriority(): void
    {
        $actions = $this->provider->getActions('App\Entity\Product');
        $action = $actions[0];

        // Priority 0 means it can be overridden by any custom provider with higher priority
        $this->assertSame(0, $action->priority);
    }

    /** @test */
    public function itReturnsProviderPriority(): void
    {
        $this->assertSame(0, $this->provider->getPriority());
    }
}
