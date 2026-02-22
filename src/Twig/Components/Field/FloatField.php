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
        $this->currentValue = $raw !== null ? (float) $raw : null;
    }

    // ── Hydration ──────────────────────────────────────────────────────────────

    public function hydrateCurrentValue(mixed $data): ?float
    {
        if ($data === null) {
            $raw = $this->readValue();
            return $raw !== null ? (float) $raw : null;
        }

        return (float) $data;
    }

    public function dehydrateCurrentValue(?float $value): ?float
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
        $this->writeValue($this->currentValue);
        parent::save();
    }

    // ── Template helpers ───────────────────────────────────────────────────────

    public function renderValue(): string
    {
        $value = $this->readValue();

        return $value !== null ? (string) (float) $value : '—';
    }
}