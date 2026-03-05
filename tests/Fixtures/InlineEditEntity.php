<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Fixtures;

use Doctrine\ORM\Mapping as ORM;
use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Attribute\AdminColumn;

/**
 * Minimal fixture entity for scalar inline-edit field tests.
 */
#[ORM\Entity]
#[Admin(
    label: 'Inline Edit Entities',
    columns: ['id', 'title', 'count', 'score', 'active'],
    enableInlineEdit: true,
)]
class InlineEditEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[AdminColumn(editable: true)]
    private string $title = '';

    #[ORM\Column(type: 'integer')]
    #[AdminColumn(editable: true)]
    private int $count = 0;

    #[ORM\Column(type: 'float')]
    #[AdminColumn(editable: true)]
    private float $score = 0.0;

    #[ORM\Column(type: 'boolean', nullable: true)]
    #[AdminColumn(editable: true)]
    private ?bool $active = null;

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

    public function getCount(): int
    {
        return $this->count;
    }

    public function setCount(int $count): self
    {
        $this->count = $count;

        return $this;
    }

    public function getScore(): float
    {
        return $this->score;
    }

    public function setScore(float $score): self
    {
        $this->score = $score;

        return $this;
    }

    public function getActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(?bool $active): self
    {
        $this->active = $active;

        return $this;
    }
}
