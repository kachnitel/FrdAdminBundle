<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Attribute;

use Attribute;

/**
 * Defines column-level permissions for entity properties in admin interface.
 *
 * This attribute allows fine-grained control over which users can view or edit
 * specific properties of an entity, complementing the entity-level permissions
 * defined in the #[Admin] attribute.
 *
 * @example
 * ```php
 * use Kachnitel\AdminBundle\Attribute\ColumnPermission;
 * use Kachnitel\AdminBundle\Security\AdminEntityVoter;
 *
 * class Product
 * {
 *     #[ColumnPermission([
 *         AdminEntityVoter::ADMIN_SHOW => 'ROLE_PRODUCT_COST_SHOW',
 *         AdminEntityVoter::ADMIN_EDIT => 'ROLE_PRODUCT_COST_EDIT',
 *     ])]
 *     private float $cost;
 * }
 * ```
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class ColumnPermission
{
    /**
     * @param array<string, string|string[]> $permissions Map of operation to required roles
     *                                                     Key: AdminEntityVoter constant (e.g., ADMIN_SHOW, ADMIN_EDIT)
     *                                                     Value: Role string or array of role strings
     */
    public function __construct(
        private array $permissions = [],
    ) {}

    /**
     * Get all configured permissions.
     *
     * @return array<string, string|string[]>
     */
    public function getPermissions(): array
    {
        return $this->permissions;
    }

    /**
     * Get permission requirements for a specific operation.
     *
     * @param string $operation AdminEntityVoter constant (e.g., ADMIN_SHOW, ADMIN_EDIT)
     * @return string|string[]|null Required role(s) or null if not configured
     */
    public function getPermission(string $operation): string|array|null
    {
        return $this->permissions[$operation] ?? null;
    }

    /**
     * Check if a specific operation has permission requirements.
     *
     * @param string $operation AdminEntityVoter constant
     */
    public function hasPermission(string $operation): bool
    {
        return isset($this->permissions[$operation]);
    }
}
