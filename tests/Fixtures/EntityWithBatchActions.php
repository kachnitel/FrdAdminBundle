<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Fixtures;

use Doctrine\ORM\Mapping as ORM;
use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Attribute\AdminAction;

/**
 * Test fixture entity for custom batch actions feature tests.
 *
 * Uses url (not route) for both-type actions so _RowActionButton.html.twig
 * uses the static-URL branch and does not attempt to resolve non-existent routes.
 */
#[ORM\Entity]
#[Admin(label: 'Batch Action Items', enableBatchActions: true)]
#[AdminAction(
    name: 'bulk-activate',
    label: 'Activate Selected',
    icon: '✅',
    url: '/admin/test/batch/activate',
    confirmMessage: 'Activate %count% items?',
    priority: 30,
    actionType: AdminAction::ACTION_TYPE_BATCH,
)]
#[AdminAction(
    name: 'bulk-archive',
    label: 'Archive',
    icon: '📦',
    url: '/admin/test/batch/archive',
    priority: 40,
    actionType: AdminAction::ACTION_TYPE_BOTH,
)]
class EntityWithBatchActions
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
