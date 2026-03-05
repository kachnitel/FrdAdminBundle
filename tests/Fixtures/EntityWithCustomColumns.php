<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Fixtures;

use Doctrine\ORM\Mapping as ORM;
use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Attribute\AdminCustomColumn;

/**
 * Test fixture entity that declares custom (virtual) columns via #[AdminCustomColumn].
 *
 * - `statusBadge` — appended automatically (no explicit columns: list)
 * - `fullName`    — declared in Admin::columns at a specific position
 *
 * Used by EntityListCustomColumnTest.
 */
#[ORM\Entity]
#[Admin(
    label: 'Custom Column Entities',
    columns: ['id', 'firstName', 'lastName', 'fullName', 'status'],
)]
#[AdminCustomColumn(
    name: 'fullName',
    template: 'test/custom_column_full_name.html.twig',
    label: 'Full Name',
)]
#[AdminCustomColumn(
    name: 'statusBadge',
    template: 'test/custom_column_status_badge.html.twig',
    label: 'Status Badge',
)]
class EntityWithCustomColumns
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 100)]
    private string $firstName = '';

    #[ORM\Column(type: 'string', length: 100)]
    private string $lastName = '';

    #[ORM\Column(type: 'string', length: 50)]
    private string $status = 'active';

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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }
}
