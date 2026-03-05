<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Components\RowAction;

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
 */
#[AsTwigComponent('K:Admin:RowAction:InlineEdit', template: '@KachnitelAdmin/components/RowAction/InlineEditButton.html.twig')]
class InlineEditButton implements RowActionComponentInterface
{
    public object $entity;
}
