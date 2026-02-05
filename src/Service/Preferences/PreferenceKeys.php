<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Service\Preferences;

/**
 * Central registry of admin preference keys.
 *
 * Provides IDE autocomplete and documents all available preference keys
 * with their expected value types.
 *
 * Preference keys follow the pattern: {CONSTANT}.{identifier}
 * Example: PreferenceKeys::COLUMN_VISIBILITY . '.Product'
 */
final class PreferenceKeys
{
    /**
     * Column visibility preference.
     *
     * Stores which columns are hidden for a specific list.
     * Key format: 'column_visibility.{listIdentifier}'
     * Value type: array<string> (array of hidden column names)
     *
     * Example: ['password', 'internalNotes']
     */
    public const COLUMN_VISIBILITY = 'column_visibility';

    // Future preferences can be added here with similar documentation:
    //
    // /**
    //  * Items per page preference.
    //  *
    //  * Stores custom items per page setting for a specific list.
    //  * Key format: 'items_per_page.{listIdentifier}'
    //  * Value type: int
    //  */
    // public const ITEMS_PER_PAGE = 'items_per_page';
    //
    // /**
    //  * Default sort preference.
    //  *
    //  * Stores default sort column and direction for a specific list.
    //  * Key format: 'default_sort.{listIdentifier}'
    //  * Value type: array{column: string, direction: string}
    //  */
    // public const DEFAULT_SORT = 'default_sort';
    //
    // /**
    //  * Dashboard layout preference.
    //  *
    //  * Stores dashboard widget layout configuration.
    //  * Key format: 'dashboard_layout'
    //  * Value type: array<string, array{x: int, y: int, width: int, height: int}>
    //  */
    // public const DASHBOARD_LAYOUT = 'dashboard_layout';

    private function __construct()
    {
        // Prevent instantiation - this is a constants-only class
    }
}
