<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Components\AdminAction;

use Kachnitel\AdminBundle\Archive\ArchiveEntityService;
use Kachnitel\AdminBundle\Archive\ArchiveService;
use Kachnitel\AdminBundle\BatchAction\BatchActionComponentInterface;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Doctrine\ORM\EntityManagerInterface;
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
 *   3. Loads each entity by ID and calls ArchiveEntityService::archive()
 *   4. Emits 'admin:action:completed' with the affected IDs so EntityList
 *      removes them from the selection and refreshes the query
 *
 * ArchiveEntityService handles the actual field mutation (boolean → true,
 * datetime → now) and flushes; see its docblock for supported field types.
 */
#[AsLiveComponent('K:Admin:Action:Archive', template: '@KachnitelAdmin/components/AdminAction/ArchiveButton.html.twig')]
class ArchiveButton implements BatchActionComponentInterface
{
    use DefaultActionTrait;
    use BatchActionTrait;

    public function __construct(
        private readonly ArchiveService $archiveService,
        private readonly ArchiveEntityService $archiveEntityService,
        private readonly EntityManagerInterface $em,
        private readonly AuthorizationCheckerInterface $authChecker,
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

        $repository = $this->em->getRepository($entityClass);
        $affected = [];

        foreach ($this->selectedIds as $id) {
            $entity = $repository->find($id);
            if ($entity !== null) {
                $this->archiveEntityService->archive($entity, $config);
                $affected[] = $id;
            }
        }

        $this->completeAction('archive', $affected);
    }
}
