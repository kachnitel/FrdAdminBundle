<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Fixtures;

/**
 * Test enum for unit tests.
 */
enum TestEnum: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    /**
     * Get a human-readable label for this enum case.
     */
    public function label(): string
    {
        return match($this) {
            self::PENDING => 'Pending',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
        };
    }
}
