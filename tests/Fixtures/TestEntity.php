<?php

namespace Kachnitel\AdminBundle\Tests\Fixtures;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Attribute\ColumnFilter;

enum TestStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}

#[ORM\Entity]
#[Admin(
    label: 'Test Entities',
    icon: 'science',
    columns: ['id', 'name', 'active'],
    itemsPerPage: 15,
    enableBatchActions: true,
    permissions: ['index' => 'ROLE_TEST_VIEW', 'edit' => 'ROLE_TEST_EDIT']
)]
class TestEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[ColumnFilter(type: ColumnFilter::TYPE_TEXT, placeholder: 'Search name...')]
    private string $name = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'integer')]
    #[ColumnFilter(type: ColumnFilter::TYPE_NUMBER)]
    private int $quantity = 0;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $price = '0.00';

    #[ORM\Column(type: 'datetime')]
    #[ColumnFilter(type: ColumnFilter::TYPE_DATE)]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'boolean')]
    #[ColumnFilter(type: ColumnFilter::TYPE_BOOLEAN)]
    private bool $active = true;

    #[ORM\Column(type: 'string', enumType: TestStatus::class)]
    private TestStatus $status = TestStatus::ACTIVE;

    #[ORM\ManyToOne(targetEntity: RelatedEntity::class)]
    #[ColumnFilter(
        type: ColumnFilter::TYPE_RELATION,
        searchFields: ['name', 'email'],
        placeholder: 'Search related...'
    )]
    private ?RelatedEntity $relatedEntity = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ColumnFilter(
        type: ColumnFilter::TYPE_RELATION,
        placeholder: 'Search customer...'
    )]
    private ?User $customer = null;

    /**
     * @var Collection<int, TagEntity>
     */
    #[ORM\OneToMany(targetEntity: TagEntity::class, mappedBy: 'testEntity')]
    private Collection $tags;

    #[ORM\Column(type: 'string')]
    #[ColumnFilter(enabled: false)]
    private string $disabledFilter = '';

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->tags = new ArrayCollection();
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function getPrice(): string
    {
        return $this->price;
    }

    public function setPrice(string $price): self
    {
        $this->price = $price;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getStatus(): TestStatus
    {
        return $this->status;
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

    public function getCustomer(): ?User
    {
        return $this->customer;
    }

    public function setCustomer(?User $customer): self
    {
        $this->customer = $customer;
        return $this;
    }

    public function getDisabledFilter(): string
    {
        return $this->disabledFilter;
    }

    /**
     * @return Collection<int, TagEntity>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(TagEntity $tag): self
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
            $tag->setTestEntity($this);
        }

        return $this;
    }

    public function removeTag(TagEntity $tag): self
    {
        if ($this->tags->removeElement($tag)) {
            if ($tag->getTestEntity() === $this) {
                $tag->setTestEntity(null);
            }
        }

        return $this;
    }
}
