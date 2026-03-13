<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\ValueObject;

/**
 * Represents a single row action configuration.
 * Immutable value object with all properties needed to render an action button.
 *
 * Conditions can be expressed in two ways:
 *
 *   1. String expression (simple property checks):
 *      condition: 'entity.status == "pending"'
 *      condition: '!entity.archived'
 *
 *   2. DI tuple (complex logic with injected dependencies):
 *      condition: [ApprovalService::class, 'canApprove']
 *      The service method receives the entity object and must return bool.
 *      The service must implement RowActionConditionInterface.
 *
 * Action rendering modes (mutually exclusive, checked in order):
 *   1. template       — custom Twig template
 *   2. liveComponent  — Twig/Live Component rendered via {{ component() }}
 *   3. method         — form-based POST/DELETE
 *   4. route/url      — plain link (default)
 *
 * Actions appear in three contexts: CONTEXT_INDEX (entity list rows), CONTEXT_SHOW (show page
 * header), and CONTEXT_EDIT (edit page header). The `contexts` parameter restricts an action
 * to specific contexts; an empty array means "all contexts".
 *
 * Context filtering is applied by RowActionRegistry *before* merging, so a context-restricted
 * action from a higher-priority provider never overwrites a default action in other contexts.
 */
final class RowAction
{
    /** Entity list — the _RowActions partial inside EntityList. */
    public const CONTEXT_INDEX = 'index';

    /** Show page header. */
    public const CONTEXT_SHOW = 'show';

    /** Edit page header. */
    public const CONTEXT_EDIT = 'edit';

    /**
     * Sentinel value meaning "priority not explicitly set".
     *
     * Used in merge() to decide whether to inherit the original action's priority.
     * If both actions have DEFAULT_PRIORITY, the original is kept.
     */
    public const DEFAULT_PRIORITY = 100;

    /**
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     *
     * @param string                                        $name           Unique action identifier
     * @param string                                        $label          Display label
     * @param string|null                                   $icon           Emoji or icon identifier
     * @param string|null                                   $route          Named route (null = admin_object_path auto-resolution)
     * @param array<string, mixed>                          $routeParams    Additional route parameters
     * @param string|null                                   $url            Static URL (alternative to route)
     * @param string|null                                   $permission     Required role (e.g. 'ROLE_ADMIN')
     * @param string|null                                   $voterAttribute Admin voter attribute (e.g. 'ADMIN_EDIT')
     * @param string|array{class-string, string}|null $condition      Expression string OR [ServiceClass::class, 'method'] DI tuple
     * @param string|null                                   $cssClass       Additional CSS classes for the button
     * @param string|null                                   $confirmMessage Confirmation message (shows confirm dialog before action)
     * @param bool                                          $openInNewTab   Whether to open link in new tab
     * @param int                                           $priority       Sort priority (lower = earlier). Use DEFAULT_PRIORITY (100) for "unset".
     * @param string|null                                   $method         HTTP method for form-based actions ('POST', 'DELETE')
     * @param string|null                                   $template       Custom Twig template for rendering the action button
     * @param string|null                                   $liveComponent  TwigComponent/LiveComponent name rendered instead of a link.
     *                                                                      Must implement RowActionComponentInterface.
     *                                                                      Always receives {entity} as prop.
     * @param array<string>                                 $contexts       Contexts in which this action is visible.
     *                                                                      Empty = all contexts (CONTEXT_INDEX, CONTEXT_SHOW, CONTEXT_EDIT).
     *                                                                      E.g. [RowAction::CONTEXT_INDEX] for list-only actions
     *                                                                      such as liveComponent buttons that fire events on the
     *                                                                      parent EntityList LiveComponent via Stimulus.
     */
    public function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly ?string $icon = null,
        public readonly ?string $route = null,
        public readonly array $routeParams = [],
        public readonly ?string $url = null,
        public readonly ?string $permission = null,
        public readonly ?string $voterAttribute = null,
        public readonly string|array|null $condition = null,
        public readonly ?string $cssClass = null,
        public readonly ?string $confirmMessage = null,
        public readonly bool $openInNewTab = false,
        public readonly int $priority = self::DEFAULT_PRIORITY,
        public readonly ?string $method = null,
        public readonly ?string $template = null,
        public readonly ?string $liveComponent = null,
        public readonly array $contexts = [],
    ) {}

    /**
     * Whether this action is available in the given context.
     * An empty contexts array means the action is available everywhere.
     */
    public function supportsContext(string $context): bool
    {
        return empty($this->contexts) || in_array($context, $this->contexts, true);
    }

    /**
     * Create a modified copy with overridden properties.
     * Only provided keys will replace existing values; array_key_exists allows explicit null overrides.
     *
     * @param array<string, mixed> $overrides
     */
    public function with(array $overrides): self
    {
        return new self(
            name:          $overrides['name']          ?? $this->name,
            label:         $overrides['label']         ?? $this->label,
            icon:          $this->pick($overrides, 'icon',          $this->icon),
            route:         $this->pick($overrides, 'route',         $this->route),
            routeParams:   !empty($overrides['routeParams']) ? $overrides['routeParams'] : $this->routeParams,
            url:           $this->pick($overrides, 'url',           $this->url),
            permission:    $this->pick($overrides, 'permission',    $this->permission),
            voterAttribute:$this->pick($overrides, 'voterAttribute',$this->voterAttribute),
            condition:     $this->pick($overrides, 'condition',     $this->condition),
            cssClass:      $this->pick($overrides, 'cssClass',      $this->cssClass),
            confirmMessage:$this->pick($overrides, 'confirmMessage',$this->confirmMessage),
            openInNewTab:  $overrides['openInNewTab']  ?? $this->openInNewTab,
            priority:      $overrides['priority']      ?? $this->priority,
            method:        $this->pick($overrides, 'method',        $this->method),
            template:      $this->pick($overrides, 'template',      $this->template),
            liveComponent: $this->pick($overrides, 'liveComponent', $this->liveComponent),
            contexts:      $overrides['contexts']      ?? $this->contexts,
        );
    }

    /**
     * Merge non-null/non-default properties from another action into this one.
     * Used by RowActionRegistry for partial overrides (without the override flag).
     * The name is always preserved from the original.
     *
     * Priority merge rule: if the incoming action uses DEFAULT_PRIORITY (meaning the
     * developer did not explicitly set a priority), the original priority is kept.
     *
     * contexts merge rule: prefer $other->contexts when non-empty, otherwise keep $this->contexts.
     * Context filtering is already applied by RowActionRegistry before merge, so by the time
     * merge() is called both actions are valid for the current context; this just preserves
     * the most-specific context declaration from the higher-priority provider.
     */
    public function merge(self $other): self
    {
        return new self(
            name:          $this->name,
            label:         $other->label,
            icon:          $other->icon           ?? $this->icon,
            route:         $other->route          ?? $this->route,
            routeParams:   !empty($other->routeParams) ? $other->routeParams : $this->routeParams,
            url:           $other->url            ?? $this->url,
            permission:    $other->permission     ?? $this->permission,
            voterAttribute:$other->voterAttribute ?? $this->voterAttribute,
            condition:     $other->condition      ?? $this->condition,
            cssClass:      $other->cssClass       ?? $this->cssClass,
            confirmMessage:$other->confirmMessage ?? $this->confirmMessage,
            openInNewTab:  $other->openInNewTab,
            priority:      $other->priority !== self::DEFAULT_PRIORITY ? $other->priority : $this->priority,
            method:        $other->method         ?? $this->method,
            template:      $other->template       ?? $this->template,
            liveComponent: $other->liveComponent  ?? $this->liveComponent,
            contexts:      !empty($other->contexts) ? $other->contexts : $this->contexts,
        );
    }

    public function hasRoute(): bool
    {
        return $this->route !== null;
    }

    public function requiresConfirmation(): bool
    {
        return $this->confirmMessage !== null;
    }

    public function isFormAction(): bool
    {
        return $this->method !== null;
    }

    /**
     * Whether this action renders as a Twig/Live Component rather than a link or form.
     * Component must implement RowActionComponentInterface and accept {entity} as a prop.
     */
    public function isComponentAction(): bool
    {
        return $this->liveComponent !== null;
    }

    /**
     * Whether the condition is a DI service tuple ([ServiceClass::class, 'method']).
     */
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
