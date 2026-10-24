<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Fixtures;

use Doctrine\ORM\Mapping as ORM;
use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Attribute\AdminColumn;
use Kachnitel\AdminBundle\Attribute\ColumnPermission;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;

/**
 * Covers #[ColumnPermission] in the New/Edit form:
 *   - title   → no restriction (always read-write)
 *   - salary  → no entry without ROLE_HR, read-only with ROLE_HR, read-write with ROLE_HR + ROLE_HR_EDIT
 *   - hidden  → #[AdminColumn(editable: false)], absent regardless of roles
 */
#[ORM\Entity]
#[ORM\Table(name: 'test_form_permission')]
#[Admin(label: 'Form permission')]
class FormPermissionEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $title = '';

    #[ORM\Column(length: 100)]
    #[ColumnPermission([
        AdminEntityVoter::ADMIN_SHOW => 'ROLE_HR',
        AdminEntityVoter::ADMIN_EDIT => 'ROLE_HR_EDIT',
    ])]
    private string $salary = '100';

    #[ORM\Column(length: 100)]
    #[AdminColumn(editable: false)]
    private string $hidden = '';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

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

    public function getHidden(): string
    {
        return $this->hidden;
    }

    public function setHidden(string $hidden): self
    {
        $this->hidden = $hidden;

        return $this;
    }
}
