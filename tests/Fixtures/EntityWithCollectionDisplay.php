<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Fixtures;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Attribute\AdminColumn;
use Kachnitel\AdminBundle\Attribute\ColumnFilter;

/**
 * Fixture for collection-display accordion/list feature tests.
 *
 * Three variants of a OneToMany collection on the same target:
 *   - tagsAccordion : collectionDisplay + collapsible (default)  → <details>/<summary>
 *   - tagsList      : collectionDisplay + non-collapsible         → always-visible list
 *   - tagsDefault   : no collectionDisplay                        → count+link (legacy)
 */
#[ORM\Entity]
#[Admin(label: 'Collection Display Entities')]
class EntityWithCollectionDisplay
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name = '';

    /**
     * Accordion (collapsible), limit 3.
     *
     * @var Collection<int, TagEntity>
     */
    #[ORM\ManyToMany(targetEntity: TagEntity::class)]
    #[ORM\JoinTable(name: 'collection_display_tags_accordion')]
    #[ColumnFilter(searchFields: ['name'])]
    #[AdminColumn(collectionDisplay: true, collectionCollapsible: true, collectionLimit: 3)]
    private Collection $tagsAccordion;

    /**
     * Always-visible list, limit 3.
     *
     * @var Collection<int, TagEntity>
     */
    #[ORM\ManyToMany(targetEntity: TagEntity::class)]
    #[ORM\JoinTable(name: 'collection_display_tags_list')]
    #[ColumnFilter(searchFields: ['name'])]
    #[AdminColumn(collectionDisplay: true, collectionCollapsible: false, collectionLimit: 3)]
    private Collection $tagsList;

    /**
     * Default rendering — count + link, no inline items.
     *
     * @var Collection<int, TagEntity>
     */
    #[ORM\ManyToMany(targetEntity: TagEntity::class)]
    #[ORM\JoinTable(name: 'collection_display_tags_default')]
    private Collection $tagsDefault;

    public function __construct()
    {
        $this->tagsAccordion = new ArrayCollection();
        $this->tagsList      = new ArrayCollection();
        $this->tagsDefault   = new ArrayCollection();
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

    /** @return Collection<int, TagEntity> */
    public function getTagsAccordion(): Collection
    {
        return $this->tagsAccordion;
    }

    /** @return Collection<int, TagEntity> */
    public function getTagsList(): Collection
    {
        return $this->tagsList;
    }

    /** @return Collection<int, TagEntity> */
    public function getTagsDefault(): Collection
    {
        return $this->tagsDefault;
    }
}
