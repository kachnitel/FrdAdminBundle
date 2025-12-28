<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Kachnitel\AdminBundle\DataSource\DataSourceInterface;
use Kachnitel\AdminBundle\DataSource\DoctrineDataSource;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Handles batch operations for entity lists.
 *
 * Extracted from EntityList to reduce component complexity and coupling.
 */
class EntityListBatchService
{
    public function __construct(
        private EntityManagerInterface $em,
        private EntityListPermissionService $permissionService,
    ) {}

    /**
     * Delete selected entities.
     *
     * @param array<int|string> $selectedIds IDs to delete
     * @param DataSourceInterface $dataSource Current data source
     * @param string $entityClass Entity class (used for permission check)
     * @param string $entityShortClass Entity short class (used for permission check)
     * @throws AccessDeniedException
     */
    public function batchDelete(
        array $selectedIds,
        DataSourceInterface $dataSource,
        string $entityClass,
        string $entityShortClass,
    ): void {
        if (!$dataSource->supportsAction('batch_delete')) {
            throw new AccessDeniedException('Batch delete not supported for this data source.');
        }

        if (!$this->permissionService->canBatchDelete($entityClass, $entityShortClass)) {
            throw new AccessDeniedException('Batch delete not allowed for this entity.');
        }

        if (!($dataSource instanceof DoctrineDataSource)) {
            throw new AccessDeniedException('Batch delete only supported for Doctrine entities.');
        }

        if (empty($selectedIds)) {
            return;
        }

        $repository = $this->em->getRepository($dataSource->getEntityClass());

        foreach ($selectedIds as $id) {
            $entity = $repository->find($id);
            if ($entity !== null) {
                $this->em->remove($entity);
            }
        }

        $this->em->flush();
    }

    /**
     * Get entity IDs from a list of entities.
     *
     * @param array<object> $entities
     * @param DataSourceInterface $dataSource
     * @return array<int|string>
     */
    public function getEntityIds(
        array $entities,
        DataSourceInterface $dataSource,
    ): array {
        return array_map(fn($entity) => $dataSource->getItemId($entity), $entities);
    }
}
