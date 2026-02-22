<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Components\Field;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Kachnitel\AdminBundle\Twig\Components\Field\Traits\AssociationFieldTrait;
use Kachnitel\AdminBundle\Twig\Components\Field\Traits\PropertyInfoTrait;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
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
    use DefaultActionTrait;
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
        $this->selectedIds = $this->idsFromCollection($this->readValue());
    }

    // ── Display helpers ────────────────────────────────────────────────────────

    /**
     * Resolve labels for all currently selected IDs for display in the edit UI.
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

        return array_map(function (int $id) use ($targetClass): array {
            $entity = $this->entityManager->find($targetClass, $id);

            return [
                'id'    => $id,
                'label' => $entity !== null ? $this->resolveLabel($entity) : "#{$id}",
            ];
        }, $this->selectedIds);
    }

    public function renderValue(): string
    {
        $collection = $this->readValue();

        if (!$collection instanceof Collection || $collection->isEmpty()) {
            return '—';
        }

        $count = $collection->count();

        return $count . ' ' . ($count === 1 ? 'item' : 'items');
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
        $this->selectedIds  = $this->idsFromCollection($this->readValue());
        $this->searchQuery  = '';
    }

    /**
     * Diff selectedIds against the persisted collection and call the entity's
     * adder/remover pair for each change. Uses ReflectionExtractor to resolve
     * the correct singularised method names (e.g. $categories → addCategory).
     *
     * @throws \RuntimeException when adder/remover cannot be resolved, or a related entity is missing
     */
    #[LiveAction]
    public function save(): void
    {
        if (!$this->canEdit()) {
            throw new \RuntimeException('Access denied for editing this field.');
        }

        $targetClass = $this->getTargetEntityClass();
        if ($targetClass === null) {
            throw new \RuntimeException(sprintf(
                '"%s::$%s" is not a recognised Doctrine association.',
                $this->entityClass,
                $this->property,
            ));
        }

        $collection = $this->readValue();
        if (!$collection instanceof Collection) {
            throw new \RuntimeException(sprintf(
                '"%s::$%s" did not return a Doctrine Collection.',
                $this->entityClass,
                $this->property,
            ));
        }

        $mutators   = $this->getCollectionMutators(); // throws clearly if not found
        $entity     = $this->getEntity();
        $existingIds = $this->idsFromCollection($collection);

        $idsToAdd    = array_diff($this->selectedIds, $existingIds);
        $idsToRemove = array_diff($existingIds, $this->selectedIds);

        foreach ($idsToAdd as $id) {
            $related = $this->entityManager->find($targetClass, $id);
            if ($related === null) {
                throw new \RuntimeException("Entity {$targetClass} with id {$id} not found.");
            }
            $entity->{$mutators['adder']}($related);
        }

        foreach ($idsToRemove as $id) {
            // Locate the entity object within the already-loaded collection
            $toRemove = null;
            foreach ($collection as $item) {
                if (method_exists($item, 'getId') && $item->getId() === $id) {
                    $toRemove = $item;
                    break;
                }
            }
            if ($toRemove !== null) {
                $entity->{$mutators['remover']}($toRemove);
            }
        }

        parent::save();
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

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
