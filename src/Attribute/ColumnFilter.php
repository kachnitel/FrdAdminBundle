<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Attribute;

use Attribute;
use Kachnitel\DataSourceContracts\FilterMetadata;

/**
 * Configure column filtering for entity properties.
 *
 * @SuppressWarnings(PHPMD.ExcessiveParameterList) Attribute classes require flat parameter lists
 *
 * When applied to entity properties, configures how that column should be filtered
 * in admin list views.
 *
 * @example Basic text search:
 * #[ColumnFilter(type: ColumnFilter::TYPE_TEXT)]
 * private string $name;
 *
 * @example Date range:
 * #[ColumnFilter(type: ColumnFilter::TYPE_DATERANGE)]
 * private \DateTimeInterface $createdAt;
 *
 * @example Multi-select enum:
 * #[ColumnFilter(multiple: true)]
 * private OrderStatus $status;
 *
 * @example Relationship with custom searchable fields:
 * #[ColumnFilter(type: ColumnFilter::TYPE_RELATION, searchFields: ['name', 'email'])]
 * private User $createdBy;
 *
 * @example Collection (ManyToMany/OneToMany):
 * #[ColumnFilter(searchFields: ['name', 'display'])]
 * private Collection $tags;
 *
 * @example Disable filtering:
 * #[ColumnFilter(enabled: false)]
 * private string $internalId;
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class ColumnFilter
{
    // ── Type constants — canonical values live on FilterMetadata ─────────────
    // These are preserved here so existing #[ColumnFilter(type: ColumnFilter::TYPE_TEXT)]
    // references continue to work with no changes.

    /** @see FilterMetadata::TYPE_TEXT */
    public const TYPE_TEXT = FilterMetadata::TYPE_TEXT;

    /** @see FilterMetadata::TYPE_NUMBER */
    public const TYPE_NUMBER = FilterMetadata::TYPE_NUMBER;

    /** @see FilterMetadata::TYPE_DATE */
    public const TYPE_DATE = FilterMetadata::TYPE_DATE;

    /** @see FilterMetadata::TYPE_DATERANGE */
    public const TYPE_DATERANGE = FilterMetadata::TYPE_DATERANGE;

    /** @see FilterMetadata::TYPE_ENUM */
    public const TYPE_ENUM = FilterMetadata::TYPE_ENUM;

    /** @see FilterMetadata::TYPE_BOOLEAN */
    public const TYPE_BOOLEAN = FilterMetadata::TYPE_BOOLEAN;

    /** @see FilterMetadata::TYPE_RELATION */
    public const TYPE_RELATION = FilterMetadata::TYPE_RELATION;

    /** @see FilterMetadata::TYPE_COLLECTION */
    public const TYPE_COLLECTION = FilterMetadata::TYPE_COLLECTION;

    public function __construct(
        /**
         * Filter type. Auto-detected from property type if null.
         * Use the TYPE_* constants above or the string values directly.
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
         * For relation / collection filters: which fields to search on the related entity.
         *
         * @var array<string>
         */
        public array $searchFields = [],

        /**
         * For relation filters: whether to allow deep filtering (related entity's relations).
         */
        public bool $deep = false,

        /**
         * Custom SQL operator (=, !=, <, >, <=, >=, LIKE, IN, BETWEEN).
         * Auto-selected based on type if not specified.
         */
        public ?string $operator = null,

        /**
         * For enum / boolean filters: whether to show "All" option.
         */
        public bool $showAllOption = true,

        /**
         * For enum filters: whether to allow multiple selection.
         * When true, uses the IN operator instead of =.
         */
        public bool $multiple = false,

        /**
         * Custom placeholder text for the filter input.
         */
        public ?string $placeholder = null,

        /**
         * Display order in the filter panel (lower numbers appear first).
         */
        public ?int $priority = null,

        /**
         * For collection filters: whether to exclude from global search.
         * Default true — collection searches can be expensive.
         */
        public bool $excludeFromGlobalSearch = true,
    ) {}
}
