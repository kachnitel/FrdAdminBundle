<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Components\AdminAction;

use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;

/**
 * Provides the three standard LiveProps expected by every batch action component
 * and the completeAction() helper that emits 'admin:action:completed' to EntityList.
 *
 * Usage:
 * ```php
 * #[AsLiveComponent('K:Admin:Action:MyBatch', ...)]
 * class MyBatchButton implements BatchActionComponentInterface
 * {
 *     use DefaultActionTrait;
 *     use BatchActionTrait;
 *
 *     #[LiveAction]
 *     public function execute(): void
 *     {
 *         // ... perform action on $this->selectedIds / $this->entityClass
 *         $this->completeAction('my-action', $this->selectedIds);
 *     }
 * }
 * ```
 *
 * Template contract — passed by _BatchActionsBar.html.twig / _BatchActionButton.html.twig:
 *   selectedIds      — IDs of currently selected entities
 *   entityClass      — Fully-qualified entity class name
 *   entityShortClass — Short class name used for voter checks and route slugs
 *
 * EntityList listens for 'admin:action:completed' and refreshes the list,
 * clearing affected IDs from its own selectedIds LiveProp.
 */
trait BatchActionTrait
{
    use ComponentToolsTrait;

    /**
     * IDs of currently selected entities, synced from EntityList's selectedIds LiveProp.
     * Updated whenever the parent EntityList re-renders (e.g. after a checkbox change).
     *
     * @var array<int|string>
     */
    #[LiveProp]
    public array $selectedIds = [];

    /**
     * Fully-qualified entity class name (e.g. App\Entity\Product).
     */
    #[LiveProp]
    public string $entityClass = '';

    /**
     * Short entity class name used for voter checks (e.g. Product).
     */
    #[LiveProp]
    public string $entityShortClass = '';

    /**
     * Emit the 'admin:action:completed' event that EntityList listens to.
     *
     * EntityList will:
     *   - Remove affectedIds from its own selectedIds
     *   - Invalidate the query cache (triggering a re-render with fresh data)
     *
     * @param string            $action      Action name for diagnostics / future listeners
     * @param array<int|string> $affectedIds IDs that were mutated by the action
     */
    protected function completeAction(string $action, array $affectedIds): void
    {
        $this->emitUp('admin:action:completed', [
            'action'      => $action,
            'affectedIds' => $affectedIds,
        ]);
    }
}
