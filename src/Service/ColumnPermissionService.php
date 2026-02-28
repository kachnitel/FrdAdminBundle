<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Service;

use Kachnitel\AdminBundle\Attribute\ColumnPermission;
use ReflectionClass;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Service for discovering and checking column-level permissions.
 *
 * Discovers #[ColumnPermission] attributes on entity properties and provides
 * methods to check whether the current user has permission to perform specific
 * actions (ADMIN_SHOW, ADMIN_EDIT, ADMIN_DELETE) on individual columns.
 *
 * The service caches permission maps per entity class for performance.
 *
 * @example
 * ```php
 * // Check if user can view a specific column
 * if ($service->canPerformAction(Product::class, 'cost', AdminEntityVoter::ADMIN_SHOW)) {
 *     // Show the column
 * }
 *
 * // Get all columns user can edit
 * $editableColumns = $service->getPermittedColumns(
 *     Product::class,
 *     ['name', 'price', 'cost'],
 *     AdminEntityVoter::ADMIN_EDIT
 * );
 * ```
 */
class ColumnPermissionService
{
    /**
     * Cached permission maps per entity class.
     *
     * Format: [
     *     'App\Entity\Product' => [
     *         'cost' => [
     *             'ADMIN_SHOW' => 'ROLE_USER',
     *             'ADMIN_EDIT' => 'ROLE_ADMIN',
     *         ],
     *     ],
     * ]
     *
     * @var array<string, array<string, array<string, string|string[]>>>
     */
    private array $permissionMapCache = [];

    public function __construct(
        private readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {}

    /**
     * Get the column-to-permissions map for an entity class.
     *
     * Returns a map of property names to their permission configurations.
     * Only includes properties that have #[ColumnPermission] attributes.
     *
     * @param class-string $entityClass
     * @return array<string, array<string, string|string[]>>
     */
    public function getColumnPermissionMap(string $entityClass): array
    {
        if (isset($this->permissionMapCache[$entityClass])) {
            return $this->permissionMapCache[$entityClass];
        }

        $map = [];
        $reflection = new ReflectionClass($entityClass);

        foreach ($reflection->getProperties() as $property) {
            $attributes = $property->getAttributes(ColumnPermission::class);

            if (empty($attributes)) {
                continue;
            }

            $permission = $attributes[0]->newInstance();
            $map[$property->getName()] = $permission->getPermissions();
        }

        $this->permissionMapCache[$entityClass] = $map;

        return $map;
    }

    /**
     * Check if the current user can perform a specific action on a column.
     *
     * Returns true if:
     * - Column has no permission restrictions, OR
     * - Action is not restricted for this column, OR
     * - User has at least one of the required roles
     *
     * @param class-string $entityClass
     * @param string $columnName
     * @param string $action One of AdminEntityVoter::ADMIN_* constants
     */
    public function canPerformAction(
        string $entityClass,
        string $columnName,
        string $action,
    ): bool {
        $map = $this->getColumnPermissionMap($entityClass);

        // Column has no restrictions
        if (!isset($map[$columnName])) {
            return true;
        }

        $columnPermissions = $map[$columnName];

        // Action not restricted for this column
        if (!isset($columnPermissions[$action])) {
            return true;
        }

        $requiredRoles = $columnPermissions[$action];

        return $this->checkRoles($requiredRoles);
    }

    /**
     * Check if the current user can perform a specific action on a column of an entity instance.
     *
     * @param object $entity Entity instance
     * @param string $columnName
     * @param string $action One of AdminEntityVoter::ADMIN_* constants
     */
    public function canPerformActionOnEntity(
        object $entity,
        string $columnName,
        string $action,
    ): bool {
        return $this->canPerformAction(
            $entity::class,
            $columnName,
            $action
        );
    }

    /**
     * Get list of columns the current user can perform a specific action on.
     *
     * @param class-string $entityClass
     * @param array<string> $allColumns All column names to check
     * @param string $action One of AdminEntityVoter::ADMIN_* constants
     * @return array<string> Filtered list of permitted columns
     */
    public function getPermittedColumns(
        string $entityClass,
        array $allColumns,
        string $action,
    ): array {
        return array_values(array_filter(
            $allColumns,
            fn(string $column) => $this->canPerformAction($entityClass, $column, $action)
        ));
    }

    /**
     * Get list of columns the current user cannot perform a specific action on.
     *
     * @param class-string $entityClass
     * @param string $action One of AdminEntityVoter::ADMIN_* constants
     * @return array<string> List of denied column names
     */
    public function getDeniedColumnsForAction(
        string $entityClass,
        string $action,
    ): array {
        $map = $this->getColumnPermissionMap($entityClass);
        $denied = [];

        foreach (array_keys($map) as $columnName) {
            if (!$this->canPerformAction($entityClass, $columnName, $action)) {
                $denied[] = $columnName;
            }
        }

        return $denied;
    }

    /**
     * Get list of all columns that have any permission restrictions.
     *
     * @param class-string $entityClass
     * @return array<string> List of restricted column names
     */
    public function getDeniedColumns(string $entityClass): array
    {
        $map = $this->getColumnPermissionMap($entityClass);

        return array_keys($map);
    }

    /**
     * Check if user has any of the required roles.
     *
     * Supports both single role (string) and multiple roles (array).
     * For arrays, uses OR logic — user needs at least one of the roles.
     *
     * @param string|string[] $requiredRoles
     */
    private function checkRoles(string|array $requiredRoles): bool
    {
        if (is_string($requiredRoles)) {
            return $this->authorizationChecker->isGranted($requiredRoles);
        }

        // Array of roles — user needs at least one
        foreach ($requiredRoles as $role) {
            if ($this->authorizationChecker->isGranted($role)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Clear the permission map cache.
     *
     * Useful for testing or when entity definitions change at runtime.
     */
    public function clearCache(): void
    {
        $this->permissionMapCache = [];
    }
}
