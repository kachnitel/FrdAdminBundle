<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\ValueObject;

use Kachnitel\AdminBundle\ValueObject\BatchAction;
use PHPUnit\Framework\Attributes as PHPUnit;
use PHPUnit\Framework\TestCase;

#[PHPUnit\Group('batch-actions')]
final class BatchActionTest extends TestCase
{
    #[PHPUnit\Test]
    public function itCreatesActionWithRequiredFieldsOnly(): void
    {
        $action = new BatchAction(name: 'publish', label: 'Publish');

        $this->assertSame('publish', $action->name);
        $this->assertSame('Publish', $action->label);
        $this->assertNull($action->icon);
        $this->assertNull($action->route);
        $this->assertNull($action->url);
        $this->assertNull($action->liveComponent);
        $this->assertNull($action->permission);
        $this->assertNull($action->voterAttribute);
        $this->assertNull($action->cssClass);
        $this->assertNull($action->confirmMessage);
        $this->assertSame(BatchAction::DEFAULT_PRIORITY, $action->priority);
    }

    #[PHPUnit\Test]
    public function isRouteActionReturnsTrueWhenRouteIsSet(): void
    {
        $action = new BatchAction(name: 'publish', label: 'Publish', route: 'app_publish');
        $this->assertTrue($action->hasRoute());
    }

    #[PHPUnit\Test]
    public function isRouteActionReturnsFalseWhenNoRoute(): void
    {
        $action = new BatchAction(name: 'publish', label: 'Publish');
        $this->assertFalse($action->hasRoute());
    }

    #[PHPUnit\Test]
    public function isLiveActionReturnsTrueWhenLiveActionIsSet(): void
    {
        $action = new BatchAction(name: 'publish', label: 'Publish', liveComponent: 'bulkPublish');
        $this->assertTrue($action->isComponentAction());
    }

    #[PHPUnit\Test]
    public function isLiveActionReturnsFalseWhenNoLiveAction(): void
    {
        $action = new BatchAction(name: 'publish', label: 'Publish');
        $this->assertFalse($action->isComponentAction());
    }

    #[PHPUnit\Test]
    public function requiresConfirmationReturnsTrueWhenMessageSet(): void
    {
        $action = new BatchAction(name: 'delete', label: 'Delete', confirmMessage: 'Delete %count% items?');
        $this->assertTrue($action->requiresConfirmation());
    }

    #[PHPUnit\Test]
    public function requiresConfirmationReturnsFalseWhenNoMessage(): void
    {
        $action = new BatchAction(name: 'publish', label: 'Publish');
        $this->assertFalse($action->requiresConfirmation());
    }

    #[PHPUnit\Test]
    public function getConfirmMessageInterpolatesCount(): void
    {
        $action = new BatchAction(name: 'delete', label: 'Delete', confirmMessage: 'Delete %count% items?');
        $this->assertSame('Delete 5 items?', $action->getConfirmMessage(5));
    }

    #[PHPUnit\Test]
    public function getConfirmMessageReturnsNullWhenNoMessage(): void
    {
        $action = new BatchAction(name: 'publish', label: 'Publish');
        $this->assertNull($action->getConfirmMessage(3));
    }

    #[PHPUnit\Test]
    public function getConfirmMessageWithoutPlaceholderReturnsMessageAsIs(): void
    {
        $action = new BatchAction(name: 'archive', label: 'Archive', confirmMessage: 'Archive all selected?');
        $this->assertSame('Archive all selected?', $action->getConfirmMessage(10));
    }
}
