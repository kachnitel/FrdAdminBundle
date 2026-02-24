<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Attribute;

use Kachnitel\AdminBundle\Attribute\Admin;
use PHPUnit\Framework\TestCase;

class AdminTest extends TestCase
{
    /**
     * @test
     */
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

    /**
     * @test
     */
    public function labelCanBeSet(): void
    {
        $admin = new Admin(label: 'Products');

        $this->assertSame('Products', $admin->getLabel());
    }

    /**
     * @test
     */
    public function iconCanBeSet(): void
    {
        $admin = new Admin(icon: 'inventory');

        $this->assertSame('inventory', $admin->getIcon());
    }

    /**
     * @test
     */
    public function formTypeCanBeSet(): void
    {
        $admin = new Admin(formType: 'App\\Form\\ProductType');

        $this->assertSame('App\\Form\\ProductType', $admin->getFormType());
    }

    /**
     * @test
     */
    public function filtersCanBeDisabled(): void
    {
        $admin = new Admin(enableFilters: false);

        $this->assertFalse($admin->isEnableFilters());
    }

    /**
     * @test
     */
    public function batchActionsCanBeEnabled(): void
    {
        $admin = new Admin(enableBatchActions: true);

        $this->assertTrue($admin->isEnableBatchActions());
    }

    /**
     * @test
     */
    public function columnVisibilityCanBeEnabled(): void
    {
        $admin = new Admin(enableColumnVisibility: true);

        $this->assertTrue($admin->isEnableColumnVisibility());
    }

    /**
     * @test
     */
    public function columnsCanBeSet(): void
    {
        $columns = ['id', 'name', 'price'];
        $admin = new Admin(columns: $columns);

        $this->assertSame($columns, $admin->getColumns());
    }

    /**
     * @test
     */
    public function excludeColumnsCanBeSet(): void
    {
        $excludeColumns = ['password', 'salt'];
        $admin = new Admin(excludeColumns: $excludeColumns);

        $this->assertSame($excludeColumns, $admin->getExcludeColumns());
    }

    /**
     * @test
     */
    public function filterableColumnsCanBeSet(): void
    {
        $filterableColumns = ['name', 'status'];
        $admin = new Admin(filterableColumns: $filterableColumns);

        $this->assertSame($filterableColumns, $admin->getFilterableColumns());
    }

    /**
     * @test
     */
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

    /**
     * @test
     */
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

    /**
     * @test
     */
    public function getPermissionForActionReturnsNullForUndefinedAction(): void
    {
        $permissions = ['index' => 'ROLE_USER'];
        $admin = new Admin(permissions: $permissions);

        $this->assertNull($admin->getPermissionForAction('delete'));
        $this->assertNull($admin->getPermissionForAction('nonexistent'));
    }

    /**
     * @test
     */
    public function getPermissionForActionReturnsNullWhenNoPermissionsSet(): void
    {
        $admin = new Admin();

        $this->assertNull($admin->getPermissionForAction('index'));
    }

    /**
     * @test
     */
    public function itemsPerPageCanBeSet(): void
    {
        $admin = new Admin(itemsPerPage: 50);

        $this->assertSame(50, $admin->getItemsPerPage());
    }

    /**
     * @test
     */
    public function sortByCanBeSet(): void
    {
        $admin = new Admin(sortBy: 'createdAt');

        $this->assertSame('createdAt', $admin->getSortBy());
    }

    /**
     * @test
     */
    public function sortDirectionCanBeSet(): void
    {
        $admin = new Admin(sortDirection: 'ASC');

        $this->assertSame('ASC', $admin->getSortDirection());
    }

    /**
     * @test
     */
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

    /**
     * @test
     */
    public function attributeCanBeAppliedToClass(): void
    {
        $reflection = new \ReflectionClass(Admin::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        $this->assertCount(1, $attributes);

        $attrInstance = $attributes[0]->newInstance();
        $this->assertSame(\Attribute::TARGET_CLASS, $attrInstance->flags);
    }
}
