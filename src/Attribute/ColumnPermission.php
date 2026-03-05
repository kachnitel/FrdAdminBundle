<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Attribute;

use Attribute;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;

/**
 * Defines column-level permissions for entity properties in admin interface.
 *
 * This attribute allows fine-grained control over which users can view or edit
 * specific properties of an entity, complementing the entity-level permissions
 * defined in the #[Admin] attribute.
 *
 * @example New API (since 0.6.0):
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
 *
 * @example Deprecated single-role API (< 0.6.0, still accepted with E_USER_DEPRECATED):
 * ```php
 * #[ColumnPermission('ROLE_HR')]   // migrates to ADMIN_SHOW restriction
 * private float $salary;
 * ```
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class ColumnPermission
{
    /**
     * Normalised map: operation => required role(s).
     *
     * @var array<string, string|string[]>
     */
    private array $permissions;

    /**
     * @param array<string, string|string[]>|string $permissions
     *   New (≥ 0.6): associative array of AdminEntityVoter constant → role string or array of role strings.
     *   Deprecated (< 0.6): plain role string — treated as an ADMIN_SHOW restriction and triggers E_USER_DEPRECATED.
     */
    public function __construct(array|string $permissions = [])
    {
        if (is_string($permissions)) {
            trigger_error(
                sprintf(
                    '#[ColumnPermission(\'%1$s\')] is deprecated since 0.6.0. '
                    . 'Replace with #[ColumnPermission([AdminEntityVoter::ADMIN_SHOW => \'%1$s\'])].',
                    $permissions,
                ),
                E_USER_DEPRECATED,
            );

            $this->permissions = [AdminEntityVoter::ADMIN_SHOW => $permissions];
        } else {
            $this->permissions = $permissions;
        }
    }

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
