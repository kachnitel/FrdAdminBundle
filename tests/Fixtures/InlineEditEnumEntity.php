<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Fixtures;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class InlineEditEnumEntity
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::ENUM, enumType: TestStatus::class)]
    public TestStatus $status = TestStatus::ACTIVE;

    // A non-enum property for error testing
    #[ORM\Column(type: 'string')]
    public string $notAnEnum = 'hello';

    public function getId(): ?int { return $this->id; }
}
