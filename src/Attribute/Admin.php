<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Attribute;

use Attribute;

/**
 * Marks an entity as manageable by the admin bundle.
 *
 * For per-column role-based visibility, @see ColumnPermission
 * For user-toggleable column visibility, use enableColumnVisibility: true
 * For inline editing in list views, use enableInlineEdit: true
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Admin
{
    /**
     * @param string|null $label Display label for this entity (defaults to class name)
     * @param string|null $icon Material icon name for this entity
     * @param string|null $formType Custom form type class for create/edit forms
     * @param bool $enableFilters Enable column filtering in list view
     * @param bool $enableBatchActions Enable batch actions (e.g., bulk delete)
     * @param bool $enableColumnVisibility Enable column show/hide toggle in list view
     * @param bool $enableInlineEdit Enable per-field inline editing in the list view.
     *   Defaults to false — opt in per entity. Individual columns can be further
     *   controlled with #[AdminColumn(editable: true|false|'expr')].
     * @param array<string>|null $columns Explicit list of columns to display (null = auto-detect)
     * @param array<string>|null $excludeColumns Columns to exclude from display
     * @param array<string>|null $filterableColumns Columns that can be filtered (null = all visible)
     * @param array<string, string>|null $permissions Per-action permission requirements
     * @param int|null $itemsPerPage Default items per page (null = use global default)
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
        private bool $enableInlineEdit = false,
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

    public function isEnableInlineEdit(): bool
    {
        return $this->enableInlineEdit;
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
     * @return array<string>|null
     */
    public function getExcludeColumns(): ?array
    {
        return $this->excludeColumns;
    }

    /**
     * @return array<string>|null
     */
    public function getFilterableColumns(): ?array
    {
        return $this->filterableColumns;
    }

    /**
     * @return array<string, string>|null
     */
    public function getPermissions(): ?array
    {
        return $this->permissions;
    }

    public function getItemsPerPage(): ?int
    {
        return $this->itemsPerPage;
    }

    public function getSortBy(): ?string
    {
        return $this->sortBy;
    }

    public function getSortDirection(): ?string
    {
        return $this->sortDirection;
    }

    /**
     * Get the required role for a specific action, or null for no restriction.
     */
    public function getPermissionForAction(string $action): ?string
    {
        return $this->permissions[$action] ?? null;
    }
}
