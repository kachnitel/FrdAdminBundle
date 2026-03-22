<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\RowAction;

use Kachnitel\AdminBundle\Archive\ArchiveService;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\ValueObject\RowAction;

/**
 * Provides archive and unarchive row action buttons for entities with archive configured.
 *
 * Only activates when ArchiveService::resolveConfig() returns a non-null config for
 * the entity class. Both buttons appear in all three contexts (index, show, edit).
 *
 * Visibility is controlled via string ExpressionLanguage conditions derived from the
 * entity's archiveExpression (e.g. 'item.archived'):
 *   - Archive:   !(item.archived)  — show when NOT yet archived
 *   - Unarchive: item.archived     — show when IS already archived
 *
 * Both require ADMIN_ARCHIVE voter attribute (checked via isActionAccessible in
 * RowActionRuntime, which maps ADMIN_ARCHIVE → 'archive' route).
 * Permission enforcement also happens at controller level.
 *
 * Priority 25/26 places these between the default Edit (20) and custom actions.
 */
class ArchiveRowActionProvider implements RowActionProviderInterface
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
     * @return array<RowAction>
     */
    public function getActions(string $entityClass): array
    {
        $config = $this->archiveService->resolveConfig($entityClass);

        if ($config === null) {
            return [];
        }

        $expression         = $config->expression;
        $archiveCondition   = '!(' . $expression . ')';
        $unarchiveCondition = $expression;

        return [
            new RowAction(
                name: 'archive',
                label: 'Archive',
                icon: '🗃',
                method: 'POST',
                voterAttribute: AdminEntityVoter::ADMIN_ARCHIVE,
                condition: $archiveCondition,
                confirmMessage: 'Archive this item? It will be hidden from the default list view.',
                priority: 25,
            ),
            new RowAction(
                name: 'unarchive',
                label: 'Unarchive',
                icon: '📤',
                method: 'POST',
                voterAttribute: AdminEntityVoter::ADMIN_ARCHIVE,
                condition: $unarchiveCondition,
                priority: 26,
            ),
        ];
    }

    public function getPriority(): int
    {
        return 12;
    }
}
