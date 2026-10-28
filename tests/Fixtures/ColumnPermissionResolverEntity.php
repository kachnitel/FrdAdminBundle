<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Fixtures;

use Kachnitel\AdminBundle\Attribute\AdminColumn;
use Kachnitel\AdminBundle\Attribute\ColumnPermission;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;

/**
 * Plain (non-ORM) fixture for AdminColumnEditabilityResolver column-permission tests.
 */
class ColumnPermissionResolverEntity
{
    public ?string $plain = null;

    #[ColumnPermission([
        AdminEntityVoter::ADMIN_SHOW => 'ROLE_HR',
        AdminEntityVoter::ADMIN_EDIT => 'ROLE_HR_EDIT',
    ])]
    public ?string $salary = null;

    #[ColumnPermission([AdminEntityVoter::ADMIN_SHOW => 'ROLE_HR'])]
    public ?string $ssn = null;

    #[AdminColumn(editable: true)]
    #[ColumnPermission([AdminEntityVoter::ADMIN_SHOW => 'ROLE_HR'])]
    public ?string $forced = null;

    #[AdminColumn(editable: false)]
    #[ColumnPermission([AdminEntityVoter::ADMIN_EDIT => 'ROLE_HR_EDIT'])]
    public ?string $blocked = null;
}
