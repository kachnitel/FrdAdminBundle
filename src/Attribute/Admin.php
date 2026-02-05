<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Attribute;

use Attribute;

/**
 * Marks an entity as manageable by the admin bundle.
 *
 * This attribute enables auto-discovery of entities for the admin interface.
 * Any entity with this attribute will automatically be available in the admin,
 * replacing the need for YAML configuration.
 *
 * Usage:
 * #[Admin(
 *     label: 'Products',
 *     icon: 'inventory',
 *     columns: ['name', 'price', 'stock'],
 *     permissions: ['index' => 'ROLE_PRODUCT_VIEW'],
 *     itemsPerPage: 25
 * )]
 * class Product { }
 *
 * For per-column role-based visibility, @see ColumnPermission
 * For user-toggleable column visibility, use enableColumnVisibility: true
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Admin
{
    /**
     * Configuration attribute constructor.
     *
     * Note: This attribute has 13 parameters because it serves as a comprehensive
     * configuration object for entity admin panels. Each parameter controls a specific
     * aspect of the admin interface behavior. Grouping these into sub-objects would
     * make the attribute API more complex without meaningful benefits.
     *
     * @param string|null $label Display label for this entity (defaults to class name)
     * @param string|null $icon Material icon name for this entity
     * @param string|null $formType Custom form type class for create/edit forms
     * @param bool $enableFilters Enable column filtering in list view
     * @param bool $enableBatchActions Enable batch actions (e.g., bulk delete)
     * @param bool $enableColumnVisibility Enable column show/hide toggle in list view
     * @param array<string>|null $columns Explicit list of columns to display (null = auto-detect from entity)
     * @param array<string>|null $excludeColumns Columns to exclude from display
     * @param array<string>|null $filterableColumns Columns that can be filtered (null = all visible columns)
     * @param array<string, string>|null $permissions Per-action permission requirements (e.g., ['index' => 'ROLE_PRODUCT_VIEW'])
     * @param int|null $itemsPerPage Default items per page for this entity (null = use global default)
     * @param string|null $sortBy Default sort column (null = 'id')
     * @param string|null $sortDirection Default sort direction 'ASC' or 'DESC' (null = 'DESC')
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        private ?string $label = null,
        private ?string $icon = null,
        private ?string $formType = null,
        private bool $enableFilters = true,
        private bool $enableBatchActions = false,
        private bool $enableColumnVisibility = false,
        private ?array $columns = null,
        private ?array $excludeColumns = null,
        private ?array $filterableColumns = null,
        private ?array $permissions = null,
        private ?int $itemsPerPage = null,
        private ?string $sortBy = null,
        private ?string $sortDirection = null,
    ) {}

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function getFormType(): ?string
    {
        return $this->formType;
    }

    public function isEnableFilters(): bool
    {
        return $this->enableFilters;
    }

    public function isEnableBatchActions(): bool
    {
        return $this->enableBatchActions;
    }

    public function isEnableColumnVisibility(): bool
    {
        return $this->enableColumnVisibility;
    }

    /**
     * Get explicit column list, or null to auto-detect.
     *
     * @return array<string>|null
     */
    public function getColumns(): ?array
    {
        return $this->columns;
    }

    /**
     * Get columns to exclude from display.
     *
     * @return array<string>|null
     */
    public function getExcludeColumns(): ?array
    {
        return $this->excludeColumns;
    }

    /**
     * Get columns that can be filtered, or null to allow all.
     *
     * @return array<string>|null
     */
    public function getFilterableColumns(): ?array
    {
        return $this->filterableColumns;
    }

    /**
     * Get per-action permission requirements.
     *
     * @return array<string, string>|null Map of action => role
     */
    public function getPermissions(): ?array
    {
        return $this->permissions;
    }

    /**
     * Get the permission required for a specific action.
     *
     * Returns null if no specific permission is set for this action.
     */
    public function getPermissionForAction(string $action): ?string
    {
        return $this->permissions[$action] ?? null;
    }

    /**
     * Get items per page for this entity, or null for global default.
     */
    public function getItemsPerPage(): ?int
    {
        return $this->itemsPerPage;
    }

    /**
     * Get default sort column.
     */
    public function getSortBy(): ?string
    {
        return $this->sortBy;
    }

    /**
     * Get default sort direction ('ASC' or 'DESC').
     */
    public function getSortDirection(): ?string
    {
        return $this->sortDirection;
    }
}
