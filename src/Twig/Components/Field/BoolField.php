<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Components\Field;

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
#[AsLiveComponent('K:Admin:Field:Bool', template: '@KachnitelAdmin/components/field/BoolField.html.twig')]
final class BoolField extends AbstractEditableField
{
    use DefaultActionTrait;

    #[LiveProp(writable: true, hydrateWith: 'hydrateCurrentValue', dehydrateWith: 'dehydrateCurrentValue')]
    public ?bool $currentValue = null;

    public function mount(object $entity, string $property): void
    {
        parent::mount($entity, $property);
        $this->currentValue = (bool) $this->readValue();
    }

    // ── Hydration ──────────────────────────────────────────────────────────────
    public function hydrateCurrentValue(mixed $data): bool
    {
        return (bool) $data;                                    // null → false, which is correct for bool
    }

    public function dehydrateCurrentValue(?bool $value): ?bool
    {
        return $value;
    }

    // ── LiveActions ────────────────────────────────────────────────────────────
    #[LiveAction]
    public function cancelEdit(): void
    {
        parent::cancelEdit();
        $this->currentValue = (bool) $this->readValue();
    }

    #[LiveAction]
    public function save(): void
    {
        $this->writeValue($this->currentValue ?? false);
        parent::save();
    }
}