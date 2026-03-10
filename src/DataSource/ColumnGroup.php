<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\DataSource;

/**
 * Represents a group of columns that share a single composite table cell.
 *
 * When `DataSourceInterface::getColumnGroups()` returns a `ColumnGroup` slot,
 * the EntityList renders a single `<th>`/`<td>` containing stacked rows — one
 * per sub-column — instead of individual cells for each column.
 *
 * Groups are built automatically by `DoctrineDataSource` from the
 * `#[AdminColumn(group: '...')]` attribute. Custom data sources may construct
 * and return `ColumnGroup` instances directly from `getColumnGroups()`.
 *
 * Display options (`subLabels`, `header`) can be configured per-group using
 * the `#[AdminColumnGroup]` attribute on the entity class.
 *
 * @see DataSourceInterface::getColumnGroups()
 */
readonly class ColumnGroup
{
    // ── Sub-column label display mode ─────────────────────────────────────────

    /**
     * Show the sub-column label as text next to each value in composite cells (default).
     */
    public const SUB_LABELS_SHOW = 'show';

    /**
     * Show the sub-column label as a small ℹ icon with the label in the `title` attribute.
     */
    public const SUB_LABELS_ICON = 'icon';

    /**
     * Hide sub-column labels entirely in composite cells.
     */
    public const SUB_LABELS_HIDDEN = 'hidden';

    // ── Composite column header style ─────────────────────────────────────────

    /**
     * Show only the humanized group label as plain text — no per-sub-column sort or
     * filter rows. Behaves like a regular column header. This is the default.
     */
    public const HEADER_TEXT = 'text';

    /**
     * Show the group label with a native HTML `<details>`/`<summary>` toggle.
     * Per-sub-column sort and filter rows are hidden by default and revealed when
     * the user clicks the disclosure triangle. No JavaScript required.
     */
    public const HEADER_COLLAPSIBLE = 'collapsible';

    /**
     * Always show the group label strip plus per-sub-column sort and filter rows.
     * Most information-dense; suitable for power-user interfaces.
     */
    public const HEADER_FULL = 'full';

    /**
     * @param string                        $id        Group identifier (e.g. 'name_block')
     * @param string                        $label     Human-readable group label for the composite header
     * @param array<string, ColumnMetadata> $columns   Sub-columns keyed by column name, in display order
     * @param string                        $subLabels Sub-column label display mode (SUB_LABELS_* constant)
     * @param string                        $header    Composite header rendering style (HEADER_* constant)
     */
    public function __construct(
        public string $id,
        public string $label,
        public array $columns,
        public string $subLabels = self::SUB_LABELS_SHOW,
        public string $header = self::HEADER_TEXT,
    ) {}
}
