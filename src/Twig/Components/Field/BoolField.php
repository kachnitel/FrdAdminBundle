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
 * Inline-editable field for boolean properties.
 *
 * ## Why ?bool instead of bool
 *
 * The hydrateWith/dehydrateWith pattern requires a null sentinel to signal
 * "re-read from entity" (triggered by cancelEdit). A non-nullable bool cannot
 * distinguish null (uninitialized) from false (user unchecked). Using ?bool
 * preserves that distinction while keeping the LiveProp strictly single-typed.
 *
 * Templates treat null the same as false.
 *
 * hydrateCurrentValue(null)  → re-reads from entity after cancelEdit.
 * hydrateCurrentValue($bool) → restores the typed value.
 */
#[AsLiveComponent('K:Admin:Field:Bool', template: '@KachnitelAdmin/components/field/bool_field.html.twig')]
final class BoolField extends AbstractEditableField
{
    use DefaultActionTrait;

    #[LiveProp(writable: true, hydrateWith: 'hydrateCurrentValue', dehydrateWith: 'dehydrateCurrentValue')]
    public ?bool $currentValue = null;

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
        $this->currentValue = (bool) $this->readValue();
    }

    // ── Hydration ──────────────────────────────────────────────────────────────

    public function hydrateCurrentValue(mixed $data): bool
    {
        if ($data === null) {
            return (bool) $this->readValue();
        }

        return (bool) $data;
    }

    public function dehydrateCurrentValue(?bool $value): ?bool
    {
        return $value;
    }

    // ── LiveActions ────────────────────────────────────────────────────────────

    #[LiveAction]
    public function cancelEdit(): void
    {
        $this->currentValue = null;
        parent::cancelEdit();
    }

    #[LiveAction]
    public function save(): void
    {
        $this->writeValue($this->currentValue ?? false);
        parent::save();
    }

    // ── Template helpers ───────────────────────────────────────────────────────

    public function renderValue(): string
    {
        return (bool) $this->readValue() ? 'Yes' : 'No';
    }
}