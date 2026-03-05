<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Fixtures;

use Doctrine\ORM\Mapping as ORM;
use Kachnitel\AdminBundle\Attribute\Admin;

/**
 * Fixture entity that intentionally exposes a ManyToOne association column.
 *
 * Used to verify that the sort guard correctly treats association columns as
 * non-sortable and falls back gracefully rather than throwing a DQL error.
 */
#[ORM\Entity]
#[Admin(
    label: 'Relation Column Entities',
    columns: ['id', 'title', 'relatedEntity'],
)]
class EntityWithRelationInColumns
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $title = '';

    #[ORM\ManyToOne(targetEntity: RelatedEntity::class)]
    private ?RelatedEntity $relatedEntity = null;

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

    public function getRelatedEntity(): ?RelatedEntity
    {
        return $this->relatedEntity;
    }

    public function setRelatedEntity(?RelatedEntity $relatedEntity): self
    {
        $this->relatedEntity = $relatedEntity;

        return $this;
    }
}
