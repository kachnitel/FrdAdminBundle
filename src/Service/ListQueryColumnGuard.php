<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;

/**
 * Keeps columns the current user may not see (#[ColumnPermission] ADMIN_SHOW denied) out of
 * the list query.
 *
 * `search`, `sortBy` and `columnFilters` are client-writable, so hiding a column from the UI
 * alone would still let its values be inferred from the results. This is the single policy
 * EntityListQueryService consults: global search skips denied columns, filters on them are
 * dropped, sorting by them falls back to the identifier, and relation/collection filters are
 * checked against the TARGET entity's own permissions.
 */
class ListQueryColumnGuard
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ColumnPermissionService $columnPermissions,
    ) {}

    /**
     * Columns of $entityClass that are ADMIN_SHOW denied for the current user.
     *
     * @param class-string $entityClass
     * @return list<string>
     */
    public function deniedColumns(string $entityClass): array
    {
        return array_values($this->columnPermissions->getDeniedColumnsForAction(
            $entityClass,
            AdminEntityVoter::ADMIN_SHOW,
        ));
    }

    /**
     * Relation and collection filters search fields of the TARGET entity, so those fields
     * are checked against the target class's own #[ColumnPermission] (otherwise filtering
     * Order.customer by an email would reveal whether that email exists).
     *
     * Returns the metadata with only permitted search fields, or null when every configured
     * field was denied, so the filter is dropped instead of falling back to another field.
     *
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>|null
     */
    public function restrictAssociationSearchFields(array $metadata): ?array
    {
        $targetClass = $metadata['targetClass'] ?? null;
        $fields = $metadata['searchFields'] ?? [];

        if (!is_string($targetClass) || !is_array($fields) || $fields === []) {
            return $metadata;
        }

        /** @var class-string $targetClass */

        $permitted = array_values(array_filter(
            $fields,
            fn (mixed $field): bool => is_string($field) && $this->canSearchPath($targetClass, $field),
        ));

        return $permitted === [] ? null : ['searchFields' => $permitted] + $metadata;
    }

    /**
     * The field to ORDER BY: a denied column would reveal its ordering, so it is replaced
     * by the identifier.
     *
     * @param class-string $entityClass
     * @param list<string> $denied
     */
    public function sortField(string $entityClass, string $sortBy, array $denied): string
    {
        // Compare the root segment so a dotted path ("salary.x") can't sidestep the check.
        if (!in_array(explode('.', $sortBy, 2)[0], $denied, true)) {
            return $sortBy;
        }

        return $this->em->getClassMetadata($entityClass)->getIdentifierFieldNames()[0] ?? 'id';
    }

    /**
     * Whether every segment of a (dotted) search path is visible to the user, walking
     * through Doctrine associations so each segment is checked against its own class.
     *
     * @param class-string $class
     */
    private function canSearchPath(string $class, string $path): bool
    {
        foreach (explode('.', $path) as $segment) {
            if (!$this->columnPermissions->canPerformAction($class, $segment, AdminEntityVoter::ADMIN_SHOW)) {
                return false;
            }

            $metadata = $this->em->getClassMetadata($class);
            if (!$metadata->hasAssociation($segment)) {
                return true; // plain or embedded field: nothing further to walk
            }

            $class = $metadata->getAssociationTargetClass($segment);
        }

        return true;
    }
}
