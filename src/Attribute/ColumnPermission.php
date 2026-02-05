<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Attribute;

use Attribute;

/**
 * Restrict column visibility based on user role.
 *
 * When applied to an entity property, the corresponding admin list column
 * will only be visible to users with the specified role. Uses Symfony's
 * built-in role voter via isGranted() — supports role hierarchy.
 *
 * Denied columns are also excluded from filters and the column visibility toggle picker.
 *
 * @example
 * #[ColumnPermission('ROLE_HR')]
 * private float $salary;
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class ColumnPermission
{
    public function __construct(
        /**
         * Required role to view this column (e.g. 'ROLE_HR').
         */
        public string $role,
    ) {}
}
