<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Fixtures;

use Doctrine\ORM\Mapping as ORM;
use Kachnitel\AdminBundle\Attribute\Admin;

/**
 * Test fixture entity for row actions feature tests.
 *
 * Covers:
 *  - String expression conditions using PropertyAccess syntax (entity.status)
 *  - AdminActionsConfig(exclude: ['edit']) — no Edit button
 *  - POST form action with confirmMessage
 *  - Static URL (no routing dependency in tests)
 */
#[ORM\Entity]
#[Admin(label: 'Just a name')]
class BasicAdminEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name = '';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }
}
