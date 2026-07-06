<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Fixtures;

use Doctrine\ORM\Mapping as ORM;
use Kachnitel\AdminBundle\Attribute\Admin;

/**
 * Entity with Doctrine-required (nullable: false) columns whose PHP
 * properties are nevertheless nullable and unset on a fresh instance — the
 * standard shape for entities backing a "new" creation form, where a real
 * value cannot exist until the user provides one.
 *
 * Deliberately distinct from AllTypesEntity: that fixture initialises every
 * date/time property in its constructor (built for type-preview rendering
 * tests) and so never has a genuinely blank required field to corrupt. This
 * entity has none of that scaffolding — priority and scheduledAt start out
 * truly unset, exactly like a real "new Product()" would before its form is
 * filled in.
 *
 * completedAt is different from priority/scheduledAt on purpose: it is
 * nullable at *both* the Doctrine and PHP level, i.e. genuinely optional,
 * not just "Doctrine-required with a PHP-nullable workaround". It exists
 * specifically as a contrast case — a field that must never show a
 * required-field error no matter what's submitted for it.
 *
 * Used by LiveFormEmptyDataRegressionTest to reproduce the DoctrineFormTypeMapper
 * / live-form empty_data defaulting regression.
 */
#[ORM\Entity]
#[Admin(label: 'Required Fields')]
class RequiredFieldsEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string')]
    private string $name = '';

    /**
     * Doctrine: NOT NULL (a priority is mandatory once saved).
     * PHP: nullable — a brand new entity has no priority yet.
     */
    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $priority = null;

    /**
     * Doctrine: NOT NULL. PHP: nullable — unset until the user picks a date.
     */
    #[ORM\Column(type: 'datetime_immutable', nullable: false)]
    private ?\DateTimeImmutable $scheduledAt = null;

    /**
     * Genuinely optional at both levels — the contrast case. See class
     * docblock.
     */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

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

    public function getPriority(): ?int
    {
        return $this->priority;
    }

    public function setPriority(?int $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    public function getScheduledAt(): ?\DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    public function setScheduledAt(?\DateTimeImmutable $scheduledAt): self
    {
        $this->scheduledAt = $scheduledAt;

        return $this;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeImmutable $completedAt): self
    {
        $this->completedAt = $completedAt;

        return $this;
    }
}
