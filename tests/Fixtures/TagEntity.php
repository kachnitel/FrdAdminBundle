<?php

namespace Kachnitel\AdminBundle\Tests\Fixtures;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Attribute\ColumnFilter;

#[ORM\Entity]
#[Admin]
class TagEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 100)]
    private string $name = '';

    #[ORM\ManyToOne(targetEntity: TestEntity::class, inversedBy: 'tags')]
    #[ColumnFilter]
    private ?TestEntity $testEntity = null;

    #[ORM\ManyToOne(targetEntity: EntityWithCollectionDisplay::class, inversedBy: 'dialogItems')]
    private ?EntityWithCollectionDisplay $dialogOwner = null;

    /**
     * @var Collection<int, TestEntity>
     */
    #[ORM\ManyToMany(targetEntity: TestEntity::class, inversedBy: 'sharedItems')]
    private Collection $sharedOwners;

    public function __construct()
    {
        $this->sharedOwners = new ArrayCollection();
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

    public function getTestEntity(): ?TestEntity
    {
        return $this->testEntity;
    }

    public function setTestEntity(?TestEntity $testEntity): self
    {
        $this->testEntity = $testEntity;
        return $this;
    }

    public function setDialogOwner(?EntityWithCollectionDisplay $dialogOwner): self
    {
        $this->dialogOwner = $dialogOwner;
        return $this;
    }

    /**
     * @return Collection<int, TestEntity>
     */
    public function getSharedOwners(): Collection
    {
        return $this->sharedOwners;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
