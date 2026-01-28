<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\DataSource;

use Kachnitel\AdminBundle\Attribute\ColumnFilter;

/**
 * Metadata for a filter in a data source.
 *
 * Describes how a filter should be rendered and how it affects queries.
 */
readonly class FilterMetadata
{
    /**
     * @param string $name Internal filter name (used for query parameters)
     * @param string $type Filter type: 'text', 'number', 'date', 'daterange', 'enum', 'boolean', 'relation'
     * @param string|null $label Human-readable label
     * @param string|null $placeholder Placeholder text for input
     * @param string $operator SQL operator: '=', '!=', '<', '>', '<=', '>=', 'LIKE', 'BETWEEN'
     * @param FilterEnumOptions|null $enumOptions Options for enum-type filters
     * @param array<string>|null $searchFields For relation filters: fields to search on
     * @param int $priority Display order (lower = first)
     * @param bool $enabled Whether this filter is enabled
     */
    public function __construct(
        public string $name,
        public string $type = ColumnFilter::TYPE_TEXT,
        public ?string $label = null,
        public ?string $placeholder = null,
        public string $operator = '=',
        public ?FilterEnumOptions $enumOptions = null,
        public ?array $searchFields = null,
        public int $priority = 999,
        public bool $enabled = true,
    ) {}

    /**
     * Create filter metadata with sensible defaults.
     */
    public static function text(
        string $name,
        ?string $label = null,
        ?string $placeholder = null,
        int $priority = 999
    ): self {
        return new self(
            name: $name,
            type: ColumnFilter::TYPE_TEXT,
            label: $label ?? self::humanize($name),
            placeholder: $placeholder,
            operator: 'LIKE',
            priority: $priority,
        );
    }

    /**
     * Create number filter.
     */
    public static function number(
        string $name,
        ?string $label = null,
        string $operator = '=',
        int $priority = 999
    ): self {
        return new self(
            name: $name,
            type: ColumnFilter::TYPE_NUMBER,
            label: $label ?? self::humanize($name),
            operator: $operator,
            priority: $priority,
        );
    }

    /**
     * Create date filter (matches exact day by default).
     */
    public static function date(
        string $name,
        ?string $label = null,
        string $operator = 'BETWEEN',
        int $priority = 999
    ): self {
        return new self(
            name: $name,
            type: ColumnFilter::TYPE_DATE,
            label: $label ?? self::humanize($name),
            operator: $operator,
            priority: $priority,
        );
    }

    /**
     * Create date range filter.
     */
    public static function dateRange(
        string $name,
        ?string $label = null,
        int $priority = 999
    ): self {
        return new self(
            name: $name,
            type: ColumnFilter::TYPE_DATERANGE,
            label: $label ?? self::humanize($name),
            operator: 'BETWEEN',
            priority: $priority,
        );
    }

    /**
     * Create enum filter from string options.
     *
     * @param array<string> $options List of option values
     */
    public static function enum(
        string $name,
        array $options,
        ?string $label = null,
        bool $showAllOption = true,
        bool $multiple = false,
        int $priority = 999
    ): self {
        return new self(
            name: $name,
            type: ColumnFilter::TYPE_ENUM,
            label: $label ?? self::humanize($name),
            operator: $multiple ? 'IN' : '=',
            enumOptions: FilterEnumOptions::fromValues($options, $showAllOption, $multiple),
            priority: $priority,
        );
    }

    /**
     * Create enum filter from PHP enum class.
     *
     * @param class-string<\BackedEnum> $enumClass
     */
    public static function enumClass(
        string $name,
        string $enumClass,
        ?string $label = null,
        bool $showAllOption = true,
        bool $multiple = false,
        int $priority = 999
    ): self {
        return new self(
            name: $name,
            type: ColumnFilter::TYPE_ENUM,
            label: $label ?? self::humanize($name),
            operator: $multiple ? 'IN' : '=',
            enumOptions: FilterEnumOptions::fromEnumClass($enumClass, $showAllOption, $multiple),
            priority: $priority,
        );
    }

    /**
     * Create boolean filter.
     */
    public static function boolean(
        string $name,
        ?string $label = null,
        bool $showAllOption = true,
        int $priority = 999
    ): self {
        return new self(
            name: $name,
            type: ColumnFilter::TYPE_BOOLEAN,
            label: $label ?? self::humanize($name),
            operator: '=',
            enumOptions: new FilterEnumOptions(showAllOption: $showAllOption),
            priority: $priority,
        );
    }

    // --- Backward compatibility accessors for enum options ---

    /**
     * Get options for enum/select filters.
     *
     * @return array<string>|null
     */
    public function getOptions(): ?array
    {
        return $this->enumOptions?->values;
    }

    /**
     * Get enum class name.
     *
     * @return string|null
     */
    public function getEnumClass(): ?string
    {
        return $this->enumOptions?->enumClass;
    }

    /**
     * Check if "All" option should be shown.
     */
    public function getShowAllOption(): bool
    {
        return $this->enumOptions !== null ? $this->enumOptions->showAllOption : true;
    }

    /**
     * Check if multiple selection is enabled (for enum filters).
     */
    public function isMultiple(): bool
    {
        return $this->enumOptions !== null && $this->enumOptions->multiple;
    }

    /**
     * Convert to array format compatible with existing FilterMetadataProvider output.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [
            'property' => $this->name,
            'label' => $this->label ?? self::humanize($this->name),
            'type' => $this->type,
            'operator' => $this->operator,
            'enabled' => $this->enabled,
            'priority' => $this->priority,
        ];

        if ($this->placeholder !== null) {
            $result['placeholder'] = $this->placeholder;
        }

        if ($this->enumOptions?->values !== null) {
            $result['options'] = $this->enumOptions->values;
        }

        if ($this->enumOptions?->enumClass !== null) {
            $result['enumClass'] = $this->enumOptions->enumClass;
        }

        if ($this->enumOptions !== null && $this->enumOptions->showAllOption !== true) {
            $result['showAllOption'] = $this->enumOptions->showAllOption;
        }

        if ($this->enumOptions !== null && $this->enumOptions->multiple) {
            $result['multiple'] = true;
        }

        if ($this->searchFields !== null) {
            $result['searchFields'] = $this->searchFields;
        }

        return $result;
    }

    /**
     * Convert property name to human-readable label.
     */
    private static function humanize(string $text): string
    {
        return ucfirst(trim(strtolower((string) preg_replace('/(?<!^)[A-Z]/', ' $0', $text))));
    }
}
