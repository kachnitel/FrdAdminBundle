<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Fixtures;

use Doctrine\ORM\Mapping as ORM;

/**
 * Intermediate entity for nested-relation filter tests.
 * Has a direct field (title) and a ManyToOne to DeepEntity (deep.label).
 */
#[ORM\Entity]
class MiddleEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $title = '';

    #[ORM\ManyToOne(targetEntity: DeepEntity::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?DeepEntity $deep = null;

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

    public function getDeep(): ?DeepEntity
    {
        return $this->deep;
    }

    public function setDeep(?DeepEntity $deep): self
    {
        $this->deep = $deep;
        return $this;
    }
}
