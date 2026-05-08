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
 * Use `actionType` to control whether this action is a row action, a batch action, or both:
 *   - `ACTION_TYPE_ROW`   (default) — appears in each entity row / show / edit page header
 *   - `ACTION_TYPE_BATCH` — appears in the batch actions bar (operates on selected IDs)
 *   - `ACTION_TYPE_BOTH`  — appears in both positions
 *
 * Batch action rendering in the batch actions bar (checked in order):
 *   1. liveAction  — emits 'batch:action' browser event; app handles via JS/LiveComponent
 *   2. route       — form POST with selected IDs as `ids[]`
 *   3. url         — form POST to static URL with selected IDs as `ids[]`
 *
 * Can be repeated on the same entity class for multiple actions:
 *
 *   #[AdminAction(name: 'approve', label: 'Approve', icon: '✅', route: 'app_approve',
 *                 condition: 'entity.status == "pending"')]
 *   #[AdminAction(name: 'bulk-archive', label: 'Archive Selected', icon: '📦',
 *                 route: 'app_bulk_archive', actionType: AdminAction::ACTION_TYPE_BATCH,
 *                 confirmMessage: 'Archive %count% items?')]
 *   class Order { }
 *
 * @SuppressWarnings(PHPMD.ExcessiveParameterList) PHP attributes must declare all configuration
 *     in the constructor; value-object grouping is not possible because attribute arguments must
 *     be compile-time constant expressions. Each parameter is part of the public API surface.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class AdminAction
{
    /** Row action: appears in each entity row, show page header, and edit page header (default). */
    public const ACTION_TYPE_ROW = 'row';

    /** Batch action: appears in the batch actions bar; operates on multiple selected IDs. */
    public const ACTION_TYPE_BATCH = 'batch';

    /** Both row and batch: appears in all row action positions and in the batch actions bar. */
    public const ACTION_TYPE_BOTH = 'both';

    /**
     * @param string                                        $name           Unique action identifier
     * @param string                                        $label          Button label text
     * @param string|null                                   $icon           Emoji or icon identifier
     * @param string|null                                   $route          Named Symfony route
     * @param array<string, mixed>                          $routeParams    Additional route parameters (row actions only)
     * @param string|null                                   $url            Static URL (alternative to route)
     * @param string|null                                   $permission     Required role, e.g. 'ROLE_EDITOR'
     * @param string|null                                   $voterAttribute Admin voter constant, e.g. 'ADMIN_EDIT'
     * @param string|array{0:class-string,1:string}|null   $condition      Row action visibility condition: string expression
     *                                                                      or [ServiceClass::class, 'method'] tuple.
     *                                                                      Ignored for batch actions.
     * @param string|null                                   $cssClass       Override button CSS classes
     * @param string|null                                   $confirmMessage Confirmation dialog message before action.
     *                                                                      Batch actions support `%count%` placeholder.
     * @param bool                                          $openInNewTab   Open link in new tab (row actions only)
     * @param int                                           $priority       Sort order (lower = earlier)
     * @param string|null                                   $method         HTTP method for form-based row actions ('POST', 'DELETE')
     * @param string|null                                   $template       Custom Twig template for rendering this button (row actions only)
     * @param string|null                                   $liveComponent  TwigComponent/LiveComponent name (row actions only).
     *                                                                      Always receives {entity} as prop.
     * @param array<string>                                 $contexts       Row action contexts (index, show, edit).
     *                                                                      Empty = all contexts. Ignored for batch actions.
     * @param bool                                          $override       If true, fully replaces an existing row action with same name (vs merge)
     * @param string                                        $actionType     One of ACTION_TYPE_ROW, ACTION_TYPE_BATCH, ACTION_TYPE_BOTH.
     *                                                                      Controls whether action appears as a row action, batch action, or both.
     * @param string|null                                   $liveAction     LiveComponent action name for batch actions.
     *                                                                      When set, triggers 'batch:action' browser event instead of form POST.
     *                                                                      Ignored for row actions (use $liveComponent instead).
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
        public readonly string $actionType = self::ACTION_TYPE_ROW,
        public readonly ?string $liveAction = null,
    ) {}
}
