<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Attribute;

use Attribute;
use Kachnitel\AdminBundle\ValueObject\BatchAction;
use Kachnitel\AdminBundle\ValueObject\RowAction;

/**
 * Declares a custom entity action button, supporting both row and batch actions.
 *
 * Row actions appear in entity list rows, show page headers, and edit page headers by default.
 * Batch actions appear in the batch actions bar when multiple entities are selected.
 *
 * Use the `type` parameter to control whether an action is a row action ('row'), batch action
 * ('batch'), or both ('both'). Use the `contexts` parameter to restrict row actions to specific
 * rendering contexts.
 *
 * Can be repeated on the same entity class for multiple actions:
 *
 *   // Row action (default)
 *   #[AdminAction(name: 'approve', label: 'Approve', icon: '✅', route: 'app_approve',
 *                 condition: 'entity.status == "pending"')]
 *   // Batch action
 *   #[AdminAction(name: 'bulk-publish', label: 'Publish Selected', icon: '🚀',
 *                 type: AdminAction::TYPE_BATCH, batchLiveAction: 'bulkPublish',
 *                 voterAttribute: 'ADMIN_EDIT')]
 *   // Both row and batch
 *   #[AdminAction(name: 'archive', label: 'Archive', icon: '📦',
 *                 type: AdminAction::TYPE_BOTH, route: 'app_archive', method: 'POST')]
 *   class Order { }
 *
 * @SuppressWarnings(PHPMD.ExcessiveParameterList) PHP attributes must declare all configuration
 *     in the constructor; value-object grouping is not possible because attribute arguments must
 *     be compile-time constant expressions. Each parameter is part of the public API surface.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class AdminAction
{
    /** Action appears in row context only */
    public const TYPE_ROW = 'row';

    /** Action appears in batch context only */
    public const TYPE_BATCH = 'batch';

    /** Action appears in both row and batch contexts */
    public const TYPE_BOTH = 'both';
    /**
     * @param string                                        $name               Unique action identifier
     * @param string                                        $label              Button label text
     * @param string|null                                   $icon               Emoji or icon identifier
     * @param string|null                                   $route              Named Symfony route (for row or form-based actions)
     * @param array<string, mixed>                          $routeParams        Additional route parameters
     * @param string|null                                   $url                Static URL (alternative to route)
     * @param string|null                                   $permission         Required role, e.g. 'ROLE_EDITOR'
     * @param string|null                                   $voterAttribute     Admin voter constant, e.g. 'ADMIN_EDIT'
     * @param string|array{0:class-string,1:string}|null   $condition          Visibility condition: string expression
     *                                                                          or [ServiceClass::class, 'method'] tuple.
     * @param string|null                                   $cssClass           Override button CSS classes
     * @param string|null                                   $confirmMessage     Confirmation dialog message before action
     * @param bool                                          $openInNewTab       Open link in new tab
     * @param int                                           $priority           Sort order (lower = earlier)
     * @param string|null                                   $method             HTTP method for form-based actions ('POST', 'DELETE')
     * @param string|null                                   $template           Custom Twig template for rendering this button
     * @param string|null                                   $liveComponent      TwigComponent/LiveComponent name rendered instead of link.
     *                                                                          Always receives {entity} as prop (row actions only).
     * @param array<string>                                 $contexts           Contexts in which this row action appears.
     *                                                                          Empty = all contexts (index, show, edit).
     *                                                                          Use [RowAction::CONTEXT_INDEX] for liveComponent
     *                                                                          actions that fire events on the parent EntityList
     *                                                                          LiveComponent and must not appear on show/edit pages.
     * @param bool                                          $override           If true, fully replaces an existing action with same name (vs merge)
     * @param string                                        $type               Action type: TYPE_ROW (default), TYPE_BATCH, or TYPE_BOTH
     *                                                                          TYPE_ROW: Appears only in row contexts
     *                                                                          TYPE_BATCH: Appears only in batch bar
     *                                                                          TYPE_BOTH: Appears in both row and batch contexts
     * @param string|null                                   $batchLiveAction    EntityList LiveComponent action method name for batch actions.
     *                                                                          Must accept BatchActionDto parameter.
     *                                                                          Only used when type is TYPE_BATCH or TYPE_BOTH.
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
        public readonly string $type = self::TYPE_ROW,
        public readonly ?string $batchLiveAction = null,
    ) {}
}
