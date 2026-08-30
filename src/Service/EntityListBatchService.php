<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Kachnitel\DataSourceContracts\DataSourceInterface;
use Kachnitel\AdminBundle\DataSource\DoctrineDataSource;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\Security\ObjectAuthorizationChecker;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Handles batch operations for entity lists.
 */
class EntityListBatchService
{
    public function __construct(
        private EntityManagerInterface $em,
        private EntityListPermissionService $permissionService,
        private ObjectAuthorizationChecker $objectAuthChecker,
    ) {}

    /**
     * Delete the selected entities the current user is authorized to delete.
     *
     * An ID that doesn't resolve to an entity (already deleted, bad ID) is
     * silently skipped — this method has always tolerated that. Object-level
     * authorization denial is now skipped the same way, rather than aborting
     * or throwing for the whole batch: the granted rows are still deleted,
     * the denied ones are left alone.
     *
     * The caller (DeleteButton) passes the returned IDs — not the original
     * $selectedIds — on to completeAction(), so a denied row stays selected
     * and stays in the list after the refresh, rather than silently
     * vanishing from the "deleted" count with no signal that it didn't go
     * through.
     *
     * @param array<int|string> $selectedIds IDs to delete
     * @return array<int|string> IDs actually removed
     * @throws AccessDeniedException
     */
    public function batchDelete(
        array $selectedIds,
        DataSourceInterface $dataSource,
        string $entityClass,
        string $entityShortClass,
    ): array {
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
            return [];
        }

        /** @var \Doctrine\ORM\EntityRepository<object> $repository */
        $repository = $this->em->getRepository($dataSource->getEntityClass());

        $removedIds = [];
        foreach ($selectedIds as $id) {
            $entity = $repository->find($id);
            if ($entity === null) {
                continue;
            }

            if (!$this->objectAuthChecker->isGranted(AdminEntityVoter::ADMIN_DELETE, $entity)) {
                continue;
            }

            $this->em->remove($entity);
            $removedIds[] = $id;
        }

        $this->em->flush();

        return $removedIds;
    }

    /**
     * Get entity IDs from a list of entities.
     *
     * @param array<object> $entities
     * @return array<int|string>
     */
    public function getEntityIds(
        array $entities,
        DataSourceInterface $dataSource,
    ): array {
        return array_map(fn(object $entity): string|int => $dataSource->getItemId($entity), $entities);
    }
}
