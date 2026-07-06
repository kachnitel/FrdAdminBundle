<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Fixtures;

use Doctrine\ORM\Mapping as ORM;
use Kachnitel\AdminBundle\Attribute\Admin;

/**
 * Same DB-required (nullable: false) integer column as RequiredFieldsEntity's
 * $priority, but with the PHP property typed the way a real entity actually
 * would be: `private int $qty;`, no default, no nullable workaround.
 *
 * RequiredFieldsEntity cannot reproduce the reported crash because every
 * property on it is PHP-nullable (`?int $priority`), so
 * preserveCurrentValueEmptyData()'s `null` return is always a legal
 * PropertyAccessor write. Here it isn't — $qty mirrors the real app's
 * PurchaseOrderLine::$qty (private int, no default, setQty(int $qty)) and
 * has no legal "not yet given a value" representation at all.
 */
#[ORM\Entity]
#[Admin(label: 'Required Fields (Strict)')]
class RequiredFieldsStrictEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string')]
    private string $name = '';

    /**
     * Doctrine: NOT NULL. PHP: non-nullable, no default — genuinely
     * uninitialized until setQty() is called.
     */
    #[ORM\Column(type: 'integer', nullable: false)]
    private int $qty;

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

    public function getQty(): int
    {
        return $this->qty;
    }

    public function setQty(int $qty): self
    {
        $this->qty = $qty;

        return $this;
    }
}
