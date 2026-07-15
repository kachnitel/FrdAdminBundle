<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Fixtures;

/**
 * Minimal plain object for exercising DeleteEntityTrait, which only needs
 * method_exists($entity, 'getId') — no Doctrine mapping required.
 */
final class DeletableEntity
{
    public function __construct(private readonly ?int $id = null) {}

    public function getId(): ?int
    {
        return $this->id;
    }
}
