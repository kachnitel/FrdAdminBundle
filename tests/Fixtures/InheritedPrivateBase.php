<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Fixtures;

use Kachnitel\AdminBundle\Attribute\AdminColumn;
use Kachnitel\AdminBundle\Attribute\ColumnPermission;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;

/**
 * Plain (non-ORM) base class whose attributes sit on PRIVATE properties:
 * ReflectionClass::getProperties()/getProperty() on a subclass do not see these.
 */
abstract class InheritedPrivateBase
{
    #[ColumnPermission([AdminEntityVoter::ADMIN_SHOW => 'ROLE_HR'])]
    private ?string $secret = null;

    #[AdminColumn(editable: false)]
    private ?string $locked = null;

    #[ColumnPermission([AdminEntityVoter::ADMIN_SHOW => 'ROLE_HR'])]
    private ?string $shadowed = null;

    /** @return array{?string, ?string, ?string} */
    public function reveal(): array
    {
        return [$this->secret, $this->locked, $this->shadowed];
    }
}
