<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\ValueObject;

/**
 * Represents a custom batch action configuration.
 * Immutable value object with all properties needed to render and execute a batch action.
 *
 * Batch actions operate on multiple selected entities (similar to RowAction for single entities).
 *
 * Handler rendering modes (mutually exclusive, checked in order):
 *   1. liveAction      — LiveComponent action method (real-time, no page reload)
 *   2. route           — Symfony route handler (POST request, full-page handling)
 *   3. url             — Static URL (used for external integrations)
 *
 * Permission checking via:
 *   1. voterAttribute  — Admin voter attribute (e.g. 'ADMIN_EDIT')
 *   2. permission      — Required role (e.g. 'ROLE_ADMIN')
 *
 * Example usage:
 *
 *   new BatchAction(
 *       name: 'bulk-publish',
 *       label: 'Publish Selected',
 *       icon: '🚀',
 *       liveAction: 'bulkPublish',
 *       voterAttribute: AdminEntityVoter::ADMIN_EDIT,
 *       confirmMessage: 'Publish %count% items?',
 *   )
 *
 * @see RowAction For single-entity actions
 */
final class BatchAction
{
    /**
     * Sentinel value meaning "priority not explicitly set".
     * Used in merge() to decide whether to inherit the original action's priority.
     */
    public const DEFAULT_PRIORITY = 100;

    /**
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     *
     * @param string                       $name              Unique action identifier within entity class
     * @param string                       $label             Display label (e.g. 'Publish Selected')
     * @param string|null                  $icon              Emoji or icon identifier (e.g. '🚀', 'fa-download')
     * @param string|null                  $liveAction        EntityList LiveComponent action method name
     *                                                         (called via Stimulus when button clicked).
     *                                                         Method signature: `bulkActionName(BatchActionDto $dto): void`
     * @param string|null                  $route             Named Symfony route for handler
     *                                                         (POST form submitted to this route).
     *                                                         Route handler receives BatchActionDto via argument resolver.
     * @param string|null                  $url               Static URL (alternative to route, for external handlers)
     * @param string|null                  $permission        Required role (e.g. 'ROLE_ADMIN')
     * @param string|null                  $voterAttribute    Admin voter attribute for permission check
     *                                                         (e.g. AdminEntityVoter::ADMIN_EDIT).
     *                                                         Preferred over $permission for context-aware checks.
     * @param string|array{class-string, string}|null $condition Expression string OR [ServiceClass::class, 'method'] DI tuple.
     *                                                         String: 'entity.status == "pending"'
     *                                                         DI: Service method receives entity and returns bool.
     * @param string|null                  $cssClass          Additional CSS classes for the button
     * @param string|null                  $confirmMessage    Confirmation message shown before execution
     *                                                         (e.g. 'Delete %count% products?').
     *                                                         Supports: %count%, %action_name%, %entity_name%
     *                                                         Null = no confirmation dialog.
     * @param bool                         $openInNewTab      Whether to open route/url in new tab (for url mode only)
     * @param int                          $priority          Sort priority (lower = earlier). Use DEFAULT_PRIORITY (100) for "unset".
     */
    public function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly ?string $icon = null,
        public readonly ?string $liveAction = null,
        public readonly ?string $route = null,
        public readonly ?string $url = null,
        public readonly ?string $permission = null,
        public readonly ?string $voterAttribute = null,
        public readonly string|array|null $condition = null,
        public readonly ?string $cssClass = null,
        public readonly ?string $confirmMessage = null,
        public readonly bool $openInNewTab = false,
        public readonly int $priority = self::DEFAULT_PRIORITY,
    ) {}

    /**
     * Create a modified copy with overridden properties.
     * Only provided keys will replace existing values; array_key_exists allows explicit null overrides.
     *
     * @param array<string, mixed> $overrides
     */
    public function with(array $overrides): self
    {
        return new self(
            name:             $overrides['name']             ?? $this->name,
            label:            $overrides['label']            ?? $this->label,
            icon:             $this->pick($overrides, 'icon',             $this->icon),
            liveAction:       $this->pick($overrides, 'liveAction',       $this->liveAction),
            route:            $this->pick($overrides, 'route',            $this->route),
            url:              $this->pick($overrides, 'url',              $this->url),
            permission:       $this->pick($overrides, 'permission',       $this->permission),
            voterAttribute:   $this->pick($overrides, 'voterAttribute',   $this->voterAttribute),
            condition:        $this->pick($overrides, 'condition',        $this->condition),
            cssClass:         $this->pick($overrides, 'cssClass',         $this->cssClass),
            confirmMessage:   $this->pick($overrides, 'confirmMessage',   $this->confirmMessage),
            openInNewTab:     $overrides['openInNewTab']     ?? $this->openInNewTab,
            priority:         $overrides['priority']         ?? $this->priority,
        );
    }

    /**
     * Merge non-null/non-default properties from another action into this one.
     * Used by BatchActionRegistry for partial overrides (without override flag).
     * The name is always preserved from the original.
     *
     * Priority merge rule: if the incoming action uses DEFAULT_PRIORITY (meaning the
     * developer did not explicitly set a priority), the original priority is kept.
     */
    public function merge(self $other): self
    {
        return new self(
            name:             $this->name,
            label:            $other->label,
            icon:             $other->icon             ?? $this->icon,
            liveAction:       $other->liveAction       ?? $this->liveAction,
            route:            $other->route            ?? $this->route,
            url:              $other->url              ?? $this->url,
            permission:       $other->permission       ?? $this->permission,
            voterAttribute:   $other->voterAttribute   ?? $this->voterAttribute,
            condition:        $other->condition        ?? $this->condition,
            cssClass:         $other->cssClass         ?? $this->cssClass,
            confirmMessage:   $other->confirmMessage   ?? $this->confirmMessage,
            openInNewTab:     $other->openInNewTab,
            priority:         $other->priority !== self::DEFAULT_PRIORITY ? $other->priority : $this->priority,
        );
    }

    // Helper methods for type-safe checks

    public function requiresConfirmation(): bool
    {
        return $this->confirmMessage !== null;
    }

    public function isLiveAction(): bool
    {
        return $this->liveAction !== null;
    }

    public function isRouteAction(): bool
    {
        return $this->route !== null;
    }

    public function isUrlAction(): bool
    {
        return $this->url !== null;
    }

    public function hasDiCondition(): bool
    {
        return is_array($this->condition);
    }

    /**
     * Pick a value from overrides array, supporting explicit null overrides.
     *
     * @param array<string, mixed> $overrides
     */
    private function pick(array $overrides, string $key, mixed $default): mixed
    {
        return array_key_exists($key, $overrides) ? $overrides[$key] : $default;
    }
}
