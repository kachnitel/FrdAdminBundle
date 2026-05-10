<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\BatchAction;

use Kachnitel\AdminBundle\Archive\ArchiveService;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\ValueObject\BatchAction;

/**
 * Provides the batch archive action for entities that have archive configured.
 *
 * Activates when ArchiveService::resolveConfig() returns a non-null config —
 * i.e. when the entity has #[Admin(archiveExpression: ...)] or the global
 * kachnitel_admin.archive.expression is set and not disabled for the entity.
 *
 * The action requires ADMIN_ARCHIVE voter attribute; BatchActionRuntime filters
 * it out for users who lack that permission before passing to the template.
 *
 * Priority 12 mirrors ArchiveRowActionProvider (the row-level archive buttons)
 * so the two features appear at a consistent priority level.
 */
class ArchiveBatchActionProvider implements BatchActionProviderInterface
{
    public function __construct(
        private readonly ArchiveService $archiveService,
    ) {}

    /**
     * @param class-string $entityClass
     */
    public function supports(string $entityClass): bool
    {
        return $this->archiveService->resolveConfig($entityClass) !== null;
    }

    /**
     * @param class-string $entityClass
     * @return array<BatchAction>
     */
    public function getActions(string $entityClass): array
    {
        return [
            new BatchAction(
                name: 'archive',
                label: 'Archive',
                icon: '🗃',
                liveComponent: 'K:Admin:Action:Archive',
                voterAttribute: AdminEntityVoter::ADMIN_ARCHIVE,
                confirmMessage: 'Archive %count% item(s)? They will be hidden from the default list view.',
                priority: 25,
            ),
        ];
    }

    public function getPriority(): int
    {
        return 12;
    }
}
