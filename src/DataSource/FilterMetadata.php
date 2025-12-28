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
     * @param string $label Human-readable label
     * @param string|null $placeholder Placeholder text for input
     * @param string $operator SQL operator: '=', '!=', '<', '>', '<=', '>=', 'LIKE', 'BETWEEN'
     * @param array<string>|null $options For enum/select filters: list of options
     * @param string|null $enumClass For enum filters: fully-qualified enum class name
     * @param bool $showAllOption For enum filters: whether to show "All" option
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
        public ?array $options = null,
        public ?string $enumClass = null,
        public bool $showAllOption = true,
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
     * Create date filter.
     */
    public static function date(
        string $name,
        ?string $label = null,
        string $operator = '>=',
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
        int $priority = 999
    ): self {
        return new self(
            name: $name,
            type: ColumnFilter::TYPE_ENUM,
            label: $label ?? self::humanize($name),
            operator: '=',
            options: $options,
            showAllOption: $showAllOption,
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
        int $priority = 999
    ): self {
        return new self(
            name: $name,
            type: ColumnFilter::TYPE_ENUM,
            label: $label ?? self::humanize($name),
            operator: '=',
            enumClass: $enumClass,
            showAllOption: $showAllOption,
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
            showAllOption: $showAllOption,
            priority: $priority,
        );
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

        if ($this->options !== null) {
            $result['options'] = $this->options;
        }

        if ($this->enumClass !== null) {
            $result['enumClass'] = $this->enumClass;
        }

        if ($this->showAllOption !== true) {
            $result['showAllOption'] = $this->showAllOption;
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
