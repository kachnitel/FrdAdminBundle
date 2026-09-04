<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Fixtures;

use Doctrine\ORM\Mapping as ORM;
use Kachnitel\AdminBundle\Attribute\Admin;

/**
 * Fixture entity for object-level authorization tests.
 *
 * Models the feature request's Contact/ContactType example in miniature:
 * $kind stands in for Contact's CUSTOMER/VENDOR/BOTH type, and ObjectAuthVoter
 * grants ADMIN_* attributes only when $kind === self::KIND_ALLOWED, denying
 * (never abstaining) otherwise — see ObjectAuthVoter's docblock for why the
 * denying-not-abstaining distinction matters for these tests.
 */
#[ORM\Entity]
#[Admin(
    label: 'Object Auth Entities',
    columns: ['id', 'name', 'kind'],
    enableObjectAuth: true,
)]
class ObjectAuthEntity
{
    public const KIND_ALLOWED = 'allowed';
    public const KIND_FORBIDDEN = 'forbidden';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name = '';

    #[ORM\Column(type: 'string', length: 20)]
    private string $kind = self::KIND_ALLOWED;

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

    public function getKind(): string
    {
        return $this->kind;
    }

    public function setKind(string $kind): self
    {
        $this->kind = $kind;
        return $this;
    }
}
