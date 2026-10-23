<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Components;

use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;

/**
 * For components rendered inside an entity list (filters, batch actions) that report
 * back to it with emitUp().
 *
 * emitUp() reaches EVERY ancestor component, optionally narrowed to a component name.
 * A list embedded in a collection dialog sits inside the outer list's element, so an
 * un-narrowed event from the dialog's filters would also update the outer list. The
 * list passes its own registered component name (`EntityList::getListComponentName()`)
 * so events reach only lists of that kind.
 *
 * Limit: UX has no instance-level targeting, so two nested lists with the SAME component
 * name still both receive it. Collection dialogs therefore don't render inside an
 * embedded list (see docs/COLLECTION_DIALOG.md).
 *
 * `listComponent` null (templates that don't pass it) keeps the previous behavior.
 *
 * @see https://symfony.com/bundles/ux-live-component/current/index.html#scoping-events
 */
trait EmitsToListTrait
{
    use ComponentToolsTrait;

    /**
     * Registered component name of the list this component belongs to.
     */
    #[LiveProp]
    public ?string $listComponent = null;

    /**
     * @param array<string, mixed> $data
     */
    protected function emitToList(string $event, array $data): void
    {
        $this->emitUp($event, $data, $this->listComponent);
    }
}
