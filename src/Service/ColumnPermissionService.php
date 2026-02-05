<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Service;

use Kachnitel\AdminBundle\Attribute\ColumnPermission;
use ReflectionClass;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Discovers #[ColumnPermission] attributes on entity properties and checks
 * whether the current user has the required role to view each column.
 *
 * Uses Symfony's built-in role voter via Security::isGranted() — supports
 * role hierarchy automatically.
 */
class ColumnPermissionService
{
    /** @var array<string, array<string, string>> Cached permission maps per entity class */
    private array $permissionMapCache = [];

    public function __construct(
        private readonly Security $security,
    ) {}

    /**
     * Get column names the current user is not allowed to see.
     *
     * @param class-string $entityClass
     * @return array<string> Column names denied for the current user
     */
    public function getDeniedColumns(string $entityClass): array
    {
        $map = $this->getColumnPermissionMap($entityClass);

        $denied = [];
        foreach ($map as $column => $role) {
            if (!$this->security->isGranted($role)) {
                $denied[] = $column;
            }
        }

        return $denied;
    }

    /**
     * Get the column-to-role permission map for an entity class.
     *
     * @param class-string $entityClass
     * @return array<string, string> Map of property name => required role
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
            if ($attributes !== []) {
                $permission = $attributes[0]->newInstance();
                $map[$property->getName()] = $permission->role;
            }
        }

        $this->permissionMapCache[$entityClass] = $map;

        return $map;
    }
}
