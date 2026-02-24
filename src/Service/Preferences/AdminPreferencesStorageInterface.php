<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Service\Preferences;

/**
 * Interface for storing admin panel user preferences.
 *
 * Consuming applications can implement this interface to store preferences
 * in their own way (e.g., user preferences database table).
 * Use #[AsAlias] on your implementation to override the default session storage.
 *
 * The bundle provides a default SessionAdminPreferencesStorage implementation.
 *
 * Preferences are stored as key-value pairs where keys follow the pattern:
 * - {preference_type}.{identifier}
 * - Example: 'column_visibility.Product', 'items_per_page.Order'
 *
 * See PreferenceKeys for available preference type constants.
 */
interface AdminPreferencesStorageInterface
{
    /**
     * Get a preference value by key.
     *
     * @param string $key Preference key (e.g., 'column_visibility.Product')
     * @param mixed $default Default value if preference not set
     * @return mixed The stored value or default
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Set a preference value.
     *
     * @param string $key Preference key
     * @param mixed $value Value to store (must be serializable)
     */
    public function set(string $key, mixed $value): void;
}
