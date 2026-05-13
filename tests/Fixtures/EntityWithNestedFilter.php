<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Fixtures;

use Doctrine\ORM\Mapping as ORM;
use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Attribute\ColumnFilter;

/**
 * Fixture for testing dot-notation (nested) search fields in ColumnFilter.
 *
 * The `middle` relation declares:
 *   - 'title'      — direct field on MiddleEntity
 *   - 'deep.label' — nested: MiddleEntity.deep.label (one JOIN level deep)
 */
#[ORM\Entity]
#[Admin(label: 'Nested Filter Entities')]
class EntityWithNestedFilter
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name = '';

    #[ORM\ManyToOne(targetEntity: MiddleEntity::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[ColumnFilter(searchFields: ['title', 'deep.label', 'deep.source.code'])]
    private ?MiddleEntity $middle = null;

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

    public function getMiddle(): ?MiddleEntity
    {
        return $this->middle;
    }

    public function setMiddle(?MiddleEntity $middle): self
    {
        $this->middle = $middle;
        return $this;
    }
}
