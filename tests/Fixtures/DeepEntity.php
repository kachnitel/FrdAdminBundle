<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Fixtures;

use Doctrine\ORM\Mapping as ORM;

/**
 * Leaf entity for nested-relation filter tests.
 * Supports two-level filtering (label) and three-level filtering (source.code).
 */
#[ORM\Entity]
class DeepEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $label = '';

    #[ORM\ManyToOne(targetEntity: SourceEntity::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?SourceEntity $source = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;
        return $this;
    }

    public function getSource(): ?SourceEntity
    {
        return $this->source;
    }

    public function setSource(?SourceEntity $source): self
    {
        $this->source = $source;
        return $this;
    }
}
