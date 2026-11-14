<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Attribute;

use Kachnitel\AdminBundle\Attribute\AdminColumn;
use PHPUnit\Framework\Attributes as PHPUnit;
use PHPUnit\Framework\TestCase;

#[PHPUnit\CoversClass(AdminColumn::class)]
#[PHPUnit\Group('collection-dialog')]
final class AdminColumnCollectionDialogTest extends TestCase
{
    #[PHPUnit\Test]
    public function collectionDialogDefaultsToFalse(): void
    {
        $this->assertFalse((new AdminColumn())->collectionDialog);
    }

    #[PHPUnit\Test]
    public function collectionDialogCanBeEnabled(): void
    {
        $this->assertTrue((new AdminColumn(collectionDialog: true))->collectionDialog);
    }

    #[PHPUnit\Test]
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
