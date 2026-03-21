<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\DataSource;

use Kachnitel\DataSourceContracts\FilterEnumOptions;
use Kachnitel\DataSourceContracts\FilterMetadata;

/**
 * Converts the legacy array filter configuration produced by FilterMetadataProvider
 * into a typed FilterMetadata value object.
 *
 * Extracted from DoctrineDataSource to isolate the array-to-DTO conversion concern.
 * This service is stateless and has no framework dependencies.
 */
class DoctrineFilterConverter
{
    /**
     * @param array<string, mixed> $config
     */
    public function convert(string $name, array $config): FilterMetadata
    {
        return new FilterMetadata(
            name: $name,
            type: $config['type'] ?? 'text',
            label: $config['label'] ?? null,
            placeholder: $config['placeholder'] ?? null,
            operator: $config['operator'] ?? '=',
            enumOptions: $this->buildEnumOptions($config),
            searchFields: $config['searchFields'] ?? null,
            priority: $config['priority'] ?? 999,
            enabled: $config['enabled'] ?? true,
            excludeFromGlobalSearch: $config['excludeFromGlobalSearch'] ?? false,
            targetClass: $config['targetClass'] ?? null,
        );
    }

    /**
     * Build FilterEnumOptions when any enum-related key is present.
     * Returns null for filter types that carry no enum configuration.
     *
     * @param array<string, mixed> $config
     */
    private function buildEnumOptions(array $config): ?FilterEnumOptions
    {
        $enumKeys = ['options', 'enumClass', 'showAllOption', 'multiple'];

        if (!array_any($enumKeys, fn (string $key): bool => isset($config[$key]))) {
            return null;
        }

        return new FilterEnumOptions(
            values: $config['options'] ?? null,
            enumClass: $config['enumClass'] ?? null,
            showAllOption: $config['showAllOption'] ?? true,
            multiple: $config['multiple'] ?? false,
        );
    }
}
