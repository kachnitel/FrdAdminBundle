<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Components\Field\Traits;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Kachnitel\AdminBundle\Service\Traits\AssociationFilterConfigTrait;
use Symfony\UX\LiveComponent\Attribute\LiveProp;

/**
 * Shared logic for association field components (RelationshipField, CollectionField).
 *
 * Composes AssociationFilterConfigTrait to reuse existing search field auto-detection
 * (priority list: name → label → title → id, with per-entity DEFAULT_SEARCH_FIELDS overrides).
 *
 * Requires the using class to provide:
 *   - $this->entityManager   (from AbstractEditableField)
 *   - $this->entityClass     (LiveProp on AbstractEditableField)
 *   - $this->property        (LiveProp on AbstractEditableField)
 *   - getTargetEntityClass() (from PropertyInfoTrait)
 *   - getEntity()            (from AbstractEditableField)
 */
trait AssociationFieldTrait
{
    use AssociationFilterConfigTrait;

    /**
     * Live search query. Bound to the search input in the template.
     * Triggers a re-render which calls getSearchResults().
     */
    #[LiveProp(writable: true)]
    public string $searchQuery = '';

    // ── AssociationFilterConfigTrait contract ──────────────────────────────────

    protected function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }

    // ── Target metadata ────────────────────────────────────────────────────────

    /**
     * @return ClassMetadata<object>
     * @throws \RuntimeException when property is not an association
     */
    private function getTargetMetadata(): ClassMetadata
    {
        $targetClass = $this->getTargetEntityClass();

        if ($targetClass === null) {
            throw new \RuntimeException(sprintf(
                '"%s::$%s" is not a Doctrine association.',
                $this->entityClass,
                $this->property,
            ));
        }

        return $this->entityManager->getClassMetadata($targetClass);
    }

    // ── Search ─────────────────────────────────────────────────────────────────

    /**
     * Run a live search against the target entity using auto-detected string fields.
     * Returns an empty array when the query is blank (no DB call).
     *
     * Results are capped at 20 and include {id, label} pairs for template rendering.
     *
     * @return array<array{id: int|string, label: string}>
     */
    public function getSearchResults(): array
    {
        if (trim($this->searchQuery) === '') {
            return [];
        }

        $targetClass    = $this->getTargetEntityClass();
        $targetMetadata = $this->getTargetMetadata();
        $targetName     = (new \ReflectionClass($targetClass))->getShortName();

        // Reuse AssociationFilterConfigTrait auto-detection (null = no ColumnFilter override)
        $searchFields = $this->resolveSearchFields($targetMetadata, $targetName, null);

        $qb   = $this->entityManager->getRepository($targetClass)->createQueryBuilder('e');
        $orX  = $qb->expr()->orX();

        foreach ($searchFields as $i => $field) {
            $param = 's_' . $i;
            if ($field === 'id') {
                // id is numeric — only include when query is numeric
                if (is_numeric($this->searchQuery)) {
                    $orX->add($qb->expr()->eq('e.id', ':' . $param));
                    $qb->setParameter($param, (int) $this->searchQuery);
                }
            } else {
                $orX->add($qb->expr()->like('e.' . $field, ':' . $param));
                $qb->setParameter($param, '%' . $this->searchQuery . '%');
            }
        }

        if ($orX->count() === 0) {
            return [];
        }

        /** @var object[] $results */
        $results = $qb->where($orX)->setMaxResults(20)->getQuery()->getResult();

        return array_map(fn(object $item): array => [
            'id'    => method_exists($item, 'getId') ? $item->getId() : 0,
            'label' => $this->resolveLabel($item),
        ], $results);
    }

    // ── Label resolution ───────────────────────────────────────────────────────

    /**
     * Resolve a human-readable label for a related entity.
     *
     * Resolution order:
     *   1. __toString() when available
     *   2. First DISPLAY_FIELD_PRIORITY field that exists on the entity
     *   3. ClassName #ID fallback
     *
     * choice_label is intentionally not supported — there are no component options.
     * Add __toString() or a named display field (name/label/title) to the related entity.
     */
    protected function resolveLabel(object $entity): string
    {
        if (method_exists($entity, '__toString')) {
            return (string) $entity;
        }

        // Mirror AssociationFilterConfigTrait::DISPLAY_FIELD_PRIORITY
        foreach (['name', 'label', 'title'] as $field) {
            if (property_exists($entity, $field)) {
                return (string) ($entity->$field ?? '');
            }
            $getter = 'get' . ucfirst($field);
            if (method_exists($entity, $getter)) {
                return (string) $entity->$getter();
            }
        }

        $shortName = (new \ReflectionClass($entity))->getShortName();
        if (method_exists($entity, 'getId')) {
            return $shortName . ' #' . $entity->getId();
        }

        return $shortName;
    }
}
