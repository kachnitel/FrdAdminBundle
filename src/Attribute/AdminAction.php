<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Attribute;

use Attribute;

/**
 * Defines a custom row action for an entity in the admin list view.
 * Repeatable — add one per action on the entity class.
 *
 * Action rendering modes (mutually exclusive, checked in order):
 *   1. template       — custom Twig template
 *   2. liveComponent  — Twig/Live Component (must implement RowActionComponentInterface)
 *   3. method         — form-based POST/DELETE button
 *   4. route/url      — plain link (default)
 *
 * @see \Kachnitel\AdminBundle\RowAction\RowActionComponentInterface for liveComponent prop contract
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class AdminAction
{
    /**
     * @param string                                        $name           Unique action identifier (e.g., 'approve', 'duplicate')
     * @param string                                        $label          Button label text
     * @param string|null                                   $icon           Emoji or icon identifier
     * @param string|null                                   $route          Named Symfony route; entity id is appended automatically
     * @param array<string, mixed>                          $routeParams    Additional route parameters merged alongside id
     * @param string|null                                   $url            Static URL — use route for dynamic entity-based links
     * @param string|null                                   $permission     Required role, e.g. 'ROLE_EDITOR'
     * @param string|null                                   $voterAttribute Admin voter constant, e.g. AdminEntityVoter::ADMIN_EDIT
     * @param string|array{class-string, string}|null $condition      Expression string or [Service::class, 'method'] DI tuple.
     *                                                                      The service must implement RowActionConditionInterface.
     * @param string|null                                   $cssClass       Override button CSS classes
     * @param string|null                                   $confirmMessage Confirmation dialog message before action
     * @param bool                                          $openInNewTab   Open link in new tab
     * @param int                                           $priority       Sort order (lower = earlier). Default Show=10, Edit=20.
     * @param string|null                                   $method         HTTP method for form-based actions ('POST', 'DELETE')
     * @param string|null                                   $template       Custom Twig template for rendering this button
     * @param string|null                                   $liveComponent  TwigComponent/LiveComponent name rendered instead of link.
     *                                                                      Component must implement RowActionComponentInterface.
     *                                                                      Always receives {entity} as prop.
     * @param bool                                          $override       If true, fully replaces an existing action with same name (vs merge)
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
        public readonly int $priority = 100,
        public readonly ?string $method = null,
        public readonly ?string $template = null,
        public readonly ?string $liveComponent = null,
        public readonly bool $override = false,
    ) {}
}
