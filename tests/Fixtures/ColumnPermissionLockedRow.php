<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Fixtures;

use Doctrine\ORM\Mapping as ORM;

/** Child of the permission-restricted `lockedRows` collection on ColumnPermissionParent. */
#[ORM\Entity]
#[ORM\Table(name: 'test_cp_locked_row')]
class ColumnPermissionLockedRow
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 200)]
    private string $description = '';

    #[ORM\ManyToOne(targetEntity: ColumnPermissionParent::class, inversedBy: 'lockedRows')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ColumnPermissionParent $parent = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getParent(): ?ColumnPermissionParent
    {
        return $this->parent;
    }

    public function setParent(?ColumnPermissionParent $parent): self
    {
        $this->parent = $parent;
        return $this;
    }
}
