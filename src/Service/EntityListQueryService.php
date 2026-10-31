<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Kachnitel\AdminBundle\Attribute\ColumnFilter;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\Service\Traits\AssociationFilterTrait;
use Kachnitel\AdminBundle\Service\Traits\DateFilterTrait;
use Kachnitel\AdminBundle\Service\Traits\ScalarFilterTrait;

/**
 * Service responsible for building database queries for entity lists.
 *
 * Columns the current user may not see (#[ColumnPermission] ADMIN_SHOW denied) are not
 * queryable: global search skips them, filters on them are dropped and sorting by them
 * falls back to the identifier. `search`, `sortBy` and `columnFilters` are client-writable,
 * so hiding a column from the UI alone would still let its values be inferred from the
 * results. All of that is decided in deniedColumns(); this is the one place every list
 * query passes through.
 */
class EntityListQueryService
{
    use ScalarFilterTrait;
    use DateFilterTrait;
    use AssociationFilterTrait;

    public function __construct(
        private EntityManagerInterface $em,
        private ColumnPermissionService $columnPermissions,
    ) {}

    /**
     * Get filtered, sorted, and paginated entities.
     *
     * @param class-string $entityClass Full entity class name
     * @param array<string, mixed> $columnFilters
     * @param array<string, array<string, mixed>> $filterMetadata
     * @param string|null $archiveDqlCondition Pre-built DQL WHERE fragment from ArchiveService
     *                                         (e.g. 'e.deletedAt IS NULL' or 'e.archived = false')
     * @param string|null $scopeDqlCondition   Pre-built DQL WHERE fragment from CollectionScopeResolver
     *                                         restricting the list to one owner's collection
     * @param array<string, mixed> $scopeParameters Bound parameters for $scopeDqlCondition
     * @return array{entities: array<object>, total: int, page: int}
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function getEntities(
        string $entityClass,
        ?string $repositoryMethod,
        string $search,
        array $columnFilters,
        array $filterMetadata,
        string $sortBy,
        string $sortDirection,
        int $page,
        int $itemsPerPage,
        ?string $archiveDqlCondition = null,
        ?string $scopeDqlCondition = null,
        array $scopeParameters = [],
    ): array {
        $qb = $this->buildQuery(
            $entityClass,
            $repositoryMethod,
            $search,
            $columnFilters,
            $filterMetadata,
            $sortBy,
            $sortDirection,
            $archiveDqlCondition,
            $scopeDqlCondition,
            $scopeParameters,
        );

        $page = max(1, $page);

        $qb->setFirstResult(($page - 1) * $itemsPerPage)
            ->setMaxResults($itemsPerPage);

        $paginator = new Paginator($qb, fetchJoinCollection: true);
        $total = $paginator->count();

        $totalPages = $total > 0 ? (int) ceil($total / $itemsPerPage) : 0;
        if ($totalPages > 0 && $page > $totalPages) {
            $page = $totalPages;
            $qb->setFirstResult(($page - 1) * $itemsPerPage);
            $paginator = new Paginator($qb, fetchJoinCollection: true);
        }

        /** @var array<object> $entities */
        $entities = iterator_to_array($paginator->getIterator());

        return [
            'entities' => $entities,
            'total' => $total,
            'page' => $page,
        ];
    }

    /**
     * Build the base query with filters and sorting.
     *
     * @param class-string $entityClass
     * @param array<string, mixed> $columnFilters
     * @param array<string, array<string, mixed>> $filterMetadata
     * @param string|null $archiveDqlCondition Pre-built DQL WHERE fragment from ArchiveService
     * @param string|null $scopeDqlCondition   Pre-built DQL WHERE fragment from CollectionScopeResolver
     * @param array<string, mixed> $scopeParameters Bound parameters for $scopeDqlCondition
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function buildQuery(
        string $entityClass,
        ?string $repositoryMethod,
        string $search,
        array $columnFilters,
        array $filterMetadata,
        string $sortBy,
        string $sortDirection,
        ?string $archiveDqlCondition = null,
        ?string $scopeDqlCondition = null,
        array $scopeParameters = [],
    ): QueryBuilder {
        /** @var \Doctrine\ORM\EntityRepository<object> $repository */
        $repository = $this->em->getRepository($entityClass);

        if ($repositoryMethod && method_exists($repository, $repositoryMethod)) {
            /** @var QueryBuilder $qb */
            $qb = $repository->{$repositoryMethod}();
        } else {
            $qb = $repository->createQueryBuilder('e');
        }

        $denied = $this->deniedColumns($entityClass);

        if ($search) {
            $this->applyGlobalSearch($qb, $entityClass, $search);
        }

        foreach (array_diff_key($columnFilters, array_flip($denied)) as $column => $value) {
            if (isset($filterMetadata[$column]) && $value !== null && $value !== '') {
                $this->applyColumnFilter($qb, $column, $value, $filterMetadata[$column]);
            }
        }

        $this->applyRestrictions($qb, $archiveDqlCondition, $scopeDqlCondition, $scopeParameters);

        $qb->orderBy('e.' . $this->sortField($entityClass, $sortBy, $denied), $sortDirection);

        return $qb;
    }

    /**
     * Apply pre-built archive and collection-scope restrictions to the list query.
     *
     * @param array<string, mixed> $scopeParameters
     */
    private function applyRestrictions(
        QueryBuilder $qb,
        ?string $archiveDqlCondition,
        ?string $scopeDqlCondition,
        array $scopeParameters,
    ): void {
        if ($archiveDqlCondition !== null) {
            $qb->andWhere($archiveDqlCondition);
        }

        if ($scopeDqlCondition === null) {
            return;
        }

        $qb->andWhere($scopeDqlCondition);
        foreach ($scopeParameters as $name => $value) {
            $qb->setParameter($name, $value);
        }
    }

    /**
     * Return the names of entity fields included in global search.
     *
     * Fields the user may not see are left out, so the search (and any help text built
     * from this list) never covers them.
     *
     * @param class-string $entityClass
     * @return array<string>
     */
    public function getSearchableFieldNames(string $entityClass): array
    {
        $metadata = $this->em->getClassMetadata($entityClass);
        $denied = $this->deniedColumns($entityClass);
        $fields = [];

        foreach ($metadata->getFieldNames() as $field) {
            $type = $metadata->getTypeOfField($field);
            if (in_array($type, ['string', 'text'], true) && !in_array($field, $denied, true)) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /**
     * Columns the current user may not see (ADMIN_SHOW denied): the single policy deciding
     * what the list query refuses to search, filter or sort by.
     *
     * @param class-string $entityClass
     * @return list<string>
     */
    private function deniedColumns(string $entityClass): array
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
    private function restrictAssociationSearchFields(array $metadata): ?array
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

    /**
     * The field to ORDER BY: a denied column would reveal its ordering, so it is replaced
     * by the identifier.
     *
     * @param class-string $entityClass
     * @param list<string> $denied
     */
    private function sortField(string $entityClass, string $sortBy, array $denied): string
    {
        // Compare the root segment so a dotted path ("salary.x") can't sidestep the check.
        if (!in_array(explode('.', $sortBy, 2)[0], $denied, true)) {
            return $sortBy;
        }

        return $this->em->getClassMetadata($entityClass)->getIdentifierFieldNames()[0] ?? 'id';
    }

    /**
     * @param class-string $entityClass
     */
    private function applyGlobalSearch(QueryBuilder $qb, string $entityClass, string $search): void
    {
        $searchableFields = $this->getSearchableFieldNames($entityClass);

        if (empty($searchableFields)) {
            return;
        }

        $orX = $qb->expr()->orX();
        foreach ($searchableFields as $field) {
            $orX->add($qb->expr()->like('e.' . $field, ':globalSearch'));
        }

        $qb->andWhere($orX)
            ->setParameter('globalSearch', '%' . $search . '%');
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function applyColumnFilter(QueryBuilder $qb, string $column, mixed $value, array $metadata): void
    {
        $metadata = $this->restrictAssociationSearchFields($metadata);
        if ($metadata === null) {
            return;
        }

        $type = $metadata['type'];

        switch ($type) {
            case ColumnFilter::TYPE_TEXT:
                $this->applyTextFilter($qb, $column, $value, $metadata);
                break;
            case ColumnFilter::TYPE_NUMBER:
            case ColumnFilter::TYPE_BOOLEAN:
            case ColumnFilter::TYPE_ENUM:
                $this->applySimpleFilter($qb, $column, $value, $metadata);
                break;
            case ColumnFilter::TYPE_DATE:
                $this->applyDateFilter($qb, $column, $value, $metadata);
                break;
            case ColumnFilter::TYPE_DATERANGE:
                $this->applyDateRangeFilter($qb, $column, $value, $metadata);
                break;
            case ColumnFilter::TYPE_RELATION:
                $this->applyRelationFilter($qb, $column, $value, $metadata);
                break;
            case ColumnFilter::TYPE_COLLECTION:
                $this->applyCollectionFilter($qb, $column, $value, $metadata);
                break;
        }
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array{0: string, 1: string}
     */
    protected function getFilterContext(string $column, array $metadata): array
    {
        $operator = is_string($metadata['operator'] ?? null) ? $metadata['operator'] : '=';
        $paramName = 'filter_' . str_replace('.', '_', $column);

        return [$operator, $paramName];
    }
}
