<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Fixtures;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use Kachnitel\AdminBundle\Attribute\Admin;

/**
 * Fixture for DateField inline-edit tests.
 *
 * One nullable property per Doctrine date column type so every
 * branch of DateField::getDateType() and shouldUseImmutable() is reachable.
 * All properties are nullable to allow the "save null" tests.
 */
#[ORM\Entity]
#[Admin(label: 'Inline Edit Date Entities', enableInlineEdit: true)]
class InlineEditDateEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    /** datetime — mutable, nullable */
    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTimeInterface $createdAt = null;

    /** datetime_immutable — immutable, nullable */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $updatedAt = null;

    /** date — date-only, nullable */
    #[ORM\Column(type: 'date', nullable: true)]
    private ?DateTimeInterface $birthDate = null;

    /** date_immutable — date-only immutable, nullable */
    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?DateTimeImmutable $expiresOn = null;

    /** time — time-only, nullable */
    #[ORM\Column(type: 'time', nullable: true)]
    private ?DateTimeInterface $meetingTime = null;

    /** time_immutable — time-only immutable, nullable */
    #[ORM\Column(type: 'time_immutable', nullable: true)]
    private ?DateTimeImmutable $loggedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getBirthDate(): ?DateTimeInterface
    {
        return $this->birthDate;
    }

    public function setBirthDate(?DateTimeInterface $birthDate): self
    {
        $this->birthDate = $birthDate;
        return $this;
    }

    public function getExpiresOn(): ?DateTimeImmutable
    {
        return $this->expiresOn;
    }

    public function setExpiresOn(?DateTimeImmutable $expiresOn): self
    {
        $this->expiresOn = $expiresOn;
        return $this;
    }

    public function getMeetingTime(): ?DateTimeInterface
    {
        return $this->meetingTime;
    }

    public function setMeetingTime(?DateTimeInterface $meetingTime): self
    {
        $this->meetingTime = $meetingTime;
        return $this;
    }

    public function getLoggedAt(): ?DateTimeImmutable
    {
        return $this->loggedAt;
    }

    public function setLoggedAt(?DateTimeImmutable $loggedAt): self
    {
        $this->loggedAt = $loggedAt;
        return $this;
    }
}
