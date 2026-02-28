<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\RowAction;

use Symfony\Component\PropertyAccess\Exception\AccessException;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * Proxies property reads to Symfony's PropertyAccess component.
 *
 * Used by RowActionExpressionLanguage so that `entity.status` in expressions
 * correctly calls `getStatus()` / `isStatus()` rather than attempting direct
 * access to a potentially private property.
 *
 * ExpressionLanguage accesses `entity.status` by calling `__get('status')` on
 * the variable when the property is not public. This class intercepts that call
 * and delegates to PropertyAccess, which applies the full getter resolution chain.
 *
 * The underlying entity is exposed via `getEntity()` for DI tuple conditions
 * that need the real object (not the proxy).
 *
 * @internal
 */
final class PropertyAccessProxy
{
    public function __construct(
        private readonly object $entity,
        private readonly PropertyAccessorInterface $accessor,
    ) {}

    public function __get(string $name): mixed
    {
        try {
            return $this->accessor->getValue($this->entity, $name);
        } catch (AccessException) {
            // Re-throw as a standard exception so ExpressionLanguage catch blocks handle it
            throw new \RuntimeException(sprintf(
                'Cannot read property "%s" on %s via PropertyAccess.',
                $name,
                $this->entity::class,
            ));
        }
    }

    public function __isset(string $name): bool
    {
        return $this->accessor->isReadable($this->entity, $name);
    }

    /**
     * Forward method calls to the underlying entity.
     * Supports `entity.getStatus()` explicit method call syntax in expressions.
     *
     * @param array<mixed> $arguments
     */
    public function __call(string $name, array $arguments): mixed
    {
        return $this->entity->{$name}(...$arguments);
    }

    /**
     * Return the real entity object.
     * Used by DI tuple conditions that need the unwrapped entity.
     */
    public function getEntity(): object
    {
        return $this->entity;
    }
}
