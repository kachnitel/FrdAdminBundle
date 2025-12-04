<?php

declare(strict_types=1);

namespace Frd\AdminBundle\Tests\Fixtures;

use Doctrine\ORM\Mapping as ORM;
use Frd\AdminBundle\Attribute\Admin;

/**
 * Entity with comprehensive Admin attribute configuration for testing.
 */
#[ORM\Entity]
#[Admin(
    label: 'Configured Items',
    icon: 'settings',
    formType: 'App\Form\ConfiguredEntityType',
    enableFilters: false,
    enableBatchActions: false,
    columns: ['id', 'name', 'email', 'status', 'createdAt'],
    excludeColumns: ['password', 'secret'],
    filterableColumns: ['name', 'email'],
    permissions: [
        'index' => 'ROLE_USER',
        'show' => 'ROLE_USER',
        'new' => 'ROLE_EDITOR',
        'edit' => 'ROLE_EDITOR',
        'delete' => 'ROLE_ADMIN',
    ],
    itemsPerPage: 50,
    sortBy: 'createdAt',
    sortDirection: 'DESC'
)]
class ConfiguredEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name = '';

    #[ORM\Column(type: 'string', length: 255)]
    private string $email = '';

    #[ORM\Column(type: 'string', length: 255)]
    private string $password = '';

    #[ORM\Column(type: 'string', length: 255)]
    private string $secret = '';

    #[ORM\Column(type: 'string', length: 50)]
    private string $status = 'active';

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
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

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }
}
