<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\DataSource;

/**
 * Options for enum-type filters.
 *
 * Groups enum-related configuration for FilterMetadata to reduce constructor parameters.
 */
readonly class FilterEnumOptions
{
    /**
     * @param array<string>|null $values For enum/select filters: list of option values
     * @param string|null $enumClass For enum filters: fully-qualified enum class name
     * @param bool $showAllOption Whether to show "All" option
     */
    public function __construct(
        public ?array $values = null,
        public ?string $enumClass = null,
        public bool $showAllOption = true,
    ) {}

    /**
     * Create from string options.
     *
     * @param array<string> $values
     */
    public static function fromValues(array $values, bool $showAllOption = true): self
    {
        return new self(values: $values, showAllOption: $showAllOption);
    }

    /**
     * Create from PHP enum class.
     *
     * @param class-string<\BackedEnum> $enumClass
     */
    public static function fromEnumClass(string $enumClass, bool $showAllOption = true): self
    {
        return new self(enumClass: $enumClass, showAllOption: $showAllOption);
    }
}
