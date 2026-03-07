<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Components\Field;

use Kachnitel\AdminBundle\Twig\Components\Field\Traits\AssociationFieldTrait;
use Kachnitel\AdminBundle\Twig\Components\Field\Traits\PropertyInfoTrait;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

/**
 * Inline-editable field for ManyToOne and OneToOne relationships.
 *
 * Stores only the related entity's identifier as a LiveProp (not the full object)
 * to avoid serialization issues across Live Component re-renders.
 *
 * Search field auto-detection follows the same priority used by AssociationFilterConfigTrait:
 * DEFAULT_SEARCH_FIELDS → name → label → title → id.
 * Add __toString() or a priority-listed field to the target entity for a better label.
 */
#[AsLiveComponent('K:Admin:Field:Relationship', template: '@KachnitelAdmin/components/field/RelationshipField.html.twig')]
final class RelationshipField extends AbstractEditableField
{
    use PropertyInfoTrait;
    use AssociationFieldTrait;

    /** ID of the currently selected related entity, or null for an empty relationship. */
    #[LiveProp(writable: true)]
    public ?int $selectedId = null;

    public function mount(object $entity, string $property): void
    {
        parent::mount($entity, $property);

        $value = $this->readValue();
        $this->selectedId = ($value !== null && method_exists($value, 'getId'))
            ? $value->getId()
            : null;
    }

    // ── Display helpers ────────────────────────────────────────────────────────

    /**
     * Label for the currently selected entity (for the edit-mode selected pill).
     * Queries DB only when a selection exists; returns null on empty relationship.
     */
    #[ExposeInTemplate]
    public function getSelectedLabel(): ?string
    {
        if ($this->selectedId === null) {
            return null;
        }

        $targetClass = $this->getTargetEntityClass();
        if ($targetClass === null) {
            return "#{$this->selectedId}";
        }

        $entity = $this->entityManager->find($targetClass, $this->selectedId);

        return $entity !== null ? $this->resolveLabel($entity) : "#{$this->selectedId}";
    }

    // ── LiveActions ────────────────────────────────────────────────────────────

    /**
     * Select a related entity from the search results dropdown.
     * Clears the search query so the dropdown collapses on re-render.
     */
    #[LiveAction]
    public function select(#[LiveArg] int $id): void
    {
        $this->selectedId  = $id;
        $this->searchQuery = '';
    }

    /**
     * Clear the current selection (set relationship to null).
     */
    #[LiveAction]
    public function clear(): void
    {
        $this->selectedId  = null;
        $this->searchQuery = '';
    }

    #[LiveAction]
    public function cancelEdit(): void
    {
        parent::cancelEdit(); // refreshes entity, clears resolvedEntity cache

        // Re-read after refresh so selectedId reflects the persisted state
        $value            = $this->readValue();
        $this->selectedId = ($value !== null && method_exists($value, 'getId'))
            ? $value->getId()
            : null;
        $this->searchQuery = '';
    }

    // ── Template method ────────────────────────────────────────────────────────

    /**
     * Resolve the target entity from $selectedId and write it to the property.
     * Called only after canEdit() passes in the base save() method.
     *
     * @throws \RuntimeException when the property is not a recognised Doctrine association
     *                           or when the selected entity cannot be found
     */
    protected function persistEdit(): void
    {
        $newValue = null;

        if ($this->selectedId !== null) {
            $targetClass = $this->getTargetEntityClass();

            if ($targetClass === null) {
                throw new \RuntimeException(sprintf(
                    '"%s::$%s" is not a recognised Doctrine association.',
                    $this->entityClass,
                    $this->property,
                ));
            }

            $newValue = $this->entityManager->find($targetClass, $this->selectedId);

            if ($newValue === null) {
                throw new \RuntimeException(
                    "Entity {$targetClass} with id {$this->selectedId} not found."
                );
            }
        }

        $this->writeValue($newValue);
    }
}
