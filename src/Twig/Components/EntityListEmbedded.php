<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Components;

use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;

/**
 * Entity list for embedding inside another page or component (collection dialogs,
 * nested lists): inherits EntityList behavior, but its state is NOT written to the
 * URL, so instances can coexist without fighting over query parameters.
 *
 * Mount with loading="lazy" when placed inside a closed <dialog> — see
 * InlineEntityForm's docblock for why that is required, not an optimisation.
 *
 * Accepts the `scope` LiveProp inherited from EntityList to list one owner's
 * collection.
 *
 * @see EntityList
 */
#[AsLiveComponent('K:Admin:EntityListEmbedded', template: '@KachnitelAdmin/components/EntityList.html.twig')]
class EntityListEmbedded extends EntityList
{
    #[LiveProp(writable: true)]
    public string $search = '';

    #[LiveProp(writable: true)]
    public string $sortBy = 'id';

    #[LiveProp(writable: true)]
    public string $sortDirection = self::SORT_DESC;

    /** @var array<string, mixed> */
    #[LiveProp(writable: true, onUpdated: 'onColumnFiltersUpdated')]
    public array $columnFilters = [];

    #[LiveProp(writable: true)]
    public int $page = 1;

    #[LiveProp(writable: true)]
    public int $itemsPerPage;

    /**
     * When true, archived/soft-deleted records are shown alongside active ones.
     */
    #[LiveProp(writable: true)]
    public bool $showArchived = false;

    public function isEmbedded(): bool
    {
        return true;
    }
}
