<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Components\AdminAction;

use Kachnitel\AdminBundle\Archive\ArchiveEntityService;
use Kachnitel\AdminBundle\Archive\ArchiveService;
use Kachnitel\AdminBundle\BatchAction\BatchActionComponentInterface;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\Security\ObjectAuthorizationChecker;
use Kachnitel\AdminBundle\Security\SkipsUnauthorizedEntitiesTrait;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Batch archive button rendered in the entity list batch actions bar.
 *
 * Registered automatically for entities that have archive configured, via
 * ArchiveBatchActionProvider. Requires ADMIN_ARCHIVE voter attribute.
 *
 * On execute():
 *   1. Checks ADMIN_ARCHIVE permission (AccessDeniedException on denial)
 *   2. Resolves the ArchiveConfig for the entity
 *   3. Loads each entity by ID, then runs the resolved set through
 *      SkipsUnauthorizedEntitiesTrait::filterAuthorizedEntities() — the
 *      same skip-on-deny helper EntityListBatchService::batchDelete()
 *      uses — before calling ArchiveEntityService::archive() on what remains
 *   4. Emits 'admin:action:completed' with the affected IDs so EntityList
 *      removes them from the selection and refreshes the query — a denied
 *      row is never added to $affected, so it stays selected/visible
 *      rather than being silently reported as archived
 *
 * ArchiveEntityService handles the actual field mutation (boolean → true,
 * datetime → now) and flushes; see its docblock for supported field types.
 * @see \Kachnitel\AdminBundle\Tests\Twig\Components\AdminAction\ArchiveButtonTest
 */
#[AsLiveComponent('K:Admin:Action:Archive', template: '@KachnitelAdmin/components/AdminAction/ArchiveButton.html.twig')]
class ArchiveButton implements BatchActionComponentInterface
{
    use DefaultActionTrait;
    use BatchActionTrait;
    use SkipsUnauthorizedEntitiesTrait;

    public function __construct(
        private readonly ArchiveService $archiveService,
        private readonly ArchiveEntityService $archiveEntityService,
        private readonly EntityManagerInterface $em,
        private readonly AuthorizationCheckerInterface $authChecker,
        private readonly ObjectAuthorizationChecker $objectAuthChecker,
    ) {}

    #[LiveAction]
    public function execute(): void
    {
        if (empty($this->selectedIds)) {
            return;
        }

        if (!$this->authChecker->isGranted(AdminEntityVoter::ADMIN_ARCHIVE, $this->entityShortClass)) {
            throw new AccessDeniedException(
                sprintf('Access denied: ADMIN_ARCHIVE on %s.', $this->entityShortClass)
            );
        }

        /** @var class-string $entityClass */
        $entityClass = $this->entityClass;
        $config = $this->archiveService->resolveConfig($entityClass);

        if ($config === null) {
            throw new \RuntimeException(
                sprintf('Archive is not configured for %s.', $this->entityShortClass)
            );
        }

        /** @var EntityRepository<object> $repository */
        $repository = $this->em->getRepository($entityClass);

        $resolved = $this->resolveByIds($repository, $this->selectedIds);
        $granted  = $this->filterAuthorizedEntities($this->objectAuthChecker, AdminEntityVoter::ADMIN_ARCHIVE, $resolved);

        foreach ($granted as $entity) {
            $this->archiveEntityService->archive($entity, $config);
        }

        $this->completeAction('archive', array_keys($granted));
    }

    /**
     * Resolve a list of selected IDs to entities via repository->find(),
     * keyed by the ID they were resolved from. IDs that don't resolve to an
     * entity are dropped from the result — mirrors
     * EntityListBatchService::resolveByIds()'s same tolerance.
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
