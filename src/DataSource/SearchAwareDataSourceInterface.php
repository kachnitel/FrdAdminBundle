<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\DataSource;

/**
 * Optional interface for DataSources that can advertise which columns
 * they search when a global search term is applied.
 *
 * Implementing this interface allows the EntityList component to display
 * a contextual tooltip listing the columns included in the global search,
 * improving usability without exposing internal query details.
 *
 * DoctrineDataSource implements this automatically. Custom DataSource
 * implementations may implement it to provide the same UX improvement.
 */
interface SearchAwareDataSourceInterface
{
    /**
     * Returns human-readable labels for the columns included in global search.
     *
     * The returned labels are shown in a tooltip next to the search input
     * so users understand what fields are being searched.
     *
     * @return array<string> e.g. ['Name', 'Description', 'Email']
     */
    public function getGlobalSearchColumnLabels(): array;
}
