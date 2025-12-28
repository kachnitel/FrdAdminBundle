<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\DataSource;

/**
 * Metadata for a column in a data source.
 *
 * Describes how a column should be displayed and behave in the admin list view.
 */
readonly class ColumnMetadata
{
    /**
     * @param string $name Internal column name (used for sorting, accessing data)
     * @param string $label Human-readable label displayed in header
     * @param string $type Data type: 'string', 'integer', 'boolean', 'datetime', 'date', 'json', 'text', 'collection'
     * @param bool $sortable Whether this column can be sorted
     * @param string|null $template Custom Twig template for rendering this column
     */
    public function __construct(
        public string $name,
        public string $label,
        public string $type = 'string',
        public bool $sortable = true,
        public ?string $template = null,
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
    ): self {
        return new self(
            name: $name,
            label: $label ?? self::humanize($name),
            type: $type,
            sortable: $sortable,
            template: $template,
        );
    }

    /**
     * Convert property name to human-readable label.
     */
    private static function humanize(string $text): string
    {
        return ucfirst(trim(strtolower((string) preg_replace('/(?<!^)[A-Z]/', ' $0', $text))));
    }
}
