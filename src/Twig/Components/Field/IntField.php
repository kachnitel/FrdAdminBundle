<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Components\Field;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Inline-editable field for integer properties.
 *
 * hydrateCurrentValue(null) → re-reads from entity after cancelEdit.
 * hydrateCurrentValue($int) → restores the typed value.
 */
#[AsLiveComponent('K:Admin:Field:Int', template: '@KachnitelAdmin/components/field/IntField.html.twig')]
final class IntField extends AbstractEditableField
{
    use DefaultActionTrait;

    #[LiveProp(writable: true, hydrateWith: 'hydrateCurrentValue', dehydrateWith: 'dehydrateCurrentValue')]
    public ?int $currentValue = null;

    public function __construct(
        EntityManagerInterface $entityManager,
        PropertyAccessorInterface $propertyAccessor,
        AuthorizationCheckerInterface $authorizationChecker,
    ) {
        parent::__construct($entityManager, $propertyAccessor, $authorizationChecker);
    }

    public function mount(object $entity, string $property): void
    {
        parent::mount($entity, $property);
        $raw = $this->readValue();
        $this->currentValue = $raw !== null ? (int) $raw : null;
    }

    // ── Hydration ──────────────────────────────────────────────────────────────

    public function hydrateCurrentValue(mixed $data): ?int
    {
        return $data !== null ? (int) $data : null;
    }

    public function dehydrateCurrentValue(?int $value): ?int
    {
        return $value;
    }

    // ── LiveActions ────────────────────────────────────────────────────────────
    #[LiveAction]
    public function cancelEdit(): void
    {
        parent::cancelEdit();
        $raw = $this->readValue();
        $this->currentValue = $raw !== null ? (int) $raw : null;
    }

    #[LiveAction]
    public function save(): void
    {
        $this->writeValue($this->currentValue);
        parent::save();
    }
}