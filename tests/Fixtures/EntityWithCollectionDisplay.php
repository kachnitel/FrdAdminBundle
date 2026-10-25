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
 *   - tagsDialogAccordion : accordion + collectionDialog          → dialog outside <details>
 *   - tagsDialogList      : always-visible list + collectionDialog
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

    /**
     * @var Collection<int, TagEntity>
     */
    #[ORM\OneToMany(targetEntity: TagEntity::class, mappedBy: 'dialogOwner')]
    #[AdminColumn(collectionDialog: true)]
    private Collection $dialogItems;

    /**
     * Accordion with a dialog: the <dialog> must not end up inside <summary>/<details>.
     *
     * @var Collection<int, TagEntity>
     */
    #[ORM\ManyToMany(targetEntity: TagEntity::class)]
    #[ORM\JoinTable(name: 'collection_display_tags_dialog_accordion')]
    #[AdminColumn(collectionDisplay: true, collectionCollapsible: true, collectionLimit: 3, collectionDialog: true)]
    private Collection $tagsDialogAccordion;

    /**
     * Always-visible list with a dialog.
     *
     * @var Collection<int, TagEntity>
     */
    #[ORM\ManyToMany(targetEntity: TagEntity::class)]
    #[ORM\JoinTable(name: 'collection_display_tags_dialog_list')]
    #[AdminColumn(collectionDisplay: true, collectionCollapsible: false, collectionLimit: 3, collectionDialog: true)]
    private Collection $tagsDialogList;

    public function __construct()
    {
        $this->tagsAccordion = new ArrayCollection();
        $this->tagsList      = new ArrayCollection();
        $this->tagsDefault   = new ArrayCollection();
        $this->dialogItems   = new ArrayCollection();
        $this->tagsDialogAccordion = new ArrayCollection();
        $this->tagsDialogList      = new ArrayCollection();
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

    /**
     * @return Collection<int, TagEntity>
     */
    public function getDialogItems(): Collection
    {
        return $this->dialogItems;
    }

    /** @return Collection<int, TagEntity> */
    public function getTagsDialogAccordion(): Collection
    {
        return $this->tagsDialogAccordion;
    }

    /** @return Collection<int, TagEntity> */
    public function getTagsDialogList(): Collection
    {
        return $this->tagsDialogList;
    }
}
