<?php

namespace Kachnitel\AdminBundle\Twig\Components;

use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\ComponentToolsTrait;

#[AsLiveComponent('K:Admin:ColumnFilter', template: '@KachnitelAdmin/components/ColumnFilter.html.twig')]
class ColumnFilter
{
    use DefaultActionTrait;
    use ComponentToolsTrait;

    #[LiveProp]
    public string $column;

    #[LiveProp(writable: true, onUpdated: 'onUpdated')]
    public string $value = '';

    #[LiveProp]
    public string $type = 'text'; // text, number, date, etc.

    public function onUpdated(): void
    {
        // Emit event to parent (EntityList)
        $this->emitUp('filter:updated', [
            'column' => $this->column,
            'value'  => $this->value,
        ]);
    }
}
