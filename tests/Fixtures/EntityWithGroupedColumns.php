<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Fixtures;

use Doctrine\ORM\Mapping as ORM;
use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Attribute\AdminColumn;

/**
 * Fixture entity for composite/grouped column feature tests.
 *
 * firstName + lastName are grouped under 'name_block'.
 * email is ungrouped.
 */
#[ORM\Entity]
#[Admin(label: 'Grouped Column Entities')]
class EntityWithGroupedColumns
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 100)]
    #[AdminColumn(group: 'name_block')]
    private string $firstName = '';

    #[ORM\Column(type: 'string', length: 100)]
    #[AdminColumn(group: 'name_block')]
    private string $lastName = '';

    #[ORM\Column(type: 'string', length: 255)]
    private string $email = '';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): self
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): self
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }
}
