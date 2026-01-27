<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Fixtures;

use Doctrine\ORM\EntityManagerInterface;
use Kachnitel\AdminBundle\DataSource\ColumnMetadata;
use Kachnitel\AdminBundle\DataSource\DataSourceInterface;
use Kachnitel\AdminBundle\DataSource\FilterMetadata;
use Kachnitel\AdminBundle\DataSource\PaginatedResult;

/**
 * Test data source that wraps TestEntity to test datasource-specific template overrides.
 *
 * This datasource exposes TestEntity data but uses the datasource template resolution
 * hierarchy instead of the entity-based hierarchy.
 */
class TestEntityDataSource implements DataSourceInterface
{
    private ?EntityManagerInterface $entityManager = null;

    public function setEntityManager(EntityManagerInterface $entityManager): void
    {
        $this->entityManager = $entityManager;
    }

    public function getIdentifier(): string
    {
        return 'data-source';
    }

    public function getLabel(): string
    {
        return 'Test Data Source';
    }

    public function getIcon(): ?string
    {
        return 'science';
    }

    public function getColumns(): array
    {
        return [
            'id' => ColumnMetadata::create('id', 'ID', 'integer'),
            'name' => ColumnMetadata::create('name', 'Name', 'string'),
            'active' => ColumnMetadata::create('active', 'Active', 'boolean'),
        ];
    }

    public function getFilters(): array
    {
        return [
            'name' => FilterMetadata::text('name', 'Name', 'Search by name...'),
            'active' => FilterMetadata::boolean('active', 'Active'),
        ];
    }

    public function getDefaultSortBy(): string
    {
        return 'id';
    }

    public function getDefaultSortDirection(): string
    {
        return 'DESC';
    }

    public function getDefaultItemsPerPage(): int
    {
        return 15;
    }

    public function query(
        string $search,
        array $filters,
        string $sortBy,
        string $sortDirection,
        int $page,
        int $itemsPerPage
    ): PaginatedResult {
        if ($this->entityManager === null) {
            return new PaginatedResult(
                items: [],
                totalItems: 0,
                currentPage: $page,
                itemsPerPage: $itemsPerPage,
            );
        }

        $qb = $this->entityManager->createQueryBuilder()
            ->select('e')
            ->from(TestEntity::class, 'e');

        // Apply search filter
        if ($search !== '') {
            $qb->andWhere('LOWER(e.name) LIKE LOWER(:search)')
                ->setParameter('search', '%' . $search . '%');
        }

        // Apply name filter
        if (!empty($filters['name'])) {
            $qb->andWhere('LOWER(e.name) LIKE LOWER(:nameFilter)')
                ->setParameter('nameFilter', '%' . $filters['name'] . '%');
        }

        // Apply active filter
        if (isset($filters['active']) && $filters['active'] !== '') {
            $qb->andWhere('e.active = :active')
                ->setParameter('active', $filters['active'] === '1');
        }

        // Get total count
        $countQb = clone $qb;
        $countQb->select('COUNT(e.id)');
        $total = (int) $countQb->getQuery()->getSingleScalarResult();

        // Apply sorting
        if (in_array($sortBy, ['id', 'name', 'active'], true)) {
            $qb->orderBy('e.' . $sortBy, $sortDirection);
        }

        // Apply pagination
        $qb->setFirstResult(($page - 1) * $itemsPerPage)
            ->setMaxResults($itemsPerPage);

        return new PaginatedResult(
            items: $qb->getQuery()->getResult(),
            totalItems: $total,
            currentPage: $page,
            itemsPerPage: $itemsPerPage,
        );
    }

    public function find(string|int $id): ?object
    {
        if ($this->entityManager === null) {
            return null;
        }

        return $this->entityManager->find(TestEntity::class, $id);
    }

    public function supportsAction(string $action): bool
    {
        return in_array($action, ['index', 'show'], true);
    }

    public function getIdField(): string
    {
        return 'id';
    }

    public function getItemId(object $item): string|int
    {
        /** @var TestEntity $item */
        return $item->getId() ?? 0;
    }

    public function getItemValue(object $item, string $field): mixed
    {
        /** @var TestEntity $item */
        return match ($field) {
            'id' => $item->getId(),
            'name' => $item->getName(),
            'active' => $item->isActive(),
            default => null,
        };
    }
}
