<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Fixtures;

use Doctrine\ORM\Mapping as ORM;
use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Attribute\AdminColumn;
use Kachnitel\AdminBundle\Attribute\AdminColumnGroup;
use Kachnitel\DataSourceContracts\ColumnGroup;

/**
 * Fixture entity for composite column display-option tests.
 *
 * - 'name_block' group: icon sub-labels, collapsible header
 * - 'address_block' group: hidden sub-labels, text-only header
 */
#[ORM\Entity]
#[Admin(label: 'Configured Group Entities')]
#[AdminColumnGroup(
    id: 'name_block',
    subLabels: ColumnGroup::SUB_LABELS_ICON,
    header: ColumnGroup::HEADER_COLLAPSIBLE,
)]
#[AdminColumnGroup(
    id: 'address_block',
    subLabels: ColumnGroup::SUB_LABELS_HIDDEN,
    header: ColumnGroup::HEADER_TEXT,
)]
class EntityWithConfiguredGroups
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
    #[AdminColumn(group: 'address_block')]
    private string $city = '';

    #[ORM\Column(type: 'string', length: 100)]
    #[AdminColumn(group: 'address_block')]
    private string $country = '';

    #[ORM\Column(type: 'string', length: 255)]
    private string $email = '';

    public function getId(): ?int { return $this->id; }

    public function getFirstName(): string { return $this->firstName; }
    public function setFirstName(string $v): self { $this->firstName = $v; return $this; }

    public function getLastName(): string { return $this->lastName; }
    public function setLastName(string $v): self { $this->lastName = $v; return $this; }

    public function getCity(): string { return $this->city; }
    public function setCity(string $v): self { $this->city = $v; return $this; }

    public function getCountry(): string { return $this->country; }
    public function setCountry(string $v): self { $this->country = $v; return $this; }

    public function getEmail(): string { return $this->email; }
    public function setEmail(string $v): self { $this->email = $v; return $this; }
}
