<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Attribute;

use Attribute;

/**
 * Defines a custom row action for an entity in the admin list view.
 *
 * Can be applied multiple times to define multiple actions.
 *
 * Usage (string expression condition):
 * #[AdminAction(
 *     name: 'approve',
 *     label: 'Approve',
 *     icon: '✅',
 *     route: 'app_product_approve',
 *     condition: 'entity.status == "pending"',
 * )]
 *
 * Usage (DI tuple condition — for complex logic with injected services):
 * #[AdminAction(
 *     name: 'approve',
 *     label: 'Approve',
 *     condition: [ApprovalService::class, 'canApprove'],
 * )]
 * The method receives the entity object and must return bool.
 *
 * Usage (override existing action):
 * #[AdminAction(name: 'show', label: 'Preview', icon: '🔍', override: true)]
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class AdminAction
{
    /**
     * @param string                                         $name           Unique action identifier
     * @param string                                         $label          Display label for the button
     * @param string|null                                    $icon           Emoji or icon identifier
     * @param string|null                                    $route          Named Symfony route (null = admin_object_path auto-resolution)
     * @param array<string, mixed>                           $routeParams    Additional route parameters
     * @param string|null                                    $url            Static URL (alternative to route)
     * @param string|null                                    $permission     Required role (e.g., 'ROLE_ADMIN')
     * @param string|null                                    $voterAttribute Admin voter attribute (e.g., 'ADMIN_EDIT')
     * @param string|array{0: class-string, 1: string}|null $condition      String expression OR [ServiceClass::class, 'method'] DI tuple
     * @param string|null                                    $cssClass       Additional CSS classes
     * @param string|null                                    $confirmMessage Confirmation dialog message before action
     * @param bool                                           $openInNewTab   Open link in new tab
     * @param int                                            $priority       Sort order (lower = earlier)
     * @param string|null                                    $method         HTTP method for form-based actions ('POST', 'DELETE')
     * @param string|null                                    $template       Custom template for rendering this button
     * @param bool                                           $override       If true, fully replaces an existing action with same name (vs merge)
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
        public readonly bool $override = false,
    ) {}
}
