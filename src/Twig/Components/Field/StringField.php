<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Components\Field;

use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;

/**
 * Inline-editable field for string/text properties.
 *
 * ## $currentValue hydration
 *
 * dehydrateCurrentValue → sends ?string to the client as-is.
 * hydrateCurrentValue(null)    → re-reads from entity (triggered after cancelEdit sets null).
 * hydrateCurrentValue($string) → restores the typed value.
 *
 * cancelEdit() refreshes entity via parent, then re-reads the current property value.
 */
#[AsLiveComponent('K:Admin:Field:String', template: '@KachnitelAdmin/components/field/StringField.html.twig')]
final class StringField extends AbstractEditableField
{
    #[LiveProp(writable: true, hydrateWith: 'hydrateCurrentValue', dehydrateWith: 'dehydrateCurrentValue')]
    public ?string $currentValue = null;

    public function mount(object $entity, string $property): void
    {
        parent::mount($entity, $property);
        $raw = $this->readValue();
        $this->currentValue = $raw !== null ? (string) $raw : null;
    }

    // ── Hydration ──────────────────────────────────────────────────────────────

    /**
     * Called on every re-render to restore $currentValue from the dehydrated client state.
     */
    public function hydrateCurrentValue(mixed $data): ?string
    {
        return $data !== null ? (string) $data : null;
    }

    public function dehydrateCurrentValue(?string $value): ?string
    {
        return $value;
    }

    // ── LiveActions ────────────────────────────────────────────────────────────

    #[LiveAction]
    public function cancelEdit(): void
    {
        parent::cancelEdit();
        $raw = $this->readValue();
        $this->currentValue = $raw !== null ? (string) $raw : null;
    }

    // ── Template method ────────────────────────────────────────────────────────

    protected function persistEdit(): void
    {
        $this->writeValue($this->currentValue);
    }
}
