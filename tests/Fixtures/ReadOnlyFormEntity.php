<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Fixtures;

use Doctrine\ORM\Mapping as ORM;
use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Attribute\AdminColumn;

/**
 * Covers #[AdminColumn(editable: false, readOnlyInForm: true)]:
 *   - title       → ordinary editable field
 *   - code        → shown in the form, disabled
 *   - hiddenCode  → editable: false only, absent from the form
 */
#[ORM\Entity]
#[ORM\Table(name: 'test_read_only_form')]
#[Admin(label: 'Read only form')]
class ReadOnlyFormEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $title = '';

    #[ORM\Column(length: 100)]
    #[AdminColumn(editable: false, readOnlyInForm: true)]
    private string $code = 'ORIGINAL';

    #[ORM\Column(length: 100)]
    #[AdminColumn(editable: false)]
    private string $hiddenCode = '';

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

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function getHiddenCode(): string
    {
        return $this->hiddenCode;
    }

    public function setHiddenCode(string $hiddenCode): self
    {
        $this->hiddenCode = $hiddenCode;

        return $this;
    }
}
