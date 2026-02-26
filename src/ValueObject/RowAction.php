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
 */
final class RowAction
{
    /**
     * Sentinel value meaning "priority not explicitly set".
     *
     * Used in merge() to decide whether to inherit the original action's priority.
     * If both actions have DEFAULT_PRIORITY, the original is kept.
     *
     * To force a specific priority of 100, set it on the lower-priority provider
     * (e.g., DefaultRowActionProvider) rather than relying on the merge default.
     */
    public const DEFAULT_PRIORITY = 100;

    /**
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     *
     * @param string                                        $name           Unique action identifier (e.g., 'show', 'edit', 'duplicate')
     * @param string                                        $label          Display label for the action button
     * @param string|null                                   $icon           Emoji or icon identifier (e.g., '👀', 'edit')
     * @param string|null                                   $route          Named route (null = uses admin_object_path automatic resolution)
     * @param array<string, mixed>                          $routeParams    Additional route parameters
     * @param string|null                                   $url            Static URL (alternative to route)
     * @param string|null                                   $permission     Required role (e.g., 'ROLE_ADMIN')
     * @param string|null                                   $voterAttribute Admin voter attribute (e.g., 'ADMIN_EDIT')
     * @param string|array{class-string, string}|null $condition      Expression string OR [ServiceClass::class, 'method'] DI tuple
     * @param string|null                                   $cssClass       Additional CSS classes for the button
     * @param string|null                                   $confirmMessage Confirmation message (if set, shows confirm dialog before action)
     * @param bool                                          $openInNewTab   Whether to open link in new tab
     * @param int                                           $priority       Sort priority (lower = earlier). Use DEFAULT_PRIORITY (100) for "unset".
     * @param string|null                                   $method         HTTP method for form-based actions ('POST', 'DELETE')
     * @param string|null                                   $template       Custom Twig template for rendering the action button
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
        );
    }

    /**
     * Merge non-null/non-default properties from another action into this one.
     * Used by RowActionRegistry for partial overrides (without the override flag).
     * The name is always preserved from the original.
     *
     * Priority merge rule: if the incoming action uses DEFAULT_PRIORITY (meaning the
     * developer did not explicitly set a priority), the original priority is kept.
     * This prevents accidental priority resets when adding a condition to a default action.
     *
     * Limitation: a developer cannot use merge() to explicitly set priority to DEFAULT_PRIORITY
     * (100) from a lower value — use override: true for that case.
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
        );
    }

    /**
     * Whether this action resolves its URL via a named Symfony route.
     */
    public function hasRoute(): bool
    {
        return $this->route !== null;
    }

    /**
     * Whether clicking this action should show a browser confirm dialog first.
     */
    public function requiresConfirmation(): bool
    {
        return $this->confirmMessage !== null;
    }

    /**
     * Whether this action renders as a form (POST/DELETE) rather than a plain link.
     */
    public function isFormAction(): bool
    {
        return $this->method !== null;
    }

    /**
     * Whether the condition is a DI service tuple ([ServiceClass::class, 'method']).
     * Returns false for string expressions and when no condition is set.
     */
    public function hasDiCondition(): bool
    {
        return is_array($this->condition);
    }

    /**
     * Return $overrides[$key] when the key is explicitly present (allows null),
     * or fall back to $default when the key is absent entirely.
     *
     * This is the single branching point replacing the repeated array_key_exists
     * ternaries that previously inflated with()'s cyclomatic complexity.
     *
     * @param array<string, mixed> $overrides
     */
    private function pick(array $overrides, string $key, mixed $default): mixed
    {
        return array_key_exists($key, $overrides) ? $overrides[$key] : $default;
    }
}
