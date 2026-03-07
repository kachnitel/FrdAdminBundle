<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Fixtures;

use Doctrine\ORM\Mapping as ORM;
use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Attribute\AdminColumn;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Fixture entity used exclusively for inline-edit validation tests.
 *
 * Declares explicit Symfony validator constraints so the save() validation path
 * can be exercised without coupling to InlineEditEntity (which intentionally has
 * no constraints to keep scalar field tests simple).
 */
#[ORM\Entity]
#[Admin(
    label: 'Inline Edit Validatable Entities',
    columns: ['id', 'title', 'score'],
    enableInlineEdit: true,
)]
class InlineEditValidatableEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    /**
     * @var string Title, max 20 characters, not blank.
     */
    #[ORM\Column(type: 'string', length: 20)]
    #[AdminColumn(editable: true)]
    #[Assert\NotBlank(message: 'Title must not be blank.')]
    #[Assert\Length(max: 20, maxMessage: 'Title cannot exceed {{ limit }} characters.')]
    private string $title = '';

    /**
     * @var float Score between 0 and 100.
     */
    #[ORM\Column(type: 'float')]
    #[AdminColumn(editable: true)]
    #[Assert\Range(min: 0, max: 100, notInRangeMessage: 'Score must be between {{ min }} and {{ max }}.')]
    private float $score = 0.0;

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

    public function getScore(): float
    {
        return $this->score;
    }

    public function setScore(float $score): self
    {
        $this->score = $score;

        return $this;
    }
}
