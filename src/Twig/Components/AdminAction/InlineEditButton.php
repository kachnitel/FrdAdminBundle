<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Components\AdminAction;

use Kachnitel\AdminBundle\RowAction\RowActionComponentInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Renders the ✏️ inline-edit entry button for a row action.
 *
 * When clicked, fires the `editRow` LiveAction on the parent EntityList component
 * via a Stimulus `live#action` data attribute — no own live state needed.
 *
 * Registered as RowAction liveComponent by InlineEditRowActionProvider.
 * Prop contract: {entity} — standardised for all RowActionComponentInterface components.
 *
 * Moved from Twig/Components/RowAction/ to Twig/Components/AdminAction/ to sit
 * alongside DeleteButton and ArchiveButton under the shared AdminAction namespace.
 */
#[AsTwigComponent('K:Admin:Action:InlineEdit', template: '@KachnitelAdmin/components/AdminAction/InlineEditButton.html.twig')]
class InlineEditButton implements RowActionComponentInterface
{
    public object $entity;
}
