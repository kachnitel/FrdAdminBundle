<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\ValueObject;

/**
 * Represents a custom batch action configuration.
 * Immutable value object with all properties needed to render and execute a batch action.
 *
 * Batch actions operate on multiple selected entities via the batch actions bar in EntityList.
 *
 * Handler rendering modes (mutually exclusive, checked in order):
 *   1. liveAction  — EntityList emits 'batch:action' browser event; app handles via JS/LiveComponent
 *   2. route       — Form POST to a Symfony route with selected IDs in request body
 *   3. url         — Form POST to a static URL with selected IDs in request body
 *
 * Permission checking (same AdminEntityVoter constants as RowAction):
 *   1. voterAttribute  — Admin voter attribute (e.g. 'ADMIN_EDIT')
 *   2. permission      — Required role (e.g. 'ROLE_ADMIN')
 *
 * The `confirmMessage` may contain a `%count%` placeholder which is replaced with
 * the number of selected items at render time.
 *
 * @see RowAction For single-entity actions
 * @see BatchActionDto For the data passed to route/liveAction handlers
 *
 * @SuppressWarnings(PHPMD.ExcessiveParameterList)
 */
final class BatchAction
{
    /**
     * Sentinel value meaning "priority not explicitly set".
     */
    public const DEFAULT_PRIORITY = 100;

    /**
     * @param string      $name           Unique action identifier
     * @param string      $label          Button label text
     * @param string|null $icon           Emoji or icon identifier
     * @param string|null $route          Named Symfony route; selected IDs posted as `ids[]`
     * @param string|null $url            Static URL (alternative to route)
     * @param string|null $liveAction     LiveComponent action name; triggers 'batch:action' browser event
     * @param string|null $permission     Required role (e.g. 'ROLE_ADMIN')
     * @param string|null $voterAttribute Admin voter attribute (e.g. 'ADMIN_EDIT')
     * @param string|null $cssClass       Override button CSS classes
     * @param string|null $confirmMessage Confirm dialog text; supports `%count%` placeholder
     * @param int         $priority       Sort order (lower = earlier)
     */
    public function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly ?string $icon = null,
        public readonly ?string $route = null,
        public readonly ?string $url = null,
        public readonly ?string $liveAction = null,
        public readonly ?string $permission = null,
        public readonly ?string $voterAttribute = null,
        public readonly ?string $cssClass = null,
        public readonly ?string $confirmMessage = null,
        public readonly int $priority = self::DEFAULT_PRIORITY,
    ) {}

    /**
     * Whether this action routes to a Symfony named route.
     */
    public function isRouteAction(): bool
    {
        return $this->route !== null;
    }

    /**
     * Whether this action triggers a LiveComponent live action.
     */
    public function isLiveAction(): bool
    {
        return $this->liveAction !== null;
    }

    /**
     * Whether a confirmation dialog should be shown before executing this action.
     */
    public function requiresConfirmation(): bool
    {
        return $this->confirmMessage !== null;
    }

    /**
     * Get the confirmation message with %count% replaced by the number of selected items.
     * Returns null when no confirmMessage is configured.
     */
    public function getConfirmMessage(int $count): ?string
    {
        if ($this->confirmMessage === null) {
            return null;
        }

        return str_replace('%count%', (string) $count, $this->confirmMessage);
    }
}
