<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Components\Field;

use Doctrine\Common\Collections\Collection;
use Kachnitel\AdminBundle\Twig\Components\Field\Traits\AssociationFieldTrait;
use Kachnitel\AdminBundle\Twig\Components\Field\Traits\PropertyInfoTrait;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

/**
 * Inline-editable field for collection associations (OneToMany, ManyToMany).
 *
 * Stores selected entity IDs as an array LiveProp. On save, resolves the adder/remover
 * method pair via ReflectionExtractor (respects Symfony's EnglishInflector, no naive rtrim).
 *
 * Search field auto-detection mirrors AssociationFilterConfigTrait:
 * DEFAULT_SEARCH_FIELDS → name → label → title → id.
 *
 * @see PropertyInfoTrait::getCollectionMutators() for adder/remover resolution details.
 */
#[AsLiveComponent('K:Admin:Field:Collection', template: '@KachnitelAdmin/components/field/CollectionField.html.twig')]
class CollectionField extends AbstractEditableField
{
    use PropertyInfoTrait;
    use AssociationFieldTrait;

    /**
     * IDs of the entities currently selected in the edit UI.
     * Populated from the live collection on mount; diffed against the persisted
     * collection on save to drive targeted add/remove calls.
     *
     * @var list<int>
     */
    #[LiveProp(writable: true)]
    public array $selectedIds = [];

    public function mount(object $entity, string $property): void
    {
        parent::mount($entity, $property);
        $this->selectedIds = $this->idsFromCollection($this->readValue());
    }

    // ── Display helpers ────────────────────────────────────────────────────────

    /**
     * Resolve labels for all currently selected IDs for display in the edit UI.
     *
     * Loads all selected entities in a single IN query rather than one find() per ID,
     * avoiding an N+1 query on every re-render when the selection is large.
     *
     * @return array<array{id: int, label: string}>
     */
    #[ExposeInTemplate]
    public function getSelectedItems(): array
    {
        if ($this->selectedIds === []) {
            return [];
        }

        $targetClass = $this->getTargetEntityClass();
        if ($targetClass === null) {
            return [];
        }

        /** @var object[] $entities */
        $entities = $this->entityManager
            ->getRepository($targetClass)
            ->createQueryBuilder('e')
            ->where('e.id IN (:ids)')
            ->setParameter('ids', $this->selectedIds)
            ->getQuery()
            ->getResult();

        // Index by ID for O(1) lookup when building the ordered result
        $entityMap = [];
        foreach ($entities as $entity) {
            if (method_exists($entity, 'getId')) {
                $id = $entity->getId();
                if (is_int($id)) {
                    $entityMap[$id] = $entity;
                }
            }
        }

        return array_map(function (int $id) use ($entityMap): array {
            return [
                'id'    => $id,
                'label' => isset($entityMap[$id]) ? $this->resolveLabel($entityMap[$id]) : "#{$id}",
            ];
        }, $this->selectedIds);
    }

    // ── LiveActions ────────────────────────────────────────────────────────────

    /**
     * Add an entity from the search results to the pending selection.
     * Prevents duplicates; clears the search query to collapse the dropdown.
     */
    #[LiveAction]
    public function addItem(#[LiveArg] int $id): void
    {
        if (!in_array($id, $this->selectedIds, true)) {
            $this->selectedIds[] = $id;
        }
        $this->searchQuery = '';
    }

    /**
     * Remove an entity from the pending selection (does not flush).
     */
    #[LiveAction]
    public function removeItem(#[LiveArg] int $id): void
    {
        $this->selectedIds = array_values(
            array_filter($this->selectedIds, fn(int $i): bool => $i !== $id)
        );
    }

    #[LiveAction]
    public function cancelEdit(): void
    {
        parent::cancelEdit(); // refreshes entity, clears resolvedEntity cache

        // Re-read collection state from the refreshed entity
        $this->selectedIds = $this->idsFromCollection($this->readValue());
        $this->searchQuery = '';
    }

    // ── Template method ────────────────────────────────────────────────────────

    /**
     * Diff selectedIds against the persisted collection and call the entity's
     * adder/remover pair for each change. Uses ReflectionExtractor to resolve
     * the correct singularised method names (e.g. $categories → addCategory).
     *
     * Called only after canEdit() passes in the base save() method.
     *
     * @throws \RuntimeException when adder/remover cannot be resolved, or a related entity is missing
     */
    protected function persistEdit(): void
    {
        $targetClass = $this->requireTargetEntityClass();
        $collection  = $this->requireCollection();
        $mutators    = $this->getCollectionMutators(); // throws clearly if not found
        $entity      = $this->getEntity();
        $existingIds = $this->idsFromCollection($collection);

        $this->applyAdditions($entity, $targetClass, array_diff($this->selectedIds, $existingIds), $mutators['adder']);
        $this->applyRemovals($entity, $collection, array_diff($existingIds, $this->selectedIds), $mutators['remover']);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Return the target entity class for the current association property.
     *
     * @return class-string
     * @throws \RuntimeException when the property is not a recognised Doctrine association
     */
    private function requireTargetEntityClass(): string
    {
        $targetClass = $this->getTargetEntityClass();

        if ($targetClass === null) {
            throw new \RuntimeException(sprintf(
                '"%s::$%s" is not a recognised Doctrine association.',
                $this->entityClass,
                $this->property,
            ));
        }

        return $targetClass;
    }

    /**
     * Return the current property value asserted to be a Doctrine Collection.
     *
     * @return Collection<int, object>
     * @throws \RuntimeException when the property value is not a Collection
     */
    private function requireCollection(): Collection
    {
        $collection = $this->readValue();

        if (!$collection instanceof Collection) {
            throw new \RuntimeException(sprintf(
                '"%s::$%s" did not return a Doctrine Collection.',
                $this->entityClass,
                $this->property,
            ));
        }

        return $collection;
    }

    /**
     * Resolve and add each entity for the given IDs to the owning entity.
     *
     * @param class-string   $targetClass
     * @param array<int>     $idsToAdd
     * @throws \RuntimeException when a related entity cannot be found
     */
    private function applyAdditions(object $entity, string $targetClass, array $idsToAdd, string $adder): void
    {
        foreach ($idsToAdd as $id) {
            $related = $this->entityManager->find($targetClass, $id);
            if ($related === null) {
                throw new \RuntimeException("Entity {$targetClass} with id {$id} not found.");
            }
            $entity->{$adder}($related);
        }
    }

    /**
     * Locate each entity for the given IDs in the loaded collection and remove it.
     * IDs that are not found in the collection are silently skipped.
     *
     * @param Collection<int, object> $collection
     * @param array<int>              $idsToRemove
     */
    private function applyRemovals(object $entity, Collection $collection, array $idsToRemove, string $remover): void
    {
        foreach ($idsToRemove as $id) {
            $toRemove = $this->findInCollection($collection, $id);
            if ($toRemove !== null) {
                $entity->{$remover}($toRemove);
            }
        }
    }

    /**
     * Find an entity by integer ID within an already-loaded Doctrine Collection.
     * Returns null when no matching item is found.
     *
     * @param Collection<int, object> $collection
     */
    private function findInCollection(Collection $collection, int $id): ?object
    {
        foreach ($collection as $item) {
            if (method_exists($item, 'getId') && $item->getId() === $id) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Extract integer IDs from a Doctrine Collection (or any iterable).
     * Non-Collection values return an empty array.
     *
     * @return list<int>
     */
    private function idsFromCollection(mixed $collection): array
    {
        if (!$collection instanceof Collection) {
            return [];
        }

        $ids = [];
        foreach ($collection as $item) {
            if (method_exists($item, 'getId')) {
                $id = $item->getId();
                if (is_int($id)) {
                    $ids[] = $id;
                }
            }
        }

        return $ids;
    }
}
