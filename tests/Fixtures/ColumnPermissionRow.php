<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Fixtures;

use Doctrine\ORM\Mapping as ORM;
use Kachnitel\AdminBundle\Attribute\ColumnPermission;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;

/**
 * Collection-row child of ColumnPermissionParent. Carries its OWN #[ColumnPermission],
 * which must apply inside the parent's collection rows (nested DynamicEntityFormType).
 */
#[ORM\Entity]
#[ORM\Table(name: 'test_cp_row')]
class ColumnPermissionRow
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 200)]
    private string $description = '';

    #[ORM\Column(length: 50, nullable: true)]
    #[ColumnPermission([
        AdminEntityVoter::ADMIN_SHOW => 'ROLE_HR',
        AdminEntityVoter::ADMIN_EDIT => 'ROLE_HR_EDIT',
    ])]
    private ?string $budget = null;

    #[ORM\ManyToOne(targetEntity: ColumnPermissionParent::class, inversedBy: 'rows')]
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

    public function getBudget(): ?string
    {
        return $this->budget;
    }

    public function setBudget(?string $budget): self
    {
        $this->budget = $budget;
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
