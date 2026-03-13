<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Attribute;

use Attribute;
use Kachnitel\AdminBundle\ValueObject\RowAction;

/**
 * Declares a custom entity action button.
 *
 * Actions appear in entity list rows, show page headers, and edit page headers by default.
 * Use the `contexts` parameter to restrict an action to specific rendering contexts.
 *
 * Can be repeated on the same entity class for multiple actions:
 *
 *   #[AdminAction(name: 'approve', label: 'Approve', icon: '✅', route: 'app_approve',
 *                 condition: 'entity.status == "pending"')]
 *   #[AdminAction(name: 'archive', label: 'Archive', icon: '📦', route: 'app_archive',
 *                 method: 'POST', confirmMessage: 'Archive this item?')]
 *   class Order { }
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class AdminAction
{
    /**
     * @param string                                        $name           Unique action identifier
     * @param string                                        $label          Button label text
     * @param string|null                                   $icon           Emoji or icon identifier
     * @param string|null                                   $route          Named Symfony route
     * @param array<string, mixed>                          $routeParams    Additional route parameters
     * @param string|null                                   $url            Static URL (alternative to route)
     * @param string|null                                   $permission     Required role, e.g. 'ROLE_EDITOR'
     * @param string|null                                   $voterAttribute Admin voter constant, e.g. 'ADMIN_EDIT'
     * @param string|array{0:class-string,1:string}|null   $condition      Visibility condition: string expression
     *                                                                      or [ServiceClass::class, 'method'] tuple.
     * @param string|null                                   $cssClass       Override button CSS classes
     * @param string|null                                   $confirmMessage Confirmation dialog message before action
     * @param bool                                          $openInNewTab   Open link in new tab
     * @param int                                           $priority       Sort order (lower = earlier)
     * @param string|null                                   $method         HTTP method for form-based actions ('POST', 'DELETE')
     * @param string|null                                   $template       Custom Twig template for rendering this button
     * @param string|null                                   $liveComponent  TwigComponent/LiveComponent name rendered instead of link.
     *                                                                      Always receives {entity} as prop.
     * @param array<string>                                 $contexts       Contexts in which this action appears.
     *                                                                      Empty = all contexts (index, show, edit).
     *                                                                      Use [RowAction::CONTEXT_INDEX] for liveComponent
     *                                                                      actions that fire events on the parent EntityList
     *                                                                      LiveComponent and must not appear on show/edit pages.
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
        public readonly int $priority = RowAction::DEFAULT_PRIORITY,
        public readonly ?string $method = null,
        public readonly ?string $template = null,
        public readonly ?string $liveComponent = null,
        public readonly array $contexts = [],
        public readonly bool $override = false,
    ) {}
}
