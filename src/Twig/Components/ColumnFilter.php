<?php

namespace Frd\AdminBundle\Twig\Components;

use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\ComponentToolsTrait;

#[AsLiveComponent('FRD:Admin:ColumnFilter', template: '@FrdAdmin/components/EntityList.html.twig')]
class ColumnFilter
{
    use DefaultActionTrait;
    use ComponentToolsTrait;

    #[LiveProp]
    public string $column;

    #[LiveProp(writable: true)]
    public string $value = '';

    #[LiveProp]
    public string $type = 'text'; // text, number, date, etc.

    /**
     * Hook called automatically when any LiveProp is updated.
     */
    public function onUpdated(mixed $property): void
    {
        if ($property === 'value') {
            // Emit event to parent (EntityList)
            // 'up' means it bubbles up to parents
            $this->emit('filter:updated', [
                'column' => $this->column,
                'value'  => $this->value,
            ]);
        }
    }
}

