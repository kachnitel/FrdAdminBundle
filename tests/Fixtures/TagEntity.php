<?php

namespace Kachnitel\AdminBundle\Tests\Fixtures;

use Doctrine\ORM\Mapping as ORM;
use Kachnitel\AdminBundle\Attribute\ColumnFilter;

#[ORM\Entity]
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

    public function __toString(): string
    {
        return $this->name;
    }
}
