<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Components\Field;

use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Inline-editable field for float/decimal properties.
 *
 * hydrateCurrentValue(null) → re-reads from entity after cancelEdit.
 * hydrateCurrentValue($float) → restores the typed value.
 */
#[AsLiveComponent('K:Admin:Field:Float', template: '@KachnitelAdmin/components/field/FloatField.html.twig')]
final class FloatField extends AbstractEditableField
{
    use DefaultActionTrait;

    #[LiveProp(writable: true, hydrateWith: 'hydrateCurrentValue', dehydrateWith: 'dehydrateCurrentValue')]
    public ?float $currentValue = null;

    public function mount(object $entity, string $property): void
    {
        parent::mount($entity, $property);
        $raw = $this->readValue();
        $this->currentValue = $raw !== null ? (float) $raw : null;
    }

    // ── Hydration ──────────────────────────────────────────────────────────────
    public function hydrateCurrentValue(mixed $data): ?float
    {
        return $data !== null ? (float) $data : null;
    }

    public function dehydrateCurrentValue(?float $value): ?float
    {
        return $value;
    }

    // ── LiveActions ────────────────────────────────────────────────────────────
    #[LiveAction]
    public function cancelEdit(): void
    {
        parent::cancelEdit();
        $raw = $this->readValue();
        $this->currentValue = $raw !== null ? (float) $raw : null;
    }

    #[LiveAction]
    public function save(): void
    {
        $this->writeValue($this->currentValue);
        parent::save();
    }
}