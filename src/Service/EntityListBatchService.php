<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Kachnitel\DataSourceContracts\DataSourceInterface;
use Kachnitel\AdminBundle\DataSource\DoctrineDataSource;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\Security\ObjectAuthorizationChecker;
use Kachnitel\AdminBundle\Security\SkipsUnauthorizedEntitiesTrait;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Handles batch operations for entity lists.
 */
class EntityListBatchService
{
    use SkipsUnauthorizedEntitiesTrait;

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
     * the denied ones are left alone. Resolution and the grant/deny split
     * happen in two steps — resolveByIds() then
     * SkipsUnauthorizedEntitiesTrait::filterAuthorizedEntities() — the same
     * two-step shape ArchiveButton::execute() uses, so both batch actions
     * share one skip-on-deny implementation rather than each re-deriving it.
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

        /** @var EntityRepository<object> $repository */
        $repository = $this->em->getRepository($dataSource->getEntityClass());

        $resolved = $this->resolveByIds($repository, $selectedIds);
        $granted  = $this->filterAuthorizedEntities($this->objectAuthChecker, AdminEntityVoter::ADMIN_DELETE, $resolved);

        foreach ($granted as $entity) {
            $this->em->remove($entity);
        }

        $this->em->flush();

        return array_keys($granted);
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

    /**
     * Resolve a list of selected IDs to entities via repository->find(),
     * keyed by the ID they were resolved from. IDs that don't resolve to an
     * entity (already deleted, bad ID) are dropped from the result — this
     * method has always tolerated that, independent of authorization.
     *
     * @param EntityRepository<object> $repository
     * @param array<int|string> $ids
     * @return array<int|string, object>
     */
    private function resolveByIds(EntityRepository $repository, array $ids): array
    {
        $resolved = [];
        foreach ($ids as $id) {
            $entity = $repository->find($id);
            if ($entity !== null) {
                $resolved[$id] = $entity;
            }
        }

        return $resolved;
    }
}
