<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\DataSource;

use Kachnitel\AdminBundle\Utils\Text;

/**
 * Metadata for a column in a data source.
 *
 * Describes how a column should be displayed and behave in the admin list view.
 */
readonly class ColumnMetadata
{
    /**
     * @param string      $name     Internal column name (used for sorting, accessing data)
     * @param string      $label    Human-readable label displayed in header
     * @param string      $type     Data type: 'string', 'integer', 'boolean', 'datetime', 'date', etc.
     * @param bool        $sortable Whether this column can be sorted
     * @param string|null $template Custom Twig template for rendering this column
     * @param string|null $group    Composite group identifier — columns sharing the same group
     *                              are stacked in a single composite table cell
     */
    public function __construct(
        public string $name,
        public string $label,
        public string $type = 'string',
        public bool $sortable = true,
        public ?string $template = null,
        public ?string $group = null,
    ) {}

    /**
     * Create column metadata with sensible defaults for common types.
     */
    public static function create(
        string $name,
        ?string $label = null,
        string $type = 'string',
        bool $sortable = true,
        ?string $template = null,
        ?string $group = null,
    ): self {
        return new self(
            name: $name,
            label: $label ?? Text::humanize($name),
            type: $type,
            sortable: $sortable,
            template: $template,
            group: $group,
        );
    }
}
