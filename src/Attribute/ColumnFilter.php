<?php

namespace Kachnitel\AdminBundle\Attribute;

use Attribute;

/**
 * Configure column filtering for entity properties.
 *
 * When applied to entity properties, configures how that column should be filtered
 * in admin list views.
 *
 * @example Basic text search:
 * #[ColumnFilter(type: 'text')]
 * private string $name;
 *
 * @example Date range:
 * #[ColumnFilter(type: 'daterange')]
 * private \DateTimeInterface $createdAt;
 *
 * @example Enum dropdown:
 * #[ColumnFilter(type: 'enum')]
 * private SafetyIncidentType $type;
 *
 * @example Relationship with custom searchable fields:
 * #[ColumnFilter(type: 'relation', searchFields: ['name', 'email', 'phone'])]
 * private User $createdBy;
 *
 * @example Disable filtering:
 * #[ColumnFilter(enabled: false)]
 * private string $internalId;
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class ColumnFilter
{
    public const TYPE_TEXT = 'text';
    public const TYPE_NUMBER = 'number';
    public const TYPE_DATE = 'date';
    public const TYPE_DATERANGE = 'daterange';
    public const TYPE_ENUM = 'enum';
    public const TYPE_BOOLEAN = 'boolean';
    public const TYPE_RELATION = 'relation';

    public function __construct(
        /**
         * Filter type (text, number, date, daterange, enum, boolean, relation).
         * Auto-detected from property type if not specified.
         */
        public ?string $type = null,

        /**
         * Whether filtering is enabled for this column.
         */
        public bool $enabled = true,

        /**
         * Label to display in filter UI.
         * Auto-generated from property name if not specified.
         */
        public ?string $label = null,

        /**
         * For relation filters: which fields to search on the related entity.
         * Example: ['name', 'email', 'phone'] for User
         */
        public array $searchFields = [],

        /**
         * For relation filters: whether to allow deep filtering (related entity's relations).
         */
        public bool $deep = false,

        /**
         * Custom operator (=, !=, <, >, <=, >=, LIKE, IN, BETWEEN).
         * Auto-selected based on type if not specified.
         */
        public ?string $operator = null,

        /**
         * For enum filters: whether to show "All" option.
         */
        public bool $showAllOption = true,

        /**
         * Custom placeholder text for filter input.
         */
        public ?string $placeholder = null,

        /**
         * Position/order in filter display (lower numbers appear first).
         */
        public ?int $priority = null,
    ) {}
}
