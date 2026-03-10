<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Attribute;

use Attribute;
use Kachnitel\AdminBundle\DataSource\ColumnGroup;

/**
 * Configures display options for a composite column group declared via
 * `#[AdminColumn(group: '...')]`.
 *
 * Place this attribute on the **entity class** alongside `#[Admin]`.
 * The `id` must match the group identifier used in `#[AdminColumn(group:)]`.
 *
 * ## Header modes
 *
 * - `HEADER_TEXT` *(default)* — renders just the humanized group label, like a
 *   regular column header. Least cluttered; filter/sort access is via individual
 *   column visibility or the full-page filter panel.
 *
 * - `HEADER_COLLAPSIBLE` — renders the group label with a native HTML
 *   `<details>`/`<summary>` toggle. Per-sub-column sort and filter rows are
 *   hidden by default and revealed on demand. No JavaScript required.
 *
 * - `HEADER_FULL` — always shows the group label strip and all per-sub-column
 *   sort and filter rows. Most information-dense; suits power-user interfaces.
 *
 * ## Example
 *
 * ```php
 * #[Admin(label: 'Orders')]
 * #[AdminColumnGroup(
 *     id: 'delivery',
 *     subLabels: ColumnGroup::SUB_LABELS_ICON,
 *     header: ColumnGroup::HEADER_COLLAPSIBLE,
 * )]
 * class Order
 * {
 *     #[AdminColumn(group: 'delivery')]
 *     private ?FulfillmentMethod $fulfillmentMethod = null;
 *
 *     #[AdminColumn(group: 'delivery')]
 *     private ?Region $region = null;
 * }
 * ```
 *
 * @see ColumnGroup::SUB_LABELS_SHOW
 * @see ColumnGroup::SUB_LABELS_ICON
 * @see ColumnGroup::SUB_LABELS_HIDDEN
 * @see ColumnGroup::HEADER_TEXT
 * @see ColumnGroup::HEADER_COLLAPSIBLE
 * @see ColumnGroup::HEADER_FULL
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class AdminColumnGroup
{
    /**
     * @param string $id        Group identifier — must match `#[AdminColumn(group: '...')]`
     * @param string $subLabels How sub-column labels are displayed in body cells.
     *                          One of ColumnGroup::SUB_LABELS_* constants.
     * @param string $header    How the composite `<th>` header is rendered.
     *                          One of ColumnGroup::HEADER_* constants.
     */
    public function __construct(
        public readonly string $id,
        public readonly string $subLabels = ColumnGroup::SUB_LABELS_SHOW,
        public readonly string $header = ColumnGroup::HEADER_TEXT,
    ) {}
}
