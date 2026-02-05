<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Fixtures;

use Doctrine\ORM\Mapping as ORM;
use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Attribute\ColumnPermission;

/**
 * Test entity for testing per-column permission feature.
 */
#[ORM\Entity]
#[Admin(
    label: 'Permission Test Entities',
    columns: ['id', 'name', 'salary', 'internalNotes', 'publicField']
)]
class PermissionTestEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name = '';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[ColumnPermission('ROLE_HR')]
    private string $salary = '0.00';

    #[ORM\Column(type: 'text', nullable: true)]
    #[ColumnPermission('ROLE_MANAGER')]
    private ?string $internalNotes = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $publicField = '';

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

    public function getSalary(): string
    {
        return $this->salary;
    }

    public function setSalary(string $salary): self
    {
        $this->salary = $salary;
        return $this;
    }

    public function getInternalNotes(): ?string
    {
        return $this->internalNotes;
    }

    public function setInternalNotes(?string $internalNotes): self
    {
        $this->internalNotes = $internalNotes;
        return $this;
    }

    public function getPublicField(): string
    {
        return $this->publicField;
    }

    public function setPublicField(string $publicField): self
    {
        $this->publicField = $publicField;
        return $this;
    }
}
