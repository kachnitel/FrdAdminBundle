<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Fixtures;

use Doctrine\ORM\Mapping as ORM;
use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Attribute\AdminAction;
use Kachnitel\AdminBundle\Attribute\AdminActionsConfig;

/**
 * Test fixture entity for row actions feature tests.
 *
 * Covers:
 *  - String expression conditions (entity.status == "pending")
 *  - AdminActionsConfig(exclude: ['edit']) — no Edit button
 *  - POST form action with confirmMessage
 *  - Static URL (no routing dependency in tests)
 */
#[ORM\Entity]
#[Admin(label: 'Approvable Items')]
#[AdminActionsConfig(exclude: ['edit'])]
#[AdminAction(
    name: 'approve',
    label: 'Approve',
    icon: '✅',
    url: '/admin/test/approve',
    condition: 'entity.status == "pending"',
    priority: 30,
)]
#[AdminAction(
    name: 'archive',
    label: 'Archive',
    icon: '📦',
    url: '/admin/test/archive',
    method: 'POST',
    confirmMessage: 'Archive this item?',
    condition: 'entity.status != "archived"',
    priority: 40,
)]
class EntityWithRowActions
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name = '';

    #[ORM\Column(type: 'string', length: 50)]
    private string $status = 'pending';

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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }
}
