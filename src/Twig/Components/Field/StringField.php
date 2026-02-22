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
 * Inline-editable field for string/text properties.
 *
 * ## $currentValue hydration
 *
 * dehydrateCurrentValue → sends ?string to the client as-is.
 * hydrateCurrentValue(null)    → re-reads from entity (triggered after cancelEdit sets null).
 * hydrateCurrentValue($string) → restores the typed value.
 *
 * cancelEdit() nulls $currentValue before delegating to parent, which refreshes the entity.
 * On the next hydration cycle hydrateCurrentValue(null) picks up the fresh entity value.
 * No explicit syncCurrentValueFromEntity method is needed.
 */
#[AsLiveComponent('K:Admin:Field:String', template: '@KachnitelAdmin/components/field/StringField.html.twig')]
final class StringField extends AbstractEditableField
{
    use DefaultActionTrait;

    #[LiveProp(writable: true, hydrateWith: 'hydrateCurrentValue', dehydrateWith: 'dehydrateCurrentValue')]
    public ?string $currentValue = null;

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
        $this->currentValue = $raw !== null ? (string) $raw : null;
    }

    // ── Hydration ──────────────────────────────────────────────────────────────

    /**
     * Called on every re-render to restore $currentValue from the dehydrated client state.
     *
     * Null means "uninitialized" — happens after cancelEdit() nulls the value.
     * In that case we re-read from the (already refreshed) entity.
     * $entityClass, $entityId, $property are guaranteed to be hydrated first
     * (they are declared in the parent class; PHP reflection yields parent props first).
     */
    public function hydrateCurrentValue(mixed $data): ?string
    {
        if ($data === null) {
            $raw = $this->readValue();
            return $raw !== null ? (string) $raw : null;
        }

        return (string) $data;
    }

    public function dehydrateCurrentValue(?string $value): ?string
    {
        return $value;
    }

    // ── LiveActions ────────────────────────────────────────────────────────────

    #[LiveAction]
    public function cancelEdit(): void
    {
        $this->currentValue = null; // hydrateCurrentValue will re-read entity on next cycle
        parent::cancelEdit();
    }

    #[LiveAction]
    public function save(): void
    {
        $this->writeValue($this->currentValue);
        parent::save();
    }

    // ── Template helpers ───────────────────────────────────────────────────────

    public function renderValue(): string
    {
        $value = $this->readValue();

        return ($value !== null && $value !== '') ? (string) $value : '—';
    }
}