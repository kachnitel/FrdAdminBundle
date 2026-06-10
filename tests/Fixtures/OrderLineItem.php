<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Fixtures;

use Doctrine\ORM\Mapping as ORM;

/**
 * Line item child entity for OneToMany form tests.
 *
 * The $order property is the inverse ManyToOne side. DynamicEntityFormType
 * detects this automatically via Doctrine's mappedBy metadata and skips it
 * — no #[AdminColumn(editable: false)] required.
 */
#[ORM\Entity]
#[ORM\Table(name: 'test_order_line_item')]
class OrderLineItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 200)]
    private string $description = '';

    #[ORM\Column]
    private int $quantity = 1;

    /**
     * Inverse side — DynamicEntityFormType skips this automatically because
     * Doctrine sets mappedBy on the inverse side of OneToMany relationships.
     * No #[AdminColumn(editable: false)] needed.
     */
    #[ORM\ManyToOne(targetEntity: OrderWithLines::class, inversedBy: 'lineItems')]
    #[ORM\JoinColumn(nullable: false)]
    private ?OrderWithLines $order = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
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

    public function getOrder(): ?OrderWithLines
    {
        return $this->order;
    }

    public function setOrder(?OrderWithLines $order): self
    {
        $this->order = $order;
        return $this;
    }
}
