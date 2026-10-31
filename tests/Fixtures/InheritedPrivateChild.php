<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Fixtures;

class InheritedPrivateChild extends InheritedPrivateBase
{
    public ?string $own = null;

    /** Redeclared without the parent's restriction: the most-derived declaration wins. */
    public ?string $shadowed = null;
}
