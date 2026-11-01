<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Fixtures;

use Doctrine\ORM\Mapping as ORM;
use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Attribute\ColumnFilter;

/** Has a relation filter that searches both a permitted and a restricted field of the owner. */
#[ORM\Entity]
#[ORM\Table(name: 'test_cp_filter_ticket')]
#[Admin(label: 'Permission Filter Tickets', columns: ['id', 'title', 'owner'])]
class PermissionFilterTicket
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $title = '';

    #[ORM\ManyToOne(targetEntity: PermissionFilterOwner::class)]
    #[ColumnFilter(type: 'relation', searchFields: ['name', 'email'])]
    private ?PermissionFilterOwner $owner = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getOwner(): ?PermissionFilterOwner
    {
        return $this->owner;
    }

    public function setOwner(?PermissionFilterOwner $owner): self
    {
        $this->owner = $owner;

        return $this;
    }
}
