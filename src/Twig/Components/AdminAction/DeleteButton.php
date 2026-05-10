<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Components\AdminAction;

use Kachnitel\AdminBundle\BatchAction\BatchActionComponentInterface;
use Kachnitel\AdminBundle\DataSource\DataSourceRegistry;
use Kachnitel\AdminBundle\Service\EntityListBatchService;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Batch delete button for the entity list batch actions bar.
 *
 * Replaces the inline batchDelete() LiveAction that used to live on EntityList.
 * Receives selectedIds / entityClass / entityShortClass as LiveProps from
 * _BatchActionsBar.html.twig, delegates to EntityListBatchService for the
 * actual deletion, then emits 'batch:completed' up to EntityList so it can
 * clear the selection and refresh the query.
 *
 * Permission check is performed by EntityListBatchService::batchDelete()
 * (delegates to EntityListPermissionService), so AccessDeniedException is
 * propagated if the user lacks ADMIN_DELETE.
 *
 * The button renders disabled when selectedIds is empty to prevent accidental
 * empty-selection submissions.
 */
#[AsLiveComponent('K:Admin:Action:Delete', template: '@KachnitelAdmin/components/AdminAction/DeleteButton.html.twig')]
class DeleteButton implements BatchActionComponentInterface
{
    use DefaultActionTrait;
    use BatchActionTrait;

    public function __construct(
        private readonly EntityListBatchService $batchService,
        private readonly DataSourceRegistry $registry,
    ) {}

    #[LiveAction]
    public function execute(): void
    {
        if (empty($this->selectedIds)) {
            return;
        }

        $dataSource = $this->registry->resolve(null, $this->entityShortClass, $this->entityClass);

        $this->batchService->batchDelete(
            $this->selectedIds,
            $dataSource,
            $this->entityClass,
            $this->entityShortClass,
        );

        $this->completeAction('delete', $this->selectedIds);
    }
}
