<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Fixtures;

enum TestStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case ARCHIVED = 'archived';

    public function label(): string
    {
        return match($this) {
            self::ACTIVE => 'Currently Active',
            self::INACTIVE => 'Not Active',
            self::ARCHIVED => 'Moved to Archive',
        };
    }
}
