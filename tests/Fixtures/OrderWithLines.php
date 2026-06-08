<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Fixtures;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Attribute\AdminColumn;

/**
 * Order fixture entity covering both collection association types:
 *   - OneToMany  → lineItems  (cascade persist/remove; child form via LiveCollectionType)
 *   - ManyToMany → tags       (multi-select via EntityType)
 *
 * cascade: ['persist', 'remove'] on lineItems is required for DynamicEntityFormType
 * to save new/deleted items without manual EntityManager calls in the controller.
 */
#[ORM\Entity]
#[ORM\Table(name: 'test_order_with_lines')]
#[Admin(label: 'Orders')]
class OrderWithLines
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $reference = '';

    /**
     * OneToMany with cascade persist/remove — required for DynamicEntityFormType
     * to handle add/remove without manual flush calls.
     *
     * @var Collection<int, OrderLineItem>
     */
    #[ORM\OneToMany(
        targetEntity: OrderLineItem::class,
        mappedBy: 'order',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    private Collection $lineItems;

    /**
     * ManyToMany — rendered as a multi-select EntityType.
     *
     * @var Collection<int, TagFixture>
     */
    #[ORM\ManyToMany(targetEntity: TagFixture::class)]
    #[ORM\JoinTable(name: 'test_order_tags')]
    private Collection $tags;

    /**
     * ManyToMany that is blocked from the form via editable:false.
     *
     * @var Collection<int, TagFixture>
     */
    #[ORM\ManyToMany(targetEntity: TagFixture::class)]
    #[ORM\JoinTable(name: 'test_order_blocked_tags')]
    #[AdminColumn(editable: false)]
    private Collection $blockedTags;

    public function __construct()
    {
        $this->lineItems   = new ArrayCollection();
        $this->tags        = new ArrayCollection();
        $this->blockedTags = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function setReference(string $reference): self
    {
        $this->reference = $reference;
        return $this;
    }

    /** @return Collection<int, OrderLineItem> */
    public function getLineItems(): Collection
    {
        return $this->lineItems;
    }

    public function addLineItem(OrderLineItem $item): self
    {
        if (!$this->lineItems->contains($item)) {
            $this->lineItems->add($item);
            $item->setOrder($this);
        }
        return $this;
    }

    public function removeLineItem(OrderLineItem $item): self
    {
        $this->lineItems->removeElement($item);
        return $this;
    }

    /** @return Collection<int, TagFixture> */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    /** @param Collection<int, TagFixture> $tags */
    public function setTags(Collection $tags): self
    {
        $this->tags = $tags;
        return $this;
    }

    /** @return Collection<int, TagFixture> */
    public function getBlockedTags(): Collection
    {
        return $this->blockedTags;
    }
}
