<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Fixtures;

use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;

/**
 * A directly-constructible ForeignKeyConstraintViolationException for tests.
 *
 * The real exception's constructor chain (via Doctrine\DBAL\Exception\DriverException)
 * requires a Doctrine\DBAL\Driver\Exception from the underlying driver connection,
 * which doesn't exist in a unit test and isn't meaningful to fake. This bypasses
 * that chain by calling \Exception's constructor directly, while remaining a
 * genuine instanceof ForeignKeyConstraintViolationException — which is all
 * DeleteEntityTrait::doDelete() actually checks for.
 */
final class TestForeignKeyConstraintViolationException extends ForeignKeyConstraintViolationException
{
    public function __construct(string $message = 'Foreign key constraint violation')
    {
        \Exception::__construct($message);
    }
}
