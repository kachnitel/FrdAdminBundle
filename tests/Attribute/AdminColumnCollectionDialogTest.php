<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Attribute;

use Kachnitel\AdminBundle\Attribute\AdminColumn;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AdminColumn::class)]
#[Group('collection-dialog')]
final class AdminColumnCollectionDialogTest extends TestCase
{
    #[Test]
    public function collectionDialogDefaultsToFalse(): void
    {
        $this->assertFalse((new AdminColumn())->collectionDialog);
    }

    #[Test]
    public function collectionDialogCanBeEnabled(): void
    {
        $this->assertTrue((new AdminColumn(collectionDialog: true))->collectionDialog);
    }

    #[Test]
    public function collectionDialogDoesNotDisturbOtherDefaults(): void
    {
        $column = new AdminColumn(collectionDialog: true);

        $this->assertFalse($column->collectionDisplay);
        $this->assertTrue($column->collectionCollapsible);
        $this->assertSame(5, $column->collectionLimit);
        $this->assertNull($column->collectionLabelMethod);
        $this->assertNull($column->editable);
        $this->assertNull($column->group);
    }
}
