<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\ValueObject;

/**
 * Data passed to batch action handlers via argument resolver.
 *
 * Immutable, type-safe parameter object passed to:
 *   1. LiveComponent actions: bulkActionName(BatchActionDto $dto)
 *   2. Route handlers: batchActionHandler(BatchActionDto $dto)
 *
 * Example:
 *
 *   public function bulkPublish(BatchActionDto $dto, PublishService $service): void
 *   {
 *       $service->publish($dto->getEntityIds());
 *   }
 */
final readonly class BatchActionDto
{
    /**
     * @param array<int|string> $entityIds Selected entity IDs
     */
    public function __construct(
        private string $name,
        private array $entityIds,
        private string $entityClass,
        private string $entityShortClass,
        private bool $allSelected = false,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    /** @return array<int|string> */
    public function getEntityIds(): array
    {
        return $this->entityIds;
    }

    public function getEntityClass(): string
    {
        return $this->entityClass;
    }

    public function getEntityShortClass(): string
    {
        return $this->entityShortClass;
    }

    public function isAllSelected(): bool
    {
        return $this->allSelected;
    }

    public function getCount(): int
    {
        return count($this->entityIds);
    }
}
