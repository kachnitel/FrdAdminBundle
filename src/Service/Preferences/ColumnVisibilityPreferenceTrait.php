<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Service\Preferences;

/**
 * Helper trait for loading and saving column visibility preferences.
 *
 * Provides type-safe methods for column visibility operations.
 * Components using this trait must implement getPreferencesStorage() and getListIdentifier().
 *
 * Usage in EntityList component:
 * ```php
 * class EntityList
 * {
 *     use ColumnVisibilityPreferenceTrait;
 *
 *     protected function getPreferencesStorage(): AdminPreferencesStorageInterface
 *     {
 *         return $this->preferencesStorage;
 *     }
 *
 *     protected function getListIdentifier(): string
 *     {
 *         return $this->dataSourceId ?? $this->entityShortClass;
 *     }
 * }
 * ```
 */
trait ColumnVisibilityPreferenceTrait
{
    /**
     * Get the preferences storage instance.
     *
     * Must be implemented by the using class.
     */
    abstract protected function getPreferencesStorage(): AdminPreferencesStorageInterface;

    /**
     * Get the list identifier for this component.
     *
     * Must be implemented by the using class.
     * Typically returns dataSourceId or entityShortClass.
     */
    abstract protected function getListIdentifier(): string;

    /**
     * Load hidden columns from preferences storage.
     *
     * @return array<string> Array of hidden column names
     */
    protected function loadHiddenColumns(): array
    {
        $key = PreferenceKeys::COLUMN_VISIBILITY . '.' . $this->getListIdentifier();
        $value = $this->getPreferencesStorage()->get($key, []);

        // Ensure we always return an array (defensive programming for custom storage implementations)
        return is_array($value) ? $value : [];
    }

    /**
     * Save hidden columns to preferences storage.
     *
     * @param array<string> $hiddenColumns Array of hidden column names
     */
    protected function saveHiddenColumns(array $hiddenColumns): void
    {
        $key = PreferenceKeys::COLUMN_VISIBILITY . '.' . $this->getListIdentifier();
        $this->getPreferencesStorage()->set($key, $hiddenColumns);
    }
}
