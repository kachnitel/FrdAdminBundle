<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Attribute;

use Kachnitel\AdminBundle\Attribute\Admin;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AdminTest extends TestCase
{
    #[Test]
    public function defaultValuesAreSetCorrectly(): void
    {
        $admin = new Admin();

        $this->assertNull($admin->getLabel());
        $this->assertNull($admin->getIcon());
        $this->assertNull($admin->getFormType());
        $this->assertTrue($admin->isEnableFilters());
        $this->assertFalse($admin->isEnableBatchActions());
        $this->assertFalse($admin->isEnableColumnVisibility());
        $this->assertNull($admin->getColumns());
        $this->assertNull($admin->getExcludeColumns());
        $this->assertNull($admin->getFilterableColumns());
        $this->assertNull($admin->getPermissions());
        $this->assertNull($admin->getItemsPerPage());
        $this->assertNull($admin->getSortBy());
        $this->assertNull($admin->getSortDirection());
    }

    #[Test]
    public function labelCanBeSet(): void
    {
        $admin = new Admin(label: 'Products');

        $this->assertSame('Products', $admin->getLabel());
    }

    #[Test]
    public function iconCanBeSet(): void
    {
        $admin = new Admin(icon: 'inventory');

        $this->assertSame('inventory', $admin->getIcon());
    }

    #[Test]
    public function formTypeCanBeSet(): void
    {
        $admin = new Admin(formType: 'App\\Form\\ProductType');

        $this->assertSame('App\\Form\\ProductType', $admin->getFormType());
    }

    #[Test]
    public function filtersCanBeDisabled(): void
    {
        $admin = new Admin(enableFilters: false);

        $this->assertFalse($admin->isEnableFilters());
    }

    #[Test]
    public function batchActionsCanBeEnabled(): void
    {
        $admin = new Admin(enableBatchActions: true);

        $this->assertTrue($admin->isEnableBatchActions());
    }

    #[Test]
    public function columnVisibilityCanBeEnabled(): void
    {
        $admin = new Admin(enableColumnVisibility: true);

        $this->assertTrue($admin->isEnableColumnVisibility());
    }

    #[Test]
    public function columnsCanBeSet(): void
    {
        $columns = ['id', 'name', 'price'];
        $admin = new Admin(columns: $columns);

        $this->assertSame($columns, $admin->getColumns());
    }

    #[Test]
    public function excludeColumnsCanBeSet(): void
    {
        $excludeColumns = ['password', 'salt'];
        $admin = new Admin(excludeColumns: $excludeColumns);

        $this->assertSame($excludeColumns, $admin->getExcludeColumns());
    }

    #[Test]
    public function filterableColumnsCanBeSet(): void
    {
        $filterableColumns = ['name', 'status'];
        $admin = new Admin(filterableColumns: $filterableColumns);

        $this->assertSame($filterableColumns, $admin->getFilterableColumns());
    }

    #[Test]
    public function permissionsCanBeSet(): void
    {
        $permissions = [
            'index' => 'ROLE_PRODUCT_VIEW',
            'edit' => 'ROLE_PRODUCT_EDIT',
            'delete' => 'ROLE_ADMIN',
        ];
        $admin = new Admin(permissions: $permissions);

        $this->assertSame($permissions, $admin->getPermissions());
    }

    #[Test]
    public function getPermissionForActionReturnsCorrectPermission(): void
    {
        $permissions = [
            'index' => 'ROLE_PRODUCT_VIEW',
            'edit' => 'ROLE_PRODUCT_EDIT',
            'delete' => 'ROLE_ADMIN',
        ];
        $admin = new Admin(permissions: $permissions);

        $this->assertSame('ROLE_PRODUCT_VIEW', $admin->getPermissionForAction('index'));
        $this->assertSame('ROLE_PRODUCT_EDIT', $admin->getPermissionForAction('edit'));
        $this->assertSame('ROLE_ADMIN', $admin->getPermissionForAction('delete'));
    }

    #[Test]
    public function getPermissionForActionReturnsNullForUndefinedAction(): void
    {
        $permissions = ['index' => 'ROLE_USER'];
        $admin = new Admin(permissions: $permissions);

        $this->assertNull($admin->getPermissionForAction('delete'));
        $this->assertNull($admin->getPermissionForAction('nonexistent'));
    }

    #[Test]
    public function getPermissionForActionReturnsNullWhenNoPermissionsSet(): void
    {
        $admin = new Admin();

        $this->assertNull($admin->getPermissionForAction('index'));
    }

    #[Test]
    public function itemsPerPageCanBeSet(): void
    {
        $admin = new Admin(itemsPerPage: 50);

        $this->assertSame(50, $admin->getItemsPerPage());
    }

    #[Test]
    public function sortByCanBeSet(): void
    {
        $admin = new Admin(sortBy: 'createdAt');

        $this->assertSame('createdAt', $admin->getSortBy());
    }

    #[Test]
    public function sortDirectionCanBeSet(): void
    {
        $admin = new Admin(sortDirection: 'ASC');

        $this->assertSame('ASC', $admin->getSortDirection());
    }

    #[Test]
    public function allParametersCanBeSetTogether(): void
    {
        $admin = new Admin(
            label: 'Products',
            icon: 'inventory',
            formType: 'App\\Form\\ProductType',
            enableFilters: true,
            enableBatchActions: true,
            enableColumnVisibility: true,
            columns: ['id', 'name', 'price'],
            excludeColumns: ['internalNotes'],
            filterableColumns: ['name', 'price'],
            permissions: ['index' => 'ROLE_USER', 'delete' => 'ROLE_ADMIN'],
            itemsPerPage: 25,
            sortBy: 'createdAt',
            sortDirection: 'DESC',
        );

        $this->assertSame('Products', $admin->getLabel());
        $this->assertSame('inventory', $admin->getIcon());
        $this->assertSame('App\\Form\\ProductType', $admin->getFormType());
        $this->assertTrue($admin->isEnableFilters());
        $this->assertTrue($admin->isEnableBatchActions());
        $this->assertTrue($admin->isEnableColumnVisibility());
        $this->assertSame(['id', 'name', 'price'], $admin->getColumns());
        $this->assertSame(['internalNotes'], $admin->getExcludeColumns());
        $this->assertSame(['name', 'price'], $admin->getFilterableColumns());
        $this->assertSame(['index' => 'ROLE_USER', 'delete' => 'ROLE_ADMIN'], $admin->getPermissions());
        $this->assertSame(25, $admin->getItemsPerPage());
        $this->assertSame('createdAt', $admin->getSortBy());
        $this->assertSame('DESC', $admin->getSortDirection());
    }

    #[Test]
    public function attributeCanBeAppliedToClass(): void
    {
        $reflection = new \ReflectionClass(Admin::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        $this->assertCount(1, $attributes);

        $attrInstance = $attributes[0]->newInstance();
        $this->assertSame(\Attribute::TARGET_CLASS, $attrInstance->flags);
    }
}
