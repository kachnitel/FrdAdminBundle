<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Fixtures;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Kachnitel\AdminBundle\Attribute\ColumnPermission;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;

/**
 * Association coverage for #[ColumnPermission] on the generated form:
 *
 *   owner       ManyToOne   SHOW ROLE_HR                     → removed without ROLE_HR
 *   reviewer    ManyToOne   SHOW ROLE_HR, EDIT ROLE_HR_EDIT  → disabled for ROLE_HR only
 *   hiddenTags  ManyToMany  SHOW ROLE_HR                     → removed without ROLE_HR
 *   lockedTags  ManyToMany  SHOW ROLE_HR, EDIT ROLE_HR_EDIT  → disabled for ROLE_HR only
 *   rows        OneToMany   unrestricted; child ColumnPermissionRow has its own restrictions
 *   lockedRows  OneToMany   SHOW ROLE_HR, EDIT ROLE_HR_EDIT  → disabled for ROLE_HR only
 */
#[ORM\Entity]
#[ORM\Table(name: 'test_cp_parent')]
class ColumnPermissionParent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $name = '';

    #[ORM\ManyToOne(targetEntity: ColumnPermissionRelated::class)]
    #[ColumnPermission([AdminEntityVoter::ADMIN_SHOW => 'ROLE_HR'])]
    private ?ColumnPermissionRelated $owner = null;

    #[ORM\ManyToOne(targetEntity: ColumnPermissionRelated::class)]
    #[ColumnPermission([
        AdminEntityVoter::ADMIN_SHOW => 'ROLE_HR',
        AdminEntityVoter::ADMIN_EDIT => 'ROLE_HR_EDIT',
    ])]
    private ?ColumnPermissionRelated $reviewer = null;

    /** @var Collection<int, TagFixture> */
    #[ORM\ManyToMany(targetEntity: TagFixture::class)]
    #[ORM\JoinTable(name: 'test_cp_parent_hidden_tags')]
    #[ColumnPermission([AdminEntityVoter::ADMIN_SHOW => 'ROLE_HR'])]
    private Collection $hiddenTags;

    /** @var Collection<int, TagFixture> */
    #[ORM\ManyToMany(targetEntity: TagFixture::class)]
    #[ORM\JoinTable(name: 'test_cp_parent_locked_tags')]
    #[ColumnPermission([
        AdminEntityVoter::ADMIN_SHOW => 'ROLE_HR',
        AdminEntityVoter::ADMIN_EDIT => 'ROLE_HR_EDIT',
    ])]
    private Collection $lockedTags;

    /** @var Collection<int, ColumnPermissionRow> */
    #[ORM\OneToMany(
        targetEntity: ColumnPermissionRow::class,
        mappedBy: 'parent',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    private Collection $rows;

    /** @var Collection<int, ColumnPermissionLockedRow> */
    #[ORM\OneToMany(
        targetEntity: ColumnPermissionLockedRow::class,
        mappedBy: 'parent',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    #[ColumnPermission([
        AdminEntityVoter::ADMIN_SHOW => 'ROLE_HR',
        AdminEntityVoter::ADMIN_EDIT => 'ROLE_HR_EDIT',
    ])]
    private Collection $lockedRows;

    public function __construct()
    {
        $this->lockedRows = new ArrayCollection();
        $this->hiddenTags = new ArrayCollection();
        $this->lockedTags = new ArrayCollection();
        $this->rows = new ArrayCollection();
    }

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

    public function getOwner(): ?ColumnPermissionRelated
    {
        return $this->owner;
    }

    public function setOwner(?ColumnPermissionRelated $owner): self
    {
        $this->owner = $owner;
        return $this;
    }

    public function getReviewer(): ?ColumnPermissionRelated
    {
        return $this->reviewer;
    }

    public function setReviewer(?ColumnPermissionRelated $reviewer): self
    {
        $this->reviewer = $reviewer;
        return $this;
    }

    /** @return Collection<int, TagFixture> */
    public function getHiddenTags(): Collection
    {
        return $this->hiddenTags;
    }

    /** @param Collection<int, TagFixture> $tags */
    public function setHiddenTags(Collection $tags): self
    {
        $this->hiddenTags = $tags;
        return $this;
    }

    /** @return Collection<int, TagFixture> */
    public function getLockedTags(): Collection
    {
        return $this->lockedTags;
    }

    /** @param Collection<int, TagFixture> $tags */
    public function setLockedTags(Collection $tags): self
    {
        $this->lockedTags = $tags;
        return $this;
    }

    /** @return Collection<int, ColumnPermissionRow> */
    public function getRows(): Collection
    {
        return $this->rows;
    }

    public function addRow(ColumnPermissionRow $row): self
    {
        if (!$this->rows->contains($row)) {
            $this->rows->add($row);
            $row->setParent($this);
        }
        return $this;
    }

    public function removeRow(ColumnPermissionRow $row): self
    {
        $this->rows->removeElement($row);
        return $this;
    }

    /** @return Collection<int, ColumnPermissionLockedRow> */
    public function getLockedRows(): Collection
    {
        return $this->lockedRows;
    }

    public function addLockedRow(ColumnPermissionLockedRow $row): self
    {
        if (!$this->lockedRows->contains($row)) {
            $this->lockedRows->add($row);
            $row->setParent($this);
        }
        return $this;
    }

    public function removeLockedRow(ColumnPermissionLockedRow $row): self
    {
        $this->lockedRows->removeElement($row);
        return $this;
    }
}
