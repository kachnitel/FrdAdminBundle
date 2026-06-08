<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Fixtures;

use Doctrine\ORM\Mapping as ORM;
use Kachnitel\AdminBundle\Attribute\AdminColumn;

/**
 * Line item child entity for OneToMany form tests.
 *
 * The $order property is the inverse ManyToOne side. In real use,
 * developers should mark it #[AdminColumn(editable: false)] so the
 * child form does not render a dropdown back to the parent Order —
 * see DynamicFormCollectionTest::testInverseSideIsHiddenViaEditableFalse.
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
     * Inverse side of the OneToMany — marked editable:false so the
     * DynamicEntityFormType child form skips it and avoids rendering
     * a parent-pointing dropdown inside the collection entry.
     */
    #[ORM\ManyToOne(targetEntity: OrderWithLines::class, inversedBy: 'lineItems')]
    #[ORM\JoinColumn(nullable: false)]
    #[AdminColumn(editable: false)]
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
