<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Fixtures;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Kachnitel\AdminBundle\Attribute\ColumnFilter;

/**
 * Fixture entity used to verify collection dot-notation filtering.
 *
 * The `items` collection covers all nesting depths:
 *   - 'name'                → direct field on ItemEntity
 *   - 'tag.name'            → two-level: ItemEntity → TagEntity
 *   - 'tag.testEntity.name' → three-level: ItemEntity → TagEntity → TestEntity
 */
#[ORM\Entity]
#[ORM\Table(name: 'test_entity_with_collection_filter')]
class EntityWithCollectionFilter
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name = '';

    /** @var Collection<int, ItemEntity> $items */
    #[ORM\ManyToMany(targetEntity: ItemEntity::class, cascade: ['persist'])]
    #[ORM\JoinTable(name: 'test_entity_collection_filter_items')]
    #[ColumnFilter(type: 'collection', searchFields: ['name', 'tag.name', 'tag.testEntity.name'])]
    private Collection $items;

    public function __construct()
    {
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return Collection<int, ItemEntity>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(ItemEntity $item): void
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
        }
    }
}
