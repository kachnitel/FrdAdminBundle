<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\ValueObject;

/**
 * Represents a single row action configuration.
 * Immutable value object with all properties needed to render an action button.
 */
final class RowAction
{
    /**
     * @param string $name Unique action identifier (e.g., 'show', 'edit', 'duplicate')
     * @param string $label Display label for the action button
     * @param string|null $icon Emoji or icon identifier (e.g., '👀', 'edit')
     * @param string|null $route Named route for the action (null = uses admin_object_path automatic resolution)
     * @param array<string, mixed> $routeParams Additional route parameters
     * @param string|null $url Static URL (alternative to route)
     * @param string|null $permission Required permission/role (e.g., 'ROLE_ADMIN')
     * @param string|null $voterAttribute Admin voter attribute (e.g., 'ADMIN_EDIT')
     * @param string|null $condition Expression for conditional display (e.g., 'entity.status != "archived"')
     * @param string|null $cssClass Additional CSS classes for the button
     * @param string|null $confirmMessage Confirmation message (if set, shows confirm dialog)
     * @param bool $openInNewTab Whether to open link in new tab
     * @param int $priority Sort priority (lower = earlier, default 100)
     * @param string|null $method HTTP method for form-based actions ('POST', 'DELETE')
     * @param string|null $template Custom Twig template for rendering the action
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
        public readonly ?string $condition = null,
        public readonly ?string $cssClass = null,
        public readonly ?string $confirmMessage = null,
        public readonly bool $openInNewTab = false,
        public readonly int $priority = 100,
        public readonly ?string $method = null,
        public readonly ?string $template = null,
    ) {}

    /**
     * Create a modified copy with overridden properties.
     * Only non-null values in $overrides will replace existing values.
     *
     * @param array<string, mixed> $overrides
     */
    public function with(array $overrides): self
    {
        return new self(
            name: $overrides['name'] ?? $this->name,
            label: $overrides['label'] ?? $this->label,
            icon: array_key_exists('icon', $overrides) ? $overrides['icon'] : $this->icon,
            route: array_key_exists('route', $overrides) ? $overrides['route'] : $this->route,
            routeParams: !empty($overrides['routeParams']) ? $overrides['routeParams'] : $this->routeParams,
            url: array_key_exists('url', $overrides) ? $overrides['url'] : $this->url,
            permission: array_key_exists('permission', $overrides) ? $overrides['permission'] : $this->permission,
            voterAttribute: array_key_exists('voterAttribute', $overrides) ? $overrides['voterAttribute'] : $this->voterAttribute,
            condition: array_key_exists('condition', $overrides) ? $overrides['condition'] : $this->condition,
            cssClass: array_key_exists('cssClass', $overrides) ? $overrides['cssClass'] : $this->cssClass,
            confirmMessage: array_key_exists('confirmMessage', $overrides) ? $overrides['confirmMessage'] : $this->confirmMessage,
            openInNewTab: $overrides['openInNewTab'] ?? $this->openInNewTab,
            priority: $overrides['priority'] ?? $this->priority,
            method: array_key_exists('method', $overrides) ? $overrides['method'] : $this->method,
            template: array_key_exists('template', $overrides) ? $overrides['template'] : $this->template,
        );
    }

    /**
     * Merge non-null properties from another action into this one.
     * Used for partial overrides without the override flag.
     */
    public function merge(self $other): self
    {
        return new self(
            name: $this->name,
            label: $other->label,
            icon: $other->icon ?? $this->icon,
            route: $other->route ?? $this->route,
            routeParams: !empty($other->routeParams) ? $other->routeParams : $this->routeParams,
            url: $other->url ?? $this->url,
            permission: $other->permission ?? $this->permission,
            voterAttribute: $other->voterAttribute ?? $this->voterAttribute,
            condition: $other->condition ?? $this->condition,
            cssClass: $other->cssClass ?? $this->cssClass,
            confirmMessage: $other->confirmMessage ?? $this->confirmMessage,
            openInNewTab: $other->openInNewTab,
            priority: $other->priority !== 100 ? $other->priority : $this->priority,
            method: $other->method ?? $this->method,
            template: $other->template ?? $this->template,
        );
    }

    /**
     * Check if this action uses a route (vs static URL).
     */
    public function hasRoute(): bool
    {
        return $this->route !== null;
    }

    /**
     * Check if this action requires confirmation.
     */
    public function requiresConfirmation(): bool
    {
        return $this->confirmMessage !== null;
    }

    /**
     * Check if this action is form-based (POST/DELETE).
     */
    public function isFormAction(): bool
    {
        return $this->method !== null;
    }
}
