<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Fixtures;

use Doctrine\ORM\Mapping as ORM;
use Kachnitel\AdminBundle\Attribute\Admin;

/**
 * Test entity with one field per Doctrine column type to verify all type preview templates render.
 */
#[ORM\Entity]
#[Admin(
    label: 'All Types',
    columns: [
        'id', 'name', 'active', 'quantity',
        'birthDate', 'createdAt', 'updatedAt', 'expiresOn',
        'meetingTime', 'loggedAtImmutable',
        'publishedAt', 'archivedAt',
        'duration',
        'status',
    ],
    enableFilters: false,
)]
class AllTypesEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string')]
    private string $name = '';

    #[ORM\Column(type: 'boolean')]
    private bool $active = true;

    #[ORM\Column(type: 'integer')]
    private int $quantity = 0;

    #[ORM\Column(type: 'date')]
    private \DateTimeInterface $birthDate;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $expiresOn;

    #[ORM\Column(type: 'time')]
    private \DateTimeInterface $meetingTime;

    #[ORM\Column(type: 'time_immutable')]
    private \DateTimeImmutable $loggedAtImmutable;

    #[ORM\Column(type: 'datetimetz')]
    private \DateTimeInterface $publishedAt;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $archivedAt;

    #[ORM\Column(type: 'dateinterval')]
    private \DateInterval $duration;

    #[ORM\Column(type: 'string', enumType: TestStatus::class)]
    private TestStatus $status = TestStatus::ACTIVE;

    public function __construct()
    {
        $this->birthDate = new \DateTime('2000-01-15');
        $this->createdAt = new \DateTime('2024-06-01 12:30:00');
        $this->updatedAt = new \DateTimeImmutable('2024-06-15 14:00:00');
        $this->expiresOn = new \DateTimeImmutable('2025-12-31');
        $this->meetingTime = new \DateTime('1970-01-01 14:30:00');
        $this->loggedAtImmutable = new \DateTimeImmutable('1970-01-01 09:15:00');
        $this->publishedAt = new \DateTime('2024-03-20 10:00:00');
        $this->archivedAt = new \DateTimeImmutable('2024-12-01 18:00:00');
        $this->duration = new \DateInterval('P3DT2H30M');
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

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getBirthDate(): \DateTimeInterface
    {
        return $this->birthDate;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getExpiresOn(): \DateTimeImmutable
    {
        return $this->expiresOn;
    }

    public function getMeetingTime(): \DateTimeInterface
    {
        return $this->meetingTime;
    }

    public function getLoggedAtImmutable(): \DateTimeImmutable
    {
        return $this->loggedAtImmutable;
    }

    public function getPublishedAt(): \DateTimeInterface
    {
        return $this->publishedAt;
    }

    public function getArchivedAt(): \DateTimeImmutable
    {
        return $this->archivedAt;
    }

    public function getDuration(): \DateInterval
    {
        return $this->duration;
    }

    public function getStatus(): TestStatus
    {
        return $this->status;
    }
}
