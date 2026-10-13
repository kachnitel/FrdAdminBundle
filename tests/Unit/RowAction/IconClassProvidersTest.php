<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\RowAction;

use Kachnitel\AdminBundle\Attribute\AdminAction;
use Kachnitel\AdminBundle\BatchAction\ArchiveBatchActionProvider;
use Kachnitel\AdminBundle\BatchAction\AttributeBatchActionProvider;
use Kachnitel\AdminBundle\RowAction\AttributeRowActionProvider;
use Kachnitel\AdminBundle\RowAction\DefaultRowActionProvider;
use Kachnitel\AdminBundle\Tests\Fixtures\EntityWithIconClassActions;
use Kachnitel\AdminBundle\ValueObject\BatchAction;
use Kachnitel\AdminBundle\ValueObject\RowAction;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('icons')]
final class IconClassProvidersTest extends TestCase
{
    #[Test]
    public function adminActionDefaultsToInheritedIconClass(): void
    {
        $this->assertNull((new AdminAction(name: 'a', label: 'A'))->iconClass);
    }

    #[Test]
    public function attributeRowProviderPassesIconClassThrough(): void
    {
        $byName = [];
        foreach ((new AttributeRowActionProvider())->getActions(EntityWithIconClassActions::class) as $action) {
            $byName[$action->name] = $action;
        }

        $this->assertSame('material-symbols-outlined', $byName['approve']->iconClass);
        $this->assertNull($byName['plain']->iconClass);
        $this->assertArrayNotHasKey('bulk', $byName);
    }

    #[Test]
    public function attributeBatchProviderPassesIconClassThrough(): void
    {
        $actions = (new AttributeBatchActionProvider())->getActions(EntityWithIconClassActions::class);

        $this->assertCount(1, $actions);
        $this->assertSame('bi bi-trash', $actions[0]->iconClass);
    }

    #[Test]
    public function builtInDefaultRowActionsOptOutOfIconClass(): void
    {
        foreach ((new DefaultRowActionProvider())->getActions(\stdClass::class) as $action) {
            $this->assertSame(RowAction::ICON_CLASS_NONE, $action->iconClass, $action->name);
        }
    }

    #[Test]
    public function builtInArchiveBatchActionOptsOutOfIconClass(): void
    {
        $provider = (new \ReflectionClass(ArchiveBatchActionProvider::class))->newInstanceWithoutConstructor();

        foreach ($provider->getActions(\stdClass::class) as $action) {
            $this->assertSame(BatchAction::ICON_CLASS_NONE, $action->iconClass, $action->name);
        }
    }
}
